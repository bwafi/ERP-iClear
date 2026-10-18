<?php

namespace App\Services\Inventory;

final class SupplierReturnQuantity
{
    public static function allows(int $purchased, int $returned, int $requested): bool
    {
        return $purchased > 0
            && $returned >= 0
            && $requested > 0
            && $requested <= $purchased - $returned;
    }
}
