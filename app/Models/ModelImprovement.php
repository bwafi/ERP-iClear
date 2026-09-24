<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * improvements — ide perbaikan multimedia (KPI Improvement 5%).
 */
class ModelImprovement extends Model
{
    protected $table = 'improvements';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'employee_id', 'judul', 'deskripsi', 'kategori', 'status',
        'submission_month', 'submission_year', 'approved_at', 'implemented_at',
        'evidence', 'evaluated_by',
    ];

    public const STATUSES = ['draft', 'submitted', 'approved', 'implemented', 'rejected'];

    public function untukEmployeePeriode(int $employeeId, int $month, int $year): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('submission_month', $month)
            ->where('submission_year', $year)
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}