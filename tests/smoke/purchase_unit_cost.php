<?php

use App\Services\Inventory\PurchaseUnitCost;

require __DIR__ . '/../../app/Services/Inventory/PurchaseUnitCost.php';

if (PurchaseUnitCost::fromNetAmount(30000, 3) !== 10000
    || PurchaseUnitCost::fromNetAmount(40001, 2) !== 20001
    || PurchaseUnitCost::fromNetAmount(0, 1) !== 0) {
    throw new RuntimeException('Biaya per unit pembelian tidak sesuai nilai nota.');
}

try {
    PurchaseUnitCost::fromNetAmount(1000, 0);
    throw new RuntimeException('Jumlah nol harus ditolak.');
} catch (InvalidArgumentException $exception) {
}

echo "purchase unit cost: OK\n";
