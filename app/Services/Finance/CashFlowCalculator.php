<?php

namespace App\Services\Finance;

use Config\Database;
use Config\Finance;

/**
 * Cash Flow KPI — arus transaksi periode berjalan.
 *
 * Sumber Penerimaan (kas masuk):
 *  - Penjualan : SUM(penjualan.harus_dibayar) per unit per hari
 *  - Service   : SUM(service.bayar) per unit per hari
 *  (Tidak lagi memakai kas_masuk — di lapangan tabel kas_masuk mayoritas
 *  berisi baris saldo "kas awal", bukan transaksi penerimaan.)
 *
 * Pengeluaran (kas keluar) tetap dari tabel kas_keluar, mengecualikan baris
 * otomatis "kas awal".
 *
 * Net Cash Flow  = Penerimaan - Kas Keluar
 * Cash Flow %    = Net Cash Flow / Penerimaan x 100
 * Score          = (CF% / target 20%) x 100, maks 100, min 0.
 */
class CashFlowCalculator implements FinanceCalculatorInterface
{
    protected $db;
    protected $config;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->config = new Finance();
    }

    public function calculate(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $cutoff = FinanceScopeService::cutoffDate();

        $penjualan = $this->sumPenjualan($unitId, $startDate, $endDate, $cutoff);
        $service = $this->sumService($unitId, $startDate, $endDate, $cutoff);
        $penerimaan = $penjualan + $service;
        $kasKeluar = $this->sumKasKeluar($unitId, $startDate, $endDate, $cutoff);

        $net = $penerimaan - $kasKeluar;
        $target = (float) $this->config->cashFlowTargetPercent;

        $persen = $penerimaan > 0 ? ($net / $penerimaan) * 100 : null;
        $score = null;
        $status = $penerimaan === 0.0 ? 'data_kosong' : 'ok';

        if ($persen !== null) {
            $score = min(max(($persen / $target) * 100, 0), 100);
        }

        return [
            'score' => $score === null ? null : round($score, 2),
            'status' => $status,
            'detail' => [
                'penjualan' => $penjualan,
                'service' => $service,
                'kas_masuk' => $penerimaan,
                'kas_keluar' => $kasKeluar,
                'net' => $net,
                'persen' => $persen === null ? null : round($persen, 2),
                'target_persen' => $target,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }

    /**
     * Arus per hari untuk grafik/tabel detail.
     *
     * @return array{labels: array, masuk: array, keluar: array, net: array}
     */
    public function daily(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $cutoff = FinanceScopeService::cutoffDate();
        $penjualanByDate = $this->groupPenjualanByDate($unitId, $startDate, $endDate, $cutoff);
        $serviceByDate = $this->groupServiceByDate($unitId, $startDate, $endDate, $cutoff);
        $keluarByDate = $this->groupKasKeluarByDate($unitId, $startDate, $endDate, $cutoff);

        $map = [];
        foreach (array_unique(array_merge(array_keys($penjualanByDate), array_keys($serviceByDate))) as $tgl) {
            $map[$tgl] = [
                'masuk' => ($penjualanByDate[$tgl] ?? 0.0) + ($serviceByDate[$tgl] ?? 0.0),
                'keluar' => 0.0,
            ];
        }
        foreach ($keluarByDate as $tgl => $total) {
            if (isset($map[$tgl])) {
                $map[$tgl]['keluar'] = $total;
            } else {
                $map[$tgl] = ['masuk' => 0.0, 'keluar' => $total];
            }
        }

        ksort($map);

        $labels = array_keys($map);
        $masuk = [];
        $keluar = [];
        $net = [];
        foreach ($map as $values) {
            $masuk[] = $values['masuk'];
            $keluar[] = $values['keluar'];
            $net[] = $values['masuk'] - $values['keluar'];
        }

        return [
            'labels' => $labels,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'net' => $net,
        ];
    }

    /**
     * Penerimaan penjualan = SUM(penjualan.harus_dibayar) dalam rentang.
     */
    private function sumPenjualan(int $unitId, string $startDate, string $endDate, string $cutoff): float
    {
        $row = $this->db->table('penjualan')
            ->selectSum('harus_dibayar', 'total')
            ->where('unit_idunit', $unitId)
            ->where('DATE(tanggal) >=', $startDate)
            ->where('DATE(tanggal) <=', $endDate)
            ->where('DATE(tanggal) >=', $cutoff)
            ->get()
            ->getRow();

        return (float) ($row->total ?? 0);
    }

    /**
     * Penerimaan service = SUM(service.bayar) dalam rentang.
     */
    private function sumService(int $unitId, string $startDate, string $endDate, string $cutoff): float
    {
        $row = $this->db->table('service')
            ->selectSum('bayar', 'total')
            ->where('unit_idunit', $unitId)
            ->where('DATE(created_at) >=', $startDate)
            ->where('DATE(created_at) <=', $endDate)
            ->where('DATE(created_at) >=', $cutoff)
            ->get()
            ->getRow();

        return (float) ($row->total ?? 0);
    }

    /**
     * Kas keluar = SUM(kas_keluar.jumlah), mengecualikan baris "kas awal".
     */
    private function sumKasKeluar(int $unitId, string $startDate, string $endDate, string $cutoff): float
    {
        $row = $this->applyKasAwalFilter(
            $this->db->table('kas_keluar')
                ->selectSum('jumlah', 'total')
                ->where('idunit', $unitId)
                ->where('tanggal >=', $startDate)
                ->where('tanggal <=', $endDate)
                ->where('tanggal >=', $cutoff)
        )
            ->get()
            ->getRow();

        return (float) ($row->total ?? 0);
    }

    /**
     * @return array<string, float>
     */
    private function groupPenjualanByDate(int $unitId, string $startDate, string $endDate, string $cutoff): array
    {
        return $this->mapByDate(
            $this->db->table('penjualan')
                ->select('DATE(tanggal) AS tanggal, SUM(harus_dibayar) AS total')
                ->where('unit_idunit', $unitId)
                ->where('DATE(tanggal) >=', $startDate)
                ->where('DATE(tanggal) <=', $endDate)
                ->where('DATE(tanggal) >=', $cutoff)
                ->groupBy('DATE(tanggal)')
        );
    }

    /**
     * @return array<string, float>
     */
    private function groupServiceByDate(int $unitId, string $startDate, string $endDate, string $cutoff): array
    {
        return $this->mapByDate(
            $this->db->table('service')
                ->select('DATE(created_at) AS tanggal, SUM(bayar) AS total')
                ->where('unit_idunit', $unitId)
                ->where('DATE(created_at) >=', $startDate)
                ->where('DATE(created_at) <=', $endDate)
                ->where('DATE(created_at) >=', $cutoff)
                ->groupBy('DATE(created_at)')
        );
    }

    /**
     * @return array<string, float>
     */
    private function groupKasKeluarByDate(int $unitId, string $startDate, string $endDate, string $cutoff): array
    {
        return $this->mapByDate(
            $this->applyKasAwalFilter(
                $this->db->table('kas_keluar')
                    ->select('DATE(tanggal) AS tanggal, SUM(jumlah) AS total')
                    ->where('idunit', $unitId)
                    ->where('tanggal >=', $startDate)
                    ->where('tanggal <=', $endDate)
                    ->where('tanggal >=', $cutoff)
                    ->groupBy('DATE(tanggal)')
            )
        );
    }

    /**
     * @param object $builder Query builder setelah select/where/groupBy.
     * @return array<string, float>
     */
    private function mapByDate($builder): array
    {
        $rows = $builder->orderBy('tanggal', 'ASC')->get()->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->tanggal] = (float) ($row->total ?? 0);
        }

        return $map;
    }

    /**
     * Baris otomatis "kas awal" (TutupKasir) memiliki kategori/no_akun/jenis
     * semuanya NULL dan deskripsi 'kas awal' — harus dibuang dari arus kas.
     */
    private function applyKasAwalFilter($builder)
    {
        return $builder->groupStart()
            ->where('deskripsi !=', 'kas awal')
            ->groupStart()
                ->where('kategori_idkategori IS NOT NULL')
                ->orWhere('no_akun IS NOT NULL')
                ->orWhere('jenis IS NOT NULL')
            ->groupEnd()
        ->groupEnd();
    }
}