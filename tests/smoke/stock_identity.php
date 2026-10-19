<?php

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE barang (idbarang INTEGER PRIMARY KEY, kode_barang TEXT, harga_beli INTEGER)');
$database->exec('CREATE TABLE stok_barang (idbarang INTEGER, kode_barang TEXT, stok_akhir INTEGER)');
$database->exec("INSERT INTO barang VALUES (1, 'LCD', 200000), (2, 'LCD', 300000)");
$database->exec("INSERT INTO stok_barang VALUES (1, 'LCD', 2), (2, 'LCD', 3)");

$row = $database->query('SELECT COUNT(*) AS total_baris, SUM(stok_barang.stok_akhir * barang.harga_beli) AS nilai_stok
    FROM stok_barang JOIN barang ON barang.idbarang = stok_barang.idbarang')->fetch(PDO::FETCH_ASSOC);
if ((int) $row['total_baris'] !== 2 || (int) $row['nilai_stok'] !== 1300000) {
    throw new RuntimeException('Kartu Stok menggandakan barang atau nilai stok.');
}

echo "Kartu Stok: identitas barang dan ringkasan sintetis lulus.\n";
