<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKpiWeight extends Model
{
    protected $table = 'kpi_weights';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'kpi_component_id',
        'position_id',
        'weight',
        'weight_group',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getByPosition($position_id, $date = null, $group = null)
    {
        $date = $date ?? date('Y-m-d');
        
        $query = $this->select('kpi_weights.*, kpi_components.code, kpi_components.name, kpi_components.type')
                    ->join('kpi_components', 'kpi_components.id = kpi_weights.kpi_component_id')
                    ->where('kpi_weights.position_id', $position_id)
                    ->where('kpi_weights.effective_from <=', $date)
                    ->groupStart()
                        ->where('kpi_weights.effective_to >=', $date)
                        ->orWhere('kpi_weights.effective_to IS NULL')
                    ->groupEnd();

        if ($group) {
            $query->where('kpi_weights.weight_group', $group);
        }

        return $query->findAll();
    }

    public function getActiveWeightsByPosition($position_id, $group = null)
    {
        return $this->getByPosition($position_id, null, $group);
    }

    public function getTotalWeightByPosition($position_id, $date = null, $group = null)
    {
        $date = $date ?? date('Y-m-d');
        
        $query = $this->select('SUM(weight) as total_weight')
                       ->where('position_id', $position_id)
                       ->where('effective_from <=', $date)
                       ->groupStart()
                           ->where('effective_to >=', $date)
                           ->orWhere('effective_to IS NULL')
                       ->groupEnd();

        if ($group) {
            $query->where('weight_group', $group);
        }

        $result = $query->first();
        
        return $result ? $result->total_weight : 0;
    }

    public function validateWeights($position_id, $date = null, $group = null)
    {
        $total = $this->getTotalWeightByPosition($position_id, $date, $group);
        return abs($total - 100) < 0.01;
    }

    public function validateAllGroups($position_id, $date = null)
    {
        $groups = ['kpi', 'absen'];
        $results = [];

        foreach ($groups as $group) {
            $total = $this->getTotalWeightByPosition($position_id, $date, $group);
            $results[$group] = [
                'total_weight' => $total,
                'is_valid' => abs($total - 100) < 0.01,
            ];
        }

        return $results;
    }
}
