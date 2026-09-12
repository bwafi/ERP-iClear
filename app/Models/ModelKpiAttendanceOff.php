<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Marker hari OFF/Libur absensi KPI (KEHADIRAN).
 *
 * Satu baris per (employee, tanggal, evaluator). Hari OFF TIDAK disimpan
 * sebagai kpi_evaluations raw_score=0 sehingga tidak tertukar dengan nilai 0
 * (terlambat ≥15 menit) — hari OFF benar-benar tidak dihitung.
 */
class ModelKpiAttendanceOff extends Model
{
    protected $table = 'kpi_attendance_off';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'employee_id',
        'evaluation_date',
        'evaluator_id',
        'period_month',
        'period_year',
    ];

    /**
     * Tandai satu hari sebagai OFF (upsert — non-destruktif).
     *
     * @param int $employeeId
     * @param string $date YYYY-MM-DD
     * @param int $evaluatorId
     * @return object
     */
    public function markOff(int $employeeId, string $date, int $evaluatorId): object
    {
        $period = explode('-', $date);

        $row = $this->where('employee_id', $employeeId)
            ->where('evaluation_date', $date)
            ->where('evaluator_id', $evaluatorId)
            ->first();

        $data = [
            'employee_id'    => $employeeId,
            'evaluation_date' => $date,
            'evaluator_id'    => $evaluatorId,
            'period_month'    => (int)$period[1],
            'period_year'     => (int)$period[0],
        ];

        if ($row) {
            $this->update((int)$row->id, $data);
            return $this->find((int)$row->id);
        }

        $insertId = $this->insert($data);

        return $this->find((int)$insertId);
    }

    /**
     * Hapus tanda OFF untuk satu (employee, tanggal, evaluator).
     */
    public function clearOff(int $employeeId, string $date, int $evaluatorId): void
    {
        $this->where('employee_id', $employeeId)
            ->where('evaluation_date', $date)
            ->where('evaluator_id', $evaluatorId)
            ->delete();
    }

    /**
     * Tanggal-tanggal OFF seorang pegawai dalam satu periode.
     *
     * @param int $employeeId
     * @param int $month
     * @param int $year
     * @return array Tanggal (Y-m-d)
     */
    public function offDatesForPeriod(int $employeeId, int $month, int $year): array
    {
        $rows = $this->select('evaluation_date')
            ->distinct()
            ->where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->findAll();

        return array_map(fn($r) => $r->evaluation_date, $rows);
    }
}