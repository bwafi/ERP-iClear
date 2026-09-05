<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiEvaluation;
use App\Models\ModelKpiComponent;
use App\Models\ModelKpiWeight;

class KpiEvaluationService
{
    protected $evaluationModel;
    protected $componentModel;
    protected $weightModel;

    const MAX_SCORE = 5;
    const MAX_NORMALIZED_SCORE = 100;

    public function __construct()
    {
        $this->evaluationModel = new ModelKpiEvaluation();
        $this->componentModel = new ModelKpiComponent();
        $this->weightModel = new ModelKpiWeight();
    }

    public function recordEvaluation($data)
    {
        $validated = $this->validateEvaluationData($data);

        if (!$validated['valid']) {
            return [
                'success' => false,
                'errors' => $validated['errors'],
            ];
        }

        // Evaluator authorization check (fixed mapping)
        $evaluatorId  = (int)($data['evaluator_id'] ?? 0);
        $employeeId   = (int)($data['employee_id'] ?? 0);
        $componentId  = (int)($data['kpi_component_id'] ?? 0);

        $component = $this->componentModel->find($componentId);
        if (!$component) {
            return [
                'success' => false,
                'errors'  => ['kpi component not found'],
            ];
        }

        $componentCode = (string)$component->code;
        if ($evaluatorId > 0 && $employeeId > 0) {
            // Otorisasi spesifik: evaluator + target + KOMPONEN + scope unit.
            if (!\App\Services\Kpi\EvaluatorAuthorizationService::canEvaluateComponent(
                $evaluatorId,
                $employeeId,
                $componentCode
            )) {
                return [
                    'success' => false,
                    'errors'  => ['evaluator not authorized for this component'],
                ];
            }
        }

        // evaluation_date is authoritative.
        // period_year / period_month are DERIVED from evaluation_date
        // (caller-provided period is ignored for daily attendance).
        $evaluationDate = $data['evaluation_date'];
        $ts = strtotime($evaluationDate);
        $periodYear  = (int) date('Y', $ts);
        $periodMonth = (int) date('m', $ts);

        // Per-event normalized/weighted (kept NOT NULL for compatibility).
        // NOTE: for attendance components this is NOT the monthly score;
        // monthly attendance uses SUM(raw)/(26*5)*100 computed at aggregation.
        $normalized = $this->normalizeScore($data['raw_score'], $data['max_score'] ?? self::MAX_SCORE);
        $weight = $this->getComponentWeight($data['kpi_component_id'], $data['employee_id']);
        $weighted = $this->calculateWeightedScore($normalized, $weight);

        $evaluationData = [
            'employee_id'       => $data['employee_id'],
            'kpi_component_id'  => $data['kpi_component_id'],
            'evaluator_id'      => $data['evaluator_id'],
            'evaluation_date'   => $evaluationDate,
            'raw_score'         => $data['raw_score'],
            'max_score'         => $data['max_score'] ?? self::MAX_SCORE,
            'normalized_score'  => $normalized,
            'weighted_score'    => $weighted,
            'notes'             => $data['notes'] ?? null,
            'period_year'       => $periodYear,
            'period_month'      => $periodMonth,
        ];

        $result = $this->evaluationModel->upsertEvaluation($evaluationData);

        return [
            'success' => (bool) $result,
            'evaluation' => $evaluationData,
        ];
    }

    public function normalizeScore($rawScore, $maxScore = self::MAX_SCORE)
    {
        if ($maxScore <= 0) {
            return 0;
        }

        $normalized = ($rawScore / $maxScore) * self::MAX_NORMALIZED_SCORE;

        return round(min($normalized, self::MAX_NORMALIZED_SCORE), 2);
    }

    public function calculateWeightedScore($normalizedScore, $weight)
    {
        $weighted = ($normalizedScore / self::MAX_NORMALIZED_SCORE) * $weight;
        return round($weighted, 2);
    }

    protected function getComponentWeight($componentId, $employeeId)
    {
        $employee = $this->getEmployeeData($employeeId);

        if (!$employee) {
            return 0;
        }

        $weight = $this->weightModel
            ->where('kpi_component_id', $componentId)
            ->where('position_id', $employee->ID_JABATAN)
            ->first();

        return $weight ? (float) $weight->weight : 0;
    }

    protected function getEmployeeData($employeeId)
    {
        $model = new \App\Models\ModelAuth();
        return $model->where('ID_AKUN', $employeeId)->first();
    }

    protected function validateEvaluationData($data)
    {
        $errors = [];

        if (!isset($data['employee_id']) || empty($data['employee_id'])) {
            $errors[] = 'employee_id is required';
        }

        if (!isset($data['kpi_component_id']) || empty($data['kpi_component_id'])) {
            $errors[] = 'kpi_component_id is required';
        }

        if (!isset($data['evaluator_id']) || empty($data['evaluator_id'])) {
            $errors[] = 'evaluator_id is required';
        }

        // evaluation_date is required & authoritative for daily attendance.
        if (empty($data['evaluation_date'])) {
            $errors[] = 'evaluation_date is required';
        } elseif (strtotime($data['evaluation_date']) === false) {
            $errors[] = 'evaluation_date is not a valid date';
        }

        if (!isset($data['raw_score'])) {
            $errors[] = 'raw_score is required';
        } elseif ($data['raw_score'] < 1 || $data['raw_score'] > ($data['max_score'] ?? self::MAX_SCORE)) {
            $errors[] = 'raw_score must be between 1 and ' . ($data['max_score'] ?? self::MAX_SCORE);
        }

        $component = $this->componentModel->where('id', $data['kpi_component_id'])->first();
        if (!$component) {
            $errors[] = 'kpi_component_id does not exist';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function getEvaluationsByEmployee($employeeId, $year, $month)
    {
        return $this->evaluationModel->getByEmployeeAndPeriod($employeeId, $year, $month);
    }

    public function getEmployeeAggregateScore($employeeId, $year, $month)
    {
        $evaluations = $this->getEvaluationsByEmployee($employeeId, $year, $month);

        if (empty($evaluations)) {
            return [
                'total_weighted_score' => 0,
                'average_normalized_score' => 0,
                'evaluation_count' => 0,
                'evaluations' => [],
            ];
        }

        $totalWeighted = 0;
        $totalNormalized = 0;

        foreach ($evaluations as $eval) {
            $totalWeighted += $eval->weighted_score;
            $totalNormalized += $eval->normalized_score;
        }

        return [
            'total_weighted_score' => round($totalWeighted, 2),
            'average_normalized_score' => round($totalNormalized / count($evaluations), 2),
            'evaluation_count' => count($evaluations),
            'evaluations' => $evaluations,
        ];
    }
}
