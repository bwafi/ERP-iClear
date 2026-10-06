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
 *
 * Ditambah syarat: seluruh barang berstok pada periode itu harus terisi
 * (terisi_barang = total_barang). Aturan ini sama dengan syarat finalisasi di
 * StokOpnameService, jadi KPI tidak mungkin menghitung periode yang isiannya
 * tidak genap. Ada satu periode legacy yang ter-finalize tanpa isian lengkap,
 * dan dengan syarat ini periode tersebut tidak ikut dihitung.
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
            ->where('terisi_barang = total_barang', null, false)
            ->where('total_barang >', 0)
            ->where('MONTH(tanggal)', (int)$month)
            ->where('YEAR(tanggal)', (int)$year)
            ->get()
            ->getRow();

        return (float) ($result->total ?? 0);
    }
}
