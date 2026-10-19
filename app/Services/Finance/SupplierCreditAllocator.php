<?php

namespace App\Services\Finance;

final class SupplierCreditAllocator
{
    public static function split(int $credit, int $debt): array
    {
        if ($credit <= 0 || $debt < 0) {
            throw new \InvalidArgumentException('Nominal kredit atau utang tidak valid.');
        }
        $applied = min($credit, $debt);
        return ['applied' => $applied, 'remaining' => $credit - $applied];
    }

    public static function plan(array $balances, int $amount): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal kredit harus positif.');
        }

        $remaining = $amount;
        $allocations = [];
        foreach ($balances as $purchaseId => $balance) {
            if ($purchaseId <= 0 || $balance < 0) {
                throw new \InvalidArgumentException('Saldo pembelian tidak valid.');
            }
            $allocated = min($remaining, $balance);
            if ($allocated > 0) {
                $allocations[$purchaseId] = $allocated;
                $remaining -= $allocated;
            }
            if ($remaining === 0) {
                break;
            }
        }
        if ($remaining !== 0) {
            throw new \RuntimeException('Kredit melebihi total utang supplier.');
        }
        return $allocations;
    }
}
