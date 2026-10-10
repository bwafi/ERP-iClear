<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi filter unit di /omset_bulanan.
 *
 * View menampilkan dropdown unit untuk role [1, 0, 34, 40] (lihat
 * jurnal/omset_bulanan.php). Sebelumnya controller hanya menghormati `?unit=`
 * untuk jabatan 1 & 40; Finance (0) dan Manager (34) jatuh ke cabang else dan
 * terkunci ke ID_UNIT sesi-nya. Karena ID_UNIT Finance = 50 (Head Office) yang
 * tidak ada di list_unit (HO dikecualikan), halaman selalu fallback ke unit
 * pertama (Probolinggo), berapa pun unit yang dipilih.
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
            'stok_barang', 'service', 'pelanggan',
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
                tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, kecamatan TEXT NULL, deleted TEXT NULL)');

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
            (4, 'ICLEAR Pandaan', 'logo.png'),
            (50, 'Head Office', 'logo.png')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (0, 'Finance', '[]'), (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (44, 50, 0, 'Ira Kurniawati'),
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
}
