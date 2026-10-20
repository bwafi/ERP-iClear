<?php

namespace Tests\integration;

use App\Database\Migrations\CreateProductPriceHistory;
use App\Database\Migrations\UseCalendarWarrantyTerms;
use App\Services\Inventory\ProductPriceCatalog;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class KartuHargaMigrationMariaDbTest extends CIUnitTestCase
{
    public function testMigrationIsIdempotentAndCopiesOnlyExistingProductMenuAccess(): void
    {
        $host = getenv('ERP_SYNTHETIC_MARIADB_HOST');
        if ($host !== 'mariadb-test') {
            $this->markTestSkipped('Migrasi Kartu Harga hanya dijalankan pada container MariaDB privat.');
        }
        $config = [
            'DBDriver' => 'MySQLi', 'hostname' => $host,
            'port' => (int) (getenv('ERP_SYNTHETIC_MARIADB_PORT') ?: 3306),
            'database' => 'erp_finance_test',
            'username' => getenv('ERP_SYNTHETIC_MARIADB_USER') ?: 'root',
            'password' => '', 'DBPrefix' => '', 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci',
        ];
        $adminDb = Database::connect($config, false);
        require_once APPPATH . 'Database/Migrations/2026-10-20-000200_CreateProductPriceHistory.php';
        self::assertSame('erp_finance_test', $adminDb->getDatabase());
        $version = (string) $adminDb->query('SELECT VERSION() AS version')->getRow()->version;
        self::assertStringContainsString('MariaDB', $version);
        $fixtureName = 'erp_finance_test_price_migration';
        $adminDb->query('DROP DATABASE IF EXISTS ' . $fixtureName);
        $adminDb->query('CREATE DATABASE ' . $fixtureName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        try {
            $config['database'] = $fixtureName;
            $db = Database::connect($config, false);
            $db->query('CREATE TABLE barang (idbarang INT PRIMARY KEY, harga BIGINT NOT NULL DEFAULT 0, harga_beli BIGINT NOT NULL DEFAULT 0, deleted TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
            $db->query('CREATE TABLE menu (idmenu INT PRIMARY KEY, urutan INT, nama_menu VARCHAR(100), roles VARCHAR(100), url VARCHAR(200) NULL, show_menu TINYINT, sub TINYINT, parent INT, utama TINYINT, categories TINYINT, icon TEXT NULL, manualbook TEXT NULL) ENGINE=InnoDB');
            $db->query('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, ROLES_JABATAN TEXT) ENGINE=InnoDB');
            $db->query('CREATE TABLE hpp_barang (idbarang INT PRIMARY KEY, hpp BIGINT NOT NULL) ENGINE=InnoDB');
            $db->query('CREATE TABLE service_sparepart (id INT PRIMARY KEY, barang_idbarang INT, harga_penjualan BIGINT NOT NULL, hpp_penjualan BIGINT NOT NULL) ENGINE=InnoDB');
            $db->query('CREATE TABLE service (idservice INT PRIMARY KEY, garansi_hari INT NULL, tanggal_selesai DATETIME NULL) ENGINE=InnoDB');
            $db->query("INSERT INTO menu (idmenu,nama_menu,url) VALUES (6,'Produk','produk')");
            $db->query("INSERT INTO jabatan VALUES (1,'[6]'),(7,'[6]'),(8,'[]')");
            $db->query('INSERT INTO barang (idbarang, harga, harga_beli) VALUES (22,100000,70000)');
            $db->query('INSERT INTO hpp_barang VALUES (22,65000)');
            $db->query('INSERT INTO service_sparepart VALUES (1,22,100000,64000)');
            (new CreateProductPriceHistory(Database::forge($config)))->up();
            (new CreateProductPriceHistory(Database::forge($config)))->up();
            $db->query('UPDATE barang SET garansi_hari_default = 45 WHERE idbarang = 22');
            require_once APPPATH . 'Database/Migrations/2026-10-20-000300_UseCalendarWarrantyTerms.php';
            (new UseCalendarWarrantyTerms(Database::forge($config)))->up();
            (new UseCalendarWarrantyTerms(Database::forge($config)))->up();

            self::assertTrue($db->fieldExists('garansi_hari_default', 'barang'));
            self::assertSame(45, (int) $db->table('barang')->where('idbarang', 22)->get()->getRow()->garansi_hari_default);
            self::assertTrue($db->tableExists('barang_harga_history'));
            self::assertSame(1, (int) $db->table('menu')->where('idmenu', 10152)->countAllResults());
            self::assertSame([6, 10152], array_map('intval', json_decode($db->table('jabatan')->where('ID_JABATAN', 1)->get()->getRow()->ROLES_JABATAN, true)));
            self::assertSame([6, 10152], array_map('intval', json_decode($db->table('jabatan')->where('ID_JABATAN', 7)->get()->getRow()->ROLES_JABATAN, true)));
            self::assertSame([], json_decode($db->table('jabatan')->where('ID_JABATAN', 8)->get()->getRow()->ROLES_JABATAN, true));
            self::assertSame(0, (int) $db->table('barang_harga_history')->countAllResults());

            $catalog = new ProductPriceCatalog($db);
            self::assertTrue($catalog->update(22, 120000, 'bulan_1', 9));
            self::assertFalse($catalog->update(22, 120000, 'bulan_1', 9));
            $product = $db->table('barang')->where('idbarang', 22)->get()->getRow();
            self::assertSame(120000, (int) $product->harga);
            self::assertSame(70000, (int) $product->harga_beli);
            self::assertNull($product->garansi_hari_default);
            self::assertSame('bulan_1', $product->garansi_opsi_default);
            self::assertSame(65000, (int) $db->table('hpp_barang')->where('idbarang', 22)->get()->getRow()->hpp);
            $oldServiceSnapshot = $db->table('service_sparepart')->where('id', 1)->get()->getRow();
            self::assertSame(100000, (int) $oldServiceSnapshot->harga_penjualan);
            self::assertSame(64000, (int) $oldServiceSnapshot->hpp_penjualan);
            self::assertSame(1, (int) $db->table('barang_harga_history')->countAllResults());
            $history = $db->table('barang_harga_history')->where('barang_idbarang', 22)->get()->getRow();
            self::assertSame(100000, (int) $history->harga_sebelum);
            self::assertSame(120000, (int) $history->harga_sesudah);
            self::assertSame(45, (int) $history->garansi_sebelum);
            self::assertNull($history->garansi_sesudah);
            self::assertSame('bulan_1', $history->garansi_opsi_sesudah);
            self::assertSame(9, (int) $history->petugas_idakun);
            self::assertNotEmpty($history->berlaku_mulai);
        } finally {
            $adminDb->query('DROP DATABASE IF EXISTS ' . $fixtureName);
        }
    }
}
