<?php

require __DIR__ . '/../../app/Services/Finance/SupplierCreditAllocator.php';
require __DIR__ . '/../../app/Services/Finance/SupplierCreditJournal.php';

use App\Services\Finance\SupplierCreditAllocator;
use App\Services\Finance\SupplierCreditJournal;

if (! SupplierCreditJournal::validAccountClasses('2000000001', '1000000001', '4000000001')
    || SupplierCreditJournal::validAccountClasses('1000000001', '2000000001', '4000000001')) {
    throw new RuntimeException('Klasifikasi akun utang/piutang/pemulihan tidak valid.');
}

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

$journal = SupplierCreditJournal::approvalLines(500000, 300000, 'AP', 'AR', 'RECOVERY');
if (array_sum(array_column($journal, 1)) !== array_sum(array_column($journal, 2))) {
    throw new RuntimeException('Jurnal persetujuan kredit harus seimbang.');
}
$journal = SupplierCreditJournal::applicationLines(200000, 'AP', 'AR');
if (array_sum(array_column($journal, 1)) !== array_sum(array_column($journal, 2))) {
    throw new RuntimeException('Jurnal pemakaian sisa kredit harus seimbang.');
}

try {
    SupplierCreditAllocator::plan([11 => 500000], 600000);
    throw new RuntimeException('Kredit melebihi utang tidak ditolak.');
} catch (RuntimeException $error) {
    if ($error->getMessage() === 'Kredit melebihi utang tidak ditolak.') {
        throw $error;
    }
}

echo "Kredit Supplier: 6 skenario sintetis lulus.\n";
