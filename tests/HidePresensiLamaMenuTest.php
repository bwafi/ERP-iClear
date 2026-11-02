<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class HidePresensiLamaMenuTest extends CIUnitTestCase
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
        $this->db->query('CREATE TABLE menu (idmenu INT PRIMARY KEY, nama_menu TEXT NULL, show_menu INT NULL)');
        $this->db->table('menu')->insertBatch([
            ['idmenu' => 112, 'nama_menu' => 'Presensi', 'show_menu' => 1],
            ['idmenu' => 116, 'nama_menu' => 'Approval Presensi', 'show_menu' => 1],
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
        require_once APPPATH . 'Database/Migrations/2026-11-02-000900_HidePresensiLamaMenu.php';
        (new \App\Database\Migrations\HidePresensiLamaMenu())->up();
    }

    private function show(int $id): int
    {
        return (int) $this->db->table('menu')->where('idmenu', $id)->get()->getRow()->show_menu;
    }

    public function testPresensiLamaDisembunyikan(): void
    {
        $this->migrasi();
        $this->migrasi();

        $this->assertSame(0, $this->show(112));
        $this->assertSame(1, $this->show(116));
    }

    public function testDownMenampilkanKembali(): void
    {
        $this->migrasi();
        (new \App\Database\Migrations\HidePresensiLamaMenu())->down();

        $this->assertSame(1, $this->show(112));
    }
}
