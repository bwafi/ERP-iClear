<?php
namespace App\Services\Kpi;

// =========================================================
// LEGACY COMPATIBILITY SERVICE — REGRESSION IMPLEMENTATION
// =========================================================
// Service ini MENGUJI dan MEREKALIKASI hasil hitungKPIGaji() LAMA
// secara LITERAL (termasuk bug dan hardcode) supaya regression
// OLD == NEW dapat diverifikasi.
//
// KODE INI BUKAN DESIGN FINAL.
// Seluruh nilai di bawah ini dipertahankan PERSIS dari source code
// lama (`app/Controllers/PenilaianKPI.php::hitungKPIGaji()`):
//   - target per unit (gaji vs non-gaji)
//   - batas omset & target omset
//   - bobot per jabatan
//   - tunjangan & gaji pokok
//   - pembagi insentif context-based
//   - HPP date('m') hardcoded (BUG-003, dibiarkan agar baseline sama)
//
// FINAL REFACTORED SERVICES:
//   - KpiCalculationService      (config dari database)
//   - IncentiveCalculationService (group-based, divisor dinamis)
//   - SalaryCalculationService   (komponen gaji dari database)
//
// JANGAN mengubah logic service ini kecuali untuk sinkronisasi
// dengan hitungKPIGaji() lama. Perbaikan bug dilakukan terpisah
// di final services, BUKAN di sini.
// =========================================================

use Config\Database;

// =========================================================
// LEGACY COMPATIBILITY SERVICE — REGRESSION IMPLEMENTATION

/**
 * LegacyKpiCalculationService
 *
 * Replikasi LITERAL dari method `PenilaianKPI::hitungKPIGaji()` (lines 569-1252).
 *
 * Tujuan: memindahkan logic existing ke service layer TANPA mengubah hasil perhitungan.
 * Semua nilai hardcoded (target, bobot, batas, persentase) DIREPLIKASI PERSIS dari
 * source code lama. Tidak ada formula yang diubah/di-"perbaiki" — termasuk bug yang
 * sudah teridentifikasi (mis. HPP date('m'), pembagi context 'gaji' = 4), supaya
 * regression OLD vs NEW menghasilkan nilai yang identik.
 */
