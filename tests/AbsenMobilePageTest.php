<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class AbsenMobilePageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';
    private string $goLiveAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();

        $this->goLiveAsli = config('Presensi')->goLiveDate;
        config('Presensi')->goLiveDate = '2020-01-01';
    }

    protected function tearDown(): void
    {
        config('Presensi')->goLiveDate = $this->goLiveAsli;

        foreach (['unit', 'akun', 'jabatan', 'presensi', 'presensi_roster'] as $tabel) {
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

        foreach (['unit', 'akun', 'jabatan', 'presensi', 'presensi_roster'] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (
                idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL,
                LATITUDE TEXT NULL, LONGTITUDE TEXT NULL, RADIUS INT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
            NAMA_AKUN TEXT NULL, STATUS_PEGAWAI INT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE presensi_roster (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                akun_idakun INT NOT NULL, tanggal DATE NOT NULL, shift VARCHAR(10) NOT NULL,
                keterangan TEXT NULL, created_by INT NULL, updated_by INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL,
                UNIQUE KEY uniq_akun_tanggal (akun_idakun, tanggal))');
        $q('CREATE TABLE presensi (
                idpresensi INT PRIMARY KEY AUTO_INCREMENT,
                waktu_masuk DATETIME NULL, waktu_pulang DATETIME NULL,
                jam_jadwal_masuk TIME NULL, jam_jadwal_pulang TIME NULL,
                jam_toleransi TIME NULL, status_absensi INT NULL,
                lat TEXT NULL, `long` TEXT NULL, ip TEXT NULL,
                foto TEXT NULL, foto_pulang VARCHAR(255) NULL,
                idjadwal_masuk INT NULL, akun_idakun INT NOT NULL,
                unit_idunit INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL,
                jarak INT NULL, status_kehadiran INT NULL, keterangan TEXT NULL,
                sesi VARCHAR(10) NULL, sumber VARCHAR(20) NULL)');

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LATITUDE, LONGTITUDE, RADIUS)
            VALUES (1, 'Unit Satu', '-8.1', '113.7', 200)");
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

    private function roster(int $akunId, string $tanggal, string $shift): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => $akunId,
            'tanggal'     => $tanggal,
            'shift'       => $shift,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /** Tanggal yang sama dengan yang dipakai controller (zona Presensi, bukan UTC). */
    private function hariIni(): string
    {
        return (new \DateTime('now', new \DateTimeZone(config('Presensi')->zonaWaktu)))->format('Y-m-d');
    }

    public function testTanpaRosterMenampilkanPesan(): void
    {
        $this->sesi(10, 1, 36);
        $this->get('absen')->assertSee('Belum ada jadwal');
    }

    public function testOffTidakMenampilkanForm(): void
    {
        $this->sesi(10, 1, 36);
        $this->roster(10, $this->hariIni(), 'OFF');
        $result = $this->get('absen');
        $result->assertSee('OFF');
        $result->assertDontSee('Absen Masuk');
    }

    public function testPagiSatuKartuDanPsDuaKartu(): void
    {
        $this->sesi(10, 1, 36);

        $this->roster(10, $this->hariIni(), 'PAGI');
        $this->get('absen')->assertSee('Pagi');

        $this->db->table('presensi_roster')
            ->where('akun_idakun', 10)->where('tanggal', $this->hariIni())
            ->update(['shift' => 'PS']);
        $result = $this->get('absen');
        $result->assertSee('PS Pagi');
        $result->assertSee('PS Sore');
    }

    public function testSebelumGoLiveHanyaPesan(): void
    {
        $this->sesi(10, 1, 36);
        $this->roster(10, $this->hariIni(), 'PAGI');
        config('Presensi')->goLiveDate = '2099-01-01';

        $this->get('absen')->assertSee('aktif mulai');
    }

    public function testHalamanMenyediakanPetaTitikAbsen(): void
    {
        $this->sesi(10, 1, 36);
        $this->roster(10, $this->hariIni(), 'PAGI');

        $result = $this->get('absen');
        $result->assertSee('Lihat titik absen');

        $body = $result->getBody();
        $this->assertStringContainsString('unpkg.com/leaflet', $body);
        $this->assertStringContainsString('data-titik-lat="-8.1"', $body);
        $this->assertStringContainsString('data-titik-radius="200"', $body);
    }

    public function testTanpaTitikUnitTidakMenampilkanPeta(): void
    {
        $this->db->table('unit')->where('idunit', 1)
            ->update(['LATITUDE' => null, 'LONGTITUDE' => null, 'RADIUS' => 0]);
        $this->sesi(10, 1, 36);
        $this->roster(10, $this->hariIni(), 'PAGI');

        $result = $this->get('absen');
        $result->assertDontSee('Lihat titik absen');
        $this->assertStringNotContainsString('unpkg.com/leaflet', $result->getBody());
    }
}
