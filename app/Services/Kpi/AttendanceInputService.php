<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiEvaluation;
use App\Models\ModelKpiComponent;
use App\Models\ModelKpiAttendanceDetail;

/**
 * Service untuk input & pembacaan KPI Absensi dengan auto-scoring.
 *
 * Model penyimpanan: SATU baris kpi_evaluations (KEHADIRAN) per
 * (employee, evaluator, tanggal), dengan SATU ATAU DUA detail
 * kpi_attendance_detail (sesi) untuk shift PS (pagi+sore).
 *
 * Backward-compatible: data lama (nilai manual 1-5) tanpa kpi_attendance_detail
 * tetap valid. Service prioritas baca auto_score dari detail jika ada.
 */
class AttendanceInputService
{
    protected $evaluationModel;
    protected $componentModel;
    protected $detailModel;

    public function __construct()
    {
        $this->evaluationModel = new ModelKpiEvaluation();
        $this->componentModel  = new ModelKpiComponent();
        $this->detailModel     = new ModelKpiAttendanceDetail();
    }

    /**
     * Simpan seluruh sesi absensi untuk satu hari (PS = pagi+sore sekaligus).
     *
     * @param int $employeeId
     * @param string $date YYYY-MM-DD
     * @param int $evaluatorId
     * @param array $sessions List sesi:
     *   [
     *     ['shift' => 'PAGI', 'session' => 'FULL', 'attendance_type' => 'NORMAL', 'actual_time' => '08:50'],
     *     ['shift' => 'PS',   'session' => 'SORE', 'attendance_type' => 'NORMAL', 'actual_time' => '17:05'],
     *   ]
     * @return array{
     *   evaluation_id:int, auto_score:float, late_minutes:int,
     *   sessions:array<int,array{shift:string,session:string,attendance_type:string,
     *            scheduled_time:string,actual_time:string,late_minutes:int,auto_score:float}>
     * }
     * @throws \InvalidArgumentException
     */
    public function saveDailyAttendance(int $employeeId, string $date, int $evaluatorId, array $sessions): array
    {
        if (empty($sessions)) {
            throw new \InvalidArgumentException('Tidak ada sesi jam masuk untuk disimpan.');
        }

        $kehadiranComp = $this->componentModel->where('code', 'KEHADIRAN')->first();
        if (!$kehadiranComp) {
            throw new \RuntimeException('Component KEHADIRAN not found in kpi_components');
        }

        $period = explode('-', $date);
        $year  = (int)$period[0];
        $month = (int)$period[1];

        // Hitung semua sesi
        $details       = [];
        $scoreSum      = 0.0;
        $lateSum       = 0;

        foreach ($sessions as $s) {
            $shift        = strtoupper((string)($s['shift'] ?? ''));
            $session      = strtoupper((string)($s['session'] ?? ''));
            $attendanceType = strtoupper((string)($s['attendance_type'] ?? 'NORMAL'));
            $actualTime   = (string)($s['actual_time'] ?? '');

            if (!in_array($shift, ['PAGI', 'SIANG', 'PS'], true)) {
                throw new \InvalidArgumentException("Invalid shift: {$shift}");
            }
            if (!in_array($session, ['PAGI', 'SORE', 'FULL'], true)) {
                throw new \InvalidArgumentException("Invalid session: {$session}");
            }
            if (!in_array($attendanceType, ['NORMAL', 'IZIN_TELAT'], true)) {
                throw new \InvalidArgumentException("Invalid attendance_type: {$attendanceType}");
            }
            if ($actualTime === '' || !preg_match('/^\d{2}:\d{2}$/', $actualTime)) {
                throw new \InvalidArgumentException("Jam masuk tidak valid: '{$actualTime}'");
            }

            $scheduled = AttendanceScoreCalculator::getScheduledTime($shift, $session);
            $r         = AttendanceScoreCalculator::calculate($shift, $session, $attendanceType, $scheduled, $actualTime);

            $details[] = [
                'shift'            => $shift,
                'session'          => $session,
                'attendance_type'  => $attendanceType,
                'scheduled_time'   => $scheduled,
                'actual_time'      => $actualTime,
                'late_minutes'     => $r['late_minutes'],
                'auto_score'       => $r['auto_score'],
            ];
            $scoreSum += $r['auto_score'];
            $lateSum  += $r['late_minutes'];
        }

        $count     = count($details);
        // Nilai harian PS = skor berdasarkan TOTAL keterlambatan gabungan
        // (telat pagi + telat sore), bukan rata-rata nilai per sesi.
        // Contoh: pagi telat 5 + sore telat 5 = 10 menit => nilai 2.
        $dailyType  = (string)($details[0]['attendance_type'] ?? 'NORMAL');
        $dailyScore = AttendanceScoreCalculator::scoreForMinutes($lateSum, $dailyType);
        $dailyLate  = $lateSum;

        $now = date('Y-m-d H:i:s');

        // Upsert evaluasi (uniqueness: employee + component + evaluator + date)
        $evaluation = $this->evaluationModel
            ->where('employee_id', $employeeId)
            ->where('evaluation_date', $date)
            ->where('kpi_component_id', (int)$kehadiranComp->id)
            ->where('evaluator_id', $evaluatorId)
            ->first();

        if ($evaluation) {
            $evaluationId = (int)$evaluation->id;
            $this->evaluationModel->update($evaluationId, [
                'raw_score'        => $dailyScore,
                'normalized_score' => round($dailyScore / 5.0 * 100.0, 2),
                'updated_at'       => $now,
            ]);
        } else {
            $evaluationId = (int)$this->evaluationModel->insert([
                'employee_id'      => $employeeId,
                'kpi_component_id' => (int)$kehadiranComp->id,
                'evaluator_id'     => $evaluatorId,
                'evaluation_date'  => $date,
                'raw_score'        => $dailyScore,
                'max_score'        => 5.0,
                'normalized_score' => round($dailyScore / 5.0 * 100.0, 2),
                'weighted_score'   => 0.0,
                'period_year'      => $year,
                'period_month'     => $month,
                'created_at'       => $now,
            ]);
            if (!$evaluationId) {
                throw new \RuntimeException('Failed to insert kpi_evaluations');
            }
        }

        // Ganti detail sesi hari itu (simpan ulang seluruh sesi yang dikirim)
        $this->detailModel->where('evaluation_id', $evaluationId)->delete();
        foreach ($details as $d) {
            $this->detailModel->insert([
                'evaluation_id'   => $evaluationId,
                'shift'           => $d['shift'],
                'session'         => $d['session'],
                'attendance_type' => $d['attendance_type'],
                'scheduled_time'  => $d['scheduled_time'],
                'actual_time'     => $d['actual_time'],
                'late_minutes'    => $d['late_minutes'],
                'auto_score'      => $d['auto_score'],
                'created_at'      => $now,
            ]);
        }

        return [
            'evaluation_id' => $evaluationId,
            'auto_score'    => $dailyScore,
            'late_minutes'  => $dailyLate,
            'sessions'      => $details,
        ];
    }

