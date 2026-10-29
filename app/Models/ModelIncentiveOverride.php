<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelIncentiveOverride extends Model
{
    protected $table = 'incentive_overrides';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'employee_id', 'unit_id', 'period', 'rate', 'reason', 'created_by',
    ];
    protected $useTimestamps = true;

    /**
     * Pengecualian rate untuk satu karyawan pada satu bulan (YYYY-MM).
     */
    public function forPeriod(int $employeeId, int $unitId, string $period)
    {
        return $this->where('employee_id', $employeeId)
            ->where('unit_id', $unitId)
            ->where('period', $period)
            ->first();
    }
}
