<?php

namespace App\Services\Finance;

use Config\Database;
use Config\Finance;

/**
 * Cash Flow KPI — arus transaksi periode berjalan.
 *
 * SEMUA angkakas/transfer diambil dari TutupKasirSourceDefinition, bukan
 * query ulang di kelas ini. Satu sumber definisi = satu angka; kalau KPI
 * menulis query sendiri, KPI akan menyimpang dari Tutup Kasir dan dari
 * core Finance movement.
 *
 * Aturan yang dijaga (lihat TutupKasirSourceDefinition):
 *  - Penjualan  : SUM(bayar_tunai) + SUM(bayar_bank), kode_invoice bukan srv.
 *                 Filter `notLike(...,'srv','after')` -> SQL `NOT LIKE 'srv%'`
 *                 (PREFIX, bukan suffix) pada CodeIgniter4 versi ini.
 *                 TIDAK memakai harus_dibayar — itu nilai invoice, bukan
 *                 uang yang benar-benar diterima.
 *  - Service    : SUM(bayar_tunai) + SUM(harus_dibayar - bayar_tunai),
 *                 dihitung per tanggal_selesai dengan status_service = 4.
 *                 TIDAK memakai SUM(bayar) per created_at — tanggal dibuat
 *                 bukan tanggal settle, dan tanpa status filter semua
 *                 status service ikut terhitung.
 *  - Kas keluar : SUM(kas_keluar.jumlah) tanpa filter apa pun.
 *  - kas_masuk  : tidak dipakai. Di lapangan tabel itu hanya baris saldo
 *                 "kas awal", bukan penerimaan.
 *
 * Net Cash Flow  = Penerimaan - Kas Keluar
 * Cash Flow %    = Net Cash Flow / Penerimaan x 100
 * Score          = (CF% / target 20%) x 100, maks 100, min 0.
 */
class CashFlowCalculator implements FinanceCalculatorInterface
{
    protected $db;
    protected $config;

    /** @var TutupKasirSourceDefinition */
    protected $src;

    public function __construct(?TutupKasirSourceDefinition $src = null)
    {
        $this->db     = Database::connect();
        $this->config = new Finance();
        $this->src    = $src ?? new TutupKasirSourceDefinition($this->db);
    }

    public function calculate(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $cutoff = FinanceScopeService::periodeMulaiDate();

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

        $cutoff = FinanceScopeService::periodeMulaiDate();
        $dari   = max($startDate, $cutoff);
        $harian = $this->src->harianRange($unitId, $dari, $endDate);

        // Masuk = kas + transfer (penjualan + service), keluar = seluruh
        // kas_keluar. Split cash/transfer tidak perlu di sini karena KPI ini
        // menghitung arus, bukan saldo rekening.
        $masuk  = [];
        $keluar = [];

        foreach ($harian['cash'] as $tgl => $nilai) {
            $masuk[$tgl] = (float) $nilai;
        }
        foreach ($harian['transfer'] as $tgl => $nilai) {
            $masuk[$tgl] = ($masuk[$tgl] ?? 0.0) + (float) $nilai;
        }
        foreach ($harian['keluar'] as $tgl => $nilai) {
            $keluar[$tgl] = (float) $nilai;
        }

        $labels = array_keys($masuk + $keluar);
        sort($labels);

        $outMasuk  = [];
        $outKeluar = [];
        $outNet    = [];
        foreach ($labels as $tgl) {
            $m = (float) ($masuk[$tgl] ?? 0.0);
            $k = (float) ($keluar[$tgl] ?? 0.0);
            $outMasuk[]  = $m;
            $outKeluar[] = $k;
            $outNet[]    = $m - $k;
        }

        return [
            'labels' => $labels,
            'masuk' => $outMasuk,
            'keluar' => $outKeluar,
            'net' => $outNet,
        ];
    }

    /**
     * Penerimaan penjualan = kas + transfer penjualan dalam rentang.
     *
     * Cut-off periode aktif diterapkan sebagai batas bawah (`max`), sama
     * seperti sebelumnya — angka sebelum 2026-10-06 tidak masuk KPI.
     */
    private function sumPenjualan(int $unitId, string $startDate, string $endDate, string $cutoff): float
    {
        $dari = max($startDate, $cutoff);

        return (float) $this->src->cashPenjualanRange($unitId, $dari, $endDate)
            + (float) $this->src->transferPenjualanRange($unitId, $dari, $endDate);
    }

    /**
     * Penerimaan service = kas + residual transfer, per tanggal_selesai,
     * hanya service berstatus 4 (selesai).
     */
    private function sumService(int $unitId, string $startDate, string $endDate, string $cutoff): float
    {
        $dari = max($startDate, $cutoff);

        return (float) $this->src->cashServiceRange($unitId, $dari, $endDate)
            + (float) $this->src->transferServiceRange($unitId, $dari, $endDate);
    }

    /**
     * Kas keluar = seluruh SUM(kas_keluar.jumlah) dalam rentang.
     *
     * Tidak lagi menyaring baris "kas awal": TutupKasir menjumlahkan utuh,
     * jadi penyaringan di sini membuat KPI berbeda dari tutup kasir.
     */
    private function sumKasKeluar(int $unitId, string $startDate, string $endDate, string $cutoff): float
    {
        $dari = max($startDate, $cutoff);

        return (float) $this->src->kasKeluarCashRange($unitId, $dari, $endDate)
            + (float) $this->src->kasKeluarTransferRange($unitId, $dari, $endDate);
    }
}