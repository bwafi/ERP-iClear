<?php

namespace Tests;

use App\Services\Finance\FinanceScopeService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi: filter bulan/tahun harus dipakai oleh semua query di
 * TutupKasir::assetberjalan() (omset, customer, tutup kasir, opname,
 * penilaian, pengeluaran). Sebelumnya hanya omset yang memakai $bulan/$tahun,
 * pengeluaran dan gaji memakai date('m')/date('Y').
 */
class AssetBerjalanFilterTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'unit', 'akun', 'jabatan', 'menu',
            'spv_units',
            'stok_barang', 'service', 'pelanggan',
            'penjualan', 'detail_penjualan', 'barang',
            'kas_keluar', 'kategori_kas',
            'penilaian', 'tutup_kasir', 'stok_opname_periode',
            'incentive_members', 'finance_payroll',
            'salary_components', 'salary_structures',
            'kpi_weights', 'kpi_components', 'kpi_targets', 'kpi_evaluations',
            'kpi_attendance_detail', 'kpi_attendance_off',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL, KABUPATEN_UNIT TEXT NULL, jenis TEXT NULL, is_trial INT NULL, is_operational INT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL, ALAMAT TEXT NULL, TERIMA_GAJI INT NULL, STATUS_PEGAWAI INT NULL, deleted TEXT NULL)');
        $q('CREATE TABLE spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');

        $q('CREATE TABLE stok_barang (
                idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL,
                stok_minimum REAL NULL, nama_barang TEXT NULL, kode_barang TEXT NULL,
                total_penjualan REAL NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, kabupaten TEXT NULL, kecamatan TEXT NULL, deleted TEXT NULL)');

        $q('CREATE TABLE penjualan (
                idpenjualan INTEGER PRIMARY KEY AUTO_INCREMENT, kode_invoice TEXT NULL,
                tanggal TEXT NULL, unit_idunit INT NULL, id_pelanggan INT NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INTEGER PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, barang_idbarang INT NULL,
                sub_total REAL NULL, hpp_penjualan REAL NULL)');
        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, nama_barang TEXT NULL, idkategori INT NULL)');

        $q('CREATE TABLE kategori_kas (idkategori_kas INTEGER PRIMARY KEY, kategori TEXT NULL)');
        $q('CREATE TABLE kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                kategori_idkategori INT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                penerima TEXT NULL, idunit INT NULL, jenis TEXT NULL, idbank TEXT NULL,
                created_on TEXT NULL, updated_on TEXT NULL, no_akun TEXT NULL)');

        $q('CREATE TABLE penilaian (
                idpenilaian INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal_penilaian TEXT NULL,
                pegawai_idpegawai INT NULL, aspek TEXT NULL, skor REAL NULL, keterangan TEXT NULL)');
        $q('CREATE TABLE tutup_kasir (
                idtutup_kasir INTEGER PRIMARY KEY AUTO_INCREMENT, tanggal TEXT NULL,
                unit INT NULL, status TEXT NULL, kas_awal REAL NULL, kas_akhir REAL NULL)');
        $q('CREATE TABLE incentive_members (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                incentive_group_id INT NULL, is_active INT NULL)');
        $q('CREATE TABLE finance_payroll (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, pegawai_id INT NULL, total REAL NULL,
                potongan_kasbon REAL NULL, total_bersih REAL NULL, status TEXT NULL, due_date TEXT NULL)');
        $q('CREATE TABLE stok_opname_periode (
                id INTEGER PRIMARY KEY AUTO_INCREMENT, unit_idunit INT NULL, tanggal TEXT NULL,
                status TEXT NULL, terisi_barang INT NULL, total_barang INT NULL)');

        // Mesin gaji payroll (KpiCalculationService + SalaryCalculationService).
        // Kosong = skor KPI 0, jadi angka gaji murni dari salary_structures + lembur.
        $q('CREATE TABLE salary_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE salary_structures (
                id INT PRIMARY KEY AUTO_INCREMENT, position_id INT NULL,
                salary_component_id INT NULL, unit_id INT NULL, context TEXT NULL,
                base_value REAL NULL, calculation_type TEXT NULL, multiplier REAL NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_components (
                id INT PRIMARY KEY, code TEXT NULL, name TEXT NULL,
                type TEXT NULL, is_active INT NULL)');
        $q('CREATE TABLE kpi_weights (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                position_id INT NULL, weight REAL NULL, weight_group TEXT NULL,
                effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_targets (
                id INT PRIMARY KEY AUTO_INCREMENT, kpi_component_id INT NULL,
                unit_id INT NULL, position_id INT NULL, context TEXT NULL,
                target_value REAL NULL, effective_from TEXT NULL, effective_to TEXT NULL)');
        $q('CREATE TABLE kpi_evaluations (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                kpi_component_id INT NULL, evaluator_id INT NULL,
                evaluation_date TEXT NULL, raw_score REAL NULL,
                period_year INT NULL, period_month INT NULL)');
        $q('CREATE TABLE kpi_attendance_detail (id INT PRIMARY KEY AUTO_INCREMENT, evaluation_id INT NULL)');
        $q('CREATE TABLE kpi_attendance_off (
                id INT PRIMARY KEY AUTO_INCREMENT, employee_id INT NULL,
                evaluation_date TEXT NULL, period_month INT NULL,
                period_year INT NULL, evaluator_id INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LOGO, KABUPATEN_UNIT, jenis, is_trial, is_operational) VALUES
            (1, 'ICLEAR Probolinggo', 'logo.png', 'Probolinggo', 'pribadi', 0, 1),
            (3, 'ICLEAR Banyuwangi', 'logo.png', 'Banyuwangi', 'franchise', 0, 1),
            (4, 'ICLEAR Pandaan', 'logo.png', 'Pasuruan', 'franchise', 0, 1),
            (50, 'Head Office', 'logo.png', 'Banyuwangi', 'Kantor', 0, 0)");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (0, 'Finance', '[]'), (40, 'SPV', '[]'), (41, 'Kepala Toko', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ALAMAT, TERIMA_GAJI, STATUS_PEGAWAI, deleted) VALUES
            (44, 50, 0, 'Ira Kurniawati', 'Jalan Test', 0, 1, NULL),
            (49, 50, 40, 'SPV Wilayah', 'Jalan Test', 0, 1, NULL),
            (100, 3, 41, 'Kepala Toko', 'Jalan Test', 1, 1, NULL),
            (101, 3, 41, 'Sudah Resign', 'Jalan Test', 1, 0, NULL)");
        $q("INSERT INTO spv_units (spv_id, unit_id) VALUES (49,1),(49,3)");
        // Struktur sengaja beda dari hardcode legacy (1.500.000) supaya test
        // bisa membedakan mesin gaji payroll dari blok lama.
        $q("INSERT INTO salary_components (id, code, name, type, is_active) VALUES
            (1, 'GAJI_POKOK', 'Gaji Pokok', 'base', 1),
            (2, 'TUNJANGAN_KINERJA', 'Tunjangan Kinerja', 'allowance', 1),
            (3, 'TUNJANGAN_ABSEN', 'Tunjangan Absen', 'allowance', 1)");
        $q("INSERT INTO salary_structures (position_id, salary_component_id, unit_id, base_value, calculation_type, multiplier, effective_from, effective_to) VALUES
            (41, 1, NULL, 2000000, 'fixed', NULL, '2026-01-01', NULL),
            (41, 2, NULL, 850000, 'percent_of_kpi', NULL, '2026-01-01', NULL),
            (41, 3, NULL, 250000, 'percent_of_kpi', NULL, '2026-01-01', NULL)");
        $q("INSERT INTO kategori_kas (idkategori_kas, kategori) VALUES (1,'Operasional'),(2,'ATK'),(3,'Listrik'),(4,'Air'),(5,'Kebersihan'),(6,'Lain-lain'),(7,'HPP'),(10,'SDM'),(11,'Lainnya'),(18,'Transport')");
        $q("INSERT INTO menu (idmenu, urutan, nama_menu, roles, url, show_menu) VALUES (1,1,'Dashboard','[]','dashboard',1)");

        // September 2026 data (bulan lampau)
        $q("INSERT INTO penjualan (idpenjualan, kode_invoice, tanggal, unit_idunit) VALUES (1,'SLL-1','2026-09-15 10:00:00',3)");
        $q("INSERT INTO detail_penjualan (penjualan_idpenjualan, barang_idbarang, sub_total, hpp_penjualan) VALUES (1,NULL,10000000,5000000)");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-09-10',1,3496682,3)");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-10-10',1,2207580,3)");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-08-10',1,1234567,3)");
        // Lembur kategori 10: masuk Total Gaji, tidak masuk Pengeluaran.
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, deskripsi, jumlah, penerima, idunit) VALUES ('2026-09-20',10,'Lembur closing',100000,'100',3)");

        // October 2026 data
        $q("INSERT INTO penjualan (idpenjualan, kode_invoice, tanggal, unit_idunit) VALUES (2,'SLL-2','2026-10-15 10:00:00',3)");
        $q("INSERT INTO detail_penjualan (penjualan_idpenjualan, barang_idbarang, sub_total, hpp_penjualan) VALUES (2,NULL,8000000,4000000)");
        // Kategori terkontrol (18) ikut Pengeluaran; 6 (Lain-lain), 11 (Administrasi), dan HPP (7) tidak.
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-10-05',6,500000,3)");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-10-06',11,700000,3)");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-10-07',7,1000000,3)");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-10-08',18,300000,3)");
        // Omset Agustus besar supaya realisasi kategori A di bawah batas 6%.
        $q("INSERT INTO penjualan (idpenjualan, kode_invoice, tanggal, unit_idunit) VALUES (3,'SLL-3','2026-08-15 10:00:00',3)");
        $q("INSERT INTO detail_penjualan (penjualan_idpenjualan, barang_idbarang, sub_total, hpp_penjualan) VALUES (3,NULL,70000000,10000000)");

        // Juli 2026 unit 1: omset 10.000.000 -> batas 600.000; realisasi 550.000 (91,7%).
        $q("INSERT INTO penjualan (idpenjualan, kode_invoice, tanggal, unit_idunit) VALUES (4,'SLL-4','2026-07-15 10:00:00',1)");
        $q("INSERT INTO detail_penjualan (penjualan_idpenjualan, barang_idbarang, sub_total, hpp_penjualan) VALUES (4,NULL,12000000,2000000)");
        $q("INSERT INTO kas_keluar (tanggal, kategori_idkategori, jumlah, idunit) VALUES ('2026-07-10',1,550000,1)");
    }

    private function sesi(int $akun, int $unit, int $jabatan): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => $akun,
            'ID_UNIT'      => $unit,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Finance',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function halaman(string $query): string
    {
        $this->db->setPrefix('');
        return (string) $this->get('asset_berjalan' . $query)->getBody();
    }

    private function kartu(string $html, string $judul): string
    {
        $pola = '#<h6[^>]*>' . preg_quote($judul, '#') . '</h6>.*?Rp\s+([0-9.]+)#s';
        $this->assertMatchesRegularExpression($pola, $html, "Kartu \"{$judul}\" tidak ditemukan");
        preg_match($pola, $html, $m);
        return $m[1];
    }

    public function testBulanLampauMenggunakanPeriodeDipilih(): void
    {
        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=3&bulan=9&tahun=2026');
        // Pengeluaran September harus Rp 3.496.682, bukan pengeluaran Oktober
        $this->assertSame('3.496.682', $this->kartu($html, 'Biaya Terkontrol (Maks 6%)'));
        // Omset September (10jt - 5jt), bukan omset Oktober (8jt - 4jt).
        $this->assertSame('5.000.000', $this->kartu($html, 'Omset Bulan Ini'));
    }

    /** Filter harus mengikuti parameter, bukan hanya bekerja untuk satu bulan tertentu. */
    public function testBulanLampauLainIkutTerfilter(): void
    {
        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=3&bulan=8&tahun=2026');

        $this->assertSame('1.234.567', $this->kartu($html, 'Biaya Terkontrol (Maks 6%)'));
    }

    /**
     * Total Gaji harus datang dari mesin payroll (salary_structures + KPI +
     * lembur), bukan blok legacy yang selalu memakai gaji pokok 1.500.000.
     */
    public function testTotalGajiMemakaiMesinPayroll(): void
    {
        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=3&bulan=9&tahun=2026');

        // 2.000.000 pokok + 350.000 penempatan + 100.000 lembur.
        // Karyawan 101 (STATUS_PEGAWAI=0) tidak ikut.
        $this->assertSame('2.450.000', $this->kartu($html, 'Total Gaji'));

        // Rincian harus memuat lembur, kalau tidak kolom-kolomnya tidak
        // menjumlah ke Total dan Finance membacanya sebagai selisih.
        $this->assertStringContainsString('<th class="text-end">Lembur</th>', $html);
        $this->assertStringContainsString('Rp 100.000', $html);
    }

    /**
     * Panel "Beban Head Office" hanya untuk role lintas cabang.
     * Staf cabang (mis. Teknisi) yang membuka URL langsung tidak boleh
     * menerima total gaji HO.
     */
    public function testPanelGajiHoTidakDirenderUntukStafCabang(): void
    {
        $this->sesi(100, 3, 36); // Teknisi unit 3
        $html = $this->halaman('?bulan=9&tahun=2026');

        $this->assertStringNotContainsString('Beban Head Office', $html);

        // Total gaji unit sendiri tetap boleh dilihat staf cabang.
        $this->assertSame('2.450.000', $this->kartu($html, 'Total Gaji'));
    }

    /** Role lintas cabang (Finance) tetap melihat panel gaji HO. */
    public function testPanelGajiHoDirenderUntukRoleLintasUnit(): void
    {
        $this->sesi(44, 50, 0); // Finance
        $html = $this->halaman('?unit=3&bulan=9&tahun=2026');

        $this->assertStringContainsString('Beban Head Office', $html);
    }

    /** Rincian HO per unit tidak dibatasi role; SPV tetap hanya membuka unit wilayahnya. */
    public function testRincianHoPerUnitDirenderUntukSpv(): void
    {
        $this->sesi(49, 50, 40); // SPV wilayah 1 & 3
        $html = $this->halaman('?unit=3&bulan=9&tahun=2026');

        $this->assertMatchesRegularExpression('#<h4[^>]*>\s*ICLEAR Banyuwangi\s*</h4>#', $html);
        $this->assertStringContainsString('Beban Head Office', $html);
        $this->assertStringContainsString('Lihat rincian per unit', $html);
    }

    /** SPV tidak bisa membuka unit di luar spv_units; fallback ke wilayah pertama. */
    public function testSpvHanyaBisaMembukaUnitWilayahnya(): void
    {
        $this->sesi(49, 50, 40);

        $luar = $this->halaman('?unit=4&bulan=9&tahun=2026');
        $this->assertMatchesRegularExpression('#<h4[^>]*>\s*ICLEAR Probolinggo\s*</h4>#', $luar);

        preg_match('#<select id="filterUnit".*?</select>#s', $luar, $m);
        $this->assertStringContainsString('ICLEAR Probolinggo', $m[0]);
        $this->assertStringContainsString('ICLEAR Banyuwangi', $m[0]);
        $this->assertStringNotContainsString('ICLEAR Pandaan', $m[0]);
    }

    /**
     * Kategori biaya terkontrol: kategori 18 (Lainnya 6%) ikut dihitung,
     * kategori 6 (Lain-lain) dan 11 (Administrasi) tidak.
     */
    public function testPengeluaranMemakaiKategoriBiayaTerkontrol(): void
    {
        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=3&bulan=10&tahun=2026');

        // 2.207.580 (kategori 1) + 300.000 (kategori 18); 500.000 kategori 6 dan 700.000 kategori 11 tidak ikut.
        $this->assertSame('2.507.580', $this->kartu($html, 'Biaya Terkontrol (Maks 6%)'));
    }

    /** Status rasio: melebihi, hampir mencapai, atau aman. */
    public function testStatusBatasEnamPersen(): void
    {
        $this->sesi(44, 50, 0);

        // Oktober: omset 4.000.000 -> batas 240.000; realisasi 2.507.580.
        $melebihi = $this->halaman('?unit=3&bulan=10&tahun=2026');
        $this->assertStringContainsString('Melebihi batas 6%', $melebihi);

        // Agustus: omset 60.000.000 -> batas 3.600.000; realisasi 1.234.567.
        $aman = $this->halaman('?unit=3&bulan=8&tahun=2026');
        $this->assertStringContainsString('dari batas — Aman', $aman);
        $this->assertStringNotContainsString('Melebihi batas 6%', $aman);

        // Juli unit 1: batas 600.000, realisasi 550.000 -> 91,7%.
        $hampir = $this->halaman('?unit=1&bulan=7&tahun=2026');
        $this->assertStringContainsString('91,7% dari batas — Hampir mencapai batas', $hampir);
    }

    /** Kartu di luar 6% = seluruh kas keluar dikurangi biaya terkontrol, termasuk HPP. */
    public function testPengeluaranDiLuarBatasTidakTermasukBiayaTerkontrol(): void
    {
        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=3&bulan=10&tahun=2026');

        // Total 4.707.580 - biaya terkontrol 2.507.580 = 2.200.000 (kategori 6, 11, HPP).
        $this->assertSame('2.200.000', $this->kartu($html, 'Biaya Kebutuhan (Di Luar 6%)'));
    }

    /** Detail biaya terkontrol hanya memuat kategori A. */
    public function testDetailTerkontrolHanyaKategoriTerkontrol(): void
    {
        $this->sesi(44, 50, 0);
        $this->db->setPrefix('');

        $res = $this->get('asset_berjalan/detail-pengeluaran?unit=3&bulan=10&tahun=2026&kelompok=terkontrol');
        $res->assertStatus(200);
        $json = json_decode((string) $res->getJSON(), true);

        $this->assertSame(2507580.0, (float) $json['total']);
        $kategori = array_map('intval', array_column($json['data'], 'kategori_idkategori'));
        sort($kategori);
        $this->assertSame([1, 18], $kategori);
    }

    /** Detail biaya kebutuhan = semua kas keluar di luar kategori A; total = angka kartu. */
    public function testDetailKebutuhanTotalSamaDenganKartu(): void
    {
        $this->sesi(44, 50, 0);
        $this->db->setPrefix('');

        $res = $this->get('asset_berjalan/detail-pengeluaran?unit=3&bulan=10&tahun=2026&kelompok=kebutuhan');
        $res->assertStatus(200);
        $json = json_decode((string) $res->getJSON(), true);

        $this->assertSame(2200000.0, (float) $json['total']);
        $kategori = array_map('intval', array_column($json['data'], 'kategori_idkategori'));
        sort($kategori);
        $this->assertSame([6, 7, 11], $kategori);
    }

    /** Server-side: hanya satu halaman baris yang dikirim, agregat tetap dari seluruh hasil filter. */
    public function testDetailServerSideHanyaKirimSatuHalaman(): void
    {
        $this->sesi(44, 50, 0);
        $this->db->setPrefix('');

        $res = $this->get('asset_berjalan/detail-pengeluaran?unit=3&bulan=10&tahun=2026&kelompok=kebutuhan&draw=7&start=0&length=1');
        $res->assertStatus(200);
        $json = json_decode((string) $res->getJSON(), true);

        $this->assertSame(7, (int) $json['draw']);
        $this->assertCount(1, $json['data']);
        $this->assertSame(3, (int) $json['recordsFiltered']);
        $this->assertSame(3, count($json['kategori']));
        $this->assertSame(2200000.0, (float) $json['total']);
    }

    /**
     * Filter kategori server-side: tabel & ringkasan ikut kategori terpilih,
     * tapi chip kategori tetap lengkap (semua kategori kelompok) supaya user
     * bisa ganti kategori tanpa reset dulu.
     */
    public function testDetailFilterKategoriServerSide(): void
    {
        $this->sesi(44, 50, 0);
        $this->db->setPrefix('');

        $res = $this->get('asset_berjalan/detail-pengeluaran?unit=3&bulan=10&tahun=2026&kelompok=kebutuhan&kategori=6');
        $res->assertStatus(200);
        $json = json_decode((string) $res->getJSON(), true);

        // Hanya baris kategori 6 (Rp 500.000), bukan seluruh kelompok.
        $this->assertSame([6], array_map('intval', array_column($json['data'], 'kategori_idkategori')));
        $this->assertSame(1, (int) $json['recordsFiltered']);
        $this->assertSame(500000.0, (float) $json['total']);
        // recordsTotal tetap total kelompok (tanpa filter kategori).
        $this->assertSame(3, (int) $json['recordsTotal']);

        // Chip tetap lengkap & membawa id untuk filter.
        $ids = array_map('intval', array_column($json['kategori'], 'id'));
        sort($ids);
        $this->assertSame([6, 7, 11], $ids);

        // Gabungan kategori + pencarian (AND); recordsTotal tetap total kelompok
        // (bukan hasil cari).
        $res2 = $this->get('asset_berjalan/detail-pengeluaran?unit=3&bulan=10&tahun=2026&kelompok=kebutuhan&kategori=6&search%5Bvalue%5D=Lain');
        $res2->assertStatus(200);
        $json2 = json_decode((string) $res2->getJSON(), true);
        $this->assertSame(3, (int) $json2['recordsTotal']);
        $this->assertSame(1, (int) $json2['recordsFiltered']);
        $this->assertSame([6], array_map('intval', array_column($json2['data'], 'kategori_idkategori')));
    }

    public function testDetailMenolakKelompokTidakDikenal(): void
    {
        $this->sesi(44, 50, 0);
        $this->db->setPrefix('');

        $this->get('asset_berjalan/detail-pengeluaran?unit=3&bulan=10&tahun=2026&kelompok=foo')
            ->assertStatus(400);
    }

    /** Staf cabang tidak boleh menarik detail unit lain lewat parameter. */
    public function testDetailMenolakUnitDiLuarScope(): void
    {
        $this->sesi(100, 3, 41);
        $this->db->setPrefix('');

        $this->get('asset_berjalan/detail-pengeluaran?unit=4&bulan=10&tahun=2026&kelompok=terkontrol')
            ->assertStatus(403);
    }

    /** Tombol detail ada, tapi tabel detail masih kosong saat halaman dimuat. */
    public function testHalamanPunyaTombolDetailTanpaMemuatData(): void
    {
        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=3&bulan=10&tahun=2026');

        $this->assertStringContainsString('data-kelompok="terkontrol"', $html);
        $this->assertStringContainsString('data-kelompok="kebutuhan"', $html);
        $this->assertStringContainsString('id="modalDetailPengeluaran"', $html);
        $this->assertStringContainsString('id="detailPengeluaranSummary"', $html);
        $this->assertStringContainsString('id="detailPengeluaranKategori"', $html);
        $this->assertMatchesRegularExpression('#<tbody id="detailPengeluaranRows">\s*</tbody>#', $html);
    }
}
