<?php

namespace Tests\unit;

use App\Models\ModelHppBarang;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class UnitCostProjectionTest extends CIUnitTestCase
{
    public function testRestockChangesOnlyCurrentUnitCost(): void
    {
        $db = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        foreach ([
            'stok_awal (idstok_awal INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, harga_beli INTEGER, input_by INTEGER)',
            'pembelian (idpembelian INTEGER, input_by INTEGER)',
            'detail_pembelian (iddetail_pembelian INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, hitung_hpp INTEGER, pembelian_idpembelian INTEGER)',
            'detail_mutasi (iddetail_mutasi INTEGER, tanggal_terima TEXT, tanggal_kirim TEXT, barang_idbarang INTEGER, terima_idunit INTEGER, kirim_idunit INTEGER, jumlah_terima INTEGER, jumlah_kirim INTEGER, hpp_barang INTEGER, mutasi_idmutasi INTEGER)',
            'mutasi (idmutasi INTEGER, input_by INTEGER)',
            'retur_pelanggan (idretur_pelanggan INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, input_by INTEGER)',
            'penjualan (idpenjualan INTEGER, tanggal TEXT, input_by INTEGER)',
            'detail_penjualan (iddetail_penjualan INTEGER, penjualan_idpenjualan INTEGER, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, hpp_penjualan INTEGER)',
            'barang_rusak (idbarang_rusak INTEGER, tanggal_rusak TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, claim_id INTEGER, input_by INTEGER)',
            'retur_suplier (idretur_suplier INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, barang_rusak_id INTEGER, input_by INTEGER)',
            'garansi_sparepart_keluar (id INTEGER, created_at TEXT, barang_id INTEGER, unit_id INTEGER, jumlah INTEGER, hpp_satuan INTEGER, input_by INTEGER)',
            'stok_barang (idbarang INTEGER, id_unit INTEGER, stok_akhir INTEGER)',
            'inventory_ledger_balances (item_id INTEGER, unit_id INTEGER, quantity INTEGER, value INTEGER)',
            'inventory_stock_adjustments (id INTEGER, created_at TEXT, item_id INTEGER, unit_id INTEGER, delta INTEGER, unit_cost INTEGER, actor_id INTEGER)',
        ] as $table) {
            $db->query('CREATE TABLE ' . $table);
        }
        $db->query('INSERT INTO stok_barang VALUES (7, 1, 3), (7, 2, 2)');
        $db->query('INSERT INTO pembelian VALUES (1, 1), (2, 1), (3, 1)');
        $db->query("INSERT INTO detail_pembelian VALUES (1, '2026-10-19 08:00:00', 7, 1, 3, 10000, 1)");
        $db->query("INSERT INTO detail_pembelian VALUES (3, '2026-10-19 08:00:00', 7, 2, 2, 30000, 3)");
        $model = new ModelHppBarang($db);
        $this->assertSame(10000, $model->currentUnitCost(7, 1));
        $this->assertSame(30000, $model->currentUnitCost(7, 2));

        $db->query("INSERT INTO penjualan VALUES (1, '2026-10-19 09:00:00', 1)");
        $db->query('INSERT INTO detail_penjualan VALUES (1, 1, 7, 1, 1, 10000)');
        $db->query('UPDATE stok_barang SET stok_akhir = 2 WHERE idbarang = 7 AND id_unit = 1');
        $db->query("INSERT INTO detail_pembelian VALUES (2, '2026-10-19 10:00:00', 7, 1, 2, 20000, 2)");
        $db->query('UPDATE stok_barang SET stok_akhir = 4 WHERE idbarang = 7 AND id_unit = 1');

        $this->assertSame(15000, $model->currentUnitCost(7, 1));
        $this->assertSame(30000, $model->currentUnitCost(7, 2));
        $snapshot = $db->query('SELECT hpp_penjualan FROM detail_penjualan WHERE iddetail_penjualan = 1')->getRow();
        $this->assertSame(10000, (int) $snapshot->hpp_penjualan);

        $db->query('INSERT INTO inventory_ledger_balances VALUES (7, 1, 4, 64000)');
        $this->assertSame(16000, $model->currentUnitCost(7, 1));
        $db->query('UPDATE inventory_ledger_balances SET quantity = 3 WHERE item_id = 7 AND unit_id = 1');
        $this->assertSame(15000, $model->currentUnitCost(7, 1));
    }
}
