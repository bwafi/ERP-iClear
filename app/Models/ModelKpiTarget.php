<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKpiTarget extends Model
{
    protected $table = 'kpi_targets';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'kpi_component_id',
        'unit_id',
        'position_id',
        'context',
        'target_value',
        'batas_awal',
        'batas_kedua',
        'batas_ketiga',
        'batas_keempat',
        'period_type',
        'period_month',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Ambil target aktif untuk KPI + unit + context + periode.
     */
    public function getTargetByKpiAndUnit($kpi_component_id, $unit_id, $context = 'default', $date = null)
    {
        $date = $date ?? date('Y-m-d');

        $target = $this->where('kpi_component_id', $kpi_component_id)
                    ->where('unit_id', $unit_id)
                    ->where('context', $context)
                    ->where('effective_from <=', $date)
                    ->groupStart()
                        ->where('effective_to >=', $date)
                        ->orWhere('effective_to IS NULL')
                    ->groupEnd()
                    ->first();

        // Fallback ke context 'default' bila target utk context spesifik
        // (mis. 'gaji'/'penilaian_kinerja') tidak ditemukan.
        // Menjaga komponen yg targetnya hanya disimpan dgn context 'default'.
        if (!$target && $context !== 'default') {
            $target = $this->where('kpi_component_id', $kpi_component_id)
                        ->where('unit_id', $unit_id)
                        ->where('context', 'default')
                        ->where('effective_from <=', $date)
                        ->groupStart()
                            ->where('effective_to >=', $date)
                            ->orWhere('effective_to IS NULL')
                        ->groupEnd()
                        ->first();
        }

        return $target;
    }

    public function getTargetsByUnit($unit_id, $date = null)
    {
        $date = $date ?? date('Y-m-d');
        
        return $this->select('kpi_targets.*, kpi_components.code, kpi_components.name')
                    ->join('kpi_components', 'kpi_components.id = kpi_targets.kpi_component_id')
                    ->where('kpi_targets.unit_id', $unit_id)
                    ->where('kpi_targets.effective_from <=', $date)
                    ->groupStart()
                        ->where('kpi_targets.effective_to >=', $date)
                        ->orWhere('kpi_targets.effective_to IS NULL')
                    ->groupEnd()
                    ->findAll();
    }

    public function getTargetsByPosition($position_id, $date = null)
    {
        $date = $date ?? date('Y-m-d');
        
        return $this->select('kpi_targets.*, kpi_components.code, kpi_components.name')
                    ->join('kpi_components', 'kpi_components.id = kpi_targets.kpi_component_id')
                    ->where('kpi_targets.position_id', $position_id)
                    ->where('kpi_targets.effective_from <=', $date)
                    ->groupStart()
                        ->where('kpi_targets.effective_to >=', $date)
                        ->orWhere('kpi_targets.effective_to IS NULL')
                    ->groupEnd()
                    ->findAll();
    }

    public function getMonthlyTargets($unit_id, $month, $year)
    {
        $date = sprintf('%04d-%02d-01', $year, $month);
        
        return $this->select('kpi_targets.*, kpi_components.code, kpi_components.name')
                    ->join('kpi_components', 'kpi_components.id = kpi_targets.kpi_component_id')
                    ->where('kpi_targets.unit_id', $unit_id)
                    ->where('kpi_targets.period_type', 'monthly')
                    ->groupStart()
                        ->where('kpi_targets.period_month', $month)
                        ->orWhere('kpi_targets.period_month IS NULL')
                    ->groupEnd()
                    ->where('kpi_targets.effective_from <=', $date)
                    ->groupStart()
                        ->where('kpi_targets.effective_to >=', $date)
                        ->orWhere('kpi_targets.effective_to IS NULL')
                    ->groupEnd()
                    ->findAll();
    }
}
