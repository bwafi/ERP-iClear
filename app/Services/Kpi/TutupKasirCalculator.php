<?php

namespace App\Services\Kpi;

/**
 * TutupKasirCalculator
 * 
 * Menghitung pencapaian tutup kasir dalam 1 bulan.
 * Target standar: 30 hari
 * 
 * Formula:
 *   - Aktual = COUNT(status) dari tabel tutup_kasir per unit, bulan, tahun
 *   - Nilai = (Aktual / Target) * 100
 *   - Nilai di-cap maksimal 100%
 */
class TutupKasirCalculator implements KpiCalculatorInterface
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function calculate($employeeId, $unitId, $month, $year)
    {
        $result = $this->db->table('tutup_kasir')
            ->select('COUNT(status) AS total')
            ->where('MONTH(tanggal)', (int)$month)
            ->where('YEAR(tanggal)', (int)$year)
            ->where('unit', (int)$unitId)
            ->get()
            ->getRow();

        return (float) ($result->total ?? 0);
    }
}
