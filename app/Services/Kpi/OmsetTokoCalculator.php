<?php

namespace App\Services\Kpi;

use App\Models\ModelPenjualan;
use App\Models\ModelDetailPenjualan;

class OmsetTokoCalculator implements KpiCalculatorInterface
{
    protected $penjualanModel;
    protected $detailPenjualanModel;

    public function __construct()
    {
        $this->penjualanModel = new ModelPenjualan();
        $this->detailPenjualanModel = new ModelDetailPenjualan();
    }

    public function calculate($employeeId, $unitId, $month, $year)
    {
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

        return (float) ($result->total_omset ?? 0);
    }
}
