<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiEvaluation;
use App\Models\ModelKpiComponent;
use App\Services\Kpi\KpiScoreService;

class AttendanceAggregationService
{
    /**
     * Attendance component codes with their weights
     */
    private const ATTENDANCE_COMPONENTS = [
        'KEHADIRAN'     => ['weight' => 40, 'name' => 'Kehadiran'],
        'KEBERSIHAN'    => ['weight' => 20, 'name' => 'Kebersihan'],
        'SERAGAM'       => ['weight' => 20, 'name' => 'Seragam'],
        'KEPATUHAN_SOP' => ['weight' => 20, 'name' => 'Kepatuhan SOP'],
    ];

    /**
     * Denominator: 26 working days * 5 (max score)
     */
    private const DENOMINATOR = 26 * 5; // 130

    private const CAP = 100;

    protected $evaluationModel;
    protected $componentModel;
    protected $scoreService;

    public function __construct()
    {
        $this->evaluationModel = new \App\Models\ModelKpiEvaluation();
        $this->componentModel = new \App\Models\ModelKpiComponent();
        $this->scoreService = new KpiScoreService();
    }

    /**
     * Calculate monthly attendance scores for an employee.
     *
     * @param int $employeeId
     * @param int $unitId
     * @param string $month MM
     * @param string $year YYYY
     * @param string $context 'gaji'|'penilaian_kinerja'|'slip_gaji'
     * @return array
     */
    public function calculateMonthlyAttendance(int $employeeId, int $unitId, string $month, string $year, string $context = 'gaji'): array
    {
        $authModel = new \App\Models\ModelAuth();
        $employee = $authModel->where('ID_AKUN', $employeeId)->first();
        $positionId = $employee ? (int)$employee->ID_JABATAN : 0;
        
        if ($positionId === 40) {
            return $this->calculateSPVAttendance($employeeId, $unitId, $month, $year, $context);
        }

        $date = sprintf('%04d-%02d-15', (int)$year, (int)$month);

        $componentCodes = array_keys(self::ATTENDANCE_COMPONENTS);

        // Get component IDs for attendance components
        $components = $this->componentModel
            ->whereIn('code', array_keys(self::ATTENDANCE_COMPONENTS))
            ->findAll();

        $componentMap = [];
        foreach ($components as $c) {
            $componentMap[$c->code] = $c->id;
        }

        // Komponen non-Kehadi  ran utk MANAGER dihitung otomatis dari rata-rata
        // skor komponen tsb milik SPV, Kepala Divisi, IT, dan Admin Center.
        $isManager = ($positionId === 34);
        $managerTeamCodes = $isManager ? ['KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'] : [];

        $results = [];
        $attendanceScore = 0.0;
        $componentsRaw = [];

        foreach ($componentCodes as $code) {
            $componentId = $componentMap[$code] ?? null;

            if (in_array($code, $managerTeamCodes, true)) {
                $normalized = $this->managerTeamAverage($componentId, $month, $year);
                $normalized = min($normalized, 100.0);

                $weight = self::ATTENDANCE_COMPONENTS[$code]['weight'] ?? 0;
                $weighted = ($normalized / 100.0) * $weight;

                $results[$code] = [
                    'normalized' => round($normalized, 4),
                    'weighted' => round($weighted, 4),
                ];
                $componentsRaw[$code] = [
                    'sum' => round($normalized * 130.0 / 100.0, 4),
                    'count' => 0,
                    'normalized' => round($normalized, 4),
                ];
                $attendanceScore += $weighted;
                continue;
            }

            if (!$componentId) {
                $componentsRaw[$code] = ['sum' => 0.0, 'count' => 0, 'normalized' => 0.0];
                $normalized = 0.0;
                $weighted = 0.0;
            } else {
                // Get daily evaluations for this employee/component/month.
                // Per tanggal bisa dinilai oleh lebih dari satu evaluator (kasus rata-rata)
                // → skor harian = AVG(raw_score) dari semua evaluator pada tanggal tsb.
                $rows = $this->evaluationModel
                    ->select('evaluation_date, AVG(raw_score) as day_avg')
                    ->where('employee_id', $employeeId)
                    ->where('kpi_component_id', $componentId)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->groupBy('evaluation_date')
                    ->findAll();

                $sumRaw = 0.0;
                foreach ($rows as $r) {
                    $sumRaw += (float)$r->day_avg;
                }
                $count = count($rows);

                // Formula: SUM / (26 * 5) * 100 = SUM / 130 * 100
                $normalized = $sumRaw / 130.0 * 100.0;
                $normalized = min($normalized, 100.0);

                $weight = self::ATTENDANCE_COMPONENTS[$code]['weight'] ?? 0;
                $weighted = ($normalized / 100.0) * self::ATTENDANCE_COMPONENTS[$code]['weight'];

                $results[$code] = [
                    'normalized' => round($normalized, 4),
                    'weighted' => round($weighted, 4),
                ];

                $componentsRaw[$code] = [
                    'sum' => $sumRaw,
                    'count' => $count,
                    'normalized' => round($normalized, 4),
                ];

                $attendanceScore += $weighted;
            }
        }

        // Cap attendance score at 100
        $attendanceScore = min($attendanceScore, 100.0);

        return [
            'components' => $results,
            'attendance_score' => round($attendanceScore, 4),
            'components_raw' => $componentsRaw,
        ];
    }

