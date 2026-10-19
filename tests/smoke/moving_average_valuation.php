<?php

use App\Services\Inventory\MovingAverageValuation;

require __DIR__ . '/../../app/Services/Inventory/MovingAverageValuation.php';

$movements = [
    ['masuk' => 3, 'keluar' => 0, 'hpp_satuan' => 10000],
    ['masuk' => 0, 'keluar' => 1, 'hpp_satuan' => 10000],
    ['masuk' => 2, 'keluar' => 0, 'hpp_satuan' => 20000],
    ['masuk' => 0, 'keluar' => 1, 'hpp_satuan' => 15000],
];
$result = MovingAverageValuation::replay($movements);
if (!$result['valid'] || $result['quantity'] !== 3 || $result['value'] !== 45000
    || $result['rows'][1]['saldo_nilai'] !== 20000
    || $result['rows'][2]['hpp_rata_rata'] !== 15000) {
    throw new RuntimeException('Moving average per lokasi tidak sesuai.');
}

$otherUnit = MovingAverageValuation::replay([
    ['masuk' => 2, 'keluar' => 0, 'hpp_satuan' => 30000],
]);
if ($otherUnit['unit_cost'] !== 30000 || $result['rows'][1]['hpp_satuan'] !== 10000) {
    throw new RuntimeException('Biaya cabang lain atau HPP service lama berubah.');
}

$negative = MovingAverageValuation::replay([
    ['masuk' => 0, 'keluar' => 1, 'hpp_satuan' => 10000],
]);
if ($negative['valid']) {
    throw new RuntimeException('Riwayat stok negatif tidak boleh diberi nilai pasti.');
}

$source = MovingAverageValuation::replay([
    ['masuk' => 2, 'keluar' => 0, 'hpp_satuan' => 15000],
    ['masuk' => 0, 'keluar' => 1, 'hpp_satuan' => 15000],
]);
$destination = MovingAverageValuation::replay([
    ['masuk' => 1, 'keluar' => 0, 'hpp_satuan' => 15000],
]);
if ($source['value'] !== 15000 || $destination['value'] !== 15000) {
    throw new RuntimeException('Transfer harus memindahkan biaya antar cabang, bukan menciptakan nilai baru.');
}

echo "moving average valuation: OK\n";
