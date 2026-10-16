<?php

namespace App\Services\Finance;

use App\Models\ModelFinanceRekonDaily;
use Config\Finance;

/**
 * KPI Akurasi Laporan Keuangan (FIN_AKURASI) — bobot 20%.
 *
 * Sumber angka = halaman Rekonsiliasi Harian Finance
 * (`/finance/rekonsiliasi` → tabel `finance_rekon_daily`), BUKAN lagi
 * perbandingan omzet ERP vs omzet sheet.
 *
 * Keputusan bisnis 2026-10-16:
 *   - AKURASI = (hari terverifikasi TANPA selisih / hari terverifikasi) × 100
 *   - "Hari terverifikasi" = hari yang ketiga komponen aktualnya sudah diisi
 *     (lengkap) DAN approval-nya sudah `verified` oleh Manager.
 *   - Satu hari berselisih (salah satu dari cash masuk / transfer masuk / kas
 *     keluar tidak 0) menurunkan nilai: ia ikut pembagi tetapi tidak ikut
 *     pembilang.
 *   - Hari yang belum diverifikasi TIDAK dihitung sama sekali — tidak membantu
 *     dan tidak menyeret (isu kelengkapan sudah ditangani KPI Rekonsiliasi).
 *
 * Periode = bulan evaluasi, dipotong di hari ini (hari ke depan mustahil
 * sudah terverifikasi). Bila belum ada satu pun hari terverifikasi, skor
 * bernilai null (status `belum_verifikasi`) supaya KPI tidak dipaksa 0.
 */
class AkurasiLaporanCalculator implements FinanceCalculatorInterface
{
    protected ?ModelFinanceRekonDaily $model;
    protected Finance $config;

    public function __construct(?ModelFinanceRekonDaily $model = null, ?Finance $config = null)
    {
        $this->model  = $model;
        $this->config = $config ?? new Finance();
    }

    /**
     * Koneksi/model dibuat lazy supaya bagian MURNI (assemble) bisa diuji
     * tanpa database.
     */
    protected function model(): ModelFinanceRekonDaily
    {
        if ($this->model === null) {
            $this->model = new ModelFinanceRekonDaily();
        }

        return $this->model;
    }

    public function calculate(int $unitId, int $month, int $year): array
    {
        $dari    = sprintf('%04d-%02d-01', $year, $month);
        $sampai  = date('Y-m-t', strtotime($dari));
        $hariIni = date('Y-m-d');

        if ($sampai > $hariIni) {
            $sampai = $hariIni;
        }

        $terekon = 0;
        $cocok   = 0;
        $error   = '';

        try {
            // Pembagi: hari lengkap + verified. Pembilang: hari lengkap +
            // verified + tanpa selisih.
            $terekon = $this->model()->countLengkapVerifiedInRange($unitId, $dari, $sampai);
            $cocok   = $this->model()->countLengkapVerifiedCocokInRange($unitId, $dari, $sampai);
        } catch (\Throwable $e) {
            // Tabel/kolom rekonsiliasi belum siap -> skor null agar caller
            // tidak mencatat nilai palsu.
            $error = $e->getMessage();
        }

        return $this->assemble([
            'unit_id'       => $unitId,
            'periode_mulai' => $dari,
            'periode_akhir' => $sampai,
            'terekon'       => $terekon,
            'cocok'         => $cocok,
            'error'         => $error,
        ]);
    }

    /**
     * Bagian MURNI: dari jumlah hari menjadi akurasi + skor + status.
     *
     * Dipisah dari query supaya formula bisa diuji tanpa database.
     *
     * @param array{
     *   unit_id?:int, periode_mulai?:string, periode_akhir?:string,
     *   terekon:int, cocok:int, error?:string
     * } $in
     */
    public function assemble(array $in): array
    {
        $terekon = max((int) ($in['terekon'] ?? 0), 0);
        $cocok   = max((int) ($in['cocok'] ?? 0), 0);
        $error   = (string) ($in['error'] ?? '');
        $bobot   = (float) ($this->config->kpiWeights['akurasi'] ?? 20);

        // Jaring pengaman: pembilang tidak boleh melebihi pembagi.
        if ($cocok > $terekon) {
            $cocok = $terekon;
        }

        $akurasi = null;
        $skor    = null;

        if ($terekon <= 0) {
            // Tidak ada pembagian dengan nol. Belum ada hari terverifikasi.
            $status = $error !== '' ? 'error' : 'belum_verifikasi';
        } else {
            $status  = 'ok';
            $akurasi = round(($cocok / $terekon) * 100, 2);
            $skor    = $akurasi;
        }

        return [
            'score'  => $skor,
            'status' => $status,
            'detail' => [
                'unit_id'       => (int) ($in['unit_id'] ?? 0),
                'periode_mulai' => $in['periode_mulai'] ?? null,
                'periode_akhir' => $in['periode_akhir'] ?? null,
                'hari_terekon'  => $terekon,
                'hari_cocok'    => $cocok,
                'hari_selisih'  => max($terekon - $cocok, 0),
                'akurasi'       => $akurasi,
                'bobot'         => $bobot,
                'kontribusi'    => $skor === null
                    ? null
                    : round($skor * $bobot / 100, 2),
                'error'         => $error !== '' ? $error : null,
            ],
        ];
    }
}
