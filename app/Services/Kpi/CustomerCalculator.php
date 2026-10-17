<?php

namespace App\Services\Kpi;

/**
 * CustomerCalculator — Total Pelanggan (strategy customer_count).
 *
 * TotalCustomer per unit per bulan = SEMUA service masuk (semua status,
 * berdasarkan DATE(created_at)) + penjualan yang ber-id pelanggan
 * (id_pelanggan, berdasarkan DATE(tanggal)).
 *
 * Untuk TEKNISI (36) & KEPALA TOKO (41), achievement memakai:
 *   - aktual = jumlah service yang pegawai tsb tangani (service_by, semua
 *     status, DATE(created_at)) pada unit & bulan tsb.
 *   - target = kpi_targets.target_value (target pelanggan UNIT: BWI 220 /
 *     JBR 180 / BWI 350 / Pandaan 250) ÷ 2 — dibagi di kode, bukan di DB.
 *   - skor   = min(aktual ÷ target × 100, 100).
 *
 * Pengecualian: KEPALA TOKO unit 1 (merangkap Admin, tidak mengerjakan service)
 * memakai akumulasi service para teknisi unit tsb (handledByUnitTeknisi) dengan
 * target PENUH (tidak ÷ 2). Kepala Toko unit lain & teknisi tidak berubah.
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

    /**
     * Akumulasi customer yang dikerjakan SEMUA teknisi (jabatan 36) di satu unit
     * pada bulan tsb. Dipakai Kepala Toko unit 1 yang merangkap Admin dan tidak
     * mengerjakan service sendiri.
     *
     * Tidak memfilter STATUS_PEGAWAI: service yang dikerjakan pada bulan tsb
     * tetap dihitung walau teknisi-nya sudah non-aktif.
     */
    public function handledByUnitTeknisi(int $unitId, int $month, int $year): int
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $db = \Config\Database::connect();

        $teknisiIds = $db->table('akun')
            ->select('ID_AKUN')
            ->where('ID_JABATAN', 36)
            ->where('ID_UNIT', $unitId)
            ->get()
            ->getResultArray();

        if ($teknisiIds === []) {
            return 0;
        }

        $ids = array_map(static fn ($r) => (int) $r['ID_AKUN'], $teknisiIds);

        return (int) $db->table('service')
            ->whereIn('service_by', $ids)
            ->where('unit_idunit', $unitId)
            ->where('DATE(created_at) >=', $startDate)
            ->where('DATE(created_at) <=', $endDate)
            ->countAllResults();
    }
}