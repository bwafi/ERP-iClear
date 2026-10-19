<?php

use App\Services\Inventory\ProductTypeReview;

require __DIR__ . '/../../app/Services/Inventory/ProductTypeReview.php';

$base = ['jenis_item' => null, 'stok' => 0, 'dibeli' => 0, 'dipakai_service' => 0, 'terjual' => 0];
$cases = [
    [['nama_barang' => 'LCD iPhone', 'dibeli' => 2], 'fisik', false],
    [['nama_barang' => 'JASA Pasang', 'dibeli' => 1], 'perlu review', true],
    [['nama_barang' => 'Service Mesin', 'stok' => 0], 'jasa', true],
    [['nama_barang' => 'LCD iPhone', 'terjual' => 1], 'perlu review', true],
    [['nama_barang' => 'PUP', 'jenis_item' => 'fisik'], 'fisik', false],
];
foreach ($cases as [$input, $expectedType, $expectedAmbiguous]) {
    [$type, , $ambiguous] = ProductTypeReview::suggest(array_merge($base, $input));
    if ($type !== $expectedType || $ambiguous !== $expectedAmbiguous) {
        throw new RuntimeException('Usulan tipe tidak sesuai bukti untuk ' . $input['nama_barang']);
    }
}
echo "product type review: OK\n";
