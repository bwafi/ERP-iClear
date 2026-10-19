<?php

namespace App\Services\Inventory;

final class MovingAverageValuation
{
    public static function replay(array $movements): array
    {
        $quantity = 0;
        $value = 0;
        $rows = [];
        $valid = true;

        foreach ($movements as $movement) {
            $incoming = (int) $movement['masuk'];
            $outgoing = (int) $movement['keluar'];
            $unitCost = isset($movement['hpp_satuan']) ? (int) $movement['hpp_satuan'] : null;
            if ($incoming < 0 || $outgoing < 0 || ($incoming > 0 && $outgoing > 0)) {
                $valid = false;
            }
            if ($incoming > 0) {
                if ($unitCost === null || $unitCost < 0) {
                    $valid = false;
                } else {
                    $value += $incoming * $unitCost;
                }
                $quantity += $incoming;
            }
            if ($outgoing > 0) {
                if ($quantity < $outgoing) {
                    $valid = false;
                } else {
                    $cost = (int) round($value / $quantity, 0, PHP_ROUND_HALF_UP);
                    if ($unitCost !== null && $unitCost !== $cost) {
                        $valid = false;
                    }
                    $movement['hpp_pakai'] = $cost;
                    $value = $quantity === $outgoing ? 0 : $value - $cost * $outgoing;
                }
                $quantity -= $outgoing;
            }
            $movement['saldo_nilai'] = $valid ? $value : null;
            $movement['hpp_rata_rata'] = $valid && $quantity > 0
                ? (int) round($value / $quantity, 0, PHP_ROUND_HALF_UP)
                : null;
            $movement['valuasi_valid'] = $valid;
            $rows[] = $movement;
        }

        return [
            'rows' => $rows,
            'quantity' => $quantity,
            'value' => $valid ? $value : null,
            'unit_cost' => $valid && $quantity > 0
                ? (int) round($value / $quantity, 0, PHP_ROUND_HALF_UP)
                : null,
            'valid' => $valid,
        ];
    }
}
