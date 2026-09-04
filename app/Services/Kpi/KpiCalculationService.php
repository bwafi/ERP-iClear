<?php
namespace App\Services\Kpi;

use App\Models\ModelKpiComponent;
use App\Models\ModelKpiTarget;
use App\Models\ModelKpiWeight;
use App\Services\Kpi\AttendanceAggregationService;

/**
 * KpiCalculationService — FINAL SERVICE (config-driven)
 *
 * Mengambil configuration dari DATABASE (kpi_components, kpi_weights, kpi_targets)
 * dan menghitung KPI score + weighted score.
 *
 * BUKAN tempat business-rule hardcoded. Rumus ada di Calculator/Strategy.
 *
 * Serving period: bulan/tahun DITERUSKAN oleh caller (tidak pakai date()).
 *
 * @see LegacyKpiCalculationService untuk replicasi OLD (regression).
 */
class KpiCalculationService
{
    protected $componentModel;
    protected $targetModel;
    protected $weightModel;
    protected $calculators = [];
    protected $attendanceAggregationService;

    public function __construct()
    {
        $this->componentModel = new ModelKpiComponent();
        $this->targetModel    = new ModelKpiTarget();
        $this->weightModel    = new ModelKpiWeight();
        $this->attendanceAggregationService = new AttendanceAggregationService();

        $this->registerCalculators();
    }

    protected function registerCalculators()
    {
        $this->calculators['omset_toko']     = new OmsetTokoCalculator();
        $this->calculators['customer_count'] = new CustomerCalculator();
        $this->calculators['omset_cabang']   = new OmsetCabangCalculator();
        $this->calculators['omset_teknisi']  = new OmsetTeknisiCalculator();
        $this->calculators['tutup_kasir']    = new TutupKasirCalculator();
        $this->calculators['stok_opname']    = new StokOpnameCalculator();
    }