    /**
     * Rata-rata skor absen per komponen utk MANAGER.
     *
     * Skor non-kehadiran Manager TIDAK diinput manual — dihitung sebagai
     * rata-rata skor komponen yang SAMA (monthly normalized) dari pegawai
     * SPV(40), Kepala Divisi(43), Team IT(45), dan Admin Center(0).
     *
     * Tiap jabatan diberi bobot rata-rata yang sama: rata-rata per jabatan,
     * lalu dirata-ratakan antar 4 jabatan (jabatan tanpa data dilewati).
     *
     * @param int|string $componentId id komponen absensi
     * @param string $month MM
     * @param string $year  YYYY
     * @return float normalized 0..100
     */
    protected function managerTeamAverage($componentId, string $month, string $year): float
    {
        $roles = [40, 43, 45, 0];
        $authModel = new \App\Models\ModelAuth();

        $roleAverages = [];

        foreach ($roles as $roleJabatan) {
            $employees = $authModel->where('ID_JABATAN', $roleJabatan)
                ->where('STATUS_PEGAWAI', 1)
                ->findAll();

            $employeeScores = [];
            foreach ($employees as $emp) {
                $rows = $this->evaluationModel
                    ->select('evaluation_date, AVG(raw_score) as day_avg')
                    ->where('employee_id', (int)$emp->ID_AKUN)
                    ->where('kpi_component_id', (int)$componentId)
                    ->where('period_year', (int)$year)
                    ->where('period_month', (int)$month)
                    ->groupBy('evaluation_date')
                    ->findAll();

                $sumRaw = 0.0;
                foreach ($rows as $r) {
                    $sumRaw += (float)$r->day_avg;
                }
                $employeeScores[] = min($sumRaw / 130.0 * 100.0, 100.0);
            }

            if (!empty($employeeScores)) {
                $roleAverages[] = array_sum($employeeScores) / count($employeeScores);
            }
        }

        if (empty($roleAverages)) {
            return 0.0;
        }

        return array_sum($roleAverages) / count($roleAverages);
    }

    /**
     * Calculate attendance score only (for salary integration)
     */
    public function getAttendanceScore(int $employeeId, int $unitId, string $month, string $year, string $context = 'gaji'): float
    {
        $result = $this->calculateMonthlyAttendance($employeeId, $unitId, $month, $year, $context);
        return $result['attendance_score'];
    }