    /**
     * Hapus seluruh data absensi (evaluasi KEHADIRAN + detail sesi) pada
     * tanggal tertentu untuk evaluator tertentu. Dipakai saat hari ditandai OFF.
     *
     * @param int $employeeId
     * @param string $date YYYY-MM-DD
     * @param int $evaluatorId
     * @return void
     */
    public function resetDay(int $employeeId, string $date, int $evaluatorId): void
    {
        $component = $this->componentModel->where('code', 'KEHADIRAN')->first();
        if (!$component) {
            return;
        }

        $evals = $this->evaluationModel
            ->where('employee_id', $employeeId)
            ->where('evaluator_id', $evaluatorId)
            ->where('evaluation_date', $date)
            ->where('kpi_component_id', (int)$component->id)
            ->findAll();

        foreach ($evals as $ev) {
            $this->detailModel->where('evaluation_id', (int)$ev->id)->delete();
            $this->evaluationModel->delete((int)$ev->id);
        }
    }

    /**
     * API lama: simpan satu sesi. Dipertahankan untuk kompatibilitas.
     */
    public function saveAttendance(
        int $employeeId,
        string $date,
        string $shift,
        string $session,
        string $attendanceType,
        string $actualTime,
        int $evaluatorId
    ): array {
        return $this->saveDailyAttendance($employeeId, $date, $evaluatorId, [[
            'shift'            => $shift,
            'session'          => $session,
            'attendance_type'  => $attendanceType,
            'actual_time'      => $actualTime,
        ]]);
    }