    /**
     * Hitung seluruh KPI (automatic) utk satu employee dalam satu periode.
     *
     * @param int    $employeeId
     * @param int    $unitId
     * @param string $month  'MM'
     * @param string $year   'YYYY'
     * @param string $context 'gaji'|'penilaian_kinerja'|'slip_gaji'
     */
    public function calculateForEmployee(int $employeeId, int $unitId, string $month, string $year, string $context = 'default', ?string $date = null)
    {
        $date = $date ?? sprintf('%04d-%02d-15', (int)$year, (int)$month);

        // Target lookup context: slip_gaji uses penilaian_kinerja targets
        // (identical business rule in legacy: non-'gaji' context share targets).
        $targetContext = ($context === 'slip_gaji') ? 'penilaian_kinerja' : $context;

        $positionId = $this->getPositionOfEmployee($employeeId);

        $weights = $this->weightModel->getByPosition($positionId, $date, 'kpi');

        $items = [];
        foreach ($weights as $w) {
            $component = $this->componentModel->where('id', $w->kpi_component_id)->first();
            if (!$component || !(int)$component->is_active) {
                continue;
            }

            // ==== AUTOMATIC: pakai calculator strategy ====
            if ($component->type === 'automatic' && $component->calculation_strategy) {
                $calculator = $this->calculators[$component->calculation_strategy] ?? null;
                if ($calculator === null) {
                    continue; // strategy belum terdaftar
                }
                $actualValue = $calculator->calculate($employeeId, $unitId, $month, $year);
                $target = $this->targetModel->getTargetByKpiAndUnit($component->id, $unitId, $targetContext, $date);
                if (!$target) {
                    continue; // belum ada target utk KPI ini
                }
                
                // OMSET: gunakan tiered scoring jika batas tersedia di target
                if (in_array($component->code, ['OMSET_TOKO', 'OMSET_CABANG'])) {
                    $achievement = $this->omsetTieredAchievement($positionId, $actualValue, $target, $context);
                } elseif ($component->code === 'OMSET_TEKNISI') {
                    // OMSET TEKNISI: rasio sederhana aktual omset cabang utuh / target per teknisi, di-cap 100
                    // Kebijakan: jumlah teknisi per cabang = 2 (konstanta), target per teknisi = target cabang / 2
                    // Target per teknisi sudah disimpan di kpi_targets.target_value oleh seeder
                    $achievement = $this->scoreService()->achievementScore($actualValue, (float)$target->target_value);
                } elseif ($component->code === 'CUSTOMER_COUNT') {
                    // CUSTOMER: Jika ada batas_bawah (batas_awal) & batas_atas (batas_keempat)
                    // Rule: jika actual >= batas_bawah, maka achievement = (actual / batas_atas) * 100
                    // Jika actual < batas_bawah, maka 0 (atau proporsional jika batas_bawah tidak ada)
                    $batasBawah = (float)($target->batas_awal ?? 0);
                    $batasAtas  = (float)($target->batas_keempat ?? 0);

                    if ($batasBawah > 0 && $batasAtas > 0) {
                        if ($actualValue >= $batasBawah) {
                            $achievement = min(($actualValue / $batasAtas) * 100.0, 100.0);
                        } else {
                            $achievement = 0.0;
                        }
                    } else {
                        // Fallback jika tidak ada range batas: capped ratio terhadap target_value
                        $achievement = $this->scoreService()->achievementScore($actualValue, (float)$target->target_value);
                    }
                } else {
                    // Other automatic: capped ratio
                    $achievement = $this->scoreService()->achievementScore($actualValue, (float)$target->target_value);
                }
            } else {
                // ==== MANUAL KPI ====
                $achievement = 0.0;
                $attendanceCodes = ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'];
                
                if (in_array($component->code, $attendanceCodes)) {
                    // Attendance: dari kpi_evaluations via AttendanceAggregationService
                    $attendanceResult = $this->attendanceAggregationService->calculateMonthlyAttendance(
                        $employeeId,
                        $unitId,
                        $month,
                        $year,
                        $context
                    );
                    
                    if (isset($attendanceResult['components'][$component->code])) {
                        $achievement = $attendanceResult['components'][$component->code]['normalized'];
                    }
                } elseif ($component->code === 'OPERASIONAL' && $positionId === 40) {
                    // OPERASIONAL: SPV uses cabang-aman logic (count units meeting threshold)
                    $operasionalCalc = new \App\Services\Kpi\Calculators\OperasionalCalculator();
                    $achievement = $operasionalCalc->calculate(
                        $employeeId,
                        $positionId,
                        $unitId,
                        $month,
                        $year,
                        $targetContext,
                        $date
                    );
                } else {
                    // Manual non-attendance: gunakan ManualKpiScorer
                    $scorer = new ManualKpiScorer();
                    $scorerCtx = [
                        'employee_id' => $employeeId,
                        'unit'        => $unitId,
                        'month'       => $month,
                        'year'        => $year,
                        'context'     => $context,
                        'date'        => $date,
                    ];
                    $achievement = $scorer->achievement($component->code, $scorerCtx);
                }
            }

            $items[] = [
                'kpi_component_id'    => $component->id,
                'code'                => $component->code,
                'name'                => $component->name,
                'type'                => $component->type,
                'calculation_strategy' => $component->calculation_strategy,
                'weight'              => (float)$w->weight,
                'achievement'         => round($achievement, 4),
                'weighted_score'      => round(($achievement / 100) * (float)$w->weight, 4),
            ];
        }

        $scoreService = $this->scoreService();

        // Attendance monthly (group 'absen') — SUMBER: kpi_evaluations.raw_score.
        // skor_total2 = attendance_score (weighted 40/20/20/20, capped 100).
        // TIDAK memakai AVG / normalized_score per-event.
        $attendance = $this->attendanceAggregationService->calculateMonthlyAttendance(
            $employeeId,
            $unitId,
            $month,
            $year,
            $context
        );
        $skorTotal2 = $attendance['attendance_score'];

        return [
            'employee_id'  => $employeeId,
            'unit_id'      => $unitId,
            'position_id'  => $positionId,
            'context'      => $context,
            'period'       => sprintf('%04d-%02d', (int)$year, (int)$month),
            'items'        => $items,
            'total_score'  => round($scoreService->totalWeightedScore(
                array_map(fn($i) => ['nilai' => $i['achievement'], 'bobot' => $i['weight']], $items)
            ), 4),
            'skor_total2'  => round($skorTotal2, 4),
            'attendance'   => $attendance,
            'detail_absen' => $attendance['components'] ?? [],
            'weight_valid' => $scoreService->validateWeights(
                array_map(fn($i) => ['bobot' => $i['weight']], $items)
            ),
        ];
    }

