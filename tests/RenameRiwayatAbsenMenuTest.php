<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class RenameRiwayatAbsenMenuTest extends CIUnitTestCase
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
            ['idmenu' => 115, 'nama_menu' => 'Riwayat Presensi', 'url' => 'riwayat_presensi'],
            ['idmenu' => 117, 'nama_menu' => 'Riwayat Presensi(admin)', 'url' => 'semua_riwayat_presensi'],
            ['idmenu' => 118, 'nama_menu' => 'Jadwal Masuk', 'url' => 'jadwal_masuk'],
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
        require_once APPPATH . 'Database/Migrations/2026-11-02-000600_RenameRiwayatAbsenMenu.php';
        (new \App\Database\Migrations\RenameRiwayatAbsenMenu())->up();
    }

    private function nama(int $id): string
    {
        return (string) $this->db->table('menu')->where('idmenu', $id)->get()->getRow()->nama_menu;
    }

    public function testNamaBerubahDanUrlTetap(): void
    {
        $this->migrasi();

        $this->assertSame('Riwayat Absen', $this->nama(115));
        $this->assertSame('Riwayat Absen (admin)', $this->nama(117));
        $this->assertSame('riwayat_presensi', $this->db->table('menu')->where('idmenu', 115)->get()->getRow()->url);
        $this->assertSame('Jadwal Masuk', $this->nama(118));
    }

    public function testIdempotenDanDownMengembalikanNama(): void
    {
        $this->migrasi();
        $this->migrasi();
        $this->assertSame('Riwayat Absen', $this->nama(115));

        (new \App\Database\Migrations\RenameRiwayatAbsenMenu())->down();
        $this->assertSame('Riwayat Presensi', $this->nama(115));
        $this->assertSame('Riwayat Presensi(admin)', $this->nama(117));
    }
}
