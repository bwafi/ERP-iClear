<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelFinancePayroll extends Model
{
    protected $table = 'finance_payroll';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit_id',
        'pegawai_id',
        'due_date',
        'paid_date',
        'status',
        'total',
        'notes',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getByUnitAndRange(int $unitId, string $startDate, string $endDate): array
    {
        return $this->where('unit_id', $unitId)
            ->where('due_date >=', $startDate)
            ->where('due_date <=', $endDate)
            ->orderBy('due_date', 'ASC')
            ->findAll();
    }
}