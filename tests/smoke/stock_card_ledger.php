<?php

use App\Services\Inventory\StockCardLedger;

require __DIR__ . '/../../app/Services/Inventory/StockCardLedger.php';

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
foreach ([
    'stok_awal (idstok_awal INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, harga_beli INTEGER, input_by INTEGER)',
    'detail_pembelian (iddetail_pembelian INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, hitung_hpp INTEGER, pembelian_idpembelian INTEGER)',
    'pembelian (idpembelian INTEGER, input_by INTEGER)',
    'detail_mutasi (iddetail_mutasi INTEGER, tanggal_terima TEXT, tanggal_kirim TEXT, barang_idbarang INTEGER, terima_idunit INTEGER, kirim_idunit INTEGER, jumlah_terima INTEGER, jumlah_kirim INTEGER, hpp_barang INTEGER, mutasi_idmutasi INTEGER)',
    'mutasi (idmutasi INTEGER, input_by INTEGER)',
    'retur_pelanggan (idretur_pelanggan INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, input_by INTEGER)',
    'penjualan (idpenjualan INTEGER, tanggal TEXT, input_by INTEGER)',
    'detail_penjualan (iddetail_penjualan INTEGER, penjualan_idpenjualan INTEGER, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, hpp_penjualan INTEGER)',
    'barang_rusak (idbarang_rusak INTEGER, tanggal_rusak TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, claim_id INTEGER, input_by INTEGER)',
    'retur_suplier (idretur_suplier INTEGER, tanggal TEXT, barang_idbarang INTEGER, unit_idunit INTEGER, jumlah INTEGER, barang_rusak_id INTEGER, input_by INTEGER)',
    'garansi_sparepart_keluar (id INTEGER, created_at TEXT, barang_id INTEGER, unit_id INTEGER, jumlah INTEGER, hpp_satuan INTEGER, input_by INTEGER)',
] as $table) {
    $database->exec('CREATE TABLE ' . $table);
}
$database->exec("INSERT INTO pembelian VALUES (1, 2)");
$database->exec("INSERT INTO detail_pembelian VALUES (1, '2026-10-01', 7, 2, 3, 10000, 1)");
$database->exec("INSERT INTO detail_pembelian VALUES (2, '2026-10-02', 7, 2, 2, 20000, 1)");
$database->exec("INSERT INTO penjualan VALUES (1, '2026-10-02', 3)");
$database->exec('INSERT INTO detail_penjualan VALUES (1, 1, 7, 2, 1, 10000)');
$database->exec("INSERT INTO garansi_sparepart_keluar VALUES (1, '2026-10-03', 7, 2, 1, 11000, 6)");
$database->exec("INSERT INTO barang_rusak VALUES (1, '2026-10-03', 7, 2, 1, 9, 4)");
$database->exec("INSERT INTO retur_suplier VALUES (1, '2026-10-04', 7, 2, 1, 1, 5)");
$statement = $database->prepare(StockCardLedger::sql());
$statement->execute(array_merge(...array_fill(0, 9, [7, 2])));
$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
$balances = array_map(static fn (array $row): int => (int) $row['saldo'], $rows);
if (count($rows) !== 6 || $balances[0] !== 3 || $balances[1] !== 5 || end($balances) !== 3
    || (int) $rows[0]['hpp_satuan'] !== 10000 || (int) $rows[1]['hpp_satuan'] !== 20000
    || array_sum(array_column($rows, 'keluar')) !== 2) {
    throw new RuntimeException('Mutasi garansi, barang rusak, dan retur tidak boleh mengurangi stok dua kali.');
}
echo "stock card ledger: OK\n";
