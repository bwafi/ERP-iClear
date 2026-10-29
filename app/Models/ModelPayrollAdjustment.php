<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPayrollAdjustment extends Model
{
    public const POTONGAN = 'potongan';

    public const BONUS = 'bonus';

    protected $table = 'payroll_adjustment';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'employee_id', 'unit_id', 'period', 'jenis', 'jumlah', 'keterangan', 'created_by',
    ];
    protected $useTimestamps = true;

    /**
     * Penyesuaian satu karyawan pada satu bulan (YYYY-MM) untuk satu jenis.
     */
    public function forPeriod(int $employeeId, string $period, string $jenis)
    {
        return $this->where('employee_id', $employeeId)
            ->where('period', $period)
            ->where('jenis', $jenis)
            ->first();
    }

    /**
     * Total penyesuaian satu karyawan pada satu bulan untuk satu jenis.
     */
    public function totalForPeriod(int $employeeId, string $period, string $jenis): float
    {
        $row = $this->selectSum('jumlah', 'total')
            ->where('employee_id', $employeeId)
            ->where('period', $period)
            ->where('jenis', $jenis)
            ->first();

        return (float) ($row->total ?? 0);
    }
}