    public function scoreService(): KpiScoreService
    {
        return new KpiScoreService();
    }

    protected function getPositionOfEmployee(int $employeeId): ?int
    {
        $model = new \App\Models\ModelAuth();
        $emp = $model->where('ID_AKUN', $employeeId)->first();
        return $emp ? (int)$emp->ID_JABATAN : null;
    }

    public function calculateAchievement($actualValue, $targetValue)
    {
        return $this->scoreService()->achievementScore((float)$actualValue, (float)$targetValue);
    }

    public function validatePositionWeights($positionId, $date = null)
    {
        $result = $this->weightModel->validateAllGroups($positionId, $date);
        return $result['kpi']['is_valid'] ?? false;
    }

    public function getWeightsByPosition($positionId, $date = null, $group = null)
    {
        return $this->weightModel->getByPosition($positionId, $date, $group);
    }

    /**
     * Full salary contract — replaces LegacyKpiCalculationService::calculate() output.
     *
     * Returns the EXACT contract consumed by:
     *   - SalaryCalculationService::calculateSalary()
     *   - View templates (gaji, penilaian_kinerja, slip_gaji)
     *   - Controller (jabatan, unit, karyawan, akun, etc.)
     *
     * DB-driven rules:
     *   - KPI items → kpi_components + kpi_weights + calculator strategies
     *   - Targets   → kpi_targets (per component, unit, context)
     *   - Attendance → kpi_evaluations (daily raw_score → monthly aggregation)
     *   - Incentive  → incentive_rules / incentive_members (group-based pool)
     *   - Penempatan → akun.alamat + akun.ID_UNIT
     *
     * Intentional delta vs legacy:
     *   - Attendance uses kpi_evaluations (not legacy penilaian table)
     *   - Incentive follows DB group rules (not legacy per-jabatan /4)
     *   - Non-attendance manual KPIs read from legacy penilaian table
     *     (MetricCalculator) until kpi_evaluations fully populated
     */
    public function calculateForSalary(
        int $employeeId,
        string $month,
        string $year,
        string $context = 'gaji',
        ?string $date = null,
        ?string $periodeDate = null
    ): array {
        $date      = $date ?? sprintf('%04d-%02d-15', (int)$year, (int)$month);
        $periodeDate = $periodeDate ?? sprintf('%04d-%02d-15', (int)$year, (int)$month);
        $bulan     = (int)$month;
        $tahun     = (int)$year;

        // ── Employee & Position ────────────────────────────────
        $authModel  = new \App\Models\ModelAuth();
        $karyawanObj = $authModel->where('ID_AKUN', $employeeId)->first();
        $karyawan   = $karyawanObj ? (array) $karyawanObj : [];
        $positionId = $karyawanObj ? (int)$karyawanObj->ID_JABATAN : null;
        $unit       = $karyawanObj ? (int)$karyawanObj->ID_UNIT    : 0;

        // ── Penempatan (location allowance) ────────────────────
        $akun = $this->queryAkun($employeeId);
        $akun->tunjangan_penempatan = ($akun->penempatan == 0) ? 350000 : 0;

        // ── KPI items (group kpi) ──────────────────────────────
        $kpiResult = $this->calculateForEmployee($employeeId, $unit, $month, $year, $context, $date);

        $skorTotal  = min($kpiResult['total_score'], 100.0);
        $skorTotal2 = $kpiResult['skor_total2'];

        // Build detail_kpi for views: array of ['nama','bobot','nilai']
        $detailKpi = [];
        foreach ($kpiResult['items'] as $item) {
            $detailKpi[] = [
                'nama'  => $item['name'],
                'bobot' => $item['weight'],
                'nilai' => $item['achievement'],
            ];
        }

        // Build detail_absen for views: array of ['nama','bobot','nilai']
        $absenWeights = $this->weightModel->getByPosition($positionId, $date, 'absen');
        $detailAbsen  = [];
        foreach ($absenWeights as $w) {
            $component = $this->componentModel->where('id', $w->kpi_component_id)->first();
            if (!$component) {
                continue;
            }
            $normalized = 0.0;
            if (isset($kpiResult['attendance']['components'][$component->code])) {
                $normalized = (float) $kpiResult['attendance']['components'][$component->code]['normalized'];
            }
            $detailAbsen[] = [
                'nama'  => $component->name,
                'bobot' => (float)$w->weight,
                'nilai' => round($normalized, 2),
            ];
        }

        // ── Omset per unit (for views) ────────────────────────
        $omsetCalc   = new OmsetTokoCalculator();
        $aktualOmset = [];
        for ($u = 1; $u <= 4; $u++) {
            $aktualOmset[$u] = $omsetCalc->calculate($employeeId, $u, $month, $year);
        }

        // ── Incentive via IncentiveCalculationService ──────────
        $insentif = $this->computeIncentive($employeeId, $unit, $month, $year, $date);

        // ── Gaji pokok from salary_structures ──────────────────
        $gajiPokok = $this->getGajiPokok($positionId, $unit, $context, $periodeDate);

        // ── Placeholder; computed by SalaryCalculationService ──
        $tunjanganKinerja = 0.0;
        $tunjanganAbsen   = 0.0;
        $gaji             = 0.0;

        return [
            'karyawan'          => $karyawan,
            'akun'              => $akun,
            'jabatan'           => $positionId,
            'unit'              => $unit,
            'aktual_omset_unit' => $aktualOmset,
            'detail_kpi'        => $detailKpi,
            'detail_absen'      => $detailAbsen,
            'skor_total'        => round($skorTotal, 2),
            'skor_total2'       => round($skorTotal2, 2),
            'tunjangan_kinerja' => $tunjanganKinerja,
            'tunjangan_absen'   => $tunjanganAbsen,
            'insentif'          => round($insentif, 2),
            'gaji_pokok'        => $gajiPokok,
            'gaji'              => $gaji,
        ];
    }

