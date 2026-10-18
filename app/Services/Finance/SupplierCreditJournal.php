<?php

namespace App\Services\Finance;

final class SupplierCreditJournal
{
    public static function validAccountClasses(string $payable, string $receivable, string $recovery): bool
    {
        return preg_match('/^2[0-9]{9}$/D', $payable) === 1
            && preg_match('/^1[0-9]{9}$/D', $receivable) === 1
            && preg_match('/^[4567][0-9]{9}$/D', $recovery) === 1
            && count(array_unique([$payable, $receivable, $recovery])) === 3;
    }

    public static function approvalLines(int $amount, int $applied, string $payable, string $receivable, string $recovery): array
    {
        if ($amount <= 0 || $applied < 0 || $applied > $amount
            || count(array_unique([$payable, $receivable, $recovery])) !== 3) {
            throw new \InvalidArgumentException('Nilai atau akun jurnal kredit tidak valid.');
        }
        $lines = [[$receivable, $amount, 0], [$recovery, 0, $amount]];
        if ($applied > 0) {
            array_push($lines, [$payable, $applied, 0], [$receivable, 0, $applied]);
        }
        return $lines;
    }

    public static function applicationLines(int $amount, string $payable, string $receivable): array
    {
        if ($amount <= 0 || $payable === $receivable) {
            throw new \InvalidArgumentException('Nilai atau akun pemakaian kredit tidak valid.');
        }
        return [[$payable, $amount, 0], [$receivable, 0, $amount]];
    }
}
