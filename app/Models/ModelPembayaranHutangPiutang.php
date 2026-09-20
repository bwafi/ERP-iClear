<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pembayaran untuk jenis authoritative (piutang_pelanggan, kasbon).
 *
 * Pembayaran pembelian existing TIDAK masuk tabel ini (tetap di
 * `pembayaran_hutang`); baris projection hanya dibaca dari sumbernya.
 */
class ModelPembayaranHutangPiutang extends Model
{
    protected $table = 'pembayaran_hutang_piutang';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'hutang_piutang_id',
        'tanggal_bayar',
        'jumlah_bayar',
        'bayar_tunai',
        'bayar_bank',
        'bank_idbank',
        'sumber',
        'referensi_tipe',
        'referensi_id',
        'keterangan',
        'input_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Total pembayaran per hutang_piutang_id (bulk).
     *
     * @param int[] $ids
     * @return array<int, int> map id => total
     */
    public function totalByPositionIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = $this->select('hutang_piutang_id, SUM(jumlah_bayar) AS total')
            ->whereIn('hutang_piutang_id', $ids)
            ->groupBy('hutang_piutang_id')
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->hutang_piutang_id] = (int) $row->total;
        }
        return $map;
    }

    public function getByPosition(int $positionId): array
    {
        return $this->where('hutang_piutang_id', $positionId)
            ->orderBy('tanggal_bayar', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Cek idempotency settlement payroll: referensi yang sama tidak boleh 2x.
     */
    public function existsByReferensi(string $tipe, int $referensiId, string $sumber = 'payroll'): bool
    {
        return (bool) $this->where('sumber', $sumber)
            ->where('referensi_tipe', $tipe)
            ->where('referensi_id', $referensiId)
            ->countAllResults();
    }

    /**
     * Pembayaran yang terkait referensi (mis. transaksi_kas_bank untuk reversal).
     */
    public function getByReferensi(string $referensiTipe, int $referensiId): array
    {
        return $this->where('referensi_tipe', $referensiTipe)
            ->where('referensi_id', $referensiId)
            ->findAll();
    }
}
