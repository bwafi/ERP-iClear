<?php

namespace Tests\integration;

use App\Services\Inventory\SupplierReturnAvailability;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class SupplierReturnAvailabilityMariaDbTest extends CIUnitTestCase
{
    public function testAvailabilityKeepsSupplierBatchAndUnitSeparateWithoutChangingStock(): void
    {
        $host = getenv('ERP_SYNTHETIC_MARIADB_HOST');
        if ($host !== 'mariadb-test') {
            $this->markTestSkipped('Pengujian hanya memakai MariaDB privat dengan fixture sintetis.');
        }
        $configuration = [
            'DBDriver' => 'MySQLi', 'hostname' => $host,
            'port' => (int) (getenv('ERP_SYNTHETIC_MARIADB_PORT') ?: 3306),
            'database' => 'erp_finance_test',
            'username' => getenv('ERP_SYNTHETIC_MARIADB_USER') ?: 'root',
            'password' => '', 'DBPrefix' => '', 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci',
        ];
        $admin = Database::connect($configuration, false);
        self::assertSame('erp_finance_test', $admin->getDatabase());
        self::assertStringContainsString('MariaDB', $admin->query('SELECT VERSION() AS version')->getRow()->version);
        $fixture = 'erp_finance_test_supplier_returns';
        $admin->query('CREATE DATABASE ' . $fixture);
        try {
            $configuration['database'] = $fixture;
            $db = Database::connect($configuration, false);
            $db->query('CREATE TABLE pembelian (idpembelian INT PRIMARY KEY, suplier_id_suplier INT) ENGINE=InnoDB');
            $db->query('CREATE TABLE detail_pembelian (iddetail_pembelian INT PRIMARY KEY, pembelian_idpembelian INT, no_batch VARCHAR(32), barang_idbarang INT, unit_idunit INT, jumlah INT) ENGINE=InnoDB');
            $db->query('CREATE TABLE barang_rusak (idbarang_rusak INT PRIMARY KEY, idpembelian INT NULL, no_nota_sup VARCHAR(32) NULL, barang_idbarang INT, unit_idunit INT, jumlah INT) ENGINE=InnoDB');
            $db->query('CREATE TABLE retur_suplier (idretur_suplier INT PRIMARY KEY, detail_pembelian_iddetail_pembelian INT, barang_rusak_id INT NULL, jumlah INT) ENGINE=InnoDB');
            $db->query('INSERT INTO pembelian VALUES (10,1),(20,2),(30,0),(40,1),(50,1)');
            $db->query("INSERT INTO detail_pembelian VALUES (101,10,'A',7,1,5),(102,20,'B',7,1,5),(103,10,'A',7,2,1),(104,30,'C',7,1,1),(105,40,'D',7,1,5),(106,50,'E',7,1,1)");
            $availability = new SupplierReturnAvailability($db);
            self::assertSame([], $availability->forUnit(0));
            self::assertSame(0, $availability->forUnit(1)[101]['returnable']);
            self::assertStringContainsString('Belum ada Barang Rusak', $availability->forUnit(1)[101]['reason']);
            $db->query("INSERT INTO barang_rusak VALUES (201,10,'A',7,1,3),(202,20,'B',7,1,2),(203,10,'A',7,2,50),(204,NULL,NULL,7,1,90),(205,40,'D',7,1,3)");
            $db->query('INSERT INTO retur_suplier VALUES (301,101,201,1),(302,105,NULL,1)');
            $rows = $availability->forUnit(1);
            self::assertSame(4, $rows[101]['remaining_purchase']);
            self::assertSame(2, $rows[101]['available_damaged']);
            self::assertSame(2, $rows[101]['returnable']);
            self::assertSame('', $rows[101]['reason']);
            self::assertSame(2, $rows[102]['returnable']);
            self::assertArrayNotHasKey(103, $rows);
            self::assertSame(0, $rows[104]['returnable']);
            self::assertStringContainsString('Supplier', $rows[104]['reason']);
            self::assertSame(0, $rows[105]['returnable']);
            self::assertStringContainsString('Retur lama', $rows[105]['reason']);
            self::assertSame(0, $rows[106]['returnable']);
            self::assertSame(1, $availability->forUnit(2)[103]['returnable']);
            self::assertSame(2, $db->table('retur_suplier')->countAllResults());
            self::assertSame(5, $db->table('barang_rusak')->countAllResults());
            $db->query('INSERT INTO retur_suplier VALUES (303,101,201,2)');
            self::assertSame(0, $availability->forUnit(1)[101]['returnable']);
            self::assertSame(2, $availability->forUnit(1)[102]['returnable']);
        } finally {
            $admin->query('DROP DATABASE ' . $fixture);
        }
    }
}
