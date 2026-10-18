<?php

namespace App\Services\Finance;

final class SupplierCreditReconciliation
{
    public static function summarize(array $credits, array $allocations, array $journals): array
    {
        $allocationsByCredit = [];
        foreach ($allocations as $allocation) {
            $allocation = (array) $allocation;
            $id = (int) ($allocation['kredit_id'] ?? 0);
            $allocationsByCredit[$id] = ($allocationsByCredit[$id] ?? 0) + (int) ($allocation['nominal'] ?? 0);
        }
        $journalsByCredit = [];
        foreach ($journals as $journal) {
            $journal = (array) $journal;
            $id = (int) ($journal['id_referensi'] ?? 0);
            $account = (string) ($journal['no_akun'] ?? '');
            if (! isset($journalsByCredit[$id][$account])) {
                $journalsByCredit[$id][$account] = [0, 0];
            }
            $journalsByCredit[$id][$account][0] += (int) ($journal['debet'] ?? 0);
            $journalsByCredit[$id][$account][1] += (int) ($journal['kredit'] ?? 0);
        }

        $result = ['jumlah_kredit' => 0, 'sisa_kredit' => 0, 'alokasi_tidak_sesuai' => 0,
            'jurnal_tidak_sesuai' => 0, 'referensi_yatim' => 0];
        foreach ($credits as $credit) {
            $credit = (array) $credit;
            $id = (int) ($credit['id'] ?? 0);
            $amount = (int) ($credit['nominal'] ?? 0);
            $remaining = (int) ($credit['sisa_kredit'] ?? 0);
            $result['jumlah_kredit']++;
            if (($credit['status'] ?? '') === 'disetujui') {
                $result['sisa_kredit'] += $remaining;
                $used = $amount - $remaining;
                if ($amount <= 0 || $remaining < 0 || $used < 0
                    || ($allocationsByCredit[$id] ?? 0) !== $used) {
                    $result['alokasi_tidak_sesuai']++;
                }
                $expected = [
                    (string) ($credit['akun_debet'] ?? '') => [$used, 0],
                    (string) ($credit['akun_piutang'] ?? '') => [$amount, $used],
                    (string) ($credit['akun_kredit'] ?? '') => [0, $amount],
                ];
                $actual = $journalsByCredit[$id] ?? [];
                ksort($expected);
                ksort($actual);
                if (count($expected) !== 3 || $actual !== $expected) {
                    $result['jurnal_tidak_sesuai']++;
                }
            } elseif ($amount !== 0 || $remaining !== 0
                || ($allocationsByCredit[$id] ?? 0) !== 0 || isset($journalsByCredit[$id])) {
                $result['alokasi_tidak_sesuai']++;
            }
            unset($allocationsByCredit[$id], $journalsByCredit[$id]);
        }
        $result['referensi_yatim'] = count($allocationsByCredit) + count($journalsByCredit);
        return $result;
    }
}
