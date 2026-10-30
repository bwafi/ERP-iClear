<?php

namespace App\Services\Finance;

use CodeIgniter\Database\BaseConnection;

/**
 * Tanggungan Asset bulanan per unit franchise.
 *
 * `unit.tanggungan` adalah saldo sisa. Setiap bulan hanya boleh ada satu
 * baris (unique unit+periode), dan nominalnya tidak boleh melebihi saldo.
 */
class TanggunganAssetService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function sisa(int $unitId): float
    {
        $row = $this->db->table('unit')
            ->select('tanggungan')
            ->where('idunit', $unitId)
            ->get()->getRow();

        return max(0.0, (float) ($row->tanggungan ?? 0));
    }

    public function nominalTersimpan(int $unitId, string $periode): ?float
    {
        $row = $this->db->table('tanggungan_asset_bulanan')
            ->select('nominal')
            ->where(['unit_id' => $unitId, 'periode' => $periode])
            ->get()->getRow();

        return $row ? (float) $row->nominal : null;
    }

    /**
     * Simpan satu baris bulanan. Idempoten: periode yang sudah ada tidak
     * dipotong lagi kecuali $bolehPerbarui (bulan berjalan). Hanya unit
     * franchise.
     *
     * @return array{baru: bool, nominal: float, sisa: float}
     */
    public function simpan(int $unitId, string $periode, float $nominalMaksimal, bool $bolehPerbarui = false): array
    {
        $unit = $this->db->table('unit')
            ->select('idunit, jenis, tanggungan')
            ->where('idunit', $unitId)
            ->get()->getRow();

        if (! $unit || strtolower((string) ($unit->jenis ?? '')) !== 'franchise') {
            return [
                'baru'    => false,
                'nominal' => 0.0,
                'sisa'    => max(0.0, (float) ($unit->tanggungan ?? 0)),
            ];
        }

        $this->db->transBegin();

        try {
            // Kunci baris unit supaya dua request bersamaan tidak memotong
            // saldo yang sama dua kali.
            $row  = $this->db->query('SELECT tanggungan FROM unit WHERE idunit = ? FOR UPDATE', [$unitId])->getRow();
            $sisa = max(0.0, (float) ($row->tanggungan ?? 0));

            $ada = $this->db->table('tanggungan_asset_bulanan')
                ->where(['unit_id' => $unitId, 'periode' => $periode])
                ->get()->getRow();

            if ($ada) {
                if (! $bolehPerbarui) {
                    $this->db->transCommit();

                    return ['baru' => false, 'nominal' => (float) $ada->nominal, 'sisa' => $sisa];
                }

                // Bulan berjalan boleh berubah sampai bulan berganti. Saldo
                // yang sudah dipotong bulan ini dikembalikan dulu, lalu
                // dipotong ulang sesuai nominal terbaru.
                $nominalLama = (float) $ada->nominal;
                $tersedia    = $sisa + $nominalLama;
                $nominal     = max(0.0, min($nominalMaksimal, $tersedia));
                $selisih     = $nominal - $nominalLama;

                if (abs($selisih) > 0.00001) {
                    $this->db->table('tanggungan_asset_bulanan')
                        ->where(['unit_id' => $unitId, 'periode' => $periode])
                        ->update(['nominal' => $nominal]);

                    $this->db->table('unit')
                        ->where('idunit', $unitId)
                        ->update(['tanggungan' => $sisa - $selisih]);
                }

                $this->db->transCommit();

                return ['baru' => false, 'nominal' => $nominal, 'sisa' => $sisa - $selisih];
            }

            $nominal = max(0.0, min($nominalMaksimal, $sisa));

            $this->db->table('tanggungan_asset_bulanan')->insert([
                'unit_id'    => $unitId,
                'periode'    => $periode,
                'nominal'    => $nominal,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($nominal > 0) {
                $this->db->table('unit')
                    ->where('idunit', $unitId)
                    ->update(['tanggungan' => $sisa - $nominal]);
            }

            $this->db->transCommit();

            return ['baru' => true, 'nominal' => $nominal, 'sisa' => $sisa - $nominal];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
