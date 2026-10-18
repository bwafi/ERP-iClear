<?php

namespace App\Services\Inventory;

final class ProductInventoryScope
{
    private const NON_PRODUCT_PREFIXES = ['PUP', 'JASA', 'MESIN', 'RESTORE', 'KOMISI'];

    public static function isPhysicalProduct(string $name, ?string $itemType = null): bool
    {
        if ($itemType === 'fisik') {
            return true;
        }
        if ($itemType === 'jasa') {
            return false;
        }
        $normalized = strtoupper(trim($name));
        foreach (self::NON_PRODUCT_PREFIXES as $prefix) {
            if (strpos($normalized, $prefix) === 0) {
                return false;
            }
        }
        return true;
    }

    public static function sqlPredicate(string $column, bool $physicalProduct = true, ?string $typeColumn = null): string
    {
        $conditions = [];
        foreach (self::NON_PRODUCT_PREFIXES as $prefix) {
            $conditions[] = "UPPER(TRIM(COALESCE({$column}, ''))) "
                . ($physicalProduct ? 'NOT LIKE' : 'LIKE') . " '{$prefix}%'";
        }
        $legacyPredicate = '(' . implode($physicalProduct ? ' AND ' : ' OR ', $conditions) . ')';
        if ($typeColumn === null) {
            return $legacyPredicate;
        }
        $explicit = $physicalProduct ? 'fisik' : 'jasa';
        return "({$typeColumn} = '{$explicit}' OR ({$typeColumn} IS NULL AND {$legacyPredicate}))";
    }
}
