<?php

namespace App\Services\Kpi;

/**
 * StokOpnameCalculator
 * 
 * Menghitung pencapaian stok opname dalam 1 bulan (1x per minggu).
 * Target standar: 4 minggu
 * 
 * Formula:
 *   - Aktual = COUNT(DISTINCT DATE(tanggal)) dari tabel stok_opname_draft per unit, bulan, tahun
 *   - Nilai = (Aktual / Target) * 100
 *   - Nilai di-cap maksimal 100%
 */
class StokOpnameCalculator implements KpiCalculatorInterface
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function calculate($employeeId, $unitId, $month, $year)
    {
        $result = $this->db->table('stok_opname_draft')
            ->select('COUNT(DISTINCT DATE(tanggal)) AS total')
            ->where('unit_idunit', (int)$unitId)
            ->where('MONTH(tanggal)', (int)$month)
            ->where('YEAR(tanggal)', (int)$year)
            ->get()
            ->getRow();

        return (float) ($result->total ?? 0);
    }
}
