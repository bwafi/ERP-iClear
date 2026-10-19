<?php

namespace Tests\integration;

use App\Database\Migrations\CreateInventoryBatchLedger;
use App\Database\Migrations\CreateInventoryLedger;
use App\Database\Migrations\CreateInventoryStockAdjustments;
use App\Database\Migrations\LinkWarrantyDamagedStock;
use App\Services\Inventory\InventoryBatchLedger;
use App\Services\Inventory\InventoryLedger;
use App\Services\Inventory\InventoryStockAdjustment;
use App\Services\Inventory\PurchaseBatchOrigin;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class InventoryBatchMariaDbTest extends CIUnitTestCase
{
    public function testMigrationAverageAndPhysicalOrigins(): void
    {
        $socket = getenv('ERP_SYNTHETIC_MARIADB_SOCKET');
        if (! $socket) {
            $this->markTestSkipped('Socket MariaDB sintetis belum disediakan.');
        }
        $db = Database::connect([
            'DBDriver' => 'MySQLi', 'hostname' => $socket, 'database' => 'erp_finance_test',
            'username' => getenv('ERP_SYNTHETIC_MARIADB_USER') ?: get_current_user(),
            'password' => '', 'DBPrefix' => '', 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_unicode_ci',
        ], false);
        $this->assertSame('erp_finance_test', $db->getDatabase());
        $this->assertSame(1, (int) $db->query('SELECT @@skip_networking AS enabled')->getRow()->enabled);
        $this->assertSame(0, (int) $db->query('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->getRow()->total);
        foreach ([
            '2026-10-18-000500_LinkDamagedStockToSupplierReturn.php',
            '2026-10-18-000700_CreateWarrantyStockIssues.php',
            '2026-10-18-000800_LinkWarrantyDamagedStock.php',
            '2026-10-19-000200_CreateInventoryLedger.php',
            '2026-10-19-000400_CreateInventoryBatchLedger.php',
            '2026-10-19-000500_CreateInventoryStockAdjustments.php',
        ] as $migration) {
            require_once APPPATH . 'Database/Migrations/' . $migration;
        }

        $schema = [
            'barang' => 'idbarang INT PRIMARY KEY, kode_barang VARCHAR(40), nama_barang VARCHAR(100), jenis_item VARCHAR(10), harga BIGINT, harga_beli BIGINT, idkategori INT, imei VARCHAR(30), jenis_hp VARCHAR(40), internal VARCHAR(40), warna VARCHAR(40), status VARCHAR(20), status_ppn VARCHAR(2), status_barang VARCHAR(20), stok_minimum INT, deleted VARCHAR(2), input VARCHAR(20), nama_barang_id VARCHAR(100)',
            'unit' => 'idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(50)',
            'detail_pembelian' => 'iddetail_pembelian INT PRIMARY KEY, pembelian_idpembelian INT, barang_idbarang INT, unit_idunit INT, jumlah INT, tanggal DATETIME, no_batch VARCHAR(60), hitung_hpp BIGINT',
            'pembelian' => 'idpembelian INT PRIMARY KEY, suplier_id_suplier INT, no_nota_supplier VARCHAR(60), input_by INT',
            'suplier' => 'id_suplier INT PRIMARY KEY, nama_suplier VARCHAR(100)',
            'stok_awal' => 'idstok_awal INT PRIMARY KEY, barang_idbarang INT, unit_idunit INT, jumlah INT, tanggal DATETIME, harga_beli BIGINT, input_by INT',
            'detail_penjualan' => 'iddetail_penjualan INT PRIMARY KEY, penjualan_idpenjualan INT, barang_idbarang INT, unit_idunit INT, jumlah INT, hpp_penjualan BIGINT, source_purchase_detail_id INT NULL',
            'penjualan' => 'idpenjualan INT PRIMARY KEY, tanggal DATETIME, input_by INT',
            'detail_mutasi' => 'iddetail_mutasi INT PRIMARY KEY, mutasi_idmutasi INT, barang_idbarang INT, kirim_idunit INT, terima_idunit INT, jumlah_kirim INT, jumlah_terima INT, tanggal_kirim DATETIME, tanggal_terima DATETIME, hpp_barang BIGINT',
            'mutasi' => 'idmutasi INT PRIMARY KEY, input_by INT',
            'retur_pelanggan' => 'idretur_pelanggan INT PRIMARY KEY, barang_idbarang INT, unit_idunit INT, jumlah INT, tanggal DATETIME, input_by INT',
            'retur_suplier' => 'idretur_suplier INT PRIMARY KEY, barang_idbarang INT, unit_idunit INT, jumlah INT, barang_rusak_id INT NULL, tanggal DATETIME, input_by INT',
            'barang_rusak' => 'idbarang_rusak INT PRIMARY KEY, barang_idbarang INT, unit_idunit INT, jumlah INT, claim_id INT NULL, tanggal_rusak DATETIME, input_by INT',
            'stok_opname' => 'barang_idbarang INT, unit_idunit INT, is_reverted INT',
            'garansi_sparepart_keluar' => 'id INT AUTO_INCREMENT PRIMARY KEY, barang_id INT, unit_id INT, jumlah INT, claim_id INT, service_id INT, hpp_satuan BIGINT, created_at DATETIME, input_by INT',
        ];
        try {
            foreach ($schema as $table => $columns) {
                $db->query("CREATE TABLE {$table} ({$columns})");
            }
            $forge = Database::forge($db);
            $view = new LinkWarrantyDamagedStock($forge);
            $method = new \ReflectionMethod($view, 'warrantyDamagedStockView');
            $db->query($method->invoke($view));
            (new CreateInventoryLedger($forge))->up();
            (new CreateInventoryBatchLedger($forge))->up();
            (new CreateInventoryStockAdjustments($forge))->up();
            $db->query("INSERT INTO barang (idbarang, kode_barang, nama_barang, jenis_item, deleted) VALUES (7, 'SYN-7', 'Sintetis', 'fisik', '0'), (8, 'SYN-J', 'Jasa', 'jasa', '0'), (9, 'SYN-9', 'Fisik baru', 'fisik', '0')");
            $db->query("INSERT INTO unit VALUES (1, 'Cabang A'), (2, 'Cabang B')");
            $db->query("INSERT INTO suplier VALUES (1, 'Supplier A'), (2, 'Supplier B')");
            $db->query("INSERT INTO pembelian (idpembelian, suplier_id_suplier, no_nota_supplier) VALUES (1, 1, 'A-1'), (2, 2, 'B-1')");
            $db->query("INSERT INTO penjualan VALUES (1, '2026-10-19', 1)");

            $valuation = new InventoryLedger($db);
            $batches = new InventoryBatchLedger($db);
            $db->query("INSERT INTO detail_pembelian (iddetail_pembelian, pembelian_idpembelian, barang_idbarang, unit_idunit, jumlah, tanggal, no_batch, hitung_hpp) VALUES (11, 1, 7, 1, 2, '2026-10-19', 'A-1', 100)");
            $valuation->postIncoming(7, 1, 'detail_pembelian', '11', 'purchase', 2, 100);
            $batches->post(7, 1, 'detail_pembelian', '11', 'purchase', 2, 11);

            $firstHpp = 100;
            $db->query('INSERT INTO detail_penjualan VALUES (21, 1, 7, 1, 1, 100, 11)');
            $valuation->postOutgoing(7, 1, 'detail_penjualan', '21', 'sale', 1);
            $batches->post(7, 1, 'detail_penjualan', '21', 'sale', -1, 11);

            $db->query("INSERT INTO detail_pembelian (iddetail_pembelian, pembelian_idpembelian, barang_idbarang, unit_idunit, jumlah, tanggal, no_batch, hitung_hpp) VALUES (12, 2, 7, 1, 3, '2026-10-19', 'B-1', 200)");
            $valuation->postIncoming(7, 1, 'detail_pembelian', '12', 'purchase', 3, 200);
            $batches->post(7, 1, 'detail_pembelian', '12', 'purchase', 3, 12);
            $db->query('INSERT INTO detail_penjualan VALUES (22, 1, 7, 1, 1, 175, 12)');
            $second = $valuation->postOutgoing(7, 1, 'detail_penjualan', '22', 'sale', 1);
            $batches->post(7, 1, 'detail_penjualan', '22', 'sale', -1, 12);

            $stock = $db->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 7 AND id_unit = 1')->getRow();
            $balance = $db->table('inventory_ledger_balances')->where('item_id', 7)->where('unit_id', 1)->get()->getRow();
            $this->assertSame(3, (int) $stock->stok_akhir);
            $this->assertSame(3, $batches->total(7, 1));
            $this->assertSame(3, (int) $balance->quantity);
            $this->assertSame(525, (int) $balance->value);
            $this->assertSame(-175, (int) $second['value']);
            $this->assertSame($firstHpp, (int) $db->table('detail_penjualan')->select('hpp_penjualan')->where('iddetail_penjualan', 21)->get()->getRow()->hpp_penjualan);
            $origins = $db->query('SELECT detail.iddetail_penjualan, supplier.nama_suplier FROM detail_penjualan detail JOIN detail_pembelian purchase_detail ON purchase_detail.iddetail_pembelian = detail.source_purchase_detail_id JOIN pembelian purchase ON purchase.idpembelian = purchase_detail.pembelian_idpembelian JOIN suplier supplier ON supplier.id_suplier = purchase.suplier_id_suplier ORDER BY detail.iddetail_penjualan')->getResultArray();
            $this->assertSame(['Supplier A', 'Supplier B'], array_column($origins, 'nama_suplier'));

            $db->query('DELETE FROM detail_penjualan WHERE iddetail_penjualan = 22');
            $valuation->reverse((int) $second['id'], 'detail_penjualan', '22', 'cancel');
            $saleBatch = $db->table('inventory_batch_events')->where('source_type', 'detail_penjualan')->where('source_id', '22')->where('source_event', 'sale')->get()->getRow();
            $firstReverse = $batches->reverse((int) $saleBatch->id, 'detail_penjualan', '22', 'cancel');
            $this->assertSame((int) $firstReverse['id'],
                (int) $batches->reverse((int) $saleBatch->id, 'detail_penjualan', '22', 'cancel')['id']);
            $this->assertSame(4, $batches->total(7, 1));
            $this->assertSame(4, (int) $db->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 7 AND id_unit = 1')->getRow()->stok_akhir);
            $db->query("INSERT INTO detail_mutasi (iddetail_mutasi, barang_idbarang, kirim_idunit, terima_idunit, jumlah_kirim, jumlah_terima, tanggal_kirim, tanggal_terima, hpp_barang, source_purchase_detail_id) VALUES (31, 7, 1, 2, 1, 1, '2026-10-19', '2026-10-19', 175, 12)");
            $transferOut = $valuation->postOutgoing(7, 1, 'detail_mutasi', '31', 'transfer_out', 1);
            $valuation->postIncomingValue(7, 2, 'detail_mutasi', '31', 'transfer_in', 1, -(int) $transferOut['value']);
            $batches->post(7, 1, 'detail_mutasi', '31', 'transfer_out', -1, 12);
            $batches->post(7, 2, 'detail_mutasi', '31', 'transfer_in', 1, 12);
            $this->assertSame(1, $batches->total(7, 2));
            $this->assertSame(3, $batches->total(7, 1));
            $this->assertSame(12, (new PurchaseBatchOrigin($db))->validate(12, 7, 2, 1));
            $this->assertSame('Supplier B', (new PurchaseBatchOrigin($db))->candidates(7, 2)[0]['nama_suplier']);
            $adjustments = new InventoryStockAdjustment($db);
            $adjustment = $adjustments->post(7, 1, -1, 'synthetic', 'count-1', 'Selisih hitung sintetis', 1);
            $this->assertSame(2, $batches->total(7, 1));
            $this->assertSame(2, (int) $db->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 7 AND id_unit = 1')->getRow()->stok_akhir);
            $this->assertSame(350, (int) $db->table('inventory_ledger_balances')->select('value')->where('item_id', 7)->where('unit_id', 1)->get()->getRow()->value);
            $adjustments->reverse((int) $adjustment['id'], 'Pembatalan hitung sintetis', 1);
            $this->assertSame(3, $batches->total(7, 1));
            $this->assertSame(525, (int) $db->table('inventory_ledger_balances')->select('value')->where('item_id', 7)->where('unit_id', 1)->get()->getRow()->value);
            $this->assertSame(3, (int) $db->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 7 AND id_unit = 1')->getRow()->stok_akhir);
            $adjustments->reverse((int) $adjustment['id'], 'Pembatalan hitung sintetis', 1);
            $this->assertSame(3, $batches->total(7, 1));
            try {
                $adjustments->post(8, 1, 1, 'synthetic', 'jasa-1', 'Tidak boleh stok jasa', 1, null, 100);
                $this->fail('Jasa diterima sebagai persediaan.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('fisik', $error->getMessage());
            }
            $found = $adjustments->post(9, 1, 2, 'synthetic', 'found-1', 'Temuan sintetis', 1, null, 300);
            $this->assertSame(2, (int) $db->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 9 AND id_unit = 1')->getRow()->stok_akhir);
            $this->assertSame(600, (int) $db->table('inventory_ledger_balances')->select('value')->where('item_id', 9)->where('unit_id', 1)->get()->getRow()->value);
            $this->assertSame(2, $batches->total(9, 1));
            $adjustments->reverse((int) $found['id'], 'Koreksi temuan sintetis', 1);
            $this->assertSame(0, (int) $db->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 9 AND id_unit = 1')->getRow()->stok_akhir);
            $this->assertSame(0, $batches->total(9, 1));
        } finally {
            $db->query('DROP VIEW IF EXISTS stok_barang');
            $db->query('DROP TRIGGER IF EXISTS inventory_ledger_no_update');
            $db->query('DROP TRIGGER IF EXISTS inventory_ledger_no_delete');
            foreach (['inventory_stock_adjustments', 'inventory_batch_events', 'inventory_batch_balances', 'inventory_ledger_entries', 'inventory_ledger_balances'] as $table) {
                $db->query("DROP TABLE IF EXISTS {$table}");
            }
            foreach (array_reverse(array_keys($schema)) as $table) {
                $db->query("DROP TABLE IF EXISTS {$table}");
            }
        }
    }
}
