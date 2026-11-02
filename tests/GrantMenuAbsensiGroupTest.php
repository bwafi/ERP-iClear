<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class GrantMenuAbsensiGroupTest extends CIUnitTestCase
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
            ['ID_JABATAN' => 1, 'NAMA_JABATAN' => 'Admin root', 'ROLES_JABATAN' => '["1","100","116"]'],
            ['ID_JABATAN' => 34, 'NAMA_JABATAN' => 'Manager', 'ROLES_JABATAN' => '["1","118"]'],
            ['ID_JABATAN' => 36, 'NAMA_JABATAN' => 'Teknisi', 'ROLES_JABATAN' => '["2"]'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS jabatan');
        $this->db->dataCache['table_names'] = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function migrasi(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-11-02-000700_GrantMenuAbsensiGroup.php';
        (new \App\Database\Migrations\GrantMenuAbsensiGroup())->up();
    }

    private function roles(int $jabatan): array
    {
        $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();

        return array_map('intval', json_decode((string) $row->ROLES_JABATAN, true) ?: []);
    }

    public function testSemuaJabatanMendapatPresensiDanRiwayat(): void
    {
        $this->migrasi();

        foreach ([1, 34, 36] as $id) {
            $this->assertContains(112, $this->roles($id));
            $this->assertContains(115, $this->roles($id));
        }
    }

    public function testRiwayatAdminHanyaUntukJabatanAdmin(): void
    {
        $this->migrasi();

        $this->assertContains(117, $this->roles(1));
        $this->assertContains(117, $this->roles(34));
        $this->assertNotContains(117, $this->roles(36));
    }

    public function testIdempotenDanTidakMenghapusRoleLain(): void
    {
        $this->migrasi();
        $this->migrasi();

        $admin = $this->roles(1);
        $this->assertSame(1, count(array_keys($admin, 112, true)));
        $this->assertSame(1, count(array_keys($admin, 115, true)));
        $this->assertSame(1, count(array_keys($admin, 117, true)));
        $this->assertContains(100, $admin);
        $this->assertContains(116, $admin);
    }
}
