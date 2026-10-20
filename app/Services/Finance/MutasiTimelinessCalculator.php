<?php

namespace App\Services\Finance;

use Config\Database;

/**
 * KPI ketepatan pelunasan hutang antar unit.
 *
 * Hanya sisi hutang yang dinilai; sisi piutang adalah cermin dari transaksi
 * yang sama dan tidak boleh dihitung dua kali. Baris legacy, soft-delete,
 * sumber selain `mutasi_unit`, dan tanggal sebelum periode baru dikecualikan.
 *
 * Klasifikasi dan skor mengikuti HutangTimelinessCalculator:
 *  - Tepat      : lunas dengan pembayaran terakhir <= jatuh tempo.
 *  - Terlambat  : lunas setelah jatuh tempo, atau belum lunas dan sudah lewat.
 *  - Open       : belum lunas dan belum jatuh tempo.
 *  - Tanpa bukti: lunas tanpa catatan pembayaran.
 *
 * Skor = Tepat / (Tepat + Terlambat) x 100.
 */
class MutasiTimelinessCalculator implements FinanceCalculatorInterface
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function calculate(int $unitId, int $month, int $year, ?string $today = null): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));
        $today     = $today ?? date('Y-m-d');
        $cutoff    = FinanceScopeService::periodeMulaiDate();

        $lastPayment = $this->lastPaymentMap();
        $rows = $this->db->table('hutang_piutang')
            ->select('id, kode, jatuh_tempo, total, sisa, status')
            ->where('unit_id', $unitId)
            ->where('jenis', 'hutang')
            ->where('sumber_tipe', 'mutasi_unit')
            ->where('scope', 'active')
            ->where('deleted', 0)
            ->where('tanggal >=', $cutoff)
            ->where('jatuh_tempo >=', $startDate)
            ->where('jatuh_tempo <=', $endDate)
            ->orderBy('jatuh_tempo', 'ASC')
            ->get()
            ->getResult();

        $tepat = $terlambat = $open = $tanpaBukti = 0;
        $items = [];

        foreach ($rows as $row) {
            $lastPay = $lastPayment[(int) $row->id] ?? null;

            if ($this->isLunas($row)) {
                if ($lastPay !== null && $lastPay <= $row->jatuh_tempo) {
                    $klasifikasi = 'Tepat Waktu';
                    $tepat++;
                } elseif ($lastPay !== null) {
                    $klasifikasi = 'Terlambat';
                    $terlambat++;
                } else {
                    $klasifikasi = 'Lunas tanpa bukti tanggal bayar';
                    $tanpaBukti++;
                }
            } elseif ($today > $row->jatuh_tempo) {
                $klasifikasi = 'Terlambat (belum dibayar)';
                $terlambat++;
            } else {
                $klasifikasi = 'Belum jatuh tempo (open)';
                $open++;
            }

            $items[] = [
                'id' => (int) $row->id,
                'no_nota' => $row->kode,
                'jatuh_tempo' => $row->jatuh_tempo,
                'total' => (float) ($row->total ?? 0),
                'sisa' => (float) ($row->sisa ?? 0),
                'last_pay' => $lastPay,
                'klasifikasi' => $klasifikasi,
            ];
        }

        $dinilai = $tepat + $terlambat;

        return [
            'score' => $dinilai > 0 ? round(($tepat / $dinilai) * 100, 2) : null,
            'status' => $dinilai > 0 ? 'ok' : 'data_kosong',
            'detail' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tepat' => $tepat,
                'terlambat' => $terlambat,
                'open' => $open,
                'tanpa_bukti' => $tanpaBukti,
                'dinilai' => $dinilai,
                'items' => $items,
            ],
        ];
    }

    private function isLunas($row): bool
    {
        $status = strtolower((string) $row->status);

        if ($status === 'lunas' || (strpos($status, 'lunas') !== false && strpos($status, 'belum') === false)) {
            return true;
        }
        if ($status !== '' && strpos($status, 'belum') !== false) {
            return false;
        }

        return (float) ($row->sisa ?? 0) <= 0;
    }

    /**
     * @return array<int, string>
     */
    private function lastPaymentMap(): array
    {
        $rows = $this->db->table('pembayaran_hutang_piutang')
            ->select('hutang_piutang_id, MAX(tanggal_bayar) AS last_pay')
            ->where('tanggal_bayar IS NOT NULL')
            ->groupBy('hutang_piutang_id')
            ->get()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->hutang_piutang_id] = $row->last_pay;
        }

        return $map;
    }
}
