<?php

require __DIR__ . '/../../app/Services/Service/ServicePriceSnapshot.php';

use App\Services\Service\ServicePriceSnapshot;

$newService = ServicePriceSnapshot::calculate(null, 750000, 1, 50000);
$historicalService = ServicePriceSnapshot::calculate(750000, 900000, 1, 0);
if ($newService !== ['price' => 750000, 'subtotal' => 700000]
    || $historicalService !== ['price' => 750000, 'subtotal' => 750000]) {
    throw new RuntimeException('Harga PL baru atau snapshot historis tidak konsisten.');
}

try {
    ServicePriceSnapshot::calculate(null, 100000, 1, 120000);
    throw new RuntimeException('Diskon melebihi harga seharusnya ditolak.');
} catch (InvalidArgumentException $expected) {
}

echo "Snapshot harga service sintetis lulus.\n";
