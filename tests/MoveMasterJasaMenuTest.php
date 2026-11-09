<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Migrasi urutan menu Master Jasa: grup 10170 naik tepat setelah Produk (1006).
 */
class MoveMasterJasaMenuTest extends CIUnitTestCase
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
        $this->db->query('CREATE TABLE menu (
            idmenu INT PRIMARY KEY, urutan INT NULL, nama_menu TEXT NULL, roles TEXT NULL,
            url TEXT NULL, show_menu INT NULL, sub INT NULL, parent INT NULL,
            utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');

        $this->db->table('menu')->insertBatch([
            ['idmenu' => 6, 'urutan' => 1006, 'nama_menu' => 'Produk', 'categories' => 1, 'show_menu' => 1],
            ['idmenu' => 9, 'urutan' => 1009, 'nama_menu' => 'Supplier', 'categories' => 1, 'show_menu' => 1],
            ['idmenu' => 10170, 'urutan' => 10170, 'nama_menu' => 'Master Jasa', 'categories' => 1, 'show_menu' => 1],
        ]);
        $this->db->table('menu')->insert([
            'idmenu' => 10171, 'urutan' => 1, 'nama_menu' => 'Tarif Jasa',
            'url' => 'tarif_jasa', 'parent' => 10170, 'show_menu' => 1,
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
        require_once APPPATH . 'Database/Migrations/2026-11-09-000100_MoveMasterJasaMenu.php';
        (new \App\Database\Migrations\MoveMasterJasaMenu())->up();
    }

    private function urutan(int $idmenu): int
    {
        return (int) $this->db->table('menu')->where('idmenu', $idmenu)->get()->getRow()->urutan;
    }

    public function testMasterJasaNaikTepatSetelahProduk(): void
    {
        $this->migrasi();

        $this->assertSame(1007, $this->urutan(10170));
        $this->assertLessThan($this->urutan(10170), $this->urutan(6), 'Produk tetap di atas Master Jasa');
        $this->assertGreaterThan($this->urutan(10170), $this->urutan(9), 'Supplier tetap di bawah Master Jasa');
    }

    public function testUpDuaKaliDanDownMengembalikanUrutan(): void
    {
        $this->migrasi();
        $this->migrasi();
        $this->assertSame(1007, $this->urutan(10170));

        (new \App\Database\Migrations\MoveMasterJasaMenu())->down();
        $this->assertSame(10170, $this->urutan(10170));
    }
}
