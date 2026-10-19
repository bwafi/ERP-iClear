<?php

namespace App\Services\Finance;

final class BalanceSheetReconciliation
{
    public static function summarize(array $accounts): array
    {
        $result = [
            'aset' => 0,
            'kewajiban' => 0,
            'ekuitas' => 0,
            'laba_berjalan' => 0,
            'selisih' => 0,
            'selisih_jurnal' => 0,
            'akun_tidak_terklasifikasi' => [],
            'jumlah_akun' => 0,
        ];

        foreach ($accounts as $account) {
            $account = (array) $account;
            $code = (string) ($account['no_akun'] ?? '');
            $debit = (int) ($account['debet'] ?? 0);
            $credit = (int) ($account['kredit'] ?? 0);
            $result['jumlah_akun']++;
            $result['selisih_jurnal'] += $debit - $credit;

            if (! preg_match('/^[0-9]{10}$/D', $code)) {
                $result['akun_tidak_terklasifikasi'][] = $code;
                continue;
            }

            if ($code[0] === '1') {
                $result['aset'] += $debit - $credit;
            } elseif ($code[0] === '2') {
                $result['kewajiban'] += $credit - $debit;
            } elseif ($code[0] === '3') {
                $result['ekuitas'] += $credit - $debit;
            } elseif ($code[0] === '4' || substr($code, 0, 3) === '701') {
                $result['laba_berjalan'] += $credit - $debit;
            } elseif ($code[0] === '5' || $code[0] === '6' || substr($code, 0, 3) === '702') {
                $result['laba_berjalan'] -= $debit - $credit;
            } else {
                $result['akun_tidak_terklasifikasi'][] = $code;
            }
        }

        $result['selisih'] = $result['aset'] - $result['kewajiban']
            - $result['ekuitas'] - $result['laba_berjalan'];

        return $result;
    }
}
