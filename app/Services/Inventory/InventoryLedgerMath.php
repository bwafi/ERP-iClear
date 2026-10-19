<?php

namespace App\Services\Inventory;

final class InventoryLedgerMath
{
    public static function incoming(int $balanceQuantity, int $balanceValue, int $quantity, int $unitCost): array
    {
        self::validateBalance($balanceQuantity, $balanceValue);
        if ($quantity <= 0 || $unitCost < 0 || ($quantity !== 0 && $unitCost > intdiv(PHP_INT_MAX, $quantity))) {
            throw new \InvalidArgumentException('Jumlah atau biaya masuk tidak valid.');
        }
        $value = $quantity * $unitCost;
        return self::apply($balanceQuantity, $balanceValue, $quantity, $value);
    }

    public static function incomingValue(int $balanceQuantity, int $balanceValue, int $quantity, int $carriedValue): array
    {
        self::validateBalance($balanceQuantity, $balanceValue);
        if ($quantity <= 0 || $carriedValue < 0) {
            throw new \InvalidArgumentException('Jumlah atau nilai transfer tidak valid.');
        }
        return self::apply($balanceQuantity, $balanceValue, $quantity, $carriedValue);
    }

    public static function outgoing(int $balanceQuantity, int $balanceValue, int $quantity): array
    {
        self::validateBalance($balanceQuantity, $balanceValue);
        if ($quantity <= 0 || $quantity > $balanceQuantity) {
            throw new \InvalidArgumentException('Stok tidak mencukupi.');
        }
        if ($quantity === $balanceQuantity) {
            return self::apply($balanceQuantity, $balanceValue, -$quantity, -$balanceValue);
        }
        $whole = intdiv($balanceValue, $balanceQuantity);
        $remainder = $balanceValue % $balanceQuantity;
        if ($remainder > intdiv(PHP_INT_MAX - intdiv($balanceQuantity, 2), $quantity)) {
            throw new \OverflowException('Perhitungan HPP melebihi batas integer.');
        }
        $value = $whole * $quantity + intdiv($remainder * $quantity + intdiv($balanceQuantity, 2), $balanceQuantity);
        return self::apply($balanceQuantity, $balanceValue, -$quantity, -$value);
    }

    public static function reverse(int $balanceQuantity, int $balanceValue, int $originalQuantity, int $originalValue): array
    {
        self::validateBalance($balanceQuantity, $balanceValue);
        if ($originalQuantity === 0 || $originalValue === PHP_INT_MIN) {
            throw new \InvalidArgumentException('Entri asal tidak valid.');
        }
        return self::apply($balanceQuantity, $balanceValue, -$originalQuantity, -$originalValue);
    }

    private static function apply(int $balanceQuantity, int $balanceValue, int $quantity, int $value): array
    {
        if ($quantity > 0 && $balanceQuantity > PHP_INT_MAX - $quantity
            || $value > 0 && $balanceValue > PHP_INT_MAX - $value) {
            throw new \OverflowException('Saldo ledger melebihi batas integer.');
        }
        $nextQuantity = $balanceQuantity + $quantity;
        $nextValue = $balanceValue + $value;
        if ($nextQuantity < 0 || $nextValue < 0 || ($nextQuantity === 0 && $nextValue !== 0)) {
            throw new \InvalidArgumentException('Pembalikan menghasilkan saldo stok atau nilai tidak valid.');
        }
        return ['quantity' => $quantity, 'value' => $value,
            'balance_quantity' => $nextQuantity, 'balance_value' => $nextValue];
    }

    private static function validateBalance(int $quantity, int $value): void
    {
        if ($quantity < 0 || $value < 0 || ($quantity === 0 && $value !== 0)) {
            throw new \InvalidArgumentException('Saldo awal ledger tidak valid.');
        }
    }
}
