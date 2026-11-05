<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class AddAbsenSayaMenuTest extends CIUnitTestCase
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
        $this->db->query('DROP TABLE IF EXISTS jabatan');
        $this->db->query('CREATE TABLE menu (
            idmenu INT PRIMARY KEY, urutan INT NULL, nama_menu TEXT NULL, roles TEXT NULL,
            url TEXT NULL, show_menu INT NULL, sub INT NULL, parent INT NULL,
            utama INT NULL, categories INT NULL, icon TEXT NULL)');
        $this->db->query('CREATE TABLE jabatan (
            ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');

        $this->db->table('menu')->insertBatch([
            ['idmenu' => 1, 'nama_menu' => 'DASHBOARD', 'url' => null, 'parent' => 0, 'urutan' => 1],
            ['idmenu' => 115, 'nama_menu' => 'Riwayat Absen', 'url' => 'riwayat_presensi', 'parent' => 1, 'urutan' => 1],
        ]);
        $this->db->table('jabatan')->insertBatch([
            ['ID_JABATAN' => 1, 'NAMA_JABATAN' => 'Admin Root', 'ROLES_JABATAN' => '["1","115"]'],
            ['ID_JABATAN' => 34, 'NAMA_JABATAN' => 'Manager', 'ROLES_JABATAN' => '["2"]'],
            ['ID_JABATAN' => 36, 'NAMA_JABATAN' => 'Teknisi', 'ROLES_JABATAN' => '["2","112"]'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS menu');
        $this->db->query('DROP TABLE IF EXISTS jabatan');
        $this->db->dataCache['table_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function migrasi(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-05-000100_AddAbsenSayaMenu.php';
        (new \App\Database\Migrations\AddAbsenSayaMenu())->up();
    }

    private function menu(int $id): ?object
    {
        return $this->db->table('menu')->where('idmenu', $id)->get()->getRow();
    }

    private function roles(int $jabatan): array
    {
        $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();

        return array_map('intval', json_decode((string) $row->ROLES_JABATAN, true) ?: []);
    }

    public function testMenuMasukDashboardDanDigrantKeSemuaJabatan(): void
    {
        $this->migrasi();

        $menu = $this->menu(10162);
        $this->assertNotNull($menu);
        $this->assertSame('Absen', $menu->nama_menu);
        $this->assertSame('absen', $menu->url);
        $this->assertEquals(1, $menu->parent);
        $this->assertEquals(0, $menu->urutan);
        $this->assertEquals(0, $menu->categories);
        $this->assertEquals(1, $menu->show_menu);

        foreach ([1, 34, 36] as $jabatan) {
            $this->assertContains(10162, $this->roles($jabatan));
        }
        $this->assertContains(115, $this->roles(1));
        $this->assertContains(112, $this->roles(36));
    }

    public function testIdempotenTidakMenduplikasiMenuDanGrant(): void
    {
        $this->migrasi();
        $this->migrasi();

        $this->assertSame(1, $this->db->table('menu')->where('idmenu', 10162)->countAllResults());
        $this->assertSame(1, count(array_keys($this->roles(1), 10162, true)));
        $this->assertSame(1, count(array_keys($this->roles(36), 10162, true)));
    }

    public function testIdMenuTerpakaiTidakDitimpa(): void
    {
        $this->db->table('menu')->insert([
            'idmenu' => 10162, 'nama_menu' => 'Menu Lain', 'url' => 'menu_lain',
            'parent' => 0, 'urutan' => 5,
        ]);

        $this->migrasi();

        $this->assertSame('Menu Lain', $this->menu(10162)->nama_menu);
        $this->assertNotContains(10162, $this->roles(1));
    }

    public function testDownMenghapusMenuDanGrant(): void
    {
        $this->migrasi();
        (new \App\Database\Migrations\AddAbsenSayaMenu())->down();

        $this->assertNull($this->menu(10162));
        foreach ([1, 34, 36] as $jabatan) {
            $this->assertNotContains(10162, $this->roles($jabatan));
        }
        $this->assertContains(115, $this->roles(1));
        $this->assertContains(112, $this->roles(36));
    }
}