    /**
     * Get raw daily scores for debugging/verification
     */
    public function getDailyScores(int $employeeId, string $month, string $year): array
    {
        $componentCodes = array_keys(self::ATTENDANCE_COMPONENTS);
        $components = $this->componentModel
            ->whereIn('code', $componentCodes)
            ->findAll();

        $results = [];
        foreach ($components as $c) {
            $scores = $this->evaluationModel
                ->where('employee_id', $employeeId)
                ->where('kpi_component_id', $c->id)
                ->where('period_year', $year)
                ->where('period_month', $month)
                ->orderBy('evaluation_date', 'ASC')
                ->findAll();

            $results[$c->code] = [];
            foreach ($scores as $s) {
                $results[$c->code][] = [
                    'date' => $s->evaluation_date,
                    'score' => $s->raw_score,
                    'evaluator' => $s->evaluator_id,
                ];
            }
        }

        return $results;
    }

    /**
     * Calculate SPV attendance using hybrid logic:
     * - KEHADIRAN: From SPV's own evaluations by Admin Probolinggo
     * - KEBERSIHAN, SERAGAM, KEPATUHAN_SOP: AVG of Kepala Toko + Kepala Divisi values
     * 
     * @param int $spvEmployeeId
     * @param int $unitId
     * @param string $month
     * @param string $year
     * @param string $context
     * @return array
     */
    protected function calculateSPVAttendance(int $spvEmployeeId, int $unitId, string $month, string $year, string $context): array
    {
        $db = \Config\Database::connect();
        
        $kepalaToko = $db->query("
            SELECT ID_AKUN 
            FROM akun 
            WHERE ID_JABATAN IN (41, 42) 
            AND STATUS_PEGAWAI = 1 
            LIMIT 1
        ")->getRow();
        
        $kepalaDivisi = $db->query("
            SELECT ID_AKUN 
            FROM akun 
            WHERE ID_JABATAN = 43 
            AND STATUS_PEGAWAI = 1 
            LIMIT 1
        ")->getRow();
        
        if (!$kepalaToko || !$kepalaDivisi) {
            return $this->getEmptyAttendanceResult();
        }
        
        $ktEmpId = (int)$kepalaToko->ID_AKUN;
        $kdEmpId = (int)$kepalaDivisi->ID_AKUN;
        
        $ktUnit = $db->query("SELECT ID_UNIT FROM akun WHERE ID_AKUN = ?", [$ktEmpId])->getRow()->ID_UNIT ?? 1;
        $kdUnit = $db->query("SELECT ID_UNIT FROM akun WHERE ID_AKUN = ?", [$kdEmpId])->getRow()->ID_UNIT ?? 1;
        
        $spvKehadiran = $this->getComponentScore($spvEmployeeId, 'KEHADIRAN', $month, $year);
        
        $ktAttendance = $this->calculateMonthlyAttendanceBase($ktEmpId, $ktUnit, $month, $year, $context);
        $kdAttendance = $this->calculateMonthlyAttendanceBase($kdEmpId, $kdUnit, $month, $year, $context);
        
        $kebersihan = ($ktAttendance['components']['KEBERSIHAN']['normalized'] + $kdAttendance['components']['KEBERSIHAN']['normalized']) / 2;
        $seragam = ($ktAttendance['components']['SERAGAM']['normalized'] + $kdAttendance['components']['SERAGAM']['normalized']) / 2;
        $sop = ($ktAttendance['components']['KEPATUHAN_SOP']['normalized'] + $kdAttendance['components']['KEPATUHAN_SOP']['normalized']) / 2;
        
        $results = [
            'KEHADIRAN' => [
                'normalized' => round($spvKehadiran, 4),
                'weighted' => round(($spvKehadiran / 100.0) * 40, 4),
            ],
            'KEBERSIHAN' => [
                'normalized' => round($kebersihan, 4),
                'weighted' => round(($kebersihan / 100.0) * 20, 4),
            ],
            'SERAGAM' => [
                'normalized' => round($seragam, 4),
                'weighted' => round(($seragam / 100.0) * 20, 4),
            ],
            'KEPATUHAN_SOP' => [
                'normalized' => round($sop, 4),
                'weighted' => round(($sop / 100.0) * 20, 4),
            ],
        ];
        
        $attendanceScore = $results['KEHADIRAN']['weighted'] + 
                          $results['KEBERSIHAN']['weighted'] + 
                          $results['SERAGAM']['weighted'] + 
                          $results['KEPATUHAN_SOP']['weighted'];
        
        $attendanceScore = min($attendanceScore, 100.0);
        
        return [
            'components' => $results,
            'attendance_score' => round($attendanceScore, 4),
            'components_raw' => [
                'KEHADIRAN' => ['normalized' => $spvKehadiran],
                'KEBERSIHAN' => ['normalized' => $kebersihan],
                'SERAGAM' => ['normalized' => $seragam],
                'KEPATUHAN_SOP' => ['normalized' => $sop],
            ],
        ];
    }
    
    /**
     * Calculate base attendance (non-SPV logic) - used internally to avoid recursion
     */
    protected function calculateMonthlyAttendanceBase(int $employeeId, int $unitId, string $month, string $year, string $context): array
    {
        $componentCodes = array_keys(self::ATTENDANCE_COMPONENTS);
        
        $components = $this->componentModel
            ->whereIn('code', array_keys(self::ATTENDANCE_COMPONENTS))
            ->findAll();

        $componentMap = [];
        foreach ($components as $c) {
            $componentMap[$c->code] = $c->id;
        }

        $results = [];
        $attendanceScore = 0.0;
        $componentsRaw = [];

        foreach ($componentCodes as $code) {
            $componentId = $componentMap[$code] ?? null;

            if (!$componentId) {
                $componentsRaw[$code] = ['sum' => 0.0, 'count' => 0, 'normalized' => 0.0];
                $normalized = 0.0;
                $weighted = 0.0;
            } else {
                $sumResult = $this->evaluationModel
                    ->select('SUM(raw_score) as sum_raw, COUNT(*) as cnt')
                    ->where('employee_id', $employeeId)
                    ->where('kpi_component_id', $componentId)
                    ->where('period_year', $year)
                    ->where('period_month', $month)
                    ->first();

                $sumRaw = (float) ($sumResult->sum_raw ?? 0);
                $count = (int) ($sumResult->cnt ?? 0);

                $normalized = $sumRaw / 130.0 * 100.0;
                $normalized = min($normalized, 100.0);

                $weight = self::ATTENDANCE_COMPONENTS[$code]['weight'] ?? 0;
                $weighted = ($normalized / 100.0) * self::ATTENDANCE_COMPONENTS[$code]['weight'];

                $results[$code] = [
                    'normalized' => round($normalized, 4),
                    'weighted' => round($weighted, 4),
                ];

                $componentsRaw[$code] = [
                    'sum' => $sumRaw,
                    'count' => $count,
                    'normalized' => round($normalized, 4),
                ];

                $attendanceScore += $weighted;
            }
        }

        $attendanceScore = min($attendanceScore, 100.0);

        return [
            'components' => $results,
            'attendance_score' => round($attendanceScore, 4),
            'components_raw' => $componentsRaw,
        ];
    }
    
    /**
     * Get normalized score for a single component
     */
    protected function getComponentScore(int $employeeId, string $componentCode, string $month, string $year): float
    {
        $component = $this->componentModel->where('code', $componentCode)->first();
        if (!$component) {
            return 0.0;
        }
        
        $sumResult = $this->evaluationModel
            ->select('SUM(raw_score) as sum_raw')
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', $component->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();
        
        $sumRaw = (float) ($sumResult->sum_raw ?? 0);
        $normalized = $sumRaw / 130.0 * 100.0;
        return min($normalized, 100.0);
    }
    
    /**
     * Return empty attendance structure
     */
    protected function getEmptyAttendanceResult(): array
    {
        return [
            'components' => [
                'KEHADIRAN' => ['normalized' => 0.0, 'weighted' => 0.0],
                'KEBERSIHAN' => ['normalized' => 0.0, 'weighted' => 0.0],
                'SERAGAM' => ['normalized' => 0.0, 'weighted' => 0.0],
                'KEPATUHAN_SOP' => ['normalized' => 0.0, 'weighted' => 0.0],
            ],
            'attendance_score' => 0.0,
            'components_raw' => [],
        ];
    }
}

