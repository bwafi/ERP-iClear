<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelFinanceKpiRecord extends Model
{
    protected $table = 'finance_kpi_records';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit_id',
        'period_year',
        'period_month',
        'kpi_code',
        'mode',
        'score',
        'contribution',
        'weight',
        'notes',
        'detail_json',
        'evaluator_id',
        'evaluated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function findOneByUnitCodePeriod(int $unitId, string $kpiCode, int $year, int $month)
    {
        return $this->where('unit_id', $unitId)
            ->where('kpi_code', $kpiCode)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
    }

    public function getByUnitAndPeriod(int $unitId, int $year, int $month): array
    {
        return $this->where('unit_id', $unitId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('kpi_code', 'ASC')
            ->findAll();
    }

    /**
     * Satu baris per (unit, kpi_code, periode). Update jika sudah ada.
     */
    public function upsert(array $data): bool
    {
        $existing = $this->where('unit_id', $data['unit_id'])
            ->where('kpi_code', $data['kpi_code'])
            ->where('period_year', $data['period_year'])
            ->where('period_month', $data['period_month'])
            ->first();

        if ($existing) {
            $data['id'] = $existing->id;
            return $this->save($data);
        }

        return (bool) $this->insert($data);
    }
}