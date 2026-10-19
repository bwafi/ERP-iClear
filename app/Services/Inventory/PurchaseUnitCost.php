<?php

namespace App\Services\Inventory;

final class PurchaseUnitCost
{
    public static function fromNetAmount(int $amount, int $quantity): int
    {
        if ($quantity <= 0 || $amount < 0) {
            throw new \InvalidArgumentException('Jumlah dan nilai pembelian tidak valid.');
        }

        return (int) round($amount / $quantity, 0, PHP_ROUND_HALF_UP);
    }
}