    /**
     * Hitung nilai tetapi TANPA menyimpan — untuk preview/konfirmasi UI.
     *
     * @param array $sessions Sama seperti saveDailyAttendance.
     * @return array{auto_score:float, late_minutes:int, sessions:array}
     */
    public function preview(array $sessions): array
    {
        $details = [];
        $scoreSum = 0.0;
        $lateSum  = 0;

        foreach ($sessions as $s) {
            $shift = strtoupper((string)($s['shift'] ?? ''));
            $session = strtoupper((string)($s['session'] ?? ''));
            $type = strtoupper((string)($s['attendance_type'] ?? 'NORMAL'));
            $actual = (string)($s['actual_time'] ?? '');

            $scheduled = AttendanceScoreCalculator::getScheduledTime($shift, $session);
            $r = AttendanceScoreCalculator::calculate($shift, $session, $type, $scheduled, $actual);

            $details[] = [
                'shift' => $shift, 'session' => $session,
                'attendance_type' => $type, 'actual_time' => $actual,
                'scheduled_time' => $scheduled,
                'late_minutes' => $r['late_minutes'], 'auto_score' => $r['auto_score'],
            ];
            $scoreSum += $r['auto_score'];
            $lateSum  += $r['late_minutes'];
        }

        $count = max(count($details), 1);
        $dailyType = (string)($details[0]['attendance_type'] ?? 'NORMAL');

        return [
            'auto_score'   => AttendanceScoreCalculator::scoreForMinutes($lateSum, $dailyType),
            'late_minutes' => $lateSum,
            'sessions'     => $details,
        ];
    }

    /**
     * Ambil nilai absensi harian (backward-compatible).
     * Prioritas: auto_score dari detail; fallback raw_score manual.
     *
     * @param int $employeeId
     * @param string $date YYYY-MM-DD
     * @return array{
     *   score:float, late_minutes:int, is_auto:bool,
     *   sessions:array<int,array{shift:string,session:string,attendance_type:string,
     *            actual_time:string,late_minutes:int,auto_score:float}>
     * }
     */
    public function getDailyScore(int $employeeId, string $date): array
    {
        $kehadiranComp = $this->componentModel->where('code', 'KEHADIRAN')->first();
        if (!$kehadiranComp) {
            return $this->emptyResult();
        }

        $evaluations = $this->evaluationModel
            ->where('employee_id', $employeeId)
            ->where('evaluation_date', $date)
            ->where('kpi_component_id', (int)$kehadiranComp->id)
            ->findAll();

        if (empty($evaluations)) {
            return $this->emptyResult();
        }

        $totalScore = 0.0;
        $totalLate  = 0;
        $count      = 0;
        $isAuto     = false;
        $sessions   = [];

        foreach ($evaluations as $eval) {
            $details = $this->detailModel->getAllByEvaluationId((int)$eval->id);

            if (!empty($details)) {
                $sesSum   = 0.0;
                foreach ($details as $dt) {
                    $sesSum += (float)$dt->auto_score;
                    $totalLate += (int)$dt->late_minutes;
                    $sessions[] = [
                        'shift'            => (string)$dt->shift,
                        'session'          => (string)$dt->session,
                        'attendance_type'  => (string)$dt->attendance_type,
                        'actual_time'      => (string)$dt->actual_time,
                        'late_minutes'     => (int)$dt->late_minutes,
                        'auto_score'       => (float)$dt->auto_score,
                    ];
                }
                $totalScore += $sesSum / count($details);
                $isAuto = true;
            } else {
                $totalScore += (float)$eval->raw_score;
            }
            $count++;
        }

        return [
            'score'        => $count > 0 ? round($totalScore / $count, 2) : 0.0,
            'late_minutes' => $totalLate,
            'is_auto'      => $isAuto,
            'sessions'     => $sessions,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'score'        => 0.0,
            'late_minutes' => 0,
            'is_auto'      => false,
            'sessions'     => [],
        ];
    }
}