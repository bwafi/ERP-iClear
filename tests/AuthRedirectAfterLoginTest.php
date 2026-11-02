<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Perilaku redirect setelah login: simpan halaman tujuan, pakai sekali,
 * dan abaikan URL eksternal.
 */
class AuthRedirectAfterLoginTest extends CIUnitTestCase
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
    }

    protected function tearDown(): void
    {
        foreach (['akun', 'jabatan', 'unit', 'stok_barang', 'service', 'pelanggan', 'menu'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach (['akun', 'jabatan', 'unit', 'stok_barang', 'service', 'pelanggan', 'menu'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NOID VARCHAR(100) NULL, KTP VARCHAR(100) NULL, EMAIL VARCHAR(100) NULL,
                PASSWORD TEXT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL,
                STATUS_PEGAWAI INT NULL)');
        // Tabel notifikasi yang disentuh BaseController::initController pada tiap request.
        $q('CREATE TABLE stok_barang (idbarang INT PRIMARY KEY, id_unit INT NULL,
                stok_akhir REAL NULL, stok_minimum REAL NULL, nama_barang TEXT NULL)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE menu (idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL, show_menu INT NULL,
                sub INT NULL, parent INT NULL, utama INT NULL, categories INT NULL,
                icon TEXT NULL, manualbook TEXT NULL)');

        $q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Satu')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN)
            VALUES (36, 'Staf', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NOID, EMAIL, PASSWORD, NAMA_AKUN, STATUS_PEGAWAI)
            VALUES (10, 1, 36, 'user1', 'user1@example.test', '" . password_hash('rahasia', PASSWORD_DEFAULT) . "', 'Budi', 1)");
    }

    private function login(string $password = 'rahasia')
    {
        return $this->post('proses_login', [
            'username' => 'user1',
            'password' => $password,
        ]);
    }

    public function testHalamanTerlindungiMenyimpanTujuanLaluRedirectKeLogin(): void
    {
        $result = $this->get('absen');

        $result->assertRedirectTo(base_url('login'));
        $this->assertSame('/absen', session()->get('redirect_after_login'));
    }

    public function testQueryStringIkutDisimpan(): void
    {
        $this->get('semua_riwayat_presensi?bulan=2026-11');

        $this->assertSame(
            '/semua_riwayat_presensi?bulan=2026-11',
            session()->get('redirect_after_login')
        );
    }

    public function testLoginBerhasilKembaliKeTujuanDanMenghapusSession(): void
    {
        $this->withSession(['redirect_after_login' => '/absen']);

        $result = $this->login();

        $result->assertRedirectTo(base_url('absen'));
        $this->assertNull(session()->get('redirect_after_login'));
    }

    public function testAlurLengkapHalamanTerlindungiLaluLogin(): void
    {
        $this->get('absen');
        $this->withSession(['redirect_after_login' => session()->get('redirect_after_login')]);

        $this->login()->assertRedirectTo(base_url('absen'));
    }

    public function testLoginBerhasilTanpaTujuanKeDashboard(): void
    {
        $result = $this->login();

        $result->assertRedirectTo(base_url());
        $this->assertNull(session()->get('redirect_after_login'));
    }

    public function testLoginGagalTidakMenghapusTujuan(): void
    {
        $this->withSession(['redirect_after_login' => '/absen']);

        $result = $this->login('password-salah');

        $result->assertRedirectTo(base_url('login'));
        $this->assertSame('/absen', session()->get('redirect_after_login'));
    }

    public function testTujuanUrlEksternalDiabaikan(): void
    {
        $this->withSession(['redirect_after_login' => 'http://evil.test/absen']);

        $result = $this->login();

        $result->assertRedirectTo(base_url());
        $this->assertNull(session()->get('redirect_after_login'));
    }

    public function testTujuanProtocolRelativeDiabaikan(): void
    {
        $this->withSession(['redirect_after_login' => '//evil.test/absen']);

        $result = $this->login();

        $result->assertRedirectTo(base_url());
        $this->assertNull(session()->get('redirect_after_login'));
    }

    public function testHalamanLoginTidakDisimpanSebagaiTujuan(): void
    {
        $this->get('login');

        $this->assertNull(session()->get('redirect_after_login'));
    }
}
