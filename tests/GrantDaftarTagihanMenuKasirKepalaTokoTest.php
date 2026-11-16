<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class GrantDaftarTagihanMenuKasirKepalaTokoTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');
        $this->db->query('DROP TABLE IF EXISTS jabatan');
        $this->db->query('CREATE TABLE jabatan (
            ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $this->db->table('jabatan')->insertBatch([
            ['ID_JABATAN' => 1, 'NAMA_JABATAN' => 'Admin root', 'ROLES_JABATAN' => '["1","33"]'],
            ['ID_JABATAN' => 35, 'NAMA_JABATAN' => 'ADMIN / KASIR', 'ROLES_JABATAN' => '["1","22","100"]'],
            ['ID_JABATAN' => 36, 'NAMA_JABATAN' => 'Teknisi', 'ROLES_JABATAN' => '["2"]'],
            ['ID_JABATAN' => 41, 'NAMA_JABATAN' => 'Kepala Toko', 'ROLES_JABATAN' => '["1","22"]'],
            ['ID_JABATAN' => 47, 'NAMA_JABATAN' => 'ADMIN / KASIR', 'ROLES_JABATAN' => null],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS jabatan');
        $this->db->dataCache['table_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function migrasi(): \App\Database\Migrations\GrantDaftarTagihanMenuKasirKepalaToko
    {
        require_once APPPATH . 'Database/Migrations/2026-11-16-000100_GrantDaftarTagihanMenuKasirKepalaToko.php';

        return new \App\Database\Migrations\GrantDaftarTagihanMenuKasirKepalaToko();
    }

    private function roles(int $jabatan): array
    {
        $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();

        return array_map('intval', json_decode((string) $row->ROLES_JABATAN, true) ?: []);
    }

    public function testAdminKasirDanKepalaTokoMendapatMenuDaftarTagihan(): void
    {
        $this->migrasi()->up();

        foreach ([35, 41, 47] as $id) {
            $this->assertContains(33, $this->roles($id), 'Jabatan ' . $id . ' harus punya menu 33');
        }
    }

    public function testJabatanLainDanRoleLainTidakBerubah(): void
    {
        $this->migrasi()->up();

        $this->assertNotContains(33, $this->roles(36));

        $kasir = $this->roles(35);
        $this->assertContains(22, $kasir);
        $this->assertContains(100, $kasir);

        $kepalaToko = $this->roles(41);
        $this->assertContains(22, $kepalaToko);
    }

    public function testIdempotenTidakMenduplikasiMenu(): void
    {
        $migrasi = $this->migrasi();
        $migrasi->up();
        $migrasi->up();

        $kasir = $this->roles(35);
        $this->assertSame(1, count(array_keys($kasir, 33, true)));

        $admin = $this->roles(1);
        $this->assertSame(1, count(array_keys($admin, 33, true)));
    }

    public function testDownMencabutMenuTanpaMenghapusRoleLain(): void
    {
        $migrasi = $this->migrasi();
        $migrasi->up();
        $migrasi->down();

        foreach ([35, 41, 47] as $id) {
            $this->assertNotContains(33, $this->roles($id), 'Jabatan ' . $id . ' harus kehilangan menu 33');
        }

        $kasir = $this->roles(35);
        $this->assertContains(22, $kasir);
        $this->assertContains(100, $kasir);
        $this->assertContains(33, $this->roles(1), 'Grant Admin root tidak boleh ikut dicabut');
    }
}
