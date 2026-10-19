<?php

namespace Tests\unit;

use App\Services\Inventory\InventoryLedger;
use App\Services\Service\SparepartKeluarHardDeleteService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class InventoryLedgerCancellationTest extends CIUnitTestCase
{
    public function testCancellationRestoresExactValueWithoutChangingHistoricalCost(): void
    {
        $db = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $db->query('CREATE TABLE inventory_ledger_balances (item_id INTEGER, unit_id INTEGER, quantity INTEGER, value INTEGER, PRIMARY KEY (item_id, unit_id))');
        $db->query('CREATE TABLE inventory_ledger_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, unit_id INTEGER, source_type TEXT, source_id TEXT, source_event TEXT, direction TEXT, quantity INTEGER, unit_cost INTEGER, value INTEGER, balance_quantity INTEGER, balance_value INTEGER, reverses_id INTEGER UNIQUE, created_at TEXT, UNIQUE (source_type, source_id, source_event))');

        $ledger = new InventoryLedger($db);
        $ledger->postIncoming(7, 1, 'detail_pembelian', '1', 'purchase', 2, 100);
        $firstSale = $ledger->postOutgoing(7, 1, 'detail_penjualan', '1', 'sale', 1);
        $ledger->postIncoming(7, 1, 'detail_pembelian', '2', 'purchase', 2, 200);
        $secondSale = $ledger->postOutgoing(7, 1, 'detail_penjualan', '2', 'sale', 1);

        $this->assertSame(-100, $firstSale['value']);
        $this->assertSame(-167, $secondSale['value']);
        $this->assertSame(333, (int) $db->table('inventory_ledger_balances')->where('item_id', 7)->where('unit_id', 1)->get()->getRow('value'));

        $reversal = $ledger->reverse((int) $secondSale['id'], 'detail_penjualan', '2', 'cancel');
        $repeat = $ledger->reverse((int) $secondSale['id'], 'detail_penjualan', '2', 'cancel');
        $this->assertSame($reversal['id'], $repeat['id']);
        $this->assertSame(167, $reversal['value']);
        $balance = $db->table('inventory_ledger_balances')->where('item_id', 7)->where('unit_id', 1)->get()->getRowArray();
        $this->assertSame(3, (int) $balance['quantity']);
        $this->assertSame(500, (int) $balance['value']);
        $this->assertSame(5, $db->table('inventory_ledger_entries')->countAllResults());
        $this->assertSame(-100, (int) $db->table('inventory_ledger_entries')->where('id', $firstSale['id'])->get()->getRow('value'));
    }

    public function testPaidInvoiceCannotBeHardDeleted(): void
    {
        $db = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $db->query('CREATE TABLE penjualan (idpenjualan INTEGER PRIMARY KEY, service_idservice INTEGER, bayar INTEGER)');
        $db->query('CREATE TABLE detail_penjualan (iddetail_penjualan INTEGER PRIMARY KEY, penjualan_idpenjualan INTEGER, barang_idbarang INTEGER, unit_idunit INTEGER)');
        $db->query('INSERT INTO penjualan VALUES (9, 4, 100)');
        $db->query('INSERT INTO detail_penjualan VALUES (10, 9, 7, 1)');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah dibayar');
        (new SparepartKeluarHardDeleteService($db))->hapusDetail(10, 4);
    }
}
