<?php
namespace App\Services\Kpi;

use App\Models\ModelKpiComponent;
use App\Models\ModelKpiTarget;
use App\Models\ModelKpiWeight;
use App\Services\Konten\ContentKpiService;
use App\Services\Konten\MultimediaKpiService;
use App\Services\Kpi\AttendanceAggregationService;
use App\Services\Marketing\MarketingKpiService;

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
    protected $marketingService;

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
        $this->calculators['produktivitas_team'] = new ProduktivitasTeamCalculator();
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
        $omzetInfo = null; // cache ringkasan omzet SPV (target/actual per cabang) utk display
        foreach ($weights as $w) {
            $component = $this->componentModel->where('id', $w->kpi_component_id)->first();
            if (!$component || !(int)$component->is_active) {
                continue;
            }

            // ==== SUPERVISOR / SPV (jabatan 40): 7 komponen area (dihitung di SupervisorKpiService) ====
            if ($positionId === 40 && in_array($component->code, SupervisorKpiService::CODES, true)) {
                $achievement = $this->supervisorService()->achievement(
                    $component->code,
                    $employeeId,
                    $unitId,
                    (int)$month,
                    (int)$year,
                    $context,
                    $targetContext,
                    $date
                );
            // ==== DIGITAL MARKETING / KEPALA DIVISI (jabatan 43): marketing KPI (Lead/Customer/CPL/Omzet/ROAS/Channel) ====
            } elseif ($positionId === 43 && in_array($component->code, MarketingKpiService::COMPONENT_CODES, true)) {
                $achievement = $this->marketingService()->scoreByCode(
                    $component->code,
                    (int)$month,
                    (int)$year
                );
            // ==== MULTIMEDIA / CREATIVE (jabatan 44): 6 KPI Owner (MultimediaKpiService) ====
            } elseif ($positionId === 44 && in_array($component->code, MultimediaKpiService::CODES, true)) {
                $achievement = $this->multimediaService()->achievement(
                    $component->code,
                    $employeeId,
                    $unitId,
                    (int)$month,
                    (int)$year
                );
            // ==== CLOSING RATE (otomatis): prospek lead CLOSING ÷ prospek rekap harian ====
            } elseif ($component->code === 'CLOSING_RATE') {
                $achievement = (new \App\Services\Kpi\Calculators\ClosingRateCalculator())
                    ->calculate($employeeId, $unitId, (int)$month, (int)$year);
            } elseif ($component->type === 'automatic' && $component->calculation_strategy) {
                $calculator = $this->calculators[$component->calculation_strategy] ?? null;
                if ($calculator === null) {
                    continue; // strategy belum terdaftar
                }
                $actualValue = $calculator->calculate($employeeId, $unitId, $month, $year);
                $target = $this->targetModel->getTargetByKpiAndUnit($component->id, $unitId, $targetContext, $date);
                if (!$target) {
                    continue; // belum ada target utk KPI ini
                }
                
                // OMSET (Toko/Cabang): threshold tanpa tier — actual < target → 0; actual ≥ target → 100 (cap).
                if (in_array($component->code, ['OMSET_TOKO', 'OMSET_CABANG'])) {
                    $targetValue = (float) $target->target_value;
                    $achievement = ($actualValue >= $targetValue)
                        ? $this->scoreService()->achievementScore($actualValue, $targetValue)
                        : 0.0;
                } elseif ($component->code === 'OMSET_TEKNISI') {
                    // OMSET TEKNISI: realisasi HANYA omzet service yg dikerjakan teknisi
                    // (OmsetTeknisiCalculator: service.service_by, status selesai, bulan tsb).
                    // Threshold tanpa tier: actual < target → 0; actual ≥ target → 100 (cap).
                    $targetValue = (float) $target->target_value;
                    $achievement = ($actualValue >= $targetValue)
                        ? $this->scoreService()->achievementScore($actualValue, $targetValue)
                        : 0.0;
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
                    if ($positionId === 35 && $component->code === 'KEHADIRAN') {
                        // ADMIN/KASIR: KPI "Kehadiran" = kelengkapan input absen harian
                        // untuk dirinya, Teknisi, Kepala Toko, & CS (satu unit).
                        $achievement = $this->adminAttendanceInputCoverage($employeeId, $unitId, $month, $year);
                    } else {
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
                } elseif ($component->code === 'KONTROL_ASET') {
                    // Kontrol Aset = hasil audit bulanan SPV FINAL + lengkap (AsetKpiService).
                    // null = Belum Diaudit (belum final atau belum lengkap, tanpa fallback).
                    $achievement = $this->kontrolAsetScore((int)$unitId, (int)$month, (int)$year);
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

            $achievementVal = ($achievement === null) ? null : round($achievement, 4);
            $weightedVal    = ($achievement === null) ? null : round(($achievement / 100) * (float)$w->weight, 4);

            // Info target/realisasi utk tampilan (read-only, tidak mempengaruhi nilai).
            $targetInfo = null;
            if ($positionId === 40 && in_array($component->code, ['OMZET_WILAYAH', 'TARGET_CABANG'], true)) {
                if ($omzetInfo === null) {
                    $omzetInfo = $this->supervisorService()->omzetDetail(
                        $employeeId, $unitId, (int)$month, (int)$year, $targetContext, $date
                    );
                }
                if ($omzetInfo) {
                    if ($component->code === 'TARGET_CABANG') {
                        $n = count($omzetInfo['cabang']);
                        $reached = 0;
                        foreach ($omzetInfo['cabang'] as $cb) {
                            if (!empty($cb['reached'])) {
                                $reached++;
                            }
                        }
                        $targetInfo = [
                            'unit_count' => $n,
                            'reached'    => $reached,
                            'shortfall'  => max($n - $reached, 0),
                            'ho'         => true,
                            'cabang'     => $omzetInfo['cabang'],
                        ];
                    } else {
                        $targetInfo = [
                            'target'    => $omzetInfo['target_ho_total'],
                            'actual'    => $omzetInfo['actual_total'],
                            'shortfall' => $omzetInfo['shortfall_ho'],
                            'ho'        => true,
                            'cabang'    => $omzetInfo['cabang'],
                        ];
                    }
                }
            } elseif (in_array($component->code, ['OMSET_TOKO', 'OMSET_CABANG', 'OMSET_TEKNISI'], true) && isset($actualValue) && isset($target)) {
                // Non-SPV: target biasa dari kpi_targets (non HO).
                $targetInfo = [
                    'target'    => (float)$target->target_value,
                    'actual'    => (float)$actualValue,
                    'shortfall' => max((float)$target->target_value - (float)$actualValue, 0.0),
                    'ho'        => false,
                    'cabang'    => null,
                ];
            }

            $items[] = [
                'kpi_component_id'    => $component->id,
                'code'                => $component->code,
                'name'                => $component->name,
                'type'                => $component->type,
                'calculation_strategy' => $component->calculation_strategy,
                'weight'              => (float)$w->weight,
                'achievement'         => $achievementVal,
                'weighted_score'      => $weightedVal,
                'target'              => $targetInfo['target'] ?? null,
                'actual'              => $targetInfo['actual'] ?? null,
                'shortfall'           => $targetInfo['shortfall'] ?? null,
                'unit_count'          => $targetInfo['unit_count'] ?? null,
                'reached'             => $targetInfo['reached'] ?? null,
                'ho'                  => $targetInfo['ho'] ?? null,
                'cabang'              => $targetInfo['cabang'] ?? null,
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

    public function supervisorService(): SupervisorKpiService
    {
        return new SupervisorKpiService();
    }

    public function kontenService(): ContentKpiService
    {
        return new ContentKpiService();
    }

    public function multimediaService(): MultimediaKpiService
    {
        return new MultimediaKpiService();
    }

    public function marketingService(): MarketingKpiService
    {
        if ($this->marketingService === null) {
            $this->marketingService = new MarketingKpiService();
        }
        return $this->marketingService;
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

        // Build detail_kpi for views: array of ['nama','bobot','nilai', 'target', 'actual', ...]
        $detailKpi = [];
        foreach ($kpiResult['items'] as $item) {
            $detailKpi[] = [
                'nama'       => $item['name'],
                'bobot'      => $item['weight'],
                'nilai'      => $item['achievement'],
                'target'     => $item['target'] ?? null,
                'actual'     => $item['actual'] ?? null,
                'shortfall'  => $item['shortfall'] ?? null,
                'unit_count' => $item['unit_count'] ?? null,
                'reached'    => $item['reached'] ?? null,
                'ho'         => $item['ho'] ?? null,
                'cabang'     => $item['cabang'] ?? null,
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
                    WHEN ALAMAT = 'Probolinggo' AND ID_UNIT = 50 THEN 1
                    ELSE 0
                END AS penempatan
            FROM akun WHERE ID_AKUN = ?
        ", [$employeeId]);
        return $query->getRow();
    }

    /**
     * Kontrol Aset (otomatis) per unit.
     *
     * = (jumlah aset kondisi "Baik" di unit) / (total aset unit) * 100
     * - kondisi "Baik" dibanding case-insensitive (free-text kolom).
     * - Aset tanpa unit dianggap milik HO → di luar scope unit cabang.
     * - Total unit 0 → 0 (belum ada aset tercatat utk unit tsb).
     */
    /**
     * Skor KONTROL_ASET dari audit bulanan SPV FINAL (AsetKpiService).
     * null = Belum Diaudit (belum final/lengkap, tanpa fallback).
     */
    protected function kontrolAsetScore(int $unitId, int $bulan, int $tahun): ?float
    {
        return (new \App\Services\Kpi\AsetKpiService())->kpiScore($unitId, $bulan, $tahun);
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
            // Scope SPV (jabatan 40) = unit dari spv_units per supervisor,
            // BUKAN semua unit. SPV 49 → [2,3], SPV 56 → [1,4].
            $units = $this->supervisorService()->scopeUnits($employeeId, $unit);

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
        // Wajib mencapai minimal capaian target (minimum_achievement).
        // =========================================================
        $omsetToko   = $omsetCalc->calculate($employeeId, $unit, $month, $year);
        $minAch      = (float)($rule->minimum_achievement ?? 0);

        if ($minAch > 0) {
            $target = $this->targetModel->getTargetByKpiAndUnit((int)$rule->kpi_component_id, $unit, 'gaji', $date);
            if (!$target || (float)$target->target_value <= 0) {
                return 0.0;
            }
            $achievement = ($omsetToko / (float)$target->target_value) * 100;
            if ($achievement < $minAch) {
                return 0.0;
            }
        } elseif ($omsetToko <= 0) {
            return 0.0;
        }

        $pool        = ((float)$rule->base_value / 100.0) * $omsetToko;
        $memberCount = $memberModel->countActiveMembers((int)$group->id, $unit, $date);

        if ($memberCount <= 0) {
            return 0.0;
        }

        return $pool / $memberCount;
    }

    /**
     * Skor kelengkapan input absen harian ADMIN/KASIR (jabatan 35).
     *
     * Tanggungan: jabatan yang boleh dinilai admin KEHADIRAN sesuai matriks
     * EvaluatorAuthorizationService (35, 36, 41, 42) pada unit yang sama.
     * Coverage = jumlah pasangan (pegawai, tanggal) yang SUDAH di-input admin
     * dibagi jumlah hari penuh bulan × jumlah pegawai tanggungan, lalu ×100.
     */
    protected function adminAttendanceInputCoverage(int $employeeId, int $unitId, string $month, string $year): float
    {
        $db = \Config\Database::connect();

        $allowed = \App\Services\Kpi\EvaluatorAuthorizationService::allowedTargetJabatans(35);
        if (empty($allowed)) {
            return 100.0;
        }

        $responsible = $db->table('akun')
            ->select('ID_AKUN')
            ->where('STATUS_PEGAWAI', 1)
            ->where('ID_UNIT', $unitId)
            ->whereIn('ID_JABATAN', $allowed)
            ->get()
            ->getResultArray();
        $responsibleIds = array_column($responsible, 'ID_AKUN');
        if (empty($responsibleIds)) {
            return 100.0;
        }

        $daysInMonth = (int)date('t', strtotime(sprintf('%04d-%02d-01', (int)$year, (int)$month)));
        $expected    = $daysInMonth * count($responsibleIds);

        $kehadiran = $this->componentModel->where('code', 'KEHADIRAN')->first();
        $kiid      = $kehadiran ? (int)$kehadiran->id : -1;

        $covered = [];

        // Input nyata (raw_score) yang tercatat atas nama admin sebagai evaluator.
        $evals = $db->table('kpi_evaluations')
            ->select('employee_id, evaluation_date')
            ->distinct()
            ->where('evaluator_id', $employeeId)
            ->whereIn('employee_id', $responsibleIds)
            ->where('kpi_component_id', $kiid)
            ->where('period_year', (int)$year)
            ->where('period_month', (int)$month)
            ->get()
            ->getResultArray();
        foreach ($evals as $r) {
            $covered[(int)$r['employee_id'] . '|' . $r['evaluation_date']] = true;
        }

        // Hari yang ditandai OFF oleh admin (input lengkap tanpa nilai harian).
        $offs = $db->table('kpi_attendance_off')
            ->select('employee_id, evaluation_date')
            ->distinct()
            ->where('evaluator_id', $employeeId)
            ->whereIn('employee_id', $responsibleIds)
            ->where('period_year', (int)$year)
            ->where('period_month', (int)$month)
            ->get()
            ->getResultArray();
        foreach ($offs as $r) {
            $covered[(int)$r['employee_id'] . '|' . $r['evaluation_date']] = true;
        }

        $actual = count($covered);
        if ($expected <= 0) {
            return 100.0;
        }

        return min(round($actual / $expected * 100, 2), 100.0);
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