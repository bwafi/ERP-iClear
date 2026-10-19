<?php

use App\Services\Inventory\ProductInventoryScope;

$database = new PDO('sqlite::memory:');
require __DIR__ . '/../../app/Services/Inventory/ProductInventoryScope.php';
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE barang (idbarang INTEGER PRIMARY KEY, nama_barang TEXT, harga_beli INTEGER)');
$database->exec('CREATE TABLE hpp_barang (idbarang INTEGER PRIMARY KEY, hpp INTEGER)');
$database->exec('CREATE TABLE stok_barang (idbarang INTEGER, stok_akhir INTEGER)');
$database->exec("INSERT INTO barang VALUES (1, 'LCD iPhone 11', 300000), (2, 'Sparepart tanpa HPP', 100000),
    (3, 'JASA Pasang', 0), (4, 'PUP Service', 0), (5, 'MESIN Teknisi', 0),
    (6, 'RESTORE iPhone', 0), (7, 'KOMISI Mesin', 0)");
$database->exec('INSERT INTO hpp_barang VALUES (1, 230000), (3, 999999), (4, 999999), (5, 999999), (6, 999999), (7, 999999)');
$database->exec('INSERT INTO stok_barang VALUES (1, 2), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1)');

$summary = $database->query('SELECT COALESCE(SUM(stok_barang.stok_akhir * hpp_barang.hpp), 0) AS nilai_stok,
    SUM(CASE WHEN stok_barang.stok_akhir > 0 AND hpp_barang.hpp IS NULL THEN 1 ELSE 0 END) AS jumlah_tanpa_hpp
    FROM stok_barang JOIN barang ON barang.idbarang = stok_barang.idbarang
    LEFT JOIN hpp_barang ON hpp_barang.idbarang = stok_barang.idbarang
    WHERE ' . ProductInventoryScope::sqlPredicate('barang.nama_barang'))->fetch(PDO::FETCH_ASSOC);

if ((int) $summary['nilai_stok'] !== 460000 || (int) $summary['jumlah_tanpa_hpp'] !== 1) {
    throw new RuntimeException('Nilai stok harus memakai HPP yang sama dengan service dan menandai HPP hilang.');
}
if (ProductInventoryScope::isPhysicalProduct('Kuras Mesin HP') !== true
    || ProductInventoryScope::isPhysicalProduct(' jasa pasang') !== false
    || ProductInventoryScope::isPhysicalProduct('Komisi Mesin') !== false
    || ProductInventoryScope::isPhysicalProduct('JASA Pasang', 'fisik') !== true
    || ProductInventoryScope::isPhysicalProduct('LCD iPhone', 'jasa') !== false) {
    throw new RuntimeException('Klasifikasi barang fisik dan jasa tidak konsisten.');
}

$database->exec('ALTER TABLE barang ADD COLUMN jenis_item TEXT');
$database->exec("UPDATE barang SET jenis_item = 'jasa' WHERE idbarang = 1");
$database->exec("UPDATE barang SET jenis_item = 'fisik' WHERE idbarang = 3");
$explicitIds = $database->query('SELECT barang.idbarang FROM barang WHERE '
    . ProductInventoryScope::sqlPredicate('barang.nama_barang', true, 'barang.jenis_item')
    . ' ORDER BY barang.idbarang')->fetchAll(PDO::FETCH_COLUMN);
if ($explicitIds !== [2, 3]) {
    throw new RuntimeException('Jenis item eksplisit harus mengalahkan awalan nama legacy.');
}

echo "Kartu Stok: valuasi HPP dan penanda harga hilang sintetis lulus.\n";
