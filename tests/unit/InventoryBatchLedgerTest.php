<?php

namespace Tests\unit;

use App\Services\Inventory\InventoryBatchLedger;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class InventoryBatchLedgerTest extends CIUnitTestCase
{
    public function testTwoSuppliersShareItemButNotPhysicalBatchBalance(): void
    {
        $db = $this->database();
        $ledger = new InventoryBatchLedger($db);

        $db->query('UPDATE stok_barang SET stok_akhir = 2 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_pembelian', '11', 'purchase', 2, 11);
        $db->query('UPDATE stok_barang SET stok_akhir = 1 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_penjualan', '21', 'sale', -1, 11);
        $db->query('UPDATE stok_barang SET stok_akhir = 4 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_pembelian', '12', 'purchase', 3, 12);
        $db->query('UPDATE stok_barang SET stok_akhir = 3 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_penjualan', '22', 'sale', -1, 12);

        $this->assertSame(3, $ledger->total(7, 1));
        $this->assertSame(1, $this->balance($db, 11));
        $this->assertSame(2, $this->balance($db, 12));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('melebihi saldo batch');
        $db->query('UPDATE stok_barang SET stok_akhir = 1 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_penjualan', '23', 'sale', -2, 11);
    }

    public function testCancellationRestoresOriginalBatchOnlyOnce(): void
    {
        $db = $this->database();
        $ledger = new InventoryBatchLedger($db);
        $db->query('UPDATE stok_barang SET stok_akhir = 2 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_pembelian', '11', 'purchase', 2, 11);
        $db->query('UPDATE stok_barang SET stok_akhir = 1 WHERE idbarang = 7 AND id_unit = 1');
        $sale = $ledger->post(7, 1, 'detail_penjualan', '21', 'sale', -1, 11);
        $db->query('UPDATE stok_barang SET stok_akhir = 2 WHERE idbarang = 7 AND id_unit = 1');
        $first = $ledger->reverse((int) $sale['id'], 'detail_penjualan', '21', 'cancel');
        $second = $ledger->reverse((int) $sale['id'], 'detail_penjualan', '21', 'cancel');
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(2, $ledger->total(7, 1));
        $this->assertSame(2, $this->balance($db, 11));
    }

    public function testUnknownSourceDoesNotInventSupplier(): void
    {
        $db = $this->database();
        $ledger = new InventoryBatchLedger($db);
        $db->query('UPDATE stok_barang SET stok_akhir = 2 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_pembelian', '11', 'purchase', 2, 11);
        $db->query('UPDATE stok_barang SET stok_akhir = 1 WHERE idbarang = 7 AND id_unit = 1');
        $ledger->post(7, 1, 'detail_penjualan', '21', 'sale', -1);
        $this->assertSame(1, $ledger->total(7, 1));
        $this->assertSame(0, $this->balance($db, 11));
        $this->assertSame(1, $this->balance($db, 0));
    }

    private function balance($db, int $batchId): int
    {
        $row = $db->table('inventory_batch_balances')->select('quantity')->where('item_id', 7)
            ->where('unit_id', 1)->where('batch_id', $batchId)->get()->getRow();
        return (int) ($row->quantity ?? 0);
    }

    private function database()
    {
        $db = Database::connect(['DBDriver' => 'SQLite3', 'database' => ':memory:', 'DBPrefix' => ''], false);
        $db->query('CREATE TABLE barang (idbarang INTEGER PRIMARY KEY)');
        $db->query('CREATE TABLE detail_pembelian (iddetail_pembelian INTEGER PRIMARY KEY, barang_idbarang INTEGER)');
        $db->query('CREATE TABLE stok_barang (idbarang INTEGER, id_unit INTEGER, stok_akhir INTEGER)');
        $db->query('CREATE TABLE inventory_batch_balances (item_id INTEGER, unit_id INTEGER, batch_id INTEGER, quantity INTEGER, PRIMARY KEY (item_id, unit_id, batch_id))');
        $db->query('CREATE TABLE inventory_batch_events (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, unit_id INTEGER, batch_id INTEGER, source_type TEXT, source_id TEXT, source_event TEXT, quantity INTEGER, reverses_id INTEGER UNIQUE, created_at TEXT, UNIQUE (source_type, source_id, source_event, batch_id))');
        $db->query('INSERT INTO barang VALUES (7)');
        $db->query('INSERT INTO detail_pembelian VALUES (11, 7), (12, 7)');
        $db->query('INSERT INTO stok_barang VALUES (7, 1, 0)');
        return $db;
    }
}
