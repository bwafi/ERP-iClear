<?php
namespace App\Services\Payroll;

use Config\Database;

/**
 * UnitSalaryCalculationService (asset berjalan dashboard)
 *
 * ═══════════════════════════════════════════════════════════════
 * LEGACY COMPATIBILITY SERVICE — REGRESSION IMPLEMENTATION
 * ═══════════════════════════════════════════════════════════════
 *
 * Replikasi LITERAL dari `TutupKasir::assetberjalan()`.
 * Bertujuan agar OLD controller vs NEW service menghasilkan angka IDENTIK.
 *
 * Catatan kritis PERBEDAAN dari hitungKPIGaji:
 *   1. Customer query: COUNT(DISTINCT dp.penjualan_idpenjualan) via detail_penjualan + barang + penjualan
 *   2. Hanya current month (date('m')/date('Y') hardcoded) — dipertahankan sama
 *   3. Loop SEMUA employee dalam unit → aggregate totalGajiUnit
 *   4. Insentif KT hardcoded /4 (bukan via pembagi context)
 *   5. Total_feed overwritten by feed_mingguan (bukan fallback ke feed_pl)
 *   6. Tidak ada case jabatan 46 (PIC)
 *
 * Service ini BUKAN final. Tidak dipakai untuk pembayaran real.
 * ═══════════════════════════════════════════════════════════════
 */
