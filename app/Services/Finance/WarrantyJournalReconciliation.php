<?php

namespace App\Services\Finance;

final class WarrantyJournalReconciliation
{
    public static function summarize(array $issues, array $journals): array
    {
        $byIssue = [];
        foreach ($journals as $journal) {
            $journal = (array) $journal;
            $reference = (int) ($journal['id_referensi'] ?? 0);
            if (! isset($byIssue[$reference])) {
                $byIssue[$reference] = ['count' => 0, 'debet' => 0, 'kredit' => 0];
            }
            $byIssue[$reference]['count']++;
            $byIssue[$reference]['debet'] += (int) ($journal['debet'] ?? 0);
            $byIssue[$reference]['kredit'] += (int) ($journal['kredit'] ?? 0);
        }

        $result = ['jumlah_mutasi' => 0, 'hpp_total' => 0, 'tanpa_jurnal' => 0, 'tidak_sesuai' => 0, 'jurnal_tanpa_mutasi' => 0];
        foreach ($issues as $issue) {
            $issue = (array) $issue;
            $id = (int) ($issue['id'] ?? 0);
            $amount = (int) ($issue['jumlah'] ?? 0) * (int) ($issue['hpp_satuan'] ?? 0);
            $result['jumlah_mutasi']++;
            $result['hpp_total'] += $amount;
            if (! isset($byIssue[$id])) {
                $result['tanpa_jurnal']++;
                continue;
            }
            $journal = $byIssue[$id];
            if ($journal['count'] !== 2 || $journal['debet'] !== $amount || $journal['kredit'] !== $amount) {
                $result['tidak_sesuai']++;
            }
            unset($byIssue[$id]);
        }
        $result['jurnal_tanpa_mutasi'] = count($byIssue);

        return $result;
    }
}
