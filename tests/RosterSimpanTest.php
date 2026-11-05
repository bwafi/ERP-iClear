<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class RosterSimpanTest extends CIUnitTestCase
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
                unit_idunit INT NULL,
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
            (1,1,1,'Admin Root',1),(100,1,35,'Pegawai Satu',1),(200,2,35,'Pegawai Dua',1)");
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

    public function testAdminKasirTidakBisaTerapkanUntukUnitLain(): void
    {
        $this->sesi(200, 2, 35);
        $pola = [1 => 'PAGI', 2 => 'PAGI', 3 => 'PAGI', 4 => 'PAGI', 5 => 'PAGI', 6 => 'OFF', 7 => 'OFF'];

        $this->post('jadwal_masuk/terapkan', [
            'akun_idakun' => 100, // unit 1
            'bulan'       => '2026-11',
            'pola'        => $pola,
        ])->assertRedirect();

        $this->assertSame(0, (int) $this->db->table('presensi_roster')->countAllResults());
        $this->assertStringContainsString('unit', (string) session('gagal'));
    }

    public function testAdminKasirTidakBisaUbahTanggalLampau(): void
    {
        $this->sesi(200, 2, 35);
        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 200,
            'bulan'       => '2020-01',
            'shift'       => ['2020-01-01' => 'PAGI'],
            'keterangan'  => ['2020-01-01' => ''],
        ])->assertRedirect();

        $this->assertSame(0, (int) $this->db->table('presensi_roster')->countAllResults());
    }

    public function testAdminRootBolehUbahTanggalLampau(): void
    {
        $this->sesi(1, 1, 1);
        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 100,
            'bulan'       => '2020-01',
            'shift'       => ['2020-01-01' => 'PS'],
            'keterangan'  => ['2020-01-01' => ''],
        ])->assertRedirect();

        $row = $this->db->table('presensi_roster')->where('akun_idakun', 100)->get()->getRow();
        $this->assertSame('PS', $row->shift);
    }

    public function testAdminRootBisaSimpanUnitKerjaLintasCabang(): void
    {
        $this->sesi(1, 1, 1);
        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 100,
            'bulan'       => '2099-01',
            'shift'       => ['2099-01-02' => 'PAGI'],
            'unit'        => ['2099-01-02' => 2],
            'keterangan'  => ['2099-01-02' => ''],
        ])->assertRedirect();

        $row = $this->db->table('presensi_roster')->where('akun_idakun', 100)->get()->getRow();
        $this->assertSame(2, (int) $row->unit_idunit);
    }

    public function testUnitKerjaDefaultUnitPegawai(): void
    {
        $this->sesi(1, 1, 1);
        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 100,
            'bulan'       => '2099-01',
            'shift'       => ['2099-01-03' => 'PAGI'],
            'keterangan'  => ['2099-01-03' => ''],
        ])->assertRedirect();

        $row = $this->db->table('presensi_roster')->where('akun_idakun', 100)->get()->getRow();
        $this->assertSame(1, (int) $row->unit_idunit);
    }

    public function testAdminKasirTidakBisaPilihUnitLain(): void
    {
        $this->sesi(200, 2, 35);
        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 200,
            'bulan'       => '2099-01',
            'shift'       => ['2099-01-04' => 'PAGI'],
            'unit'        => ['2099-01-04' => 1],
            'keterangan'  => ['2099-01-04' => ''],
        ])->assertRedirect();

        $this->assertSame(0, (int) $this->db->table('presensi_roster')->countAllResults());
        $this->assertStringContainsString('unit', (string) session('gagal'));
    }

    public function testUnitKunjunganTidakTeresetSaatAdminUnitMenyimpan(): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => 100, 'tanggal' => '2099-01-05', 'shift' => 'PAGI',
            'unit_idunit' => 2, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->sesi(1, 1, 35);

        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 100,
            'bulan'       => '2099-01',
            'shift'       => ['2099-01-05' => 'SIANG'],
            'unit'        => ['2099-01-05' => 2],
            'keterangan'  => ['2099-01-05' => ''],
        ])->assertRedirect();

        $row = $this->db->table('presensi_roster')->where('akun_idakun', 100)->get()->getRow();
        $this->assertSame('SIANG', $row->shift);
        $this->assertSame(2, (int) $row->unit_idunit);
    }

    public function testSimpanTanpaUnitMempertahankanUnitKunjungan(): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => 100, 'tanggal' => '2099-01-06', 'shift' => 'PAGI',
            'unit_idunit' => 2, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->sesi(1, 1, 35);

        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 100,
            'bulan'       => '2099-01',
            'shift'       => ['2099-01-06' => 'OFF'],
            'keterangan'  => ['2099-01-06' => ''],
        ])->assertRedirect();

        $row = $this->db->table('presensi_roster')->where('akun_idakun', 100)->get()->getRow();
        $this->assertSame('OFF', $row->shift);
        $this->assertSame(2, (int) $row->unit_idunit);
    }

    public function testIzinTanpaKeteranganTersimpan(): void
    {
        $this->sesi(1, 1, 1);
        $this->post('jadwal_masuk/simpan', [
            'akun_idakun' => 100,
            'bulan'       => '2099-01',
            'shift'       => ['2099-01-01' => 'IZIN'],
            'keterangan'  => ['2099-01-01' => ''],
        ])->assertRedirect();

        $row = $this->db->table('presensi_roster')->where('akun_idakun', 100)->get()->getRow();
        $this->assertSame('IZIN', $row->shift);
    }
}
