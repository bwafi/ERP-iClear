<?php

namespace App\Services\Finance;

final class BalanceSheetReconciliation
{
    public static function summarize(array $accounts): array
    {
        return self::summarizeAsOf($accounts, $accounts, $accounts);
    }

    public static function summarizeAsOf(array $balanceAccounts, array $currentYearProfitAccounts, array $allAccounts): array
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

        foreach ($allAccounts as $account) {
            $account = (array) $account;
            $code = (string) ($account['no_akun'] ?? '');
            $debit = (int) ($account['debet'] ?? 0);
            $credit = (int) ($account['kredit'] ?? 0);
            $result['jumlah_akun']++;
            $result['selisih_jurnal'] += $debit - $credit;

            if (! preg_match('/^[0-9]{10}$/D', $code)
                || (! in_array($code[0], ['1', '2', '3', '4', '5', '6'], true)
                    && ! in_array(substr($code, 0, 3), ['701', '702'], true))) {
                $result['akun_tidak_terklasifikasi'][] = $code;
            }
        }

        foreach ($balanceAccounts as $account) {
            $account = (array) $account;
            $code = (string) ($account['no_akun'] ?? '');
            $debit = (int) ($account['debet'] ?? 0);
            $credit = (int) ($account['kredit'] ?? 0);
            if (! preg_match('/^[0-9]{10}$/D', $code)) {
                continue;
            }
            if ($code[0] === '1') {
                $result['aset'] += $debit - $credit;
            } elseif ($code[0] === '2') {
                $result['kewajiban'] += $credit - $debit;
            } elseif ($code[0] === '3') {
                $result['ekuitas'] += $credit - $debit;
            }
        }

        foreach ($currentYearProfitAccounts as $account) {
            $account = (array) $account;
            $code = (string) ($account['no_akun'] ?? '');
            $debit = (int) ($account['debet'] ?? 0);
            $credit = (int) ($account['kredit'] ?? 0);
            if (! preg_match('/^[0-9]{10}$/D', $code)) {
                continue;
            }
            if ($code[0] === '4' || substr($code, 0, 3) === '701') {
                $result['laba_berjalan'] += $credit - $debit;
            } elseif ($code[0] === '5' || $code[0] === '6' || substr($code, 0, 3) === '702') {
                $result['laba_berjalan'] -= $debit - $credit;
            }
        }

        $result['selisih'] = $result['aset'] - $result['kewajiban']
            - $result['ekuitas'] - $result['laba_berjalan'];

        return $result;
    }

    public static function sourceExceptions(array $sources): array
    {
        $unbalanced = [];
        $withoutReference = 0;
        foreach ($sources as $source) {
            $source = (array) $source;
            $referenceType = trim((string) ($source['tabel_referensi'] ?? ''));
            $referenceId = trim((string) ($source['id_referensi'] ?? ''));
            if ($referenceType === '' || $referenceId === '') {
                $withoutReference += (int) ($source['jumlah_baris'] ?? 1);
                continue;
            }
            $debit = (int) ($source['debet'] ?? 0);
            $credit = (int) ($source['kredit'] ?? 0);
            if ($debit !== $credit) {
                $unbalanced[] = [
                    'id_unit' => (int) ($source['id_unit'] ?? 0),
                    'tabel_referensi' => $referenceType,
                    'id_referensi' => $referenceId,
                    'jumlah_baris' => (int) ($source['jumlah_baris'] ?? 0),
                    'debet' => $debit,
                    'kredit' => $credit,
                    'selisih' => $debit - $credit,
                ];
            }
        }
        return ['tidak_seimbang' => $unbalanced, 'baris_tanpa_referensi' => $withoutReference];
    }

    public static function invalidAccounts(array $accounts): array
    {
        return array_values(array_filter($accounts, static function ($account): bool {
            $account = (array) $account;
            $code = trim((string) ($account['no_akun'] ?? ''));
            return ! preg_match('/^[0-9]{10}$/D', $code)
                || ($account['akun_master'] ?? null) === null;
        }));
    }
}
