<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class RosterPageTest extends CIUnitTestCase
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
        foreach (['unit', 'akun', 'jabatan', 'menu', 'stok_barang', 'service', 'pelanggan', 'presensi_roster'] as $tabel) {
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

        foreach (['unit', 'akun', 'jabatan', 'menu', 'stok_barang', 'service', 'pelanggan', 'presensi_roster'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL,
            LATITUDE TEXT NULL, LONGTITUDE TEXT NULL, RADIUS INT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
            NAMA_AKUN TEXT NULL, STATUS_PEGAWAI INT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE stok_barang (idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL,
            stok_minimum REAL NULL, nama_barang TEXT NULL)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
            pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL, tanggal_selesai TEXT NULL,
            tanggal_bisa_diambil TEXT NULL, tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL,
                manualbook TEXT NULL)');
        $q('CREATE TABLE presensi_roster (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                akun_idakun INT NOT NULL, tanggal DATE NOT NULL, shift VARCHAR(10) NOT NULL,
                keterangan TEXT NULL, created_by INT NULL, updated_by INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uniq_akun_tanggal (akun_idakun, tanggal))');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1,'Unit Satu'),(2,'Unit Dua')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (1,'Admin Root','[]'),(34,'Manager','[]'),(35,'Admin Kasir','[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI) VALUES
            (1,1,34,'Admin Pusat',1),(100,1,35,'Pegawai Satu',1),(200,2,35,'Pegawai Dua',1)");
    }

    private function sesi(int $akun, int $unit, int $jabatan): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => $akun,
            'ID_UNIT'    => $unit,
            'ID_JABATAN' => $jabatan,
            'NAMA'       => 'Tester',
        ]);
    }

    public function testAdminKasirHanyaMelihatPegawaiUnitSendiri(): void
    {
        $this->sesi(200, 2, 35);
        $result = $this->get('jadwal_masuk?bulan=2026-11&unit=1');

        $result->assertStatus(200);
        $result->assertSee('Pegawai Dua');
        $result->assertDontSee('Pegawai Satu');
        $result->assertSee('pola[1]');
        $result->assertSee('Buka Absen Mobile');
    }

    public function testRoleLintasBisaMemilihUnit(): void
    {
        $this->sesi(1, 1, 34);
        $result = $this->get('jadwal_masuk?bulan=2026-11&unit=1');

        $result->assertSee('Pegawai Satu');
        $result->assertSee('name="unit"');
    }

    public function testPolaSelaluTerlihatDanCatatanIzinLangsungTerbuka(): void
    {
        $this->db->query("INSERT INTO presensi_roster (akun_idakun, tanggal, shift, keterangan)
            VALUES (100,'2026-11-05','IZIN','Sakit')");

        $this->sesi(1, 1, 34);
        $result = $this->get('jadwal_masuk?bulan=2026-11&unit=1&akun=100');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('Pola mingguan', $body);
        $this->assertStringNotContainsString('<details class="jad-pattern"', $body);
        $this->assertStringContainsString('jad-note-wrap is-open', $body);
        $this->assertStringContainsString('name="keterangan[2026-11-05]"', $body);
    }
}
