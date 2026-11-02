<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class MenuRosterGrantTest extends CIUnitTestCase
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
            ID_JABATAN INT PRIMARY KEY,
            NAMA_JABATAN TEXT NULL,
            ROLES_JABATAN TEXT NULL)');

        $this->db->table('jabatan')->insertBatch([
            ['ID_JABATAN' => 0, 'NAMA_JABATAN' => 'Super Admin', 'ROLES_JABATAN' => '["1","2"]'],
            ['ID_JABATAN' => 1, 'NAMA_JABATAN' => 'Admin Root', 'ROLES_JABATAN' => '["1","118"]'],
            ['ID_JABATAN' => 34, 'NAMA_JABATAN' => 'Manager', 'ROLES_JABATAN' => '[]'],
            ['ID_JABATAN' => 35, 'NAMA_JABATAN' => 'Admin Kasir', 'ROLES_JABATAN' => '["2"]'],
            ['ID_JABATAN' => 47, 'NAMA_JABATAN' => 'Kasir', 'ROLES_JABATAN' => '["2"]'],
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
        require_once APPPATH . 'Database/Migrations/2026-11-02-000300_GrantMenuJadwalMasukRoster.php';
        (new \App\Database\Migrations\GrantMenuJadwalMasukRoster())->up();
    }

    private function roles(int $jabatan): array
    {
        $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();

        return array_map('strval', json_decode((string) $row->ROLES_JABATAN, true) ?: []);
    }

    public function testGrantIdempotenDanTidakMenyentuhRoleLain(): void
    {
        $this->migrasi();
        $this->migrasi();

        $this->assertSame(['1', '2', '118'], $this->roles(0));
        $this->assertSame(['1', '118'], $this->roles(1));
        $this->assertSame(['118'], $this->roles(34));
        $this->assertSame(['2', '118'], $this->roles(35));
        $this->assertSame(['2', '118'], $this->roles(47));
        $this->assertSame(['2'], $this->roles(36));
    }
}
