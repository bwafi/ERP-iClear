<?php

namespace App\Services\Finance;

use Config\Database;

/**
 * KPI Ketepatan Pembayaran Hutang (auto).
 *
 * Periode dinilai : transaksi pembelian yang JATUH TEMPO-nya jatuh dalam bulan
 *                   yang dievaluasi (bukan tanggal_masuk).
 * Tanggal selesai : MAX(pembayaran_hutang.tanggal_bayar) per pembelian.
 *                   pembelian.tanggal_lunas tidak dipakai karena rutin existing
 *                   tidak pernah mengisinya GAP → gunakan MAX(tanggal_bayar).
 *
 * Klasifikasi:
 *  - Tepat      : lunas dengan MAX(tanggal_bayar) <= jatuh_tempo.
 *  - Terlambat  : lunas dengan MAX(tanggal_bayar) > jatuh_tempo, ATAU belum
 *                  lunas dan hari ini > jatuh_tempo (dianggap terlambat).
 *  - Open       : belum lunas dan hari ini <= jatuh_tempo → tidak dinilai,
 *                  keluar dari penyebut, tetap ditampilkan.
 *  - Tanpa bukti: lunas tapi tidak ada catatan pembayaran sama sekali
 *                  (data lama) → tidak bisa dipastikan kapan dibayar.
 *
 * Skor  = Tepat / (Tepat + Terlambat) x 100.
 */
class HutangTimelinessCalculator implements FinanceCalculatorInterface
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function calculate(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $today = date('Y-m-d');

        $lastPayment = $this->lastPaymentMap();
        $purchases = $this->db->table('pembelian')
            ->select('
                pembelian.idpembelian,
                pembelian.no_nota_supplier,
                pembelian.jatuh_tempo,
                pembelian.total_transaksi,
                pembelian.sisa,
                pembelian.status,
                suplier.nama_suplier
            ')
            ->join('suplier', 'suplier.id_suplier = pembelian.suplier_id_suplier', 'left')
            ->where('pembelian.unit_idunit', $unitId)
            ->where('pembelian.jatuh_tempo >=', $startDate)
            ->where('pembelian.jatuh_tempo <=', $endDate)
            ->orderBy('pembelian.jatuh_tempo', 'ASC')
            ->get()
            ->getResult();

        $tepat = 0;
        $terlambat = 0;
        $open = 0;
        $tanpaBukti = 0;
        $items = [];

        foreach ($purchases as $p) {
            $lunas = $this->isLunas($p);
            $lastPay = $lastPayment[(int) $p->idpembelian] ?? null;

            if ($lunas) {
                if ($lastPay && $lastPay <= $p->jatuh_tempo) {
                    $klasifikasi = 'Tepat Waktu';
                    $tepat++;
                } elseif ($lastPay && $lastPay > $p->jatuh_tempo) {
                    $klasifikasi = 'Terlambat';
                    $terlambat++;
                } else {
                    $klasifikasi = 'Lunas tanpa bukti tanggal bayar';
                    $tanpaBukti++;
                }
            } else {
                if ($today > $p->jatuh_tempo) {
                    $klasifikasi = 'Terlambat (belum dibayar)';
                    $terlambat++;
                } else {
                    $klasifikasi = 'Belum jatuh tempo (open)';
                    $open++;
                }
            }

            $items[] = [
                'id' => (int) $p->idpembelian,
                'no_nota' => $p->no_nota_supplier,
                'supplier' => $p->nama_suplier,
                'jatuh_tempo' => $p->jatuh_tempo,
                'total' => (float) ($p->total_transaksi ?? 0),
                'sisa' => (float) ($p->sisa ?? 0),
                'last_pay' => $lastPay,
                'klasifikasi' => $klasifikasi,
            ];
        }

        $dinilai = $tepat + $terlambat;
        $score = $dinilai > 0 ? round(($tepat / $dinilai) * 100, 2) : null;
        $status = $dinilai > 0 ? 'ok' : 'data_kosong';

        return [
            'score' => $score,
            'status' => $status,
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

    /**
     * Pembelian dianggap lunas berdasarkan flag aplikasi: status 'Lunas'
     * atau sisa <= 0 (backup bila status tidak konsisten).
     */
    private function isLunas($p): bool
    {
        $status = strtolower((string) $p->status);

        // "Lunas" vs "Belum Lunas" (substring "lunas" ada di keduanya!)
        if ($status === 'lunas' || (strpos($status, 'lunas') !== false && strpos($status, 'belum') === false)) {
            return true;
        }
        if ($status !== '' && strpos($status, 'belum') !== false) {
            return false;
        }

        // Backup bila status kosong/tidak konsisten: tidak bersisa = lunas.
        return (float) ($p->sisa ?? 0) <= 0;
    }

    /**
     * MAP idpembelian(int) => MAX(tanggal_bayar). pembelian_idpembelian
     * berjenis varchar sehingga dikonversi eksplisit.
     *
     * @return array<int, string>
     */
    private function lastPaymentMap(): array
    {
        $rows = $this->db->table('pembayaran_hutang')
            ->select('pembelian_idpembelian, MAX(tanggal_bayar) AS last_pay')
            ->where('tanggal_bayar IS NOT NULL')
            ->groupBy('pembelian_idpembelian')
            ->get()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->pembelian_idpembelian] = $row->last_pay;
        }

        return $map;
    }
}