    /** Query akun + penempatan case. */
    protected function queryAkun(int $employeeId)
    {
        $db = \Config\Database::connect();
        $query = $db->query("
            SELECT
                NAMA_AKUN, ALAMAT, ID_UNIT,
                CASE
                    WHEN ALAMAT = 'Probolinggo' AND ID_UNIT = 1 THEN 1
                    WHEN ALAMAT = 'Jember'       AND ID_UNIT = 2 THEN 1
                    WHEN ALAMAT = 'Banyuwangi'   AND ID_UNIT = 3 THEN 1
                    ELSE 0
                END AS penempatan
            FROM akun WHERE ID_AKUN = ?
        ", [$employeeId]);
        return $query->getRow();
    }

    /**
     * Compute incentive via DB-driven IncentiveCalculationService.
     *
     * Business rule (confirmed): KT pool = 3% × omzet / active members.
     * SPV/other jabatan → checks incentive_members membership.
     */
    protected function computeIncentive(
        int $employeeId,
        int $unit,
        string $month,
        string $year,
        string $date
    ): float {
        $memberModel = new \App\Models\ModelIncentiveMember();
        $groupModel  = new \App\Models\ModelIncentiveGroup();
        $ruleModel   = new \App\Models\ModelIncentiveRule();
        $omsetCalc   = new OmsetTokoCalculator();

        $member = $memberModel->where('employee_id', $employeeId)->where('is_active', 1)->first();
        if (!$member) {
            return 0.0;
        }

        $group = $groupModel->where('id', $member->incentive_group_id)->first();
        if (!$group || !(int)$group->is_active) {
            return 0.0;
        }

        $date = $date ?? date('Y-m-d');
        $rule = $ruleModel->where('incentive_group_id', $group->id)
            ->where('effective_from <=', $date)
            ->groupStart()
                ->where('effective_to >=', $date)
                ->orWhere('effective_to IS NULL')
            ->groupEnd()
            ->first();
        if (!$rule) {
            return 0.0;
        }

        // =========================================================
        // SPECIAL: SPV (0.5% per branch meeting target)
        // =========================================================
        if ($group->code === 'SPV') {
            $db = \Config\Database::connect();
            $totalIncentive = 0.0;
            $units = [1, 2, 3, 4]; // Production units
            
            foreach ($units as $uId) {
                $actualOmset = $omsetCalc->calculate(0, $uId, $month, $year);
                $target = $this->targetModel->getTargetByKpiAndUnit($rule->kpi_component_id, $uId, 'gaji', $date);
                
                if ($target && $actualOmset >= (float)$target->target_value) {
                    $totalIncentive += $actualOmset * ((float)$rule->base_value / 100.0);
                }
            }
            return $totalIncentive;
        }

        // =========================================================
        // DEFAULT: Group Pool (KT, Digital, etc.)
        // =========================================================
        $omsetToko   = $omsetCalc->calculate($employeeId, $unit, $month, $year);
        $pool        = ((float)$rule->base_value / 100.0) * $omsetToko;
        $memberCount = $memberModel->countActiveMembers((int)$group->id, $unit, $date);

        if ($memberCount <= 0) {
            return 0.0;
        }

        return $pool / $memberCount;
    }

    /** GAJI_POKOK from salary_structures. */
    protected function getGajiPokok(int $positionId, int $unit, string $context, string $date): float
    {
        $salaryService = new \App\Services\Payroll\SalaryCalculationService();
        $structure = $salaryService->getSalaryStructure($positionId, $unit, $context, $date);
        foreach ($structure as $struct) {
            if ($struct->code === 'GAJI_POKOK') {
                return (float)$struct->base_value;
            }
        }
        return 1500000.0;
    }

    /**
     * Tiered achievement score for omset based on DB batas_* columns.
     */
    protected function omsetTieredAchievement(int $positionId, float $actual, $target, string $context): float
    {
        if (!$target || $target->target_value <= 0) {
            return 0.0;
        }

        $val = (float)$target->target_value;
        $b1  = (float)($target->batas_awal ?? 0);
        $b2  = (float)($target->batas_kedua ?? 0);
        $b3  = (float)($target->batas_ketiga ?? 0);
        $b4  = (float)($target->batas_keempat ?? 0);

        // Tiered calculation based on thresholds
        if ($actual >= $b4) return 100.0;
        if ($actual >= $b3) return 75.0;
        if ($actual >= $b2) return 50.0;
        if ($actual >= $b1) return 25.0;
        
        // Linear fallback if below b1 but above 0
        if ($b1 > 0) {
            return min(($actual / $b1) * 25.0, 25.0);
        }
        
        return 0.0;
    }

    public function getWeightValidationResult($positionId, $date = null, $group = 'kpi')
    {
        $weights = $this->weightModel->getByPosition($positionId, $date, $group);
        $total = array_reduce($weights, fn($sum, $w) => $sum + (float)$w->weight, 0.0);

        return [
            'weights'      => $weights,
            'total_weight' => $total,
            'is_valid'     => abs($total - 100) < 0.01,
            'difference'   => abs($total - 100),
        ];
    }
}