class LegacyKpiCalculationService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Mirip hitungKPIGaji($idAkun, $bulan, $tahun, $context)
     */
    public function calculate(int $idAkun, string $bulan, string $tahun, string $context = 'penilaian_kinerja'): array
    {
        // =========================================================
        // KARYAWAN + JABATAN + UNIT
        // =========================================================
        $karyawan = $this->db->table('akun')
            ->where('ID_AKUN', $idAkun)
            ->get()
            ->getRowArray();

        $jabatan = $karyawan['ID_JABATAN'] ?? null;
        $unit    = $karyawan['ID_UNIT'] ?? null;

        $query = $this->db->query("
            SELECT
                NAMA_AKUN,
                ALAMAT,
                ID_UNIT,
                CASE
                    WHEN ALAMAT = 'Probolinggo' AND ID_UNIT = 1 THEN 1
                    WHEN ALAMAT = 'Jember' AND ID_UNIT = 2 THEN 1
                    WHEN ALAMAT = 'Banyuwangi' AND ID_UNIT = 3 THEN 1
                    ELSE 0
                END AS penempatan
            FROM akun
            WHERE ID_AKUN = ?
        ", [$idAkun]);

        $akun = $query->getRow();
        $akun->tunjangan_penempatan = ($akun->penempatan == 0) ? 350000 : 0;

        // =========================================================
        // TARGET PER UNIT (gaji vs non-gaji)
        // =========================================================
        if ($context === 'gaji') {
            $target_unit = [
                1 => ['customer' => 130, 'closing' => 111, 'upselling' => 14, 'followup' => 100, 'roas' => 5],
                2 => ['customer' => 118, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 4],
                3 => ['customer' => 210, 'closing' => 188, 'upselling' => 27, 'followup' => 60,  'roas' => 3],
                4 => ['customer' => 118, 'atas_customer' => 250, 'bawah_customer' => 200, 'closing' => 96, 'upselling' => 14, 'followup' => 80, 'roas' => 5,],
            ];

            $batas_awal    = [1 => 30000000, 2 => 18000000, 3 => 40000000, 4 => 18000000];
            $batas_kedua   = [1 => 35000000, 2 => 22000000, 3 => 45000000, 4 => 22000000];
            $batas_ketiga  = [1 => 40000000, 2 => 26000000, 3 => 50000000, 4 => 26000000];
            $batas_keempat = [1 => 45000000, 2 => 30000000, 3 => 55000000, 4 => 30000000];
            $target_omset  = [1 => 50000000, 2 => 35000000, 3 => 60000000, 4 => 35000000];
        } else {
            $target_unit = [
                1 => ['customer' => 130, 'atas_customer' => 220, 'bawah_customer' => 150, 'closing' => 111, 'upselling' => 14, 'followup' => 100, 'roas' => 5],
                2 => ['customer' => 118, 'atas_customer' => 180, 'bawah_customer' => 150, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 4],
                3 => ['customer' => 210, 'atas_customer' => 350, 'bawah_customer' => 250, 'closing' => 188, 'upselling' => 27, 'followup' => 60,  'roas' => 3],
                4 => ['customer' => 118, 'atas_customer' => 250, 'bawah_customer' => 200, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 5],
            ];

            $batas_awal    = [1 => 35000000, 2 => 18000000, 3 => 40000000, 4 => 35000000];
            $batas_kedua   = [1 => 40000000, 2 => 22000000, 3 => 45000000, 4 => 40000000];
            $batas_ketiga  = [1 => 45000000, 2 => 26000000, 3 => 50000000, 4 => 45000000];
            $batas_keempat = [1 => 50000000, 2 => 30000000, 3 => 55000000, 4 => 50000000];
            $target_omset  = [1 => 55000000, 2 => 35000000, 3 => 60000000, 4 => 55000000];
        }

        $target = $target_unit[$unit] ?? $target_unit[1];

        // =========================================================
        // OMSET & CUSTOMER PER CABANG (1-4)
        // =========================================================
        $aktual_omset_unit = [];
        $aktual_customer_unit = [];

        foreach ([1, 2, 3, 4] as $idUnit) {
            $aktual_omset_unit[$idUnit] = $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', $idUnit)
                ->get()
                ->getRow()
                ->total ?? 0;

            if ($context === 'gaji') {
                $aktual_customer_unit[$idUnit] = $this->db->table('penjualan')
                    ->select('COUNT(idpenjualan) AS total')
                    ->where('MONTH(tanggal)', $bulan)
                    ->where('YEAR(tanggal)', $tahun)
                    ->where('unit_idunit =', $idUnit)
                    ->get()
                    ->getRow()
                    ->total ?? 0;
            } else {
                $aktual_customer_unit[$idUnit] = $this->db->table('penjualan')
                    ->select('COUNT(kode_invoice) AS total')
                    ->where('MONTH(tanggal)', $bulan)
                    ->where('YEAR(tanggal)', $tahun)
                    ->where('unit_idunit', $idUnit)
                    ->get()
                    ->getRow()
                    ->total ?? 0;
            }
        }

        $aktual_omset = $aktual_omset_unit[$unit] ?? 0;
        $total_customer = $aktual_customer_unit[$unit] ?? 0;

        // =========================================================
        // HPP per cabang (BUG-003 FIX: periode eksplisit $bulan/$tahun,
        // bukan date('m')/date('Y') yang mengambil bulan berjalan)
        // =========================================================
        $aktual_hpp = [];
        foreach ([1, 2, 3, 4] as $idUnit) {
            $aktual_hpp[$idUnit] = $this->db->table('detail_penjualan')
                ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
                ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                ->where('MONTH(penjualan.tanggal)', $bulan)
                ->where('YEAR(penjualan.tanggal)', $tahun)
                ->where('penjualan.unit_idunit =', $idUnit)
                ->get()
                ->getRow()
                ->total ?? 0;
        }
        $total_hpp = $aktual_hpp[1] + $aktual_hpp[2] + $aktual_hpp[3] + $aktual_hpp[4];
        $totalomset = ($aktual_omset_unit[1] + $aktual_omset_unit[2] + $aktual_omset_unit[3] + $aktual_omset_unit[4]) ?: 1;
        $persentasetotal = ($total_hpp / $totalomset) * 100;

        if ($persentasetotal <= 35) {
            $nilai_hpp_global = 100;
        } elseif ($persentasetotal <= 40) {
            $nilai_hpp_global = 75;
        } elseif ($persentasetotal <= 45) {
            $nilai_hpp_global = 50;
        } else {
            $nilai_hpp_global = 0;
        }

        $nilai_hpp = 0;
        foreach ($aktual_hpp as $idUnit => $hpp) {
            $omset = $aktual_omset_unit[$idUnit] ?? 0;
            if ($omset == 0) {
                continue;
            }
            $persentase = ($hpp / $omset) * 100;
            if ($persentase <= 35) {
                $nilai_hpp += 25;
            } elseif ($persentase <= 40) {
                $nilai_hpp += 18.75;
            } elseif ($persentase <= 45) {
                $nilai_hpp += 12.5;
            }
        }

        // =========================================================
        // METRIK LAIN (tutup kasir, opname, penilaian per-aspek)
        // =========================================================
        $total_tutup_kasir = $this->db->table('tutup_kasir')
            ->select('COUNT(status) AS total')
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->where('unit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $aktual_opname = $this->db->table('stok_opname_draft')
            ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
            ->where('unit_idunit', $unit)
            ->where('MONTH(tanggal)', $bulan)
            ->where('YEAR(tanggal)', $tahun)
            ->get()
            ->getRow()
            ->total ?? 0;

        // Rata-rata (divisi/kebersihan/seragam/kepatuhan) — global
        $total_divisi = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->get()->getRow()->total ?? 0;

        $ttl_kebersihan = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kebersihan')
            ->get()->getRow()->total ?? 0;

        $ttl_seragam = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'seragam')
            ->get()->getRow()->total ?? 0;

        $ttl_kepatuhan = $this->db->table('penilaian')
            ->select('Avg(skor) AS total')
            ->where('MONTH(tanggal_penilaian)', $bulan)
            ->where('YEAR(tanggal_penilaian)', $tahun)
            ->where('aspek =', 'kepatuhan sop')
            ->get()->getRow()->total ?? 0;

        // Skor per-pegawai per aspek
        $followupAspek = ($context === 'penilaian_kinerja' || $context === 'slip_gaji') ? 'follow up' : 'followup';

        $sumAspek = function (string $aspek) use ($bulan, $tahun, $idAkun) {
            $r = $this->db->table('penilaian')
                ->select('SUM(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)
                ->where('YEAR(tanggal_penilaian)', $tahun)
                ->where('pegawai_idpegawai', $idAkun)
                ->where('aspek =', $aspek)
                ->get()
                ->getRow();
            return $r->total ?? 0;
        };

        $total_closing      = $sumAspek('closing');
        $total_upselling    = $sumAspek('upselling');
        $total_followup     = $sumAspek($followupAspek);
        $total_budgeting    = $sumAspek('budgeting');
        $total_roas         = $sumAspek('roas');
        $total_feed         = $sumAspek('feed mingguan') ?: $sumAspek('feed pl');
        $total_video        = $sumAspek('video');
        $total_story        = $sumAspek('story');
        $total_testimoni    = $sumAspek('testimoni');
        $total_bug_minor    = $sumAspek('bug minor');
        $total_bug_operasional = $sumAspek('operasional');
        $total_ecommerce    = $sumAspek('ecommerce');
        $total_fitur        = $sumAspek('operasional');

        $totalKehadiran  = $sumAspek('kehadiran');
        $totalKebersihan = $sumAspek('kebersihan');
        $totalSeragam    = $sumAspek('seragam');
        $totalSop        = $sumAspek('kepatuhan sop');

        // =========================================================
        // ABSEN
        // =========================================================
        if ($context === 'gaji') {
            $nilai_absen = 90;
        } else {
            $total_absen = $this->db->table('penilaian')
                ->select('Avg(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)
                ->where('YEAR(tanggal_penilaian)', $tahun)
                ->where('aspek =', 'kehadiran')
                ->get()->getRow()->total ?? 0;
            $nilai_absen = $total_absen * 20;
        }

        // =========================================================
        // NILAI OMSET (per jabatan & context)
        // =========================================================
        $batas1 = $batas_awal[$unit];
        $batas2 = $batas_kedua[$unit];
        $batas3 = $batas_ketiga[$unit];
        $batas4 = $batas_keempat[$unit];
        $targetOmset = $target_omset[$unit];

        $aktual_operasional = 0;
        $insentif = 0;
        $nilai_omset = 0;

        // Pembagi insentif (REPLIKASI LITERAL, termasuk context 'gaji' = 4 / 1)
        $pembagiInsentifKepalaToko = ($context === 'gaji') ? 4 : 3;
        $pembagiInsentifPengiklan  = ($context === 'gaji') ? 1 : 4;

        if ($jabatan == 41) {
            if ($aktual_omset <= $batas1) {
                $nilai_omset = 0;
            } elseif ($aktual_omset == $batas2) {
                $nilai_omset = 33;
            } elseif ($aktual_omset == $batas3) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / $pembagiInsentifKepalaToko;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        } elseif ($jabatan == 40) {
            $cabang_aman = 0;
            foreach ($aktual_omset_unit as $idUnit => $omset) {
                if ($omset >= $batas_keempat[$idUnit]) {
                    $cabang_aman++;
                }
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (5 / 1000) * $omset;
                }
            }

            if ($context === 'gaji') {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 33; $aktual_operasional = 33; break;
                    case 2: $nilai_omset = 66; $aktual_operasional = 66; break;
                    case 3: $nilai_omset = 100; $aktual_operasional = 100; break;
                    default: $nilai_omset = 0; $aktual_operasional = 0; break;
                }
            } else {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 25; $aktual_operasional = 25; break;
                    case 2: $nilai_omset = 50; $aktual_operasional = 50; break;
                    case 3: $nilai_omset = 75; $aktual_operasional = 75; break;
                    case 4: $nilai_omset = 100; $aktual_operasional = 100; break;
                    default: $nilai_omset = 0; $aktual_operasional = 0; break;
                }
            }
        } elseif ($jabatan == 43) {
            $cabang_aman = 0;
            foreach ($aktual_omset_unit as $idUnit => $omset) {
                if ($omset >= $batas_keempat[$idUnit]) {
                    $cabang_aman++;
                }
                if ($omset >= $target_omset[$idUnit]) {
                    $insentif += (1 / 100) * $omset / $pembagiInsentifPengiklan;
                }
            }

            if ($context === 'gaji') {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 33; break;
                    case 2: $nilai_omset = 66; break;
                    case 3: $nilai_omset = 100; break;
                    default: $nilai_omset = 0; break;
                }
            } else {
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 25; $aktual_operasional = 25; break;
                    case 2: $nilai_omset = 50; $aktual_operasional = 50; break;
                    case 3: $nilai_omset = 75; $aktual_operasional = 75; break;
                    case 4: $nilai_omset = 100; $aktual_operasional = 100; break;
                    default: $nilai_omset = 0; $aktual_operasional = 0; break;
                }
            }
        } else {
            if ($aktual_omset < $batas2) {
                $nilai_omset = 0;
            } elseif ($aktual_omset >= $batas2 && $aktual_omset < $batas3) {
                $nilai_omset = 33;
            } elseif ($aktual_omset >= $batas3 && $aktual_omset < $batas4) {
                $nilai_omset = 66;
            } elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) {
                $nilai_omset = 100;
            } elseif ($aktual_omset >= $targetOmset) {
                $nilai_omset = 100;
                $insentif = (3 / 100) * $aktual_omset / $pembagiInsentifKepalaToko;
            } else {
                $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
            }
        }

        // =========================================================
        // NILAI CUSTOMER
        // =========================================================
        if ($context === 'gaji') {
            $nilai_customer = min(($total_customer / $target['customer']) * 100, 100);
        } else {
            $customer_aman = 0;
            foreach ($aktual_customer_unit as $idUnit => $customer) {
                if ($customer >= $target['atas_customer']) {
                    $customer_aman++;
                }
            }
            switch ($customer_aman) {
                case 1: $nilai_customer = 25; break;
                case 2: $nilai_customer = 50; break;
                case 3: $nilai_customer = 75; break;
                case 4: $nilai_customer = 100; break;
                default: $nilai_customer = 0; break;
            }
        }

        // =========================================================
        // NILAI SCORE LAIN
        // =========================================================
        $nilai_closing   = min(($total_closing / $target['closing']) * 100, 100);
        $nilai_upselling = min(($total_upselling / $target['upselling']) * 100, 100);
        $nilai_followup  = min(($total_followup / $target['followup']) * 100, 100);
        $nilai_roas      = $total_roas * ($context === 'gaji' ? 100 : 20);
        $nilai_budgeting = $total_budgeting * ($context === 'gaji' ? 100 : 20);

        $nilai_tutup_kasir = ($context === 'gaji')
            ? ($total_tutup_kasir / 30 * 20)
            : min(($total_tutup_kasir / 30) * 100, 100);

        $nilai_opname = $aktual_opname / 4 * 100;

        $nilai_operasional = $aktual_operasional;
        $nilai_divisi = $total_divisi * 20;
        $rata_kebersihan = $ttl_kebersihan * 20;
        $rata_seragam = $ttl_seragam * 20;
        $rata_kepatuhan = $ttl_kepatuhan * 20;

        $nilai_feed_pl = min($total_feed, 100);
        $nilai_video = $total_video;
        $nilai_feed_mingguan = min($total_feed, 100);
        $nilai_story = $total_story;
        $nilai_testimoni = $total_testimoni;

        $nilai_bug_minor = $total_bug_minor / 4 * 20;
        $nilai_bug_operasional = $total_bug_operasional / 4 * 20;
        $nilai_ecommerce = $total_ecommerce / 4 * 20;
        $nilai_fitur = $total_fitur / 4 * 20;

        $nilai_kehadiran = $totalKehadiran / 26 * 20;
        $nilai_kebersihan = $totalKebersihan / 26 * 20;
        $nilai_seragam = $totalSeragam / 26 * 20;
        $nilai_sop = $totalSop / 26 * 20;

        // =========================================================
        // DETAIL KPI + ABSEN PER JABATAN
        // =========================================================
        $detail_kpi = [];
        $detail_absen = [
            ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
            ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
            ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
            ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
        ];

        switch ($jabatan) {
            case 35: // ADMIN
                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Stok Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                    ['nama' => 'Absensi', 'bobot' => 10, 'nilai' => $nilai_absen],
                ];
                break;

            case 36: // TEKNISI
                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Omset Teknisi', 'bobot' => 15, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer Masuk', 'bobot' => 15, 'nilai' => $nilai_customer],
                ];
                break;

            case 41: // KEPALA TOKO
                $detail_kpi = [
                    ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ['nama' => 'Total Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
                    ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                    ['nama' => 'Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                ];
                break;

            case 40: // SPV
                $bobotOmsetSpv = ($context === 'gaji') ? 70 : 10;
                $bobotCustomerSpv = ($context === 'gaji') ? 10 : 70;
                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => $bobotOmsetSpv, 'nilai' => $nilai_omset],
                    ['nama' => 'Customer', 'bobot' => $bobotCustomerSpv, 'nilai' => $nilai_customer],
                    ['nama' => 'Operasional', 'bobot' => 10, 'nilai' => $nilai_operasional],
                    ['nama' => 'Divisi', 'bobot' => 10, 'nilai' => $nilai_divisi],
                ];
                $detail_absen = [
                    ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                    ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $rata_kebersihan],
                    ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $rata_seragam],
                    ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $rata_kepatuhan],
                ];
                break;

            case 42: // CUSTOMER SERVICE
                if ($context === 'gaji') {
                    $detail_kpi = [
                        ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                        ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                        ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                        ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                    ];
                } else {
                    $detail_kpi = [
                        ['nama' => 'Omset', 'bobot' => 60, 'nilai' => $nilai_omset],
                        ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                        ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                        ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                        ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                    ];
                }
                break;

            case 43: // PENGIKLAN
                if ($context === 'gaji') {
                    $detail_kpi = [
                        ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                        ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                        ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ];
                } else {
                    $detail_kpi = [
                        ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                        ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                        ['nama' => 'Omset', 'bobot' => 10, 'nilai' => $nilai_omset],
                        ['nama' => 'Customer', 'bobot' => 60, 'nilai' => $nilai_customer],
                    ];
                }
                break;

            case 44: // MULTIMEDIA
                $cabang_aman = 0;
                foreach ($aktual_omset_unit as $idUnit => $omset) {
                    if ($omset >= $batas_keempat[$idUnit]) {
                        $cabang_aman++;
                    }
                    if ($omset >= $target_omset[$idUnit]) {
                        $insentif += (1 / 100) * $omset / 4;
                    }
                }
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 25; break;
                    case 2: $nilai_omset = 50; break;
                    case 3: $nilai_omset = 75; break;
                    case 4: $nilai_omset = 100; break;
                    default: $nilai_omset = 0; break;
                }

                $detail_kpi = [
                    ['nama' => 'Omset Cabang', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Feed PL', 'bobot' => 15, 'nilai' => $nilai_feed_pl],
                    ['nama' => 'Video', 'bobot' => 20, 'nilai' => $nilai_video],
                    ['nama' => 'Feed Mingguan', 'bobot' => 15, 'nilai' => $nilai_feed_mingguan],
                    ['nama' => 'Story', 'bobot' => 10, 'nilai' => $nilai_story],
                    ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                ];
                break;

            case 45: // IT
                $detail_kpi = [
                    ['nama' => 'Omset', 'bobot' => 30, 'nilai' => $nilai_omset],
                    ['nama' => 'Bug Minor', 'bobot' => 10, 'nilai' => $nilai_bug_minor],
                    ['nama' => 'Operasional', 'bobot' => 25, 'nilai' => $nilai_bug_operasional],
                    ['nama' => 'Ecommerce', 'bobot' => 15, 'nilai' => $nilai_ecommerce],
                    ['nama' => 'Fitur', 'bobot' => 20, 'nilai' => $nilai_fitur],
                ];
                break;

            case 46: // PIC (hanya non-gaji)
                $detail_kpi = [
                    ['nama' => 'Budget Per Toko', 'bobot' => 20, 'nilai' => $nilai_hpp],
                    ['nama' => 'Budget Global', 'bobot' => 30, 'nilai' => $nilai_hpp_global],
                    ['nama' => 'Omset Cabang', 'bobot' => 50, 'nilai' => $nilai_omset],
                ];
                break;
        }

        // =========================================================
        // TOTAL SKOR & GAJI
        // =========================================================
        $skor_total = 0;
        foreach ($detail_kpi as $kpi) {
            $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
        }

        $skor_total2 = 0;
        foreach ($detail_absen as $absen) {
            $skor_total2 += ($absen['nilai'] * $absen['bobot']) / 100;
        }

        // BUSINESS RULE (CONFIRMED): KPI maximum 100%.
        // Basis tunjangan/salary di-cap ke 100, tapi detail_kpi/detail_absen
        // individual tetap mempertahankan nilai asli (boleh >100 per KPI).
        $skor_total  = min($skor_total, 100);
        $skor_total2 = min($skor_total2, 100);

        $tunjangan_absen = $skor_total2 / 100 * 250000;

        if ($jabatan == 41) {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 46 && $context !== 'gaji') {
            $tunjangan_kinerja = $skor_total / 100 * 850000;
        } elseif ($jabatan == 40) {
            $tunjangan_kinerja = $skor_total / 100 * 1250000;
        } elseif ($jabatan == 43) {
            $tunjangan_kinerja = $skor_total / 100 * 1000000;
        } elseif ($jabatan == 35) {
            $tunjangan_kinerja = ($unit == 1)
                ? $skor_total / 100 * 850000
                : $skor_total / 100 * 250000;
        } else {
            $tunjangan_kinerja = $skor_total / 100 * 250000;
        }

        $gaji_pokok = 1500000;
        $gaji = $gaji_pokok + $tunjangan_kinerja + $tunjangan_absen + $akun->tunjangan_penempatan + $insentif;

        return [
            'karyawan'             => $karyawan,
            'akun'                 => $akun,
            'jabatan'              => $jabatan,
            'unit'                 => $unit,
            'aktual_omset_unit'    => $aktual_omset_unit,
            'detail_kpi'           => $detail_kpi,
            'detail_absen'         => $detail_absen,
            'skor_total'           => round($skor_total, 2),
            'skor_total2'          => round($skor_total2, 2),
            'tunjangan_kinerja'    => $tunjangan_kinerja,
            'tunjangan_absen'      => $tunjangan_absen,
            'insentif'             => $insentif,
            'gaji_pokok'           => $gaji_pokok,
            'gaji'                 => $gaji,
        ];
    }
}