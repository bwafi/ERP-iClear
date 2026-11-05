<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class PresensiSchemaMigrationTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->resetSkema();
        $this->migrasi();
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS presensi_roster');
        $this->db->query('DROP TABLE IF EXISTS presensi');
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function resetSkema(): void
    {
        $this->db->query('DROP TABLE IF EXISTS presensi_roster');
        $this->db->query('DROP TABLE IF EXISTS presensi');
        $this->db->query('CREATE TABLE presensi (
            idpresensi INT PRIMARY KEY AUTO_INCREMENT,
            akun_idakun INT NOT NULL,
            unit_idunit INT NULL,
            waktu_masuk DATETIME NULL
        )');
    }

    private function migrasi(): void
    {
        $dir = APPPATH . 'Database/Migrations/';
        require_once $dir . '2026-11-02-000100_CreatePresensiRoster.php';
        require_once $dir . '2026-11-02-000200_AddSesiSumberToPresensi.php';
        require_once $dir . '2026-11-05-000300_AddUnitToPresensiRoster.php';
        (new \App\Database\Migrations\CreatePresensiRoster())->up();
        (new \App\Database\Migrations\AddSesiSumberToPresensi())->up();
        (new \App\Database\Migrations\AddUnitToPresensiRoster())->up();
    }

    public function testRosterDanKolomPresensiTerbentuk(): void
    {
        $this->assertTrue($this->db->tableExists('presensi_roster'));
        $this->assertTrue($this->db->fieldExists('sesi', 'presensi'));
        $this->assertTrue($this->db->fieldExists('sumber', 'presensi'));
        $this->assertTrue($this->db->fieldExists('unit_idunit', 'presensi_roster'));
        $this->assertArrayHasKey('idx_presensi_akun_masuk', $this->db->getIndexData('presensi'));
    }

    public function testDijalankanDuaKaliTetapIdempoten(): void
    {
        $this->db->table('presensi_roster')->insert([
            'akun_idakun' => 10,
            'tanggal'     => '2026-11-02',
            'shift'       => 'PAGI',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->migrasi();
        $this->migrasi();

        $this->assertSame(1, (int) $this->db->table('presensi_roster')->countAllResults());
        $this->assertTrue($this->db->fieldExists('sesi', 'presensi'));
    }

    public function testTanpaTabelPresensiTidakMembuatTabelBaru(): void
    {
        $this->db->query('DROP TABLE presensi');
        $this->migrasi();

        $this->assertFalse($this->db->tableExists('presensi'));
    }
}
