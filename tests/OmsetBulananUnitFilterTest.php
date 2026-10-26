<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi filter unit di /omset_bulanan.
 *
 * View menampilkan dropdown unit untuk role yang boleh memilih unit (lihat
 * jurnal/omset_bulanan.php, kini dikendalikan `$boleh_pilih_unit` dari
 * TutupKasir::resolveUnitScope()). Sebelumnya controller hanya menghormati
 * `?unit=` untuk jabatan 1 & 40; Finance (0) dan Manager (34) jatuh ke cabang
 * else dan terkunci ke ID_UNIT sesi-nya. Karena ID_UNIT Finance = 50 (Head
 * Office) yang tidak ada di list_unit (HO dikecualikan), halaman selalu
 * fallback ke unit pertama (Probolinggo), berapa pun unit yang dipilih.
 *
 * Termasuk regresi Direktur (jabatan 2): boleh memilih unit seperti Admin root,
 * tetapi dibatasi daftar unit per akun; direktur tanpa pemetaan lintas unit.
 *
 * Marker deterministik: view menulis `const ARUS_KAS_UNIT = <selected_unit>;`.
 *
 * Catatan prefix: controller memakai qualified column mentah
 * (`detail_penjualan.sub_total`, join `penjualan...`). Dengan DBPrefix `db_`
 * (default phpunit.xml) nama tabel ter-prefix tapi qualifier kolom tidak,
 * sehingga query gagal. Karena produksi ber-DBPrefix kosong, tes ini menyetel
 * prefix koneksi ke '' (dan tabel tanpa prefix), lalu mengembalikannya.
 */
