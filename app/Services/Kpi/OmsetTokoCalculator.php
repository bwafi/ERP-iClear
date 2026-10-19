<?php

namespace App\Services\Kpi;

use App\Models\ModelPenjualan;
use App\Models\ModelDetailPenjualan;

class OmsetTokoCalculator implements KpiCalculatorInterface
{
    protected $penjualanModel;
    protected $detailPenjualanModel;

    /**
     * Cache per-request: hasil hanya bergantung pada (unit, bulan, tahun) dan
     * tabel penjualan tidak ditulis selama request. Dipakai juga oleh
     * DigitalMarketingKpiService/OperasionalCalculator, jadi cache ditaruh di
     * sini (bukan di pemanggil) agar semua jalur ikut hemat.
     */
    private static $memo = [];

    public function __construct()
    {
        $this->penjualanModel = new ModelPenjualan();
        $this->detailPenjualanModel = new ModelDetailPenjualan();
    }

    public function calculate($employeeId, $unitId, $month, $year)
    {
        $key = \App\Libraries\ReadCache::kunci('omset', $unitId, $month, $year);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = sprintf('%04d-%02d-t', $year, $month);
        $endDate = date('Y-m-d', strtotime('last day of ' . $startDate));

        // Omset = Gross Profit = SUM(sub_total - hpp_penjualan) per unit
        $result = $this->detailPenjualanModel
            ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) as total_omset')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('penjualan.unit_idunit', $unitId)
            ->where('DATE(penjualan.tanggal) >=', $startDate)
            ->where('DATE(penjualan.tanggal) <=', $endDate)
            ->first();

        return self::$memo[$key] = (float) ($result->total_omset ?? 0);
    }
}
