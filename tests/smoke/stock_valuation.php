<?php

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE barang (idbarang INTEGER PRIMARY KEY, harga_beli INTEGER)');
$database->exec('CREATE TABLE hpp_barang (idbarang INTEGER PRIMARY KEY, hpp INTEGER)');
$database->exec('CREATE TABLE stok_barang (idbarang INTEGER, stok_akhir INTEGER)');
$database->exec('INSERT INTO barang VALUES (1, 300000), (2, 100000)');
$database->exec('INSERT INTO hpp_barang VALUES (1, 230000)');
$database->exec('INSERT INTO stok_barang VALUES (1, 2), (2, 1)');

$summary = $database->query('SELECT COALESCE(SUM(stok_barang.stok_akhir * hpp_barang.hpp), 0) AS nilai_stok,
    SUM(CASE WHEN stok_barang.stok_akhir > 0 AND hpp_barang.hpp IS NULL THEN 1 ELSE 0 END) AS jumlah_tanpa_hpp
    FROM stok_barang JOIN barang ON barang.idbarang = stok_barang.idbarang
    LEFT JOIN hpp_barang ON hpp_barang.idbarang = stok_barang.idbarang')->fetch(PDO::FETCH_ASSOC);

if ((int) $summary['nilai_stok'] !== 460000 || (int) $summary['jumlah_tanpa_hpp'] !== 1) {
    throw new RuntimeException('Nilai stok harus memakai HPP yang sama dengan service dan menandai HPP hilang.');
}

echo "Kartu Stok: valuasi HPP dan penanda harga hilang sintetis lulus.\n";