class OmsetBulananUnitFilterTest extends CIUnitTestCase
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
            'stok_barang', 'service', 'service_sparepart', 'pelanggan',
            'penjualan', 'detail_penjualan', 'barang',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        // LOGO dibaca template.php saat render; tanpa kolom ini test gagal.
        $q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        // Sidebar (inc/left_vertical.php -> Core) + template.php membaca menu.
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');

        // Tabel pendukung BaseController::initController (notifikasi stok/service).
        $q('CREATE TABLE stok_barang (
                idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL,
                stok_minimum REAL NULL, nama_barang TEXT NULL, kode_barang TEXT NULL,
                total_penjualan REAL NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                tipe_hp TEXT NULL, created_at TEXT NULL,
                no_service TEXT NULL, service_by INT NULL,
                refund_garansi_tanggal TEXT NULL, refund_garansi_jumlah INT NULL DEFAULT 0)');
        $q('CREATE TABLE service_sparepart (
                idservice_sparepart INTEGER PRIMARY KEY AUTO_INCREMENT,
                service_idservice INT NULL, barang_idbarang INT NULL,
                unit_idunit INT NULL, hpp_omset_garansi REAL NULL,
                jumlah_tambahan_garansi INT NULL, status_retur TEXT NULL,
                tanggal_hpp_klaim TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, kabupaten TEXT NULL, kecamatan TEXT NULL, deleted TEXT NULL)');

        // Tabel laporan omset.
        $q('CREATE TABLE penjualan (
                idpenjualan INTEGER PRIMARY KEY AUTO_INCREMENT, kode_invoice TEXT NULL,
                tanggal TEXT NULL, unit_idunit INT NULL, id_pelanggan INT NULL)');
        $q('CREATE TABLE detail_penjualan (
                iddetail_penjualan INTEGER PRIMARY KEY AUTO_INCREMENT,
                penjualan_idpenjualan INT NULL, barang_idbarang INT NULL,
                sub_total REAL NULL, hpp_penjualan REAL NULL)');
        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, nama_barang TEXT NULL, idkategori INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LOGO) VALUES
            (1, 'ICLEAR Probolinggo', 'logo.png'),
            (2, 'ICLEAR Jember', 'logo.png'),
            (3, 'ICLEAR Banyuwangi', 'logo.png'),
            (4, 'ICLEAR Pandaan', 'logo.png'),
            (5, 'ICLEAR Genteng', 'logo.png'),
            (50, 'Head Office', 'logo.png')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (0, 'Finance', '[]'), (2, 'Direktur', '[]'), (35, 'ADMIN / KASIR', '[]'), (43, 'Kepala Divisi', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (44, 50, 0, 'Ira Kurniawati'),
            (46, 3, 2, 'Wahid Alfarizki'),
            (70, 4, 2, 'Guruh Dwi Prasetyo'),
            (99, 3, 2, 'Direktur Tanpa Mapping'),
            (55, 50, 43, 'Kepala Divisi'),
            (43, 2, 35, 'Kasir Jember')");
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

    /**
     * AuthFilter menutup koneksi setelah tiap request, jadi prefix '' harus
     * dipasang ulang tepat sebelum request berikutnya dibangun controller.
     */
    private function halaman(string $query): string
    {
        $this->db->setPrefix('');

        return (string) $this->get('omset_bulanan' . $query)->getBody();
    }

    private function marker(string $html): int
    {
        $this->assertMatchesRegularExpression('/const ARUS_KAS_UNIT = (\d+);/', $html);

        preg_match('/const ARUS_KAS_UNIT = (\d+);/', $html, $m);

        return (int) $m[1];
    }

    public function testFinanceBisaMemfilterUnitPandaan(): void
    {
        $this->sesi(44, 50, 0);

        $html = $this->halaman('?unit=4');

        $this->assertSame(4, $this->marker($html), 'Finance harus bisa melihat unit yang dipilih (Pandaan)');
    }

    public function testFinanceTanpaFilterJatuhKeUnitPertama(): void
    {
        $this->sesi(44, 50, 0);

        $html = $this->halaman('');

        // ID_UNIT 50 (Head Office) tidak ada di list_unit -> default unit pertama.
        $this->assertSame(1, $this->marker($html));
    }

    public function testFinanceUnitTidakValidJatuhKeUnitPertama(): void
    {
        $this->sesi(44, 50, 0);

        $html = $this->halaman('?unit=999');

        $this->assertSame(1, $this->marker($html));
    }

    public function testRoleTakBerwenangTidakBisaGantiUnitViaQuery(): void
    {
        // Kasir (35) tidak termasuk daftar role yang boleh memilih unit.
        $this->sesi(43, 2, 35);

        $html = $this->halaman('?unit=1');

        $this->assertSame(2, $this->marker($html), 'Kasir harus tetap terkunci ke unit sesinya');
    }

    // ── Kepala Divisi (43): lintas unit, tetapi hanya untuk halaman ini. ──

    public function testKepalaDivisiBisaMemfilterUnitPandaan(): void
    {
        $this->sesi(55, 50, 43);

        $html = $this->halaman('?unit=4');

        $this->assertSame(4, $this->marker($html), 'Kepala Divisi harus bisa melihat unit yang dipilih');
    }

    public function testKepalaDivisiTanpaFilterJatuhKeUnitPertama(): void
    {
        $this->sesi(55, 50, 43);

        // ID_UNIT 50 (Head Office) tidak ada di list_unit -> default unit pertama.
        $this->assertSame(1, $this->marker($this->halaman('')));
    }

    // ── Direktur (jabatan 2): boleh memilih unit seperti Admin root, tetapi
    //    dibatasi daftar unit per akun (resolveUnitScope::$direkturUnitMap). ──

    public function testDirekturTerbatasBisaMemilihUnitYangDiizinkan(): void
    {
        // Akun 46 diizinkan unit [3, 5] (Banyuwangi, Genteng).
        $this->sesi(46, 3, 2);

        $this->assertSame(5, $this->marker($this->halaman('?unit=5')));
        $this->assertSame(3, $this->marker($this->halaman('?unit=3')));
    }

    public function testDirekturTerbatasDitolakDiUnitLain(): void
    {
        // Akun 46 tidak boleh melihat unit 1 walau diminta lewat query.
        $this->sesi(46, 3, 2);

        $this->assertSame(3, $this->marker($this->halaman('?unit=1')));
    }

    public function testDirekturTerbatasTanpaFilterJatuhKeUnitPertama(): void
    {
        $this->sesi(46, 3, 2);

        $this->assertSame(3, $this->marker($this->halaman('')));
    }

    public function testDirekturPandaanTetapTerbatas(): void
    {
        // Akun 70 hanya diizinkan unit [4].
        $this->sesi(70, 4, 2);

        $this->assertSame(4, $this->marker($this->halaman('?unit=5')));
        $this->assertSame(4, $this->marker($this->halaman('?unit=4')));
    }

    public function testDirekturTanpaMappingBertindakLintasUnit(): void
    {
        // Akun tanpa pemetaan diperlakukan seperti Admin root (semua cabang).
        $this->sesi(99, 3, 2);

        $this->assertSame(5, $this->marker($this->halaman('?unit=5')));
        $this->assertSame(1, $this->marker($this->halaman('?unit=1')));
    }

    // ── Grafik Omset Harian: filter pembanding banyak bulan ────────────────
    // View mengirim `const compareMonths = [...]` berisi 12 bulan ke belakang
    // dari periode terpilih, dan default pilihannya adalah bulan sebelumnya.

    public function testGrafikMenyediakanDuaBelasBulanPembanding(): void
    {
        $this->db->setPrefix('');
        $this->db->query("INSERT INTO penjualan (idpenjualan, kode_invoice, tanggal, unit_idunit, id_pelanggan) VALUES
            (9001, 'INV-JAN', '2025-01-10 10:00:00', 1, NULL),
            (9002, 'INV-DEC', '2024-12-20 10:00:00', 1, NULL)");
        $this->db->query("INSERT INTO detail_penjualan (penjualan_idpenjualan, barang_idbarang, sub_total, hpp_penjualan) VALUES
            (9001, NULL, 500000, 200000),
            (9002, NULL, 300000, 100000)");
        $this->db->query("INSERT INTO service
            (idservice, status_service, unit_idunit, tanggal_selesai, created_at, no_service, service_by,
             refund_garansi_tanggal, refund_garansi_jumlah)
            VALUES (9002, 4, 1, '2024-12-20 10:00:00', '2024-12-20 09:00:00', 'SRV-PEMBANDING', 43,
                    '2024-12-20', 100000)");

        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=1&bulan=2&tahun=2025');

        $this->assertStringContainsString('const compareMonths = ', $html);

        // Bulan sebelumnya (Januari 2025) tersedia dan memuat omset hariannya
        // (sub_total - hpp = 300000 pada 10 Jan).
        $this->assertStringContainsString('"value":"2025-01"', $html);
        $this->assertStringContainsString('"label":"January 2025"', $html);
        $this->assertStringContainsString('"10":300000', $html);

        // Pembanding juga dikurangi refund pada tanggal refund:
        // 20 Des 2024 = 300000 - 100000 (HPP) - 100000 (refund) = 100000.
        $this->assertStringContainsString('"20":100000', $html);

        // Dua belas bulan ke belakang: yang tertua adalah Februari 2024.
        $this->assertStringContainsString('"value":"2024-02"', $html);
    }

    public function testOmsetBulananDikurangiHppKlaimService(): void
    {
        $this->db->setPrefix('');
        $this->db->query("INSERT INTO service
            (idservice, status_service, unit_idunit, tanggal_selesai, created_at, no_service, service_by)
            VALUES (9001, 4, 1, '2026-10-10 10:00:00', '2026-10-10 09:00:00', 'SRV-TEST', 43)");
        $this->db->query("INSERT INTO penjualan
            (idpenjualan, kode_invoice, tanggal, unit_idunit, id_pelanggan)
            VALUES (9101, 'INV-TEST', '2026-10-10 10:00:00', 1, NULL)");
        $this->db->query("INSERT INTO detail_penjualan
            (penjualan_idpenjualan, barang_idbarang, sub_total, hpp_penjualan)
            VALUES (9101, NULL, 1000000, 200000)");
        $this->db->query("INSERT INTO service_sparepart
            (service_idservice, barang_idbarang, unit_idunit, hpp_omset_garansi,
             jumlah_tambahan_garansi, status_retur, tanggal_hpp_klaim)
            VALUES (9001, NULL, 1, 50000, 1, 'tidak_bisa', '2026-10-10')");

        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=1&bulan=10&tahun=2026');

        // Penjualan 1.000.000 - HPP 200.000 = 800.000; klaim 50.000 -> 750.000.
        $this->assertStringContainsString('Rp 750.000', $html);
        $this->assertStringContainsString('total: 750000', $html);
    }

    public function testOmsetBulananDanHarianDikurangiRefundGaransi(): void
    {
        $this->db->setPrefix('');
        $this->db->query("INSERT INTO service
            (idservice, status_service, unit_idunit, tanggal_selesai, created_at, no_service, service_by,
             refund_garansi_tanggal, refund_garansi_jumlah)
            VALUES (9002, 4, 1, '2026-10-20 10:00:00', '2026-10-20 09:00:00', 'SRV-REFUND', 43,
                    '2026-10-15', 300000)");
        $this->db->query("INSERT INTO penjualan
            (idpenjualan, kode_invoice, tanggal, unit_idunit, id_pelanggan)
            VALUES (9102, 'INV-REFUND', '2026-10-15 10:00:00', 1, NULL)");
        $this->db->query("INSERT INTO detail_penjualan
            (penjualan_idpenjualan, barang_idbarang, sub_total, hpp_penjualan)
            VALUES (9102, NULL, 1000000, 200000)");

        $this->sesi(44, 50, 0);
        $html = $this->halaman('?unit=1&bulan=10&tahun=2026');

        // Penjualan 1.000.000 - HPP 200.000 - refund 300.000 = 500.000.
        $this->assertStringContainsString('Rp 500.000', $html);
        $this->assertStringContainsString('total: 500000', $html);
    }
}
