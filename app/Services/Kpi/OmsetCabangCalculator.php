<?php

namespace App\Services\Kpi;

use App\Models\ModelDetailPenjualan;

class OmsetCabangCalculator implements KpiCalculatorInterface
{
    protected $detailPenjualan;

    /** Cache per-request (lihat OmsetTokoCalculator::$memo). */
    private static $memo = [];

    public function __construct()
    {
        $this->detailPenjualan = new ModelDetailPenjualan();
    }

    public function calculate($employeeId, $unitId, $month, $year)
    {
        $key = \App\Libraries\ReadCache::kunci('omset', $unitId, $month, $year);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-d', strtotime('last day of ' . $startDate));

        // Omset Cabang = Gross Profit = SUM(sub_total - hpp_penjualan) per unit
        $result = $this->detailPenjualan
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) as total_omset')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('penjualan.unit_idunit', $unitId)
            ->where('DATE(penjualan.tanggal) >=', $startDate)
            ->where('DATE(penjualan.tanggal) <=', $endDate)
            ->first();

        return self::$memo[$key] = (float) ($result->total_omset ?? 0);
    }
}
