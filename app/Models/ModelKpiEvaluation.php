<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKpiEvaluation extends Model
{
    protected $table = 'kpi_evaluations';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'employee_id',
        'kpi_component_id',
        'evaluator_id',
        'evaluation_date',
        'raw_score',
        'max_score',
        'normalized_score',
        'weighted_score',
        'notes',
        'period_year',
        'period_month',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getByEmployeeAndPeriod($employee_id, $year, $month)
    {
        return $this->select('kpi_evaluations.*, kpi_components.code, kpi_components.name, kpi_components.category')
                    ->join('kpi_components', 'kpi_components.id = kpi_evaluations.kpi_component_id')
                    ->where('kpi_evaluations.employee_id', $employee_id)
                    ->where('kpi_evaluations.period_year', $year)
                    ->where('kpi_evaluations.period_month', $month)
                    ->findAll();
    }

    public function getByEmployee($employee_id, $start_date = null, $end_date = null)
    {
        $builder = $this->select('kpi_evaluations.*, kpi_components.code, kpi_components.name')
                        ->join('kpi_components', 'kpi_components.id = kpi_evaluations.kpi_component_id')
                        ->where('kpi_evaluations.employee_id', $employee_id);
        
        if ($start_date) {
            list($y, $m) = explode('-', $start_date);
            $builder->where('(kpi_evaluations.period_year > ' . (int)$y . ' OR (kpi_evaluations.period_year = ' . (int)$y . ' AND kpi_evaluations.period_month >= ' . (int)$m . '))');
        }
        
        if ($end_date) {
            list($y, $m) = explode('-', $end_date);
            $builder->where('(kpi_evaluations.period_year < ' . (int)$y . ' OR (kpi_evaluations.period_year = ' . (int)$y . ' AND kpi_evaluations.period_month <= ' . (int)$m . '))');
        }
        
        return $builder->orderBy('kpi_evaluations.period_year', 'DESC')
                       ->orderBy('kpi_evaluations.period_month', 'DESC')
                       ->findAll();
    }

    public function getAverageByKpi($kpi_component_id, $year, $month)
    {
        $result = $this->select('AVG(normalized_score) as avg_score, COUNT(*) as count')
                       ->where('kpi_component_id', $kpi_component_id)
                       ->where('period_year', $year)
                       ->where('period_month', $month)
                       ->first();
        
        return $result;
    }

    /**
     * Get sum of raw_score for attendance components per employee per month.
     * Returns sum of raw_score and count of evaluations per attendance component.
     *
     * @param int $employeeId
     * @param int $year
     * @param int $month
     * @return array component_code => ['sum' => float, 'count' => int]
     */
    public function getDailyAttendanceSum(int $employeeId, int $year, int $month): array
    {
        $attendanceCodes = ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'];
        
        $componentIds = $this->db->table('kpi_components')
            ->select('id')
            ->whereIn('code', ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'])
            ->get()->getResultArray();
        
        $componentMap = [];
        foreach ($componentIds as $c) {
            $componentMap[$c->id] = $c->code; // We'll need to map back to code
        }
        
        // Better: get all attendance components with their codes
        $components = $this->db->table('kpi_components')
            ->select('id, code')
            ->whereIn('code', ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'])
            ->findAll();
        
        $componentMap = [];
        foreach ($components as $c) {
            $componentMap[$c->id] = $c->code;
        }
        
        if (empty($componentIds = array_keys($componentMap))) {
            return [];
        }
        
        // Get sum of raw_score and count for each component
        $builder = $this->select('kpi_component_id, SUM(raw_score) as sum_raw, COUNT(*) as cnt')
            ->where('employee_id', $employeeId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('kpi_component_id', array_keys($componentMap))
            ->groupBy('kpi_component_id')
            ->findAll();
        
        $result = [];
        foreach ($rows as $row) {
            $code = $componentMap[$row->kpi_component_id] ?? null;
            if ($code) {
                $result[$code] = [
                    'sum' => (float)($row->sum_raw ?? 0),
                    'count' => (int)($row->cnt ?? 0)
                ];
            }
        }
        
        // Ensure all 4 attendance components are present
        $attendanceCodes = ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'];
        foreach ($attendanceCodes as $code) {
            if (!isset($result[$code])) {
                $result[$code] = ['sum' => 0.0, 'count' => 0];
            }
        }
        
        return $result;
    }

    /**
     * Upsert per DAILY uniqueness: employee + component + evaluation_date.
     *
     * Same employee + same component + same date → update existing row.
     * Same employee + same component + DIFFERENT date → new row (daily attendance).
     */
    public function upsertEvaluation($data)
    {
        // Daily uniqueness: employee + component + EVALUATOR + evaluation_date.
        // Memungkinkan beberapa evaluator menilai pegawai/komponen/tanggal yang sama
        // (utk kasus rata-rata, mis. Manager dinilai SPV+Kadiv+IT+Admin Center).
        $existing = $this->where('employee_id', $data['employee_id'])
                         ->where('kpi_component_id', $data['kpi_component_id'])
                         ->where('evaluator_id', $data['evaluator_id'] ?? null)
                         ->where('evaluation_date', $data['evaluation_date'] ?? null)
                         ->first();

        if ($existing) {
            $data['id'] = $existing->id;
            return $this->save($data);
        }

        return $this->insert($data);
    }

    /**
     * Count daily evaluations per employee/component within a month.
     * Useful for aggregation (SUM of raw daily scores).
     */
    public function countDailyByEmployeeComponent($employee_id, $kpi_component_id, $year, $month): int
    {
        return (int) $this->where('employee_id', $employee_id)
            ->where('kpi_component_id', $kpi_component_id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereNotNull('evaluation_date')
            ->countAllResults();
    }

    /**
     * Get daily attendance evaluations for a specific employee, component, and month.
     * Returns raw scores grouped by date for verification/debugging.
     */
    public function getDailyScores(int $employeeId, int $kpiComponentId, int $year, int $month): array
    {
        return $this->select('evaluation_date, raw_score')
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', $kpiComponentId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('evaluation_date', 'ASC')
            ->findAll();
    }
}
