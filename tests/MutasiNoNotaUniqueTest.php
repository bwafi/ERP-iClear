<?php

namespace Tests;

use App\Database\Migrations\RenumberDanUnikkanNoNotaMutasi;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class MutasiNoNotaUniqueTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        require_once APPPATH . 'Database/Migrations/2026-10-20-000100_RenumberDanUnikkanNoNotaMutasi.php';

        $this->db = Database::connect();
        $this->db->query('DROP TABLE IF EXISTS db_mutasi');
        $this->db->query('CREATE TABLE db_mutasi (
            idmutasi INT PRIMARY KEY AUTO_INCREMENT,
            no_nota_mutasi VARCHAR(45) NULL
        )');
        $this->db->query("INSERT INTO db_mutasi (idmutasi, no_nota_mutasi) VALUES
            (1, 'A'), (2, 'A'), (3, 'A'), (4, 'B'), (5, 'B'), (6, 'C')");
    }

    protected function tearDown(): void
    {
        $this->db->query('DROP TABLE IF EXISTS db_mutasi');

        parent::tearDown();
    }

    public function testRenumberDanPasangUniqueIndex(): void
    {
        $migration = new RenumberDanUnikkanNoNotaMutasi();
        $migration->up();
        $migration->up(); // idempotent

        $rows = $this->db->table('mutasi')->orderBy('idmutasi')->get()->getResultArray();
        $this->assertSame(['A', 'A-R2', 'A-R3', 'B', 'B-R2', 'C'], array_column($rows, 'no_nota_mutasi'));

        $indexes = $this->db->getIndexData('mutasi');
        $this->assertArrayHasKey('uniq_mutasi_no_nota', $indexes);
    }

    public function testNomorDuplikatDitolakSetelahIndex(): void
    {
        (new RenumberDanUnikkanNoNotaMutasi())->up();

        $this->expectException(\Throwable::class);
        $this->db->table('mutasi')->insert(['no_nota_mutasi' => 'A']);
    }
}
