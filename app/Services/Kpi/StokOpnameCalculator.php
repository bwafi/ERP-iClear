<?php

namespace App\Services\Kpi;

/**
 * StokOpnameCalculator
 * 
 * Menghitung pencapaian stok opname dalam 1 bulan (1x per minggu).
 * Target standar: 4 minggu
 * 
 * Formula:
 *   - Aktual = jumlah periode stok opname FINAL (distinct tanggal) per unit, bulan, tahun
 *   - Nilai = (Aktual / Target) * 100
 *   - Nilai di-cap maksimal 100%
 * 
 * Hanya periode yang sudah FINAL yang dihitung (draft belum final tidak ikut),
 * konsisten dengan alur kerja Stok Opname DRAFT -> FINAL.
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
        $result = $this->db->table('stok_opname_periode')
            ->select('COUNT(*) AS total')
            ->where('unit_idunit', (int)$unitId)
            ->where('status', 'FINAL')
            ->where('MONTH(tanggal)', (int)$month)
            ->where('YEAR(tanggal)', (int)$year)
            ->get()
            ->getRow();

        return (float) ($result->total ?? 0);
    }
}
