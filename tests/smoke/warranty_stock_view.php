<?php

namespace CodeIgniter\Database {
    class Migration
    {
    }
}

namespace {
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000500_LinkDamagedStockToSupplierReturn.php';
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000700_CreateWarrantyStockIssues.php';
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000800_LinkWarrantyDamagedStock.php';

    $database = new \PDO('sqlite::memory:');
    $database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $database->exec('CREATE TABLE barang (
        idbarang INTEGER, kode_barang TEXT, nama_barang TEXT, harga INTEGER,
        harga_beli INTEGER, idkategori INTEGER, imei TEXT, jenis_hp TEXT,
        internal TEXT, warna TEXT, status TEXT, status_ppn TEXT,
        status_barang TEXT, stok_minimum INTEGER, deleted TEXT, input TEXT,
        nama_barang_id TEXT
    )');
    $database->exec('CREATE TABLE unit (idunit INTEGER, NAMA_UNIT TEXT)');
    $database->exec('CREATE TABLE detail_pembelian (barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER)');
    $database->exec('CREATE TABLE stok_awal (barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER)');
    $database->exec('CREATE TABLE detail_penjualan (barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER)');
    $database->exec('CREATE TABLE detail_mutasi (barang_idbarang INTEGER, kirim_idunit INTEGER, terima_idunit INTEGER, jumlah_kirim INTEGER, jumlah_terima INTEGER)');
    $database->exec('CREATE TABLE retur_pelanggan (barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER)');
    $database->exec('CREATE TABLE retur_suplier (barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, barang_rusak_id INTEGER)');
    $database->exec('CREATE TABLE barang_rusak (idbarang_rusak INTEGER, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, claim_id INTEGER)');
    $database->exec('CREATE TABLE stok_opname (barang_idbarang INTEGER, unit_idunit INTEGER, is_reverted INTEGER)');
    $database->exec('CREATE TABLE garansi_sparepart_keluar (claim_id INTEGER, service_id INTEGER, barang_id INTEGER, unit_id INTEGER, jumlah INTEGER, hpp_satuan INTEGER, UNIQUE(claim_id, barang_id))');
    $database->exec("INSERT INTO barang (idbarang, kode_barang, nama_barang, deleted) VALUES (1, 'TEST', 'Sintetis', '0')");
    $database->exec("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'TEST')");
    $database->exec('INSERT INTO detail_pembelian VALUES (1, 1, 10)');
    $database->exec('INSERT INTO detail_penjualan VALUES (1, 1, 1)');
    $database->exec('INSERT INTO barang_rusak VALUES (100, 1, 1, 1, NULL)');
    $database->exec('INSERT INTO retur_suplier VALUES (1, 1, 1, 100)');

    $migration = new \App\Database\Migrations\LinkWarrantyDamagedStock();
    $method = new \ReflectionMethod($migration, 'warrantyDamagedStockView');
    $sql = $method->invoke($migration);
    $database->exec(str_replace('CREATE OR REPLACE VIEW', 'CREATE VIEW', $sql));

    $stock = static function () use ($database): int {
        return (int) $database->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 1 AND id_unit = 1')->fetchColumn();
    };
    if ($stock() !== 8) {
        throw new \RuntimeException('Stok awal, service, dan barang rusak tidak sesuai.');
    }
    $database->exec('INSERT INTO garansi_sparepart_keluar VALUES (7, 12, 1, 1, 2, 230000)');
    if ($stock() !== 6) {
        throw new \RuntimeException('Pengganti garansi harus mengurangi stok dua unit tepat sekali.');
    }
    $database->exec('INSERT INTO barang_rusak VALUES (101, 1, 1, 1, 7)');
    if ($stock() !== 6) {
        throw new \RuntimeException('Barang rusak dari customer tidak boleh mengurangi stok jual lagi.');
    }
    $database->exec('INSERT INTO retur_suplier VALUES (1, 1, 1, 101)');
    if ($stock() !== 6) {
        throw new \RuntimeException('Retur barang rusak garansi tidak boleh mengurangi stok jual lagi.');
    }
    try {
        $database->exec('INSERT INTO garansi_sparepart_keluar VALUES (7, 12, 1, 1, 2, 230000)');
        throw new \RuntimeException('Duplikasi mutasi garansi tidak ditolak.');
    } catch (\PDOException $error) {
        if ($stock() !== 6) {
            throw new \RuntimeException('Duplikasi garansi mengubah stok.');
        }
    }
    $database->exec('INSERT INTO garansi_sparepart_keluar VALUES (8, 12, 1, 1, 1, 250000)');
    if ($stock() !== 5) {
        throw new \RuntimeException('Klaim kedua tidak mengurangi stok satu unit tambahan.');
    }
    $hpp = (int) $database->query('SELECT SUM(jumlah * hpp_satuan) FROM garansi_sparepart_keluar WHERE service_id = 12')->fetchColumn();
    if ($hpp !== 710000) {
        throw new \RuntimeException('HPP dua klaim berbeda tidak tersimpan terpisah.');
    }

    echo "Garansi: mutasi stok, retur terkait, dan deduplikasi sintetis lulus.\n";
}
