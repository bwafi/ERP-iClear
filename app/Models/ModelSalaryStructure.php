<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelSalaryStructure extends Model
{
    protected $table = 'salary_structures';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'position_id',
        'salary_component_id',
        'base_value',
        'calculation_type',
        'multiplier',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getByPosition($position_id, $date = null)
    {
        $date = $date ?? date('Y-m-d');
        
        return $this->select('salary_structures.*, salary_components.code, salary_components.name, salary_components.type')
                    ->join('salary_components', 'salary_components.id = salary_structures.salary_component_id')
                    ->where('salary_structures.position_id', $position_id)
                    ->where('salary_structures.effective_from <=', $date)
                    ->groupStart()
                        ->where('salary_structures.effective_to >=', $date)
                        ->orWhere('salary_structures.effective_to IS NULL')
                    ->groupEnd()
                    ->findAll();
    }

    public function calculateComponentValue($position_id, $salary_component_id, $kpi_score = null, $base_salary = 1500000)
    {
        $structure = $this->select('salary_structures.*, salary_components.type')
                          ->join('salary_components', 'salary_components.id = salary_structures.salary_component_id')
                          ->where('salary_structures.position_id', $position_id)
                          ->where('salary_structures.salary_component_id', $salary_component_id)
                          ->where('salary_structures.effective_from <=', date('Y-m-d'))
                          ->groupStart()
                              ->where('salary_structures.effective_to >=', date('Y-m-d'))
                              ->orWhere('salary_structures.effective_to IS NULL')
                          ->groupEnd()
                          ->first();
        
        if (!$structure) {
            return 0;
        }

        switch ($structure->calculation_type) {
            case 'fixed':
                return $structure->base_value ?? 0;
            
            case 'percent_of_base':
                $multiplier = $structure->multiplier ?? 1;
                return ($multiplier / 100) * $base_salary;
            
            case 'percent_of_kpi':
                $multiplier = $structure->multiplier ?? 1;
                $kpi_percentage = $kpi_score ?? 0;
                return ($multiplier / 100) * $structure->base_value * ($kpi_percentage / 100);
            
            default:
                return 0;
        }
    }

    public function getTotalSalary($position_id, $kpi_score = null, $base_salary = 1500000)
    {
        $structures = $this->getByPosition($position_id);
        $total = 0;
        
        foreach ($structures as $structure) {
            if ($structure->salary_component->type === 'base') {
                $total += $this->calculateComponentValue($position_id, $structure->salary_component_id);
            } else {
                $total += $this->calculateComponentValue($position_id, $structure->salary_component_id, $kpi_score, $base_salary);
            }
        }
        
        return $total;
    }
}
