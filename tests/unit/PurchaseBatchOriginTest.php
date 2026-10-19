<?php

namespace Tests\unit;

use App\Services\Inventory\PurchaseBatchOrigin;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class PurchaseBatchOriginTest extends CIUnitTestCase
{
    public function testSameItemCanBeAttributedToDifferentSuppliersWithoutChangingItemId(): void
    {
        $db = $this->database();
        $origin = new PurchaseBatchOrigin($db);

        $candidates = $origin->candidates(7, 1);
        $this->assertCount(2, $candidates);
        $this->assertSame('Supplier B', $candidates[0]['nama_suplier']);
        $this->assertSame('Supplier A', $candidates[1]['nama_suplier']);
        $this->assertSame(11, $origin->validate(11, 7, 1, 1));
        $this->assertSame(12, $origin->validate(12, 7, 1, 1));
        $this->assertNull($origin->validate(null, 7, 1, 1));
    }

    public function testOtherUnitOrOverAttributedBatchIsRejected(): void
    {
        $db = $this->database();
        $origin = new PurchaseBatchOrigin($db);
        try {
            $origin->validate(11, 7, 2, 1);
            $this->fail('Batch dari unit lain diterima.');
        } catch (\InvalidArgumentException $error) {
            $this->assertStringContainsString('unit', $error->getMessage());
        }

        $db->query('INSERT INTO detail_penjualan VALUES (21, 11, 2)');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('melebihi');
        $origin->validate(11, 7, 1, 1);
    }

    public function testLaterPurchaseCannotBeAssignedToEarlierService(): void
    {
        $origin = new PurchaseBatchOrigin($this->database());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lebih baru');
        $origin->validate(12, 7, 1, 1, '2026-10-18 10:00:00');
    }

    private function database()
    {
        $db = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $db->query('CREATE TABLE suplier (id_suplier INTEGER PRIMARY KEY, nama_suplier TEXT)');
        $db->query('CREATE TABLE pembelian (idpembelian INTEGER PRIMARY KEY, suplier_id_suplier INTEGER, no_nota_supplier TEXT)');
        $db->query('CREATE TABLE detail_pembelian (iddetail_pembelian INTEGER PRIMARY KEY, pembelian_idpembelian INTEGER, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, tanggal TEXT)');
        $db->query('CREATE TABLE detail_penjualan (iddetail_penjualan INTEGER PRIMARY KEY, source_purchase_detail_id INTEGER, jumlah INTEGER)');
        $db->query("INSERT INTO suplier VALUES (1, 'Supplier A'), (2, 'Supplier B')");
        $db->query("INSERT INTO pembelian VALUES (1, 1, 'A-1'), (2, 2, 'B-1')");
        $db->query("INSERT INTO detail_pembelian VALUES (11, 1, 7, 1, 2, '2026-10-19'), (12, 2, 7, 1, 3, '2026-10-19')");
        return $db;
    }
}
