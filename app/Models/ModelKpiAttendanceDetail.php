<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKpiAttendanceDetail extends Model
{
    protected $table = 'kpi_attendance_detail';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'evaluation_id',
        'shift',
        'session',
        'attendance_type',
        'scheduled_time',
        'actual_time',
        'late_minutes',
        'auto_score',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Ambil detail absensi berdasarkan evaluation_id (KEHADIRAN).
     *
     * @param int $evaluationId
     * @return object|null
     */
    public function getByEvaluationId(int $evaluationId): ?object
    {
        return $this->where('evaluation_id', $evaluationId)->first();
    }

    /**
     * Ambil SEMUA detail absensi untuk satu evaluation_id.
     * Bisa 1 baris (shift biasa) atau 2 baris (PS: PAGI + SORE).
     *
     * @param int $evaluationId
     * @return array
     */
    public function getAllByEvaluationId(int $evaluationId): array
    {
        return $this->where('evaluation_id', $evaluationId)->orderBy('session', 'ASC')->findAll();
    }

    /**
     * Ambil detail absensi untuk employee + tanggal (bisa 1 atau 2 baris untuk PS).
     *
     * @param int $employeeId
     * @param string $date YYYY-MM-DD
     * @return array
     */
    public function getByEmployeeDate(int $employeeId, string $date): array
    {
        return $this->select('kpi_attendance_detail.*')
            ->join('kpi_evaluations', 'kpi_evaluations.id = kpi_attendance_detail.evaluation_id', 'inner')
            ->join('kpi_components', 'kpi_components.id = kpi_evaluations.kpi_component_id', 'inner')
            ->where('kpi_evaluations.employee_id', $employeeId)
            ->where('kpi_evaluations.evaluation_date', $date)
            ->where('kpi_components.code', 'KEHADIRAN')
            ->findAll();
    }
}