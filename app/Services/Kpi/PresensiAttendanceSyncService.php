<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiAttendanceOff;
use App\Models\ModelKpiComponent;

/**
 * Menyambungkan punch absen mobile (`presensi`) ke nilai KEHADIRAN KPI.
 *
 * Satu baris `kpi_evaluations` per employee + tanggal. Evaluator mengikuti
 * aturan user: unit Head Office (50) -> Manager, unit cabang -> SPV unit
 * (spv_units), fallback Manager bila mapping SPV tidak ada.
 *
 * Baris hasil sinkronisasi ditandai di `notes` (AUTO_ABSEN...). Baris yang
 * sudah diisi manual tidak pernah ditimpa; koreksi manual menghapus/menandai
 * baris otomatis pada tanggal yang sama agar tidak terjadi rata-rata ganda.
 */
class PresensiAttendanceSyncService
{
    public const AUTO_NOTE   = 'AUTO_ABSEN';
    public const MANUAL_NOTE = 'MANUAL_ABSEN';

    private $db;
    private $attendance;
    private $component;

    public function __construct(?AttendanceInputService $attendance = null)
    {
        $this->db         = \Config\Database::connect();
        $this->attendance = $attendance ?? new AttendanceInputService();
        $this->component  = (new ModelKpiComponent())->where('code', 'KEHADIRAN')->first();
    }

    /**
     * Evaluator KPI KEHADIRAN untuk seorang pegawai.
     *
     * @return int|null null bila Manager tidak ditemukan.
     */
    public function evaluatorFor(int $employeeId): ?int
    {
        $akun = $this->db->table('akun')
            ->select('ID_UNIT')
            ->where('ID_AKUN', $employeeId)
            ->get()
            ->getRow();

        if (! $akun) {
            return null;
        }

        if ((int) $akun->ID_UNIT === KpiCalculationService::HO_UNIT_ID) {
            return $this->managerId();
        }

        return $this->spvIdForUnit((int) $akun->ID_UNIT) ?? $this->managerId();
    }

    /**
     * Sinkronkan satu hari. Aman dipanggil berulang.
     *
     * @return array{status:string, reason?:string, score?:float, late_minutes?:int}
     */
    public function syncDay(int $employeeId, string $date): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['status' => 'skipped', 'reason' => 'invalid_date'];
        }

        $goLive = (string) config('Presensi')->goLiveDate;
        if ($goLive !== '' && $date < $goLive) {
            return ['status' => 'skipped', 'reason' => 'before_go_live'];
        }

        if (! $this->component) {
            return ['status' => 'skipped', 'reason' => 'component_missing'];
        }

        $evaluatorId = $this->evaluatorFor($employeeId);
        if (! $evaluatorId) {
            return ['status' => 'skipped', 'reason' => 'evaluator_missing'];
        }

        [$auto, $manual] = $this->evaluationsFor($employeeId, $date);

        // Manual selalu menang. Baris otomatis yang tertinggal dibersihkan.
        if (! empty($manual)) {
            $this->deleteEvaluations($auto);

            return ['status' => 'skipped', 'reason' => 'manual'];
        }

        $roster = $this->db->table('presensi_roster')
            ->where('akun_idakun', $employeeId)
            ->where('tanggal', $date)
            ->get()
            ->getRow();

        $shift = $roster ? strtoupper((string) $roster->shift) : '';
        if (in_array($shift, ['OFF', 'IZIN', 'SAKIT', 'CUTI'], true)) {
            $this->deleteEvaluations($auto);
            $this->markOffIfMissing($employeeId, $date, $evaluatorId);

            return ['status' => 'off'];
        }

        $punches = $this->db->table('presensi')
            ->where('akun_idakun', $employeeId)
            ->where('waktu_masuk >=', $date . ' 00:00:00')
            ->where('waktu_masuk <=', $date . ' 23:59:59')
            ->orderBy('waktu_masuk', 'ASC')
            ->get()
            ->getResult();

        if (empty($punches)) {
            return ['status' => 'skipped', 'reason' => 'no_punch'];
        }

        $sessions = $this->sessionsFromPunches($punches, $shift);
        if (empty($sessions)) {
            return ['status' => 'skipped', 'reason' => 'no_session'];
        }

        // Punch nyata = hadir; penanda OFF otomatis pada tanggal ini tidak berlaku.
        $this->db->table('kpi_attendance_off')
            ->where('employee_id', $employeeId)
            ->where('evaluation_date', $date)
            ->where('evaluator_id', $evaluatorId)
            ->delete();

        try {
            $result = $this->attendance->saveDailyAttendance($employeeId, $date, $evaluatorId, $sessions);
        } catch (\Throwable $e) {
            log_message('error', 'Sync KPI absen gagal untuk pegawai ' . $employeeId . ' tanggal ' . $date . ': ' . $e->getMessage());

            return ['status' => 'error', 'reason' => $e->getMessage()];
        }

        $this->db->table('kpi_evaluations')
            ->where('id', (int) $result['evaluation_id'])
            ->update(['notes' => self::AUTO_NOTE]);

        return [
            'status'       => 'synced',
            'score'        => (float) $result['auto_score'],
            'late_minutes' => (int) $result['late_minutes'],
        ];
    }

    /**
     * Sinkronkan seluruh tanggal dalam satu bulan (YYYY-MM).
     *
     * @return array{synced:int, off:int, skipped:int, error:int}
     */
    public function syncMonth(int $employeeId, string $month): array
    {
        $hasil = ['synced' => 0, 'off' => 0, 'skipped' => 0, 'error' => 0];

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $hasil;
        }

        $start = $month . '-01';
        $end   = date('Y-m-t', strtotime($start));
        $goLive = (string) config('Presensi')->goLiveDate;
        if ($goLive !== '' && $start < $goLive) {
            $start = $goLive;
        }
        if ($start > $end) {
            return $hasil;
        }

        for ($ts = strtotime($start); $ts <= strtotime($end); $ts = strtotime('+1 day', $ts)) {
            $r = $this->syncDay($employeeId, date('Y-m-d', $ts));
            $status = $r['status'] ?? 'error';
            if (isset($hasil[$status])) {
                $hasil[$status]++;
            } else {
                $hasil['error']++;
            }
        }

        return $hasil;
    }

    /**
     * Dipanggil setelah KEHADIRAN disimpan manual dari halaman penilaian.
     * Baris otomatis lain dihapus; bila evaluatornya sama, baris itu ditandai
     * manual agar sinkronisasi berikutnya tidak menimpanya.
     */
    public function clearAutoForManual(int $employeeId, string $date, int $manualEvaluatorId, bool $markOff = false): void
    {
        if (! $this->component) {
            return;
        }

        [$auto] = $this->evaluationsFor($employeeId, $date);

        foreach ($auto as $row) {
            if ((int) $row->evaluator_id === $manualEvaluatorId) {
                $this->db->table('kpi_evaluations')
                    ->where('id', (int) $row->id)
                    ->update(['notes' => self::MANUAL_NOTE]);
                continue;
            }

            $this->deleteEvaluations([$row]);
        }

        if ($markOff) {
            return;
        }

        $autoEvaluatorId = $this->evaluatorFor($employeeId);
        if ($autoEvaluatorId) {
            $this->db->table('kpi_attendance_off')
                ->where('employee_id', $employeeId)
                ->where('evaluation_date', $date)
                ->where('evaluator_id', $autoEvaluatorId)
                ->delete();
        }
    }

    /**
     * @return array{0:array<int,object>,1:array<int,object>} [otomatis, manual]
     */
    private function evaluationsFor(int $employeeId, string $date): array
    {
        $rows = $this->db->table('kpi_evaluations')
            ->where('employee_id', $employeeId)
            ->where('evaluation_date', $date)
            ->where('kpi_component_id', (int) $this->component->id)
            ->get()
            ->getResult();

        $auto   = [];
        $manual = [];
        foreach ($rows as $row) {
            if (strpos((string) $row->notes, self::AUTO_NOTE) === 0) {
                $auto[] = $row;
            } else {
                $manual[] = $row;
            }
        }

        return [$auto, $manual];
    }

    /**
     * @param array<int,object> $rows
     */
    private function deleteEvaluations(array $rows): void
    {
        foreach ($rows as $row) {
            $this->db->table('kpi_attendance_detail')
                ->where('evaluation_id', (int) $row->id)
                ->delete();
            $this->db->table('kpi_evaluations')
                ->where('id', (int) $row->id)
                ->delete();
        }
    }

    private function markOffIfMissing(int $employeeId, string $date, int $evaluatorId): void
    {
        $ada = $this->db->table('kpi_attendance_off')
            ->where('employee_id', $employeeId)
            ->where('evaluation_date', $date)
            ->countAllResults();

        if ($ada === 0) {
            (new ModelKpiAttendanceOff())->markOff($employeeId, $date, $evaluatorId);
        }
    }

    /**
     * @param array<int,object> $punches
     * @return array<int,array{shift:string,session:string,attendance_type:string,actual_time:string}>
     */
    private function sessionsFromPunches(array $punches, string $rosterShift = ''): array
    {
        $sessions = [];

        foreach ($punches as $punch) {
            $sesi = strtoupper((string) ($punch->sesi ?: 'FULL'));
            if (! in_array($sesi, ['FULL', 'PAGI', 'SORE'], true)) {
                $sesi = 'FULL';
            }

            if ($sesi === 'PAGI' || $sesi === 'SORE') {
                $shift = 'PS';
            } elseif (in_array($rosterShift, ['PAGI', 'SIANG', 'FULL'], true)) {
                // Shift roster adalah sumber kebenaran; PAGI dan FULL sama-sama
                // masuk 08:45 sehingga tidak bisa dibedakan dari jam jadwal.
                $shift = $rosterShift;
            } else {
                $jadwal = substr((string) $punch->jam_jadwal_masuk, 0, 5);
                $shift  = $jadwal === AttendanceScoreCalculator::SCHEDULED_TIMES['SIANG'] ? 'SIANG' : 'PAGI';
            }

            if (isset($sessions[$sesi])) {
                continue;
            }

            $actual = date('H:i', strtotime((string) $punch->waktu_masuk));
            $sessions[$sesi] = [
                'shift'           => $shift,
                'session'         => $sesi,
                'attendance_type' => 'NORMAL',
                'actual_time'     => $actual,
            ];
        }

        return array_values($sessions);
    }

    private function managerId(): ?int
    {
        $row = $this->db->table('akun')
            ->select('ID_AKUN')
            ->where('ID_JABATAN', 34)
            ->where('STATUS_PEGAWAI', 1)
            ->orderBy('ID_AKUN', 'ASC')
            ->get()
            ->getRow();

        return $row ? (int) $row->ID_AKUN : null;
    }

    private function spvIdForUnit(int $unitId): ?int
    {
        $row = $this->db->table('spv_units')
            ->select('spv_units.spv_id')
            ->join('akun', 'akun.ID_AKUN = spv_units.spv_id', 'inner')
            ->where('spv_units.unit_id', $unitId)
            ->where('akun.STATUS_PEGAWAI', 1)
            ->orderBy('spv_units.id', 'ASC')
            ->get()
            ->getRow();

        return $row ? (int) $row->spv_id : null;
    }
}
