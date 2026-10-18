<?php

namespace App\Services\Service;

final class ServiceHppSnapshot
{
    public static function resolve(?object $existingItem, $currentHpp)
    {
        if ($existingItem !== null && property_exists($existingItem, 'hpp_penjualan')) {
            return $existingItem->hpp_penjualan;
        }

        return $currentHpp;
    }

    public static function resolveWarrantyReplacement(?object $existingItem, int $quantity, $currentHpp)
    {
        $previousQuantity = (int) ($existingItem->jumlah_tambahan_garansi ?? 0);
        if ($previousQuantity > 0) {
            if ($quantity !== $previousQuantity) {
                throw new \InvalidArgumentException('Jumlah pengganti lama tidak boleh diubah karena HPP historisnya sudah tercatat.');
            }

            return $existingItem->hpp_penjualan_garansi ?? null;
        }

        return $quantity > 0 ? $currentHpp : 0;
    }
}
