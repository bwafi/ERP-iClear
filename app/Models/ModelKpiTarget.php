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
     * Ambil target aktif untuk KPI + unit + periode.
     *
     * Konteks sudah dikonsolidasi jadi SATU ('default'), sehingga parameter
     * $context dipertahankan hanya untuk kompatibilitas pemanggil dan diabaikan.
     */
    public function getTargetByKpiAndUnit($kpi_component_id, $unit_id, $context = 'default', $date = null)
    {
        $date = $date ?? date('Y-m-d');

        return $this->where('kpi_component_id', $kpi_component_id)
                    ->where('unit_id', $unit_id)
                    ->where('context', 'default')
                    ->where('effective_from <=', $date)
                    ->groupStart()
                        ->where('effective_to >=', $date)
                        ->orWhere('effective_to IS NULL')
                    ->groupEnd()
                    ->first();
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

    /**
     * Daftar target untuk editor admin (join komponen, unit, jabatan).
     * Filter bersifat opsional; unit_id 0 berarti "global" (unit_id NULL).
     */
    public function getEditorTargets(array $filters = []): array
    {
        $builder = $this->select('kpi_targets.*, kpi_components.code, kpi_components.name,
                                  unit.NAMA_UNIT, jabatan.NAMA_JABATAN')
                    ->join('kpi_components', 'kpi_components.id = kpi_targets.kpi_component_id')
                    ->join('unit', 'unit.idunit = kpi_targets.unit_id', 'left')
                    ->join('jabatan', 'jabatan.ID_JABATAN = kpi_targets.position_id', 'left');

        if (!empty($filters['kpi_component_id'])) {
            $builder->where('kpi_targets.kpi_component_id', (int)$filters['kpi_component_id']);
        }
        if (!empty($filters['unit_id'])) {
            $builder->where('kpi_targets.unit_id', (int)$filters['unit_id']);
        }
        if (!empty($filters['position_id'])) {
            $builder->where('kpi_targets.position_id', (int)$filters['position_id']);
        }
        if (!empty($filters['q'])) {
            $q = (string)$filters['q'];
            $builder->groupStart()
                ->like('kpi_components.code', $q)
                ->orLike('kpi_components.name', $q)
                ->orLike('unit.NAMA_UNIT', $q)
                ->orLike('jabatan.NAMA_JABATAN', $q)
            ->groupEnd();
        }

        return $builder->orderBy('kpi_components.code', 'ASC')
                    ->orderBy('kpi_targets.unit_id', 'ASC')
                    ->orderBy('kpi_targets.effective_from', 'DESC')
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
