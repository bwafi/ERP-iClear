<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiEvaluation;

class KpiCalculationExample
{
    public function exampleManualKpiEvaluation()
    {
        $evaluationService = new KpiEvaluationService();

        $evaluationData = [
            'employee_id' => 1,
            'kpi_component_id' => 5,
            'evaluator_id' => 10,
            'raw_score' => 4,
            'max_score' => 5,
            'notes' => 'Good performance this month',
            'period_year' => 2024,
            'period_month' => 8,
        ];

        $result = $evaluationService->recordEvaluation($evaluationData);

        if ($result['success']) {
            echo "Evaluation recorded successfully\n";
            echo "Normalized Score: " . $result['evaluation']['normalized_score'] . "\n";
            echo "Weighted Score: " . $result['evaluation']['weighted_score'] . "\n";
        } else {
            echo "Errors: " . implode(', ', $result['errors']) . "\n";
        }

        return $result;
    }

    public function exampleAutomaticKpiCalculation()
    {
        $kpiService = new KpiCalculationService();

        $employeeId = 1;
        $unitId = 1;
        $month = 8;
        $year = 2024;

        $results = $kpiService->calculateForEmployee($employeeId, $unitId, $month, $year, 'default');

        echo "Total KPI: " . $results['total_score'] . "\n";
        foreach ($results['items'] as $kpi) {
            echo "KPI: " . $kpi['name'] . "\n";
            echo "Weight: " . $kpi['weight'] . "%\n";
            echo "Achievement: " . $kpi['achievement'] . "%\n";
            echo "Weighted Score: " . $kpi['weighted_score'] . "\n";
            echo "---\n";
        }

        return $results;
    }

    public function exampleValidatePositionWeights()
    {
        $kpiService = new KpiCalculationService();

        $positionId = 35;
        $validationResult = $kpiService->getWeightValidationResult($positionId);

        echo "Position ID: " . $positionId . "\n";
        echo "Total Weight: " . $validationResult['total_weight'] . "%\n";
        echo "Is Valid: " . ($validationResult['is_valid'] ? 'Yes' : 'No') . "\n";
        echo "Difference from 100: " . $validationResult['difference'] . "%\n";

        foreach ($validationResult['weights'] as $weight) {
            echo "  - " . $weight->name . ": " . $weight->weight . "%\n";
        }

        return $validationResult;
    }

    public function exampleIncentiveCalculation()
    {
        $incentiveService = new \App\Services\Incentive\IncentiveCalculationService();

        // Group-based: pool = 1% omset, dibagi jumlah member aktif
        $groupCode = 'DIGITAL_DIVISION';
        $unitId = 1;
        $omsetToko = 100000000; // 100 juta
        $achievement = 125;

        $result = $incentiveService->calculateGroupIncentive(
            $groupCode,
            $unitId,
            $omsetToko,
            $achievement
        );

        if ($result['success']) {
            echo "Incentive calculated successfully\n";
            echo "Group: " . $result['group_code'] . " ({$result['group_name']})\n";
            echo "Rate: " . $result['rate_percent'] . "%\n";
            echo "Omset: " . $result['omset_toko'] . "\n";
            echo "Pool: " . $result['pool_amount'] . "\n";
            echo "Member count: " . $result['member_count'] . "\n";
            echo "Individual Incentive: " . $result['incentive_amount'] . "\n";
        } else {
            echo "Incentive not calculated: " . $result['reason'] . "\n";
        }

        return $result;
    }

    public function exampleSalaryCalculation()
    {
        $salaryService = new \App\Services\Payroll\SalaryCalculationService();

        $employeeId = 1;
        $positionId = 35;
        $unitId = 1;
        $context = 'gaji';
        $kpiScores = ['TUNJANGAN_KINERJA' => 95, 'TUNJANGAN_ABSEN' => 90];

        $result = $salaryService->calculateSalary(
            $employeeId,
            $positionId,
            $unitId,
            $context,
            $kpiScores,
            0.0,
            100000
        );

        if ($result['success']) {
            echo "Salary breakdown for Employee $employeeId:\n";
            foreach ($result['components'] as $comp) {
                echo sprintf(
                    "  %s (%s): %s\n",
                    $comp['component_name'],
                    $comp['component_code'],
                    number_format($comp['amount'], 2)
                );
            }
            echo "Total Salary: " . number_format($result['total_gaji'], 2) . "\n";
        } else {
            echo "Error: salary structure missing\n";
        }

        return $result;
    }

    public function exampleAggregateEmployeeScore()
    {
        $evaluationService = new KpiEvaluationService();

        $employeeId = 1;
        $year = 2024;
        $month = 8;

        $aggregate = $evaluationService->getEmployeeAggregateScore($employeeId, $year, $month);

        echo "Employee $employeeId - August 2024 Summary:\n";
        echo "Total Weighted Score: " . $aggregate['total_weighted_score'] . "\n";
        echo "Average Normalized Score: " . $aggregate['average_normalized_score'] . "\n";
        echo "Total Evaluations: " . $aggregate['evaluation_count'] . "\n";

        foreach ($aggregate['evaluations'] as $eval) {
            echo "  - " . $eval->name . ": " . $eval->normalized_score . " (weighted: " . $eval->weighted_score . ")\n";
        }

        return $aggregate;
    }
}
