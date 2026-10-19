<?php

use App\Services\Finance\ServiceCashRecognition;

require __DIR__ . '/../../app/Services/Finance/ServiceCashRecognition.php';

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->exec('CREATE TABLE service (idservice INTEGER PRIMARY KEY, status_service INTEGER, tanggal_selesai TEXT, bayar_tunai INTEGER)');
$database->exec('CREATE TABLE jurnal (tabel_referensi TEXT, id_referensi INTEGER, tanggal TEXT)');
$database->exec("INSERT INTO service VALUES (1, 2, NULL, 50000), (2, 4, '2026-10-19 11:00:00', 30000), (3, 1, NULL, 90000), (4, 90, NULL, 20000)");
$database->exec("INSERT INTO jurnal VALUES ('service', 1, '2026-10-18 10:00:00'), ('service', 1, '2026-10-18 10:00:00'), ('service', 4, '2026-10-17 09:00:00')");

$query = 'SELECT service.idservice, ' . ServiceCashRecognition::dateExpression() . ' AS recognized_at '
    . 'FROM service WHERE ' . ServiceCashRecognition::recognizedExpression() . ' ORDER BY service.idservice';
$rows = $database->query($query)->fetchAll(PDO::FETCH_ASSOC);

if ($rows !== [
    ['idservice' => 1, 'recognized_at' => '2026-10-18 10:00:00'],
    ['idservice' => 2, 'recognized_at' => '2026-10-19 11:00:00'],
]) {
    throw new RuntimeException('Service klaim tidak boleh hilang atau dihitung dua kali.');
}

echo "service cash claim: OK\n";
