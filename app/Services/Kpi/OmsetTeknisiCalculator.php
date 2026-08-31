<?php

namespace App\Services\Kpi;

use App\Models\ModelDetailPenjualan;

/**
 * OmsetTeknisiCalculator — replicates OmsetToko behavior for Teknisi KPI.
 * Legacy service uses same omset value for both Omset Toko and Omset Teknisi.
 */
class OmsetTeknisiCalculator implements KpiCalculatorInterface
{
    protected $omsetCalculator;

    public function __construct()
    {
        $this->omsetCalculator = new OmsetTokoCalculator();
    }

    public function calculate($employeeId, $unitId, $month, $year)
    {
        return $this->omsetCalculator->calculate($employeeId, $unitId, $month, $year);
    }
}