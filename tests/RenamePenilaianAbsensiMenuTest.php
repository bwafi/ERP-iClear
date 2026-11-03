<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class RenamePenilaianAbsensiMenuTest extends CIUnitTestCase
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
        $this->db->query('CREATE TABLE menu (idmenu INT PRIMARY KEY, nama_menu TEXT NULL, url TEXT NULL)');
        $this->db->table('menu')->insertBatch([
            ['idmenu' => 10027, 'nama_menu' => 'Penilaian Absensi', 'url' => 'penilaian/absen'],
            ['idmenu' => 10026, 'nama_menu' => 'Penilaian KPI', 'url' => 'penilaian/kpi'],
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
        require_once APPPATH . 'Database/Migrations/2026-11-03-000100_RenamePenilaianAbsensiMenu.php';
        (new \App\Database\Migrations\RenamePenilaianAbsensiMenu())->up();
    }

    private function nama(int $id): string
    {
        return (string) $this->db->table('menu')->where('idmenu', $id)->get()->getRow()->nama_menu;
    }

    public function testNamaBerubahDanUrlTetap(): void
    {
        $this->migrasi();

        $this->assertSame('Penilaian Harian', $this->nama(10027));
        $this->assertSame('penilaian/absen', $this->db->table('menu')->where('idmenu', 10027)->get()->getRow()->url);
        $this->assertSame('Penilaian KPI', $this->nama(10026));
    }

    public function testIdempotenDanDownMengembalikanNama(): void
    {
        $this->migrasi();
        $this->migrasi();
        $this->assertSame('Penilaian Harian', $this->nama(10027));

        (new \App\Database\Migrations\RenamePenilaianAbsensiMenu())->down();
        $this->assertSame('Penilaian Absensi', $this->nama(10027));
    }
}
