<?php

namespace App\Services\Kpi;

/**
 * CustomerCalculator — Total Pelanggan (strategy customer_count).
 *
 * TotalCustomer per unit per bulan = SEMUA service masuk (semua status,
 * berdasarkan DATE(created_at)) + penjualan yang ber-id pelanggan
 * (id_pelanggan, berdasarkan DATE(tanggal)).
 *
 * Untuk TEKNISI (jabatan 36), achievement memakai:
 *   - aktual = jumlah service yang teknisi tsb tangani (service_by, semua
 *     status, DATE(created_at)) pada unit & bulan tsb.
 *   - target = kpi_targets.target_value (target pelanggan UNIT: BWI 220 /
 *     JBR 180 / BWI 350 / Pandaan 250) ÷ 2 — dibagi di kode, bukan di DB.
 *   - skor   = min(aktual ÷ target × 100, 100).
 */
class CustomerCalculator implements KpiCalculatorInterface
{
    /**
     * Total Pelanggan per unit per bulan.
     *
     * @return int TotalCustomer = service(all status) + penjualan(id_pelanggan)
     */
    public function calculate($employeeId, $unitId, $month, $year)
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $db = \Config\Database::connect();

        $service = (int) $db->table('service')
            ->where('unit_idunit', $unitId)
            ->where('DATE(created_at) >=', $startDate)
            ->where('DATE(created_at) <=', $endDate)
            ->countAllResults();

        $penjualan = (int) $db->table('penjualan')
            ->where('unit_idunit', $unitId)
            ->where('id_pelanggan IS NOT NULL AND id_pelanggan <> 0', null, false)
            ->where('DATE(tanggal) >=', $startDate)
            ->where('DATE(tanggal) <=', $endDate)
            ->countAllResults();

        return $service + $penjualan;
    }

    /**
     * Customer yang ditangani seorang teknisi pada bulan tsb (sesuai unit-nya).
     * Memakai SEMUA status service (bukan hanya status service = 4 / selesai),
     * konsisten dgn definisi "service masuk" di TotalCustomer.
     */
    public function handledByTeknisi(int $teknisiId, int $unitId, int $month, int $year): int
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        return (int) \Config\Database::connect()
            ->table('service')
            ->where('service_by', $teknisiId)
            ->where('unit_idunit', $unitId)
            ->where('DATE(created_at) >=', $startDate)
            ->where('DATE(created_at) <=', $endDate)
            ->countAllResults();
    }
}