<?php

namespace App\Services\Finance;

use App\Models\ModelFinancePayroll;
use Config\Database;

/**
 * KPI Ketepatan Pembayaran Payroll (auto).
 *
 * Sumber = tabel finance_payroll (register gaji Finance). Satu baris = satu
 * pembayaran gaji dengan:
 *  - due_date  : jatuh tempo pembayaran (jadwal gajian).
 *  - paid_date : tanggal aktual dibayar (NULL = belum dibayar).
 *
 * Periode dinilai : baris yang DUE_DATE-nya jatuh dalam bulan dievaluasi
 *                   (menyamakan pola Hutang — jatuh_tempo).
 *
 * Klasifikasi:
 *  - Tepat      : paid_date <= due_date.
 *  - Terlambat  : paid_date > due_date, ATAU belum dibayar & hari ini > due_date
 *                  (dianggap terlambat).
 *  - Open       : belum dibayar & hari ini <= due_date → tidak dinilai.
 *
 * Skor = Tepat / (Tepat + Terlambat) x 100. Tanpa record → null (data_kosong).
 */
class PayrollTimelinessCalculator implements FinanceCalculatorInterface
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function calculate(int $unitId, int $month, int $year): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        $today = date('Y-m-d');

        $rows = (new ModelFinancePayroll())
            ->where('unit_id', $unitId)
            ->where('due_date >=', $startDate)
            ->where('due_date <=', $endDate)
            ->orderBy('due_date', 'ASC')
            ->findAll();

        $tepat = 0;
        $terlambat = 0;
        $open = 0;
        $items = [];

        foreach ($rows as $row) {
            $paid = $row->paid_date ?: null;

            if ($paid !== null) {
                if ($paid <= $row->due_date) {
                    $klasifikasi = 'Tepat Waktu';
                    $tepat++;
                } else {
                    $klasifikasi = 'Terlambat';
                    $terlambat++;
                }
            } else {
                if ($today > $row->due_date) {
                    $klasifikasi = 'Terlambat (belum dibayar)';
                    $terlambat++;
                } else {
                    $klasifikasi = 'Belum jatuh tempo (open)';
                    $open++;
                }
            }

            $items[] = [
                'id' => (int) $row->id,
                'due_date' => $row->due_date,
                'paid_date' => $paid,
                'total' => (float) ($row->total ?? 0),
                'status' => $row->status,
                'notes' => $row->notes,
                'klasifikasi' => $klasifikasi,
            ];
        }

        $dinilai = $tepat + $terlambat;
        $score = $dinilai > 0 ? round(($tepat / $dinilai) * 100, 2) : null;

        return [
            'score' => $score,
            'status' => $dinilai > 0 ? 'ok' : (empty($rows) ? 'data_kosong' : 'belum_dinilai'),
            'detail' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tepat' => $tepat,
                'terlambat' => $terlambat,
                'open' => $open,
                'dinilai' => $dinilai,
                'total_payroll' => array_sum(array_column($items, 'total')),
                'items' => $items,
            ],
        ];
    }
}