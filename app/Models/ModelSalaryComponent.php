<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelSalaryComponent extends Model
{
    protected $table = 'salary_components';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'code',
        'name',
        'type',
        'description',
        'default_value',
        'is_active',
        'is_configurable',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getActiveComponents()
    {
        return $this->where('is_active', 1)->findAll();
    }

    public function getByType($type)
    {
        return $this->where('type', $type)
                    ->where('is_active', 1)
                    ->findAll();
    }

    public function getBaseComponents()
    {
        return $this->getByType('base');
    }

    public function getAllowanceComponents()
    {
        return $this->getByType('allowance');
    }

    public function getIncentiveComponents()
    {
        return $this->getByType('incentive');
    }

    public function getDeductionComponents()
    {
        return $this->getByType('deduction');
    }

    public function getConfigurableComponents()
    {
        return $this->where('is_configurable', 1)
                    ->where('is_active', 1)
                    ->findAll();
    }

    public function getNonConfigurableComponents()
    {
        return $this->where('is_configurable', 0)
                    ->where('is_active', 1)
                    ->findAll();
    }

    public function getByCode($code)
    {
        return $this->where('code', $code)->first();
    }
}
