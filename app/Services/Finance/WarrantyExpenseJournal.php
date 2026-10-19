<?php

namespace App\Services\Finance;

final class WarrantyExpenseJournal
{
    public static function lines(array $template, int $amount): array
    {
        if ($amount <= 0 || count($template) !== 2) {
            throw new \InvalidArgumentException('HPP atau template jurnal garansi belum valid.');
        }

        $lines = [];
        foreach ($template as $account) {
            $account = (array) $account;
            $code = (string) ($account['no_akun'] ?? '');
            $side = (string) ($account['debet_kredit'] ?? '');
            if ($side === 'debet' && preg_match('/^([56][0-9]{9}|702[0-9]{7})$/D', $code) === 1) {
                $lines[] = [$code, $amount, 0];
            } elseif ($side === 'kredit' && preg_match('/^1[0-9]{9}$/D', $code) === 1) {
                $lines[] = [$code, 0, $amount];
            } else {
                throw new \InvalidArgumentException('Template jurnal garansi harus mendebit beban dan mengkredit persediaan.');
            }
        }

        if ($lines[0][0] === $lines[1][0] || $lines[0][1] === $lines[1][1]) {
            throw new \InvalidArgumentException('Template jurnal garansi harus memiliki dua akun berbeda dan berlawanan.');
        }

        return $lines;
    }
}
