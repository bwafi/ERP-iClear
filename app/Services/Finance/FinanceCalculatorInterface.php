<?php

namespace App\Services\Finance;

interface FinanceCalculatorInterface
{
    /**
     * Hitung skor KPI 0-100 untuk sebuah unit pada periode.
     *
     * @return array ['score' => float|null, 'status' => string, 'detail' => array]
     */
    public function calculate(int $unitId, int $month, int $year): array;
}