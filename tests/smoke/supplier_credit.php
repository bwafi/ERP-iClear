<?php

require __DIR__ . '/../../app/Services/Finance/SupplierCreditAllocator.php';

use App\Services\Finance\SupplierCreditAllocator;

$expected = [11 => 500000, 12 => 200000];
$actual = SupplierCreditAllocator::plan([11 => 500000, 12 => 300000], 700000);
if ($actual !== $expected) {
    throw new RuntimeException('Kredit harus mengurangi total utang lintas faktur secara berurutan.');
}

if (SupplierCreditAllocator::plan([11 => 500000, 12 => 300000], 200000) !== [11 => 200000]) {
    throw new RuntimeException('Kredit sebagian tidak boleh memotong faktur kedua.');
}

if (SupplierCreditAllocator::split(500000, 300000) !== ['applied' => 300000, 'remaining' => 200000]) {
    throw new RuntimeException('Sisa kredit supplier harus dibawa ke tagihan berikutnya.');
}

try {
    SupplierCreditAllocator::plan([11 => 500000], 600000);
    throw new RuntimeException('Kredit melebihi utang tidak ditolak.');
} catch (RuntimeException $error) {
    if ($error->getMessage() === 'Kredit melebihi utang tidak ditolak.') {
        throw $error;
    }
}

echo "Kredit Supplier: 4 skenario sintetis lulus.\n";
