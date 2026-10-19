<?php

namespace Tests\unit;

use App\Models\ModelKartuStok;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class ZeroStockCardTest extends CIUnitTestCase
{
    public function testPhysicalMasterWithoutMovementsHasZeroCardPerUnit(): void
    {
        $db = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $db->query('CREATE TABLE barang (idbarang INTEGER, kode_barang TEXT, nama_barang TEXT, imei TEXT, harga INTEGER, harga_beli INTEGER, jenis_hp TEXT, warna TEXT, status_ppn INTEGER, jenis_item TEXT, idkategori INTEGER, deleted INTEGER)');
        $db->query('CREATE TABLE unit (idunit INTEGER, NAMA_UNIT TEXT)');
        $db->query('CREATE TABLE kategori (id INTEGER, nama_kategori TEXT, `delete` INTEGER)');
        $db->query('CREATE TABLE stok_barang (idbarang INTEGER, id_unit INTEGER, stok_awal INTEGER, stok_akhir INTEGER, total_pembelian INTEGER, total_penjualan INTEGER, total_retur_pelanggan INTEGER, total_retur_supplier INTEGER, total_mutasi_masuk INTEGER, total_mutasi_keluar INTEGER)');
        $db->query("INSERT INTO kategori VALUES (3, 'Sparepart', 0)");
        $db->query("INSERT INTO unit VALUES (1, 'TEST A'), (2, 'TEST B')");
        $db->query("INSERT INTO barang VALUES (101, 'TEST-LCD', 'LCD TEST', NULL, 50000, 10000, NULL, NULL, 0, 'fisik', 3, 0)");
        $db->query("INSERT INTO barang VALUES (102, 'TEST-JASA', 'Jasa Pasang TEST', NULL, 50000, 0, NULL, NULL, 0, 'jasa', 3, 0)");

        $model = new ModelKartuStok($db);
        $this->assertSame(2, $model->countKartuStokAll());
        $rows = $model->getKartuStokDT(10, 0, '', 'barang.kode_barang', 'ASC', '2');
        $this->assertCount(1, $rows);
        $this->assertSame(101, (int) $rows[0]->idbarang);
        $this->assertSame(2, (int) $rows[0]->id_unit);
        $this->assertSame(0, (int) $rows[0]->stok_akhir);
    }
}