class UnitSalaryCalculationService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Hitung seluruh gaji per unit + pengeluaran + omset_bulan.
     *
     * Parameter diasumsikan MATCH dengan kondisi session penjalanan real:
     *   - $idJabatan:  dari session('ID_JABATAN') — jabatan user pemanggil
     *   - $unit:       ID unit aktif yang dipilih user (1-4)
     *   - $bulan:      month angka 'mm', default bulan berjalan
     *   - $tahun:      year 'yyyy', default tahun berjalan
     *
     * Note: asli menggunakan date('m')/date('Y') — diset parameter agar bisa
     * regression test untuk periode tertentu, tetapi DEFAULT tetap current month.
     */
    public function calculateForUnit(
        int $idJabatan,
        int $unit,
        string $bulan = null,
        string $tahun = null
    ): array {
        $bulan = $bulan ?? date('m');
        $tahun = $tahun ?? date('Y');

        $list_unit = $this->db->table('unit')
            ->orderBy('idunit', 'ASC')
            ->get()
            ->getResultArray();

        // ── OMSET BULAN INI UNTUK UNIT YANG DIPILIH ──────────────────
        $omset_bulan = $this->db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
            ->where('penjualan.unit_idunit', $unit)
            ->get()
            ->getRow()
            ->total ?? 0;

        $totalGajiUnit = 0;
        $employeeData = [];

        // ── LOOP SETIAP KARYAWAN DALAM UNIT ──────────────────────────
        $karyawanUnit = $this->db->table('akun')
            ->where('ID_UNIT', $unit)
            ->get()
            ->getResultArray();

        foreach ($karyawanUnit as $karyawanArr) {
            $selected_karyawan = (int) $karyawanArr['ID_AKUN'];
            $jabatan = (int) $karyawanArr['ID_JABATAN'];

            // ── QUERY PENEMPATAN ────────────────────────────────────
            $akun = $this->db->query("
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
            ", [$selected_karyawan])->getRow();

            $akun->tunjangan_penempatan = ($akun->penempatan == 0) ? 350000 : 0;

            // ── TARGET & THRESHOLD (context gaji) ───────────────────
            $target_unit = [
                1 => ['customer' => 130, 'closing' => 111, 'upselling' => 14, 'followup' => 100, 'roas' => 5],
                2 => ['customer' => 118, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 4],
                3 => ['customer' => 210, 'closing' => 188, 'upselling' => 27, 'followup' => 60,  'roas' => 3],
                4 => ['customer' => 118, 'closing' => 96,  'upselling' => 14, 'followup' => 80,  'roas' => 4],
            ];
            $target = $target_unit[$unit] ?? $target_unit[1];

            $batas_awal    = [1 => 30000000, 2 => 18000000, 3 => 40000000, 4 => 18000000];
            $batas_kedua   = [1 => 35000000, 2 => 22000000, 3 => 45000000, 4 => 22000000];
            $batas_ketiga  = [1 => 40000000, 2 => 26000000, 3 => 50000000, 4 => 26000000];
            $batas_keempat = [1 => 45000000, 2 => 30000000, 3 => 55000000, 4 => 30000000];
            $target_omset  = [1 => 50000000, 2 => 35000000, 3 => 60000000, 4 => 35000000];

            // ── OMSET & CUSTOMER PER CABANG ────────────────────────
            $aktual_omset_unit = [];
            $aktual_customer_unit = [];

            foreach ([1, 2, 3, 4] as $idUnit) {
                $aktual_omset_unit[$idUnit] = $this->db->table('detail_penjualan')
                    ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
                    ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
                    ->where('MONTH(penjualan.tanggal)', $bulan)
                    ->where('YEAR(penjualan.tanggal)', $tahun)
                    ->where('penjualan.unit_idunit', $idUnit)
                    ->get()->getRow()->total ?? 0;

                $aktual_customer_unit[$idUnit] = $this->db->table('detail_penjualan dp')
                    ->select('COUNT(DISTINCT dp.penjualan_idpenjualan) AS total')
                    ->join('barang b', 'b.idbarang = dp.barang_idbarang')
                    ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
                    ->where('MONTH(p.tanggal)', $bulan)
                    ->where('YEAR(p.tanggal)', $tahun)
                    ->where('p.unit_idunit', $idUnit)
                    ->get()->getRow()->total ?? 0;
            }

            $aktual_omset = $aktual_omset_unit[$unit] ?? 0;
            $aktual_customer = $aktual_customer_unit[$unit] ?? 0;

            // ── TUTUP KASIR, OPNAME, ABSEN ─────────────────────────
            $total_tutup_kasir = $this->db->table('tutup_kasir')
                ->select('COUNT(status) AS total')
                ->where('MONTH(tanggal)', $bulan)->where('YEAR(tanggal)', $tahun)
                ->where('unit', $unit)->get()->getRow()->total ?? 0;

            $aktual_opname = $this->db->table('stok_opname_draft')
                ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
                ->where('unit_idunit', $unit)
                ->where('MONTH(tanggal)', $bulan)->where('YEAR(tanggal)', $tahun)
                ->get()->getRow()->total ?? 0;

            $aktual_absen = 90; // HARDCODED — dipertahankan sama

            // ── GLOBAL AVERAGE (divisi/kebersihan/seragam/kepatuhan) ─
            $total_divisi = $this->db->table('penilaian')
                ->select('Avg(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)->where('YEAR(tanggal_penilaian)', $tahun)
                ->get()->getRow()->total ?? 0;

            $ttl_kebersihan = $this->db->table('penilaian')
                ->select('Avg(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)->where('YEAR(tanggal_penilaian)', $tahun)
                ->where('aspek', 'kebersihan')
                ->get()->getRow()->total ?? 0;

            $ttl_seragam = $this->db->table('penilaian')
                ->select('Avg(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)->where('YEAR(tanggal_penilaian)', $tahun)
                ->where('aspek', 'seragam')
                ->get()->getRow()->total ?? 0;

            $ttl_kepatuhan = $this->db->table('penilaian')
                ->select('Avg(skor) AS total')
                ->where('MONTH(tanggal_penilaian)', $bulan)->where('YEAR(tanggal_penilaian)', $tahun)
                ->where('aspek', 'kepatuhan sop')
                ->get()->getRow()->total ?? 0;

            // ── SUM ASPEK PER PEGAWAI ──────────────────────────────
            $sumAspek = function ($aspek) use ($selected_karyawan, $bulan, $tahun) {
                return $this->db->table('penilaian')
                    ->select('SUM(skor) AS total')
                    ->where('MONTH(tanggal_penilaian)', $bulan)->where('YEAR(tanggal_penilaian)', $tahun)
                    ->where('pegawai_idpegawai', $selected_karyawan)->where('aspek', $aspek)
                    ->get()->getRow()->total ?? 0;
            };

            $total_closing       = $sumAspek('closing');
            $total_upselling     = $sumAspek('upselling');
            $total_followup      = $sumAspek('followup'); // assetberjalan pakai 'followup' (tanpa spasi)
            $total_budgeting     = $sumAspek('budgeting');
            $total_roas          = $sumAspek('roas');
            $total_feed_pl       = $sumAspek('feed pl');
            $total_video         = $sumAspek('video');
            $total_feed_mingguan = $sumAspek('feed mingguan');
            $total_story         = $sumAspek('story');
            $total_testimoni     = $sumAspek('testimoni');
            $total_bug_minor     = $sumAspek('bug minor');
            $total_operasional   = $sumAspek('operasional');
            $total_ecommerce     = $sumAspek('ecommerce');

            $totalKehadiran  = $sumAspek('kehadiran');
            $totalKebersihan = $sumAspek('kebersihan');
            $totalSeragam    = $sumAspek('seragam');
            $totalSop        = $sumAspek('kepatuhan sop');

            // ── NILAI ASPEK ────────────────────────────────────────
            // total_feed: overwritten by feed_mingguan (last write wins — VERBATIM from controller)
            $total_feed = $total_feed_pl;
            $total_feed = $total_feed_mingguan;

            $batas1 = $batas_awal[$unit];
            $batas2 = $batas_kedua[$unit];
            $batas3 = $batas_ketiga[$unit];
            $batas4 = $batas_keempat[$unit];
            $targetOmset = $target_omset[$unit];
            $aktual_operasional = 0;
            $insentif = 0;
            $nilai_omset = 0;

            // ── NILAI OMSET BY JABATAN ──────────────────────────────
            if ($jabatan == 41) {
                if ($aktual_omset <= $batas1) { $nilai_omset = 0; }
                elseif ($aktual_omset == $batas2) { $nilai_omset = 33; }
                elseif ($aktual_omset == $batas3) { $nilai_omset = 66; }
                elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) { $nilai_omset = 100; }
                elseif ($aktual_omset >= $targetOmset) {
                    $nilai_omset = 100;
                    $insentif = (3 / 100) * $aktual_omset / 4; // HARDCODED /4
                } else {
                    $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
                }
            } elseif ($jabatan == 40) {
                $cabang_aman = 0;
                foreach ($aktual_omset_unit as $idUnit => $omset) {
                    if ($omset >= $batas_keempat[$idUnit]) { $cabang_aman++; }
                    if ($omset >= $target_omset[$idUnit]) { $insentif += (5 / 1000) * $omset; }
                }
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 33; $aktual_operasional = 33; break;
                    case 2: $nilai_omset = 66; $aktual_operasional = 66; break;
                    case 3: $nilai_omset = 100; $aktual_operasional = 100; break;
                    default: $nilai_omset = 0; $aktual_operasional = 0; break;
                }
            } elseif ($jabatan == 43) {
                $cabang_aman = 0;
                foreach ($aktual_omset_unit as $idUnit => $omset) {
                    if ($omset >= $batas_keempat[$idUnit]) { $cabang_aman++; }
                    if ($omset >= $target_omset[$idUnit]) { $insentif += (1 / 100) * $omset; }
                }
                switch ($cabang_aman) {
                    case 1: $nilai_omset = 33; break;
                    case 2: $nilai_omset = 66; break;
                    case 3: $nilai_omset = 100; break;
                    default: $nilai_omset = 0; break;
                }
            } else {
                if ($aktual_omset < $batas2) { $nilai_omset = 0; }
                elseif ($aktual_omset >= $batas2 && $aktual_omset < $batas3) { $nilai_omset = 33; }
                elseif ($aktual_omset >= $batas3 && $aktual_omset < $batas4) { $nilai_omset = 66; }
                elseif ($aktual_omset >= $batas4 && $aktual_omset < $targetOmset) { $nilai_omset = 100; }
                elseif ($aktual_omset >= $targetOmset) {
                    $nilai_omset = 100;
                    $insentif = (3 / 100) * $aktual_omset / 4; // HARDCODED /4
                } else {
                    $nilai_omset = (($aktual_omset - $batas1) / ($batas4 - $batas1)) * 100;
                }
            }

            $nilai_customer    = min(($aktual_customer / $target['customer']) * 100, 100);
            $nilai_closing     = min(($total_closing / $target['closing']) * 100, 100);
            $nilai_upselling   = min(($total_upselling / $target['upselling']) * 100, 100);
            $nilai_followup    = min(($total_followup / $target['followup']) * 100, 100);
            $nilai_roas        = $total_roas * 100;
            $nilai_budgeting   = $total_budgeting * 100;
            $nilai_tutup_kasir = $total_tutup_kasir / 30 * 20;
            $nilai_opname      = $aktual_opname / 4 * 100;
            $nilai_absen       = $aktual_absen;
            $nilai_operasional = $aktual_operasional;
            $nilai_divisi      = $total_divisi * 20;

            $rata_kebersihan = $ttl_kebersihan * 20;
            $rata_seragam    = $ttl_seragam * 20;
            $rata_kepatuhan  = $ttl_kepatuhan * 20;

            $nilai_feed_pl       = $total_feed;
            $nilai_video         = $total_video;
            $nilai_feed_mingguan = $total_feed;
            $nilai_story         = $total_story;
            $nilai_testimoni     = $total_testimoni;
            $nilai_bug_minor         = $total_bug_minor / 4 * 20;
            $nilai_bug_operasional   = $total_operasional / 4 * 20;
            $nilai_ecommerce         = $total_ecommerce / 4 * 20;
            $nilai_fitur             = $total_operasional / 4 * 20; // 'fitur' pakai aspek 'operasional'

            $nilai_kehadiran = $totalKehadiran / 26 * 20;
            $nilai_kebersihan = $totalKebersihan / 26 * 20;
            $nilai_seragam    = $totalSeragam / 26 * 20;
            $nilai_sop        = $totalSop / 26 * 20;

            // ── DETAIL KPI + ABSEN PER JABATAN ─────────────────────
            // VERBATIM dari assetberjalan(): detail_kpi & detail_absen
            // DIINISIALISASI KOSONG dan diisi per case jabatan.
            // Jabatan tanpa case (mis. 1, 46) => keduanya KOSONG.
            $detail_kpi = [];
            $detail_absen = [];

            switch ($jabatan) {
                case 35:
                    $detail_kpi = [
                        ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                        ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                        ['nama' => 'Stok Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                        ['nama' => 'Absensi', 'bobot' => 10, 'nilai' => $nilai_absen],
                    ];
                    $detail_absen = [
                        ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                        ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                        ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                        ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                    ];
                    break;
                case 36:
                    $detail_kpi = [
                        ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                        ['nama' => 'Omset Teknisi', 'bobot' => 15, 'nilai' => $nilai_omset],
                        ['nama' => 'Customer Masuk', 'bobot' => 15, 'nilai' => $nilai_customer],
                    ];
                    $detail_absen = [
                        ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                        ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                        ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                        ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                    ];
                    break;
                case 41:
                    $detail_kpi = [
                        ['nama' => 'Omset Toko', 'bobot' => 70, 'nilai' => $nilai_omset],
                        ['nama' => 'Total Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
                        ['nama' => 'Tutup Kasir', 'bobot' => 10, 'nilai' => $nilai_tutup_kasir],
                        ['nama' => 'Opname', 'bobot' => 10, 'nilai' => $nilai_opname],
                    ];
                    $detail_absen = [
                        ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                        ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                        ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                        ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                    ];
                    break;
                case 40:
                    $detail_kpi = [
                        ['nama' => 'Omset Cabang', 'bobot' => 70, 'nilai' => $nilai_omset],
                        ['nama' => 'Customer', 'bobot' => 10, 'nilai' => $nilai_customer],
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
                case 42:
                    $detail_kpi = [
                        ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                        ['nama' => 'Closing', 'bobot' => 10, 'nilai' => $nilai_closing],
                        ['nama' => 'Upselling', 'bobot' => 10, 'nilai' => $nilai_upselling],
                        ['nama' => 'Follow Up', 'bobot' => 10, 'nilai' => $nilai_followup],
                    ];
                    $detail_absen = [
                        ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                        ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                        ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                        ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                    ];
                    break;
                case 43:
                    $detail_kpi = [
                        ['nama' => 'Budgeting', 'bobot' => 15, 'nilai' => $nilai_budgeting],
                        ['nama' => 'ROAS', 'bobot' => 15, 'nilai' => $nilai_roas],
                        ['nama' => 'Omset', 'bobot' => 70, 'nilai' => $nilai_omset],
                    ];
                    $detail_absen = [
                        ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                        ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                        ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                        ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                    ];
                    break;
                case 44:
                    $detail_kpi = [
                        ['nama' => 'Omset Cabang', 'bobot' => 30, 'nilai' => $nilai_omset],
                        ['nama' => 'Feed PL', 'bobot' => 15, 'nilai' => $nilai_feed_pl],
                        ['nama' => 'Video', 'bobot' => 20, 'nilai' => $nilai_video],
                        ['nama' => 'Feed Mingguan', 'bobot' => 15, 'nilai' => $nilai_feed_mingguan],
                        ['nama' => 'Story', 'bobot' => 10, 'nilai' => $nilai_story],
                        ['nama' => 'Testimoni', 'bobot' => 10, 'nilai' => $nilai_testimoni],
                    ];
                    $detail_absen = [
                        ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                        ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                        ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                        ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                    ];
                    break;
                case 45:
                    $detail_kpi = [
                        ['nama' => 'Omset', 'bobot' => 30, 'nilai' => $nilai_omset],
                        ['nama' => 'Bug Minor', 'bobot' => 10, 'nilai' => $nilai_bug_minor],
                        ['nama' => 'Operasional', 'bobot' => 25, 'nilai' => $nilai_bug_operasional],
                        ['nama' => 'Ecommerce', 'bobot' => 15, 'nilai' => $nilai_ecommerce],
                        ['nama' => 'Fitur', 'bobot' => 20, 'nilai' => $nilai_fitur],
                    ];
                    $detail_absen = [
                        ['nama' => 'Kehadiran', 'bobot' => 40, 'nilai' => $nilai_kehadiran],
                        ['nama' => 'Kebersihan', 'bobot' => 20, 'nilai' => $nilai_kebersihan],
                        ['nama' => 'Seragam', 'bobot' => 20, 'nilai' => $nilai_seragam],
                        ['nama' => 'Kepatuhan SOP', 'bobot' => 20, 'nilai' => $nilai_sop],
                    ];
                    break;
            }

            // ── SKOR TOTAL ──────────────────────────────────────────
            $skor_total = 0;
            foreach ($detail_kpi as $kpi) {
                $skor_total += ($kpi['nilai'] * $kpi['bobot']) / 100;
            }
            $skor_total2 = 0;
            foreach ($detail_absen as $absen) {
                $skor_total2 += ($absen['nilai'] * $absen['bobot']) / 100;
            }

            // ── TUNJANGAN & GAJI ────────────────────────────────────
            $tunjangan_absen = $skor_total2 / 100 * 250000;

            if ($jabatan == 41) { $tunjangan_kinerja = $skor_total / 100 * 850000; }
            elseif ($jabatan == 40) { $tunjangan_kinerja = $skor_total / 100 * 1250000; }
            elseif ($jabatan == 43) { $tunjangan_kinerja = $skor_total / 100 * 1000000; }
            elseif ($jabatan == 35) {
                $tunjangan_kinerja = ($unit == 1)
                    ? $skor_total / 100 * 850000
                    : $skor_total / 100 * 250000;
            } else { $tunjangan_kinerja = $skor_total / 100 * 250000; }

            $gaji_pokok = 1500000;
            $gaji = $gaji_pokok + $tunjangan_kinerja + $tunjangan_absen + $akun->tunjangan_penempatan + $insentif;

            $totalGajiUnit += $gaji;

            $employeeData[$selected_karyawan] = [
                'nama'             => $akun->NAMA_AKUN,
                'jabatan'          => $jabatan,
                'unit'             => $unit,
                'detail_kpi'       => $detail_kpi,
                'detail_absen'     => $detail_absen,
                'skor_total'       => round($skor_total, 2),
                'skor_total2'      => round($skor_total2, 2),
                'tunjangan_kinerja'=> $tunjangan_kinerja,
                'tunjangan_absen'  => $tunjangan_absen,
                'insentif'         => $insentif,
                'penempatan'       => $akun->tunjangan_penempatan,
                'gaji_pokok'       => $gaji_pokok,
                'gaji'             => $gaji,
            ];
        }

        // ── PENGELUARAN ─────────────────────────────────────────────
        $pengeluaran = $this->db->table('kas_keluar')
            ->selectSum('kas_keluar.jumlah', 'total')
            ->join('kategori_kas', 'kategori_kas.idkategori_kas = kas_keluar.kategori_idkategori')
            ->where('MONTH(kas_keluar.tanggal)', $bulan)
            ->where('YEAR(kas_keluar.tanggal)', $tahun)
            ->where('kas_keluar.idunit', $unit)
            ->whereIn('kas_keluar.kategori_idkategori', [1, 2, 3, 4, 5, 11, 18])
            ->get()
            ->getRow()
            ->total ?? 0;

        return [
            'id_jabatan'     => $idJabatan,
            'selected_unit'  => $unit,
            'list_unit'      => $list_unit,
            'omset_bulan'    => $omset_bulan,
            'pengeluaran'    => $pengeluaran,
            'totalGajiUnit'  => round($totalGajiUnit, 2),
            'employeeData'   => $employeeData,
        ];
    }
}