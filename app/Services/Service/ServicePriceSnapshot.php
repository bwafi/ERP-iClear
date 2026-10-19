<?php

namespace App\Services\Service;

final class ServicePriceSnapshot
{
    public static function calculate(?int $historicalPrice, int $listPrice, int $quantity, int $discount): array
    {
        $price = $historicalPrice ?? $listPrice;
        if ($price < 0 || $quantity <= 0 || $discount < 0 || $discount > $price * $quantity) {
            throw new \InvalidArgumentException('Harga PL, jumlah, atau diskon sparepart tidak valid.');
        }

        return ['price' => $price, 'subtotal' => $price * $quantity - $discount];
    }
}
