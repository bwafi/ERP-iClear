<?php

namespace CodeIgniter\Database {
    class Migration
    {
    }
}

namespace {
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000500_LinkDamagedStockToSupplierReturn.php';

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
    $database->exec('CREATE TABLE barang_rusak (barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER)');
    $database->exec('CREATE TABLE stok_opname (barang_idbarang INTEGER, unit_idunit INTEGER, is_reverted INTEGER)');
    $database->exec("INSERT INTO barang (idbarang, kode_barang, nama_barang, deleted) VALUES (1, 'TEST', 'Sintetis', '0')");
    $database->exec("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'TEST')");
    $database->exec('INSERT INTO detail_pembelian VALUES (1, 1, 10)');
    $database->exec('INSERT INTO barang_rusak VALUES (1, 1, 2)');

    $migration = new \App\Database\Migrations\LinkDamagedStockToSupplierReturn();
    $method = new \ReflectionMethod($migration, 'linkedStockView');
    $sql = $method->invoke($migration);
    $database->exec(str_replace('CREATE OR REPLACE VIEW', 'CREATE VIEW', $sql));

    $stock = static function () use ($database): int {
        return (int) $database->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 1 AND id_unit = 1')->fetchColumn();
    };

    if ($stock() !== 8) {
        throw new \RuntimeException('Barang rusak harus mengurangi stok satu kali.');
    }

    $database->exec('INSERT INTO retur_suplier VALUES (1, 1, 1, 100)');
    if ($stock() !== 8) {
        throw new \RuntimeException('Retur terkait barang rusak tidak boleh mengurangi stok kedua kalinya.');
    }

    $database->exec('INSERT INTO retur_suplier VALUES (1, 1, 1, NULL)');
    if ($stock() !== 7) {
        throw new \RuntimeException('Retur lama yang tidak terkait tetap mengurangi stok.');
    }

    echo "Kartu Stok: 3 skenario sintetis lulus.\n";
}
