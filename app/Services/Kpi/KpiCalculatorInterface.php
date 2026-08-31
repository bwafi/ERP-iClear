<?php

namespace App\Services\Kpi;

interface KpiCalculatorInterface
{
    public function calculate($employeeId, $unitId, $month, $year);
}
