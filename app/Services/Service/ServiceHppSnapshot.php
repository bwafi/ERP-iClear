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

    public static function resolveWarrantyClaimIssue(?object $issue, int $quantity, $currentHpp): int
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException('Jumlah pengganti tidak valid.');
        }
        if ($issue !== null) {
            if ($quantity !== (int) $issue->jumlah || (int) $issue->hpp_satuan <= 0) {
                throw new \InvalidArgumentException('Mutasi klaim yang sudah dicatat tidak boleh diubah.');
            }
            return (int) $issue->hpp_satuan;
        }
        if ($quantity === 0) {
            return 0;
        }
        if ((int) $currentHpp <= 0) {
            throw new \InvalidArgumentException('HPP sparepart pengganti belum tersedia.');
        }
        return (int) $currentHpp;
    }
}
