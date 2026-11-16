<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Migrasi menu Master Jasa: grup + item Tarif Jasa, grant idempoten.
 */
class AddTarifJasaMenuTest extends CIUnitTestCase
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
            utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');
        $this->db->query('CREATE TABLE jabatan (
            ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');

        $this->db->table('jabatan')->insertBatch([
            ['ID_JABATAN' => 35, 'NAMA_JABATAN' => 'Admin / Kasir', 'ROLES_JABATAN' => '["2"]'],
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
        require_once APPPATH . 'Database/Migrations/2026-11-08-000200_AddTarifJasaMenu.php';
        (new \App\Database\Migrations\AddTarifJasaMenu())->up();
    }

    private function roles(int $jabatan): array
    {
        $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();
        return json_decode($row->ROLES_JABATAN, true) ?? [];
    }

    public function testMembuatGrupDanItemTarifJasa(): void
    {
        $this->migrasi();

        $grup = $this->db->table('menu')->where('idmenu', 10170)->get()->getRow();
        $this->assertSame('Master Jasa', $grup->nama_menu);
        $this->assertSame(1, (int) $grup->categories);

        $item = $this->db->table('menu')->where('idmenu', 10171)->get()->getRow();
        $this->assertSame('Tarif Jasa', $item->nama_menu);
        $this->assertSame('tarif_jasa', $item->url);
        $this->assertSame(10170, (int) $item->parent);

        $this->assertContains(10170, $this->roles(35));
        $this->assertContains(10171, $this->roles(35));
        $this->assertNotContains(10170, $this->roles(36), 'Teknisi bukan audience master jasa');
    }

    public function testUpDuaKaliTidakMenduplikasiMenuAtauGrant(): void
    {
        $this->migrasi();
        $this->migrasi();

        $this->assertSame(2, $this->db->table('menu')->countAllResults());
        $this->assertSame(3, count($this->roles(35)), 'grant tidak terduplikasi: ["2",10170,10171]');
    }
}
