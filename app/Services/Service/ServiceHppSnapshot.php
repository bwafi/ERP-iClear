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
}
