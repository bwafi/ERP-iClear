<?php

namespace App\Services\Payroll;

/**
 * Ringkasan satu periode payroll untuk papan fase di /payroll.
 *
 * Bucket payroll tidak saling tumpang tindih: baris yang sudah dibayar
 * selalu masuk "paid", baris rencana masuk "auto" kalau belum dikoreksi
 * dan "manual" kalau sudah. Bonus/lembur dihitung terpisah.
 */
class PayrollRunSummary
{
    public static function build(array $payrollRows, array $bonusRows = [], ?string $today = null): array
    {
        $run = [
            'auto'   => ['count' => 0, 'total' => 0],
            'manual' => ['count' => 0, 'total' => 0],
            'paid'   => ['count' => 0, 'total' => 0],
            'bonus'  => ['count' => 0, 'total' => 0],
        ];

        $totals = [
            'count'          => 0,
            'total'          => 0,
            'paid_count'     => 0,
            'paid_total'     => 0,
            'unpaid_count'   => 0,
            'unpaid_total'   => 0,
            'overdue_count'  => 0,
            'overdue_total'  => 0,
            'employees'      => 0,
        ];

        $todayTs    = strtotime($today ?? date('Y-m-d'));
        $employees  = [];

        foreach ($payrollRows as $row) {
            $total  = (int) ($row->total ?? 0);
            $isPaid = ($row->status ?? '') === 'dibayar';
            $dueTs  = strtotime((string) ($row->due_date ?? ''));
            $isLate = ! $isPaid && $dueTs !== false && $dueTs < $todayTs;

            $bucket = $isPaid
                ? 'paid'
                : ((($row->sumber ?? 'manual') === 'auto') ? 'auto' : 'manual');

            $run[$bucket]['count']++;
            $run[$bucket]['total'] += $total;

            $totals['count']++;
            $totals['total'] += $total;

            if ($isPaid) {
                $totals['paid_count']++;
                $totals['paid_total'] += $total;
            } else {
                $totals['unpaid_count']++;
                $totals['unpaid_total'] += $total;
            }

            if ($isLate) {
                $totals['overdue_count']++;
                $totals['overdue_total'] += $total;
            }

            $employees[(int) ($row->pegawai_id ?? 0)] = true;
        }

        foreach ($bonusRows as $row) {
            $run['bonus']['count']++;
            $run['bonus']['total'] += (int) ($row->jumlah ?? 0);
        }

        $totals['employees'] = count($employees);

        return ['run' => $run, 'totals' => $totals];
    }
}
