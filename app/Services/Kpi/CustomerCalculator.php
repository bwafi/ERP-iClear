<?php

namespace App\Services\Kpi;

use App\Models\ModelPenjualan;

class CustomerCalculator implements KpiCalculatorInterface
{
    protected $penjualanModel;

    public function __construct()
    {
        $this->penjualanModel = new ModelPenjualan();
    }

    public function calculate($employeeId, $unitId, $month, $year)
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-d', strtotime('last day of ' . $startDate));

        // Customer per unit: COUNT(idpenjualan) tidak filter sales_by (per unit level)
        $result = $this->penjualanModel
            ->select('COUNT(idpenjualan) as customer_count')
            ->where('unit_idunit', $unitId)
            ->where('DATE(tanggal) >=', $startDate)
            ->where('DATE(tanggal) <=', $endDate)
            ->first();

        return (int) ($result->customer_count ?? 0);
    }
}
