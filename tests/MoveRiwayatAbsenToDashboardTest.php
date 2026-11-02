<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class MoveRiwayatAbsenToDashboardTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->db->query('DROP TABLE IF EXISTS menu');
        $this->db->query('CREATE TABLE menu (idmenu INT PRIMARY KEY, nama_menu TEXT NULL, url TEXT NULL, parent INT NULL, urutan INT NULL)');
        $this->db->table('menu')->insertBatch([
            ['idmenu' => 1, 'nama_menu' => 'DASHBOARD', 'url' => null, 'parent' => 0, 'urutan' => 1],
            ['idmenu' => 10161, 'nama_menu' => 'Absensi', 'url' => null, 'parent' => 0, 'urutan' => 100],
            ['idmenu' => 115, 'nama_menu' => 'Riwayat Absen', 'url' => 'riwayat_presensi', 'parent' => 10161, 'urutan' => 103],
            ['idmenu' => 117, 'nama_menu' => 'Riwayat Absen (admin)', 'url' => 'semua_riwayat_presensi', 'parent' => 10161, 'urutan' => 104],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS menu');
        $this->db->dataCache['table_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function migrasi(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-02-000800_MoveRiwayatAbsenToDashboard.php';
        (new \App\Database\Migrations\MoveRiwayatAbsenToDashboard())->up();
    }

    private function baris(int $id): object
    {
        return $this->db->table('menu')->where('idmenu', $id)->get()->getRow();
    }

    public function testRiwayatAbsenPindahKeDashboard(): void
    {
        $this->migrasi();

        $this->assertSame(1, (int) $this->baris(115)->parent);
        $this->assertSame('riwayat_presensi', $this->baris(115)->url);
        $this->assertSame('Riwayat Absen', $this->baris(115)->nama_menu);
        $this->assertSame(10161, (int) $this->baris(117)->parent);
    }

    public function testIdempotenDanDownMengembalikan(): void
    {
        $this->migrasi();
        $this->migrasi();
        $this->assertSame(1, (int) $this->baris(115)->parent);

        (new \App\Database\Migrations\MoveRiwayatAbsenToDashboard())->down();
        $this->assertSame(10161, (int) $this->baris(115)->parent);
        $this->assertSame(103, (int) $this->baris(115)->urutan);
    }
}
