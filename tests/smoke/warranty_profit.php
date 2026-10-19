<?php

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE service (idservice INTEGER, status_service INTEGER, harus_dibayar INTEGER)');
$database->exec('CREATE TABLE riwayat_claim_garansi (service_idservice INTEGER)');
$database->exec('CREATE TABLE penjualan (idpenjualan INTEGER, service_idservice INTEGER, kode_invoice TEXT, total_penjualan INTEGER)');
$database->exec('CREATE TABLE detail_penjualan (penjualan_idpenjualan INTEGER, jumlah INTEGER, hpp_penjualan INTEGER)');
$database->exec('CREATE TABLE service_sparepart (service_idservice INTEGER, jumlah INTEGER, hpp_penjualan INTEGER)');
$database->exec('CREATE TABLE garansi_sparepart_keluar (jumlah INTEGER, hpp_satuan INTEGER)');
$database->exec('INSERT INTO service VALUES (1, 2, 750000)');
$database->exec('INSERT INTO riwayat_claim_garansi VALUES (1)');
$database->exec("INSERT INTO penjualan VALUES (1, 1, 'SRV00000000000001', 500000), (2, NULL, 'POS1', 100000)");
$database->exec('INSERT INTO detail_penjualan VALUES (1, 1, 219120), (2, 1, 40000)');
$database->exec('INSERT INTO service_sparepart VALUES (1, 1, 219120)');
$database->exec('INSERT INTO garansi_sparepart_keluar VALUES (1, 230000)');

$serviceRevenue = (int) $database->query('SELECT SUM(harus_dibayar) FROM service
    WHERE status_service = 4 OR (status_service = 2 AND EXISTS
    (SELECT 1 FROM riwayat_claim_garansi klaim WHERE klaim.service_idservice = service.idservice))')->fetchColumn();
$retailRevenue = (int) $database->query("SELECT SUM(total_penjualan) FROM penjualan
    WHERE service_idservice IS NULL AND kode_invoice NOT LIKE 'SRV%'")->fetchColumn();
$retailHpp = (int) $database->query("SELECT SUM(detail_penjualan.hpp_penjualan * detail_penjualan.jumlah)
    FROM detail_penjualan JOIN penjualan ON penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan
    WHERE penjualan.service_idservice IS NULL AND penjualan.kode_invoice NOT LIKE 'SRV%'")->fetchColumn();
$serviceHpp = (int) $database->query('SELECT SUM(hpp_penjualan * jumlah) FROM service_sparepart')->fetchColumn();
$warrantyHpp = (int) $database->query('SELECT SUM(hpp_satuan * jumlah) FROM garansi_sparepart_keluar')->fetchColumn();

if ($serviceRevenue + $retailRevenue !== 850000
    || $retailHpp + $serviceHpp + $warrantyHpp !== 489120) {
    throw new RuntimeException('Pendapatan atau HPP service/garansi dihitung ganda.');
}

echo "Laba Rugi: pendapatan awal dan HPP pengganti sintetis terhitung sekali.\n";
