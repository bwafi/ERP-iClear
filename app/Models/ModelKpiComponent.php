<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKpiComponent extends Model
{
    protected $table = 'kpi_components';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'code',
        'name',
        'description',
        'type',
        'category',
        'unit_of_measure',
        'calculation_strategy',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getActiveComponents()
    {
        return $this->where('is_active', 1)->findAll();
    }

    public function getByCategory($category)
    {
        return $this->where('category', $category)
                    ->where('is_active', 1)
                    ->findAll();
    }

    public function getByType($type)
    {
        return $this->where('type', $type)
                    ->where('is_active', 1)
                    ->findAll();
    }

    public function getByCode($code)
    {
        return $this->where('code', $code)->first();
    }

    public function getAutomaticComponents()
    {
        return $this->where('type', 'automatic')
                    ->where('is_active', 1)
                    ->findAll();
    }

    public function getManualComponents()
    {
        return $this->where('type', 'manual')
                    ->where('is_active', 1)
                    ->findAll();
    }
}
