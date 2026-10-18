<?php

namespace CodeIgniter\Database {
    class Migration
    {
    }
}

namespace {
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000500_LinkDamagedStockToSupplierReturn.php';
    require __DIR__ . '/../../app/Database/Migrations/2026-10-18-000700_CreateWarrantyStockIssues.php';

    $databaseName = 'codex_erp_warranty_' . bin2hex(random_bytes(4));
    $connection = new \PDO(
        'mysql:host=localhost;charset=utf8mb4',
        getenv('ERP_SYNTHETIC_MYSQL_USER') ?: 'root',
        getenv('ERP_SYNTHETIC_MYSQL_PASSWORD') ?: ''
    );
    $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $connection->exec("CREATE DATABASE `{$databaseName}`");

    try {
        $connection->exec("USE `{$databaseName}`");
        $connection->exec('CREATE TABLE barang (
            idbarang INT PRIMARY KEY, kode_barang VARCHAR(40), nama_barang VARCHAR(100), harga BIGINT,
            harga_beli BIGINT, idkategori INT, imei VARCHAR(30), jenis_hp VARCHAR(40),
            internal VARCHAR(40), warna VARCHAR(40), status VARCHAR(20), status_ppn VARCHAR(2),
            status_barang VARCHAR(20), stok_minimum INT, deleted VARCHAR(2), input VARCHAR(20),
            nama_barang_id VARCHAR(100)
        )');
        $connection->exec('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(50))');
        $connection->exec('CREATE TABLE detail_pembelian (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE stok_awal (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE detail_penjualan (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE detail_mutasi (barang_idbarang INT, kirim_idunit INT, terima_idunit INT, jumlah_kirim INT, jumlah_terima INT)');
        $connection->exec('CREATE TABLE retur_pelanggan (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE retur_suplier (barang_idbarang INT, unit_idunit INT, jumlah INT, barang_rusak_id INT)');
        $connection->exec('CREATE TABLE barang_rusak (barang_idbarang INT, unit_idunit INT, jumlah INT)');
        $connection->exec('CREATE TABLE stok_opname (barang_idbarang INT, unit_idunit INT, is_reverted INT)');
        $connection->exec('CREATE TABLE garansi_sparepart_keluar (
            claim_id INT, service_id INT, barang_id INT, unit_id INT, jumlah INT, hpp_satuan BIGINT,
            UNIQUE KEY uniq_garansi_claim_barang (claim_id, barang_id)
        )');
        $connection->exec("INSERT INTO barang (idbarang, kode_barang, nama_barang, deleted) VALUES (1, 'TEST', 'Sintetis', '0')");
        $connection->exec("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'TEST')");
        $connection->exec('INSERT INTO detail_pembelian VALUES (1, 1, 10)');
        $connection->exec('INSERT INTO detail_penjualan VALUES (1, 1, 1)');
        $connection->exec('INSERT INTO barang_rusak VALUES (1, 1, 1)');
        $connection->exec('INSERT INTO retur_suplier VALUES (1, 1, 1, 100)');

        $migration = new \App\Database\Migrations\CreateWarrantyStockIssues();
        $viewMethod = new \ReflectionMethod($migration, 'warrantyStockView');
        $connection->exec($viewMethod->invoke($migration));
        $stock = static function () use ($connection): int {
            return (int) $connection->query('SELECT stok_akhir FROM stok_barang WHERE idbarang = 1 AND id_unit = 1')->fetchColumn();
        };
        if ($stock() !== 8) {
            throw new \RuntimeException('Retur terkait barang rusak mengurangi stok dua kali.');
        }
        $connection->exec('INSERT INTO garansi_sparepart_keluar VALUES (7, 12, 1, 1, 2, 230000)');
        if ($stock() !== 6) {
            throw new \RuntimeException('Penggantian garansi tidak mengurangi stok tepat dua unit.');
        }
        try {
            $connection->exec('INSERT INTO garansi_sparepart_keluar VALUES (7, 12, 1, 1, 2, 230000)');
            throw new \RuntimeException('Duplikasi klaim garansi tidak ditolak.');
        } catch (\PDOException $error) {
            if ($stock() !== 6) {
                throw new \RuntimeException('Duplikasi klaim mengubah saldo stok.');
            }
        }
        echo "MySQL sintetis: mutasi garansi dan retur terkait lulus.\n";
    } finally {
        $connection->exec("DROP DATABASE `{$databaseName}`");
    }
}
