<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class AbsenMobilePunchTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';
    private string $goLiveAsli = '';
    private string $fotoDirAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->buatSkema();

        $this->goLiveAsli = config('Presensi')->goLiveDate;
        config('Presensi')->goLiveDate = '2020-01-01';

        $this->fotoDirAsli = config('Presensi')->fotoDir;
        config('Presensi')->fotoDir = WRITEPATH . 'uploads/presensi_test';
    }

    protected function tearDown(): void
    {
        config('Presensi')->goLiveDate = $this->goLiveAsli;
        config('Presensi')->fotoDir    = $this->fotoDirAsli;
        $this->hapusDir(WRITEPATH . 'uploads/presensi_test');

        foreach (['unit', 'akun', 'jabatan', 'presensi', 'presensi_roster'] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->dataCache['table_names'] = [];
        $this->db->dataCache['field_names'] = [];
        $this->db->setPrefix($this->prefixAsli);

        parent::tearDown();
    }

    private function hapusDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->hapusDir($path) : unlink($path);
        }
        rmdir($dir);
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

    private function hariIni(): string
    {
        return (new \DateTime('now', new \DateTimeZone(config('Presensi')->zonaWaktu)))->format('Y-m-d');
    }

    private function siapkanFoto(string $relatif): void
    {
        $path = config('Presensi')->fotoDir . '/' . $relatif;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        // JPEG 1x1 minimal.
        file_put_contents($path, base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            . 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        ));
    }

    public function testTanpaFotoDitolak(): void
    {
        $this->sesi(10, 1, 36);
        $this->roster(10, $this->hariIni(), 'PAGI');

        $result = $this->post('absen/masuk', [
            'sesi' => 'FULL', 'latitude' => -8.1, 'longitude' => 113.7,
        ]);

        $result->assertStatus(422);
        $this->assertFalse(json_decode($result->getJSON(), true)['ok']);
        $this->assertSame(0, (int) $this->db->table('presensi')->countAllResults());
    }

    public function testDiLuarRadiusDitolak(): void
    {
        $this->sesi(10, 1, 36);
        $this->roster(10, $this->hariIni(), 'PAGI');

        $result = $this->post('absen/masuk', [
            'sesi' => 'FULL', 'latitude' => -8.5, 'longitude' => 113.7,
        ]);

        $result->assertStatus(422);
        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('m dari titik absen', $json['message']);
        $this->assertSame('jarak', $json['kode']);
    }

    public function testPulangBarisOrangLainDitolak(): void
    {
        $this->sesi(11, 1, 36);
        $this->db->table('presensi')->insert([
            'idpresensi' => 77, 'akun_idakun' => 10, 'unit_idunit' => 1,
            'waktu_masuk' => date('Y-m-d H:i:s'), 'sesi' => 'FULL',
        ]);

        $result = $this->post('absen/pulang', ['idpresensi' => 77]);
        $result->assertStatus(403);
    }

    public function testFotoHanyaUntukPemilikAtauScope(): void
    {
        $this->siapkanFoto('202611/uji.jpg');
        $this->db->table('presensi')->insert([
            'idpresensi' => 77, 'akun_idakun' => 10, 'unit_idunit' => 1,
            'waktu_masuk' => '2026-11-02 08:50:00', 'foto' => '202611/uji.jpg',
        ]);

        $this->sesi(99, 2, 35);
        $this->get('absen/foto/77')->assertStatus(403);

        $this->sesi(10, 1, 36);
        $result = $this->get('absen/foto/77');
        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'image/jpeg');
    }
}
