<?php
namespace App\Services\Kpi;

use App\Models\ModelKpiComponent;
use App\Models\ModelKpiTarget;
use App\Models\ModelKpiWeight;
use App\Services\Finance\FinanceKpiService;
use App\Services\Konten\ContentKpiService;
use App\Services\Konten\MultimediaKpiService;
use App\Services\Kpi\AttendanceAggregationService;
use App\Services\Marketing\DigitalMarketingKpiService;
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
    /** Jabatan Customer Service. */
    public const CS_POSITION_ID = 42;

    /**
     * Unit sumber OMSET_TOKO untuk Customer Service.
     *
     * CS ditempatkan di Head Office (unit 50) yang tidak mencatat penjualan,
     * sehingga OMSET_TOKO selalu bernilai 0 bila memakai unit penempatan sendiri.
     * CS dinilai dari omset toko unit 1 (ICLEAR Probolinggo).
     */
    public const CS_OMSET_UNIT_ID = 1;

    /**
     * Unit 1 (ICLEAR Probolinggo): Kepala Toko-nya merangkap Admin dan tidak
     * mengerjakan service sendiri. Realisasi Total Pelanggan-nya diambil dari
     * akumulasi service para teknisi unit ini (CustomerCalculator::handledByUnitTeknisi),
     * bukan dari service yang ia tangani sendiri.
     */
    public const KEPALA_TOKO_TEKNISI_UNIT_ID = 1;

    /**
     * NAMA_UNIT Head Office — unit pusat, bukan cabang.
     *
     * HO tidak bertransaksi penjualan sehingga omsetnya 0; memaksakan HO
     * masuk daftar cabang akan menambah baris kosong pada ringkasan omset.
     */
    public const HO_UNIT_NAME = "Head Office";

    /** Unit Head Office. */
    public const HO_UNIT_ID = 50;

    /** Unit ICLEAR Probolinggo. */
    public const PROBOLINGGO_UNIT_ID = 1;

    /** Aturan insentif HO & Probolinggo berlaku mulai periode ini. */
    public const INCENTIVE_RULE_V2_START = "2026-10-01";

    /** Jabatan staff toko yang ikut pool insentif Probolinggo. */
    private const STORE_POSITION_IDS = [35, 36, 41, 42];

    protected $componentModel;
    protected $targetModel;
    protected $weightModel;
    protected $calculators = [];
    protected $attendanceAggregationService;
    protected $marketingService;
    protected $digitalMarketingService;
    protected $financeKpiService;
    protected $managerKpiService;

    public function __construct()
    {
        $this->componentModel = new ModelKpiComponent();
        $this->targetModel = new ModelKpiTarget();
        $this->weightModel = new ModelKpiWeight();
        $this->attendanceAggregationService = new AttendanceAggregationService();

        $this->registerCalculators();
    }

    protected function registerCalculators()
    {
        $this->calculators["omset_toko"] = new OmsetTokoCalculator();
        $this->calculators["customer_count"] = new CustomerCalculator();
        $this->calculators["omset_cabang"] = new OmsetCabangCalculator();
        $this->calculators["omset_teknisi"] = new OmsetTeknisiCalculator();
        $this->calculators["tutup_kasir"] = new TutupKasirCalculator();
        $this->calculators["stok_opname"] = new StokOpnameCalculator();
        $this->calculators[
            "produktivitas_team"
        ] = new ProduktivitasTeamCalculator();
    }

    /**
     * Cache per-request (lihat AttendanceAggregationService::$memo).
     * Komponen & jabatan pegawai adalah data acuan: dibaca berkali-kali dalam
     * satu perhitungan, tidak berubah selama request.
     */
    private static $memo = [];

    protected function componentById(int $componentId)
    {
        $key = \App\Libraries\ReadCache::kunci("component", $componentId);
        if (!isset(self::$memo[$key])) {
            self::$memo[$key] = $this->componentModel
                ->where("id", $componentId)
                ->first();
        }

        return self::$memo[$key];
    }

    /**
     * Realisasi satu komponen via calculator-nya, dihitung sekali per periode.
     * Formula kalkulator hanya bergantung pada argumen ini (bukan urutan
     * pemanggilan), dan tabel penjualan/service tidak ditulis selama request.
     */
    protected function calculatorValue(
        string $strategy,
        int $employeeId,
        int $unitId,
        int $month,
        int $year
    ) {
        $key = \App\Libraries\ReadCache::kunci(
            "calc",
            $strategy,
            $employeeId,
            $unitId,
            $month,
            $year,
        );
        if (!array_key_exists($key, self::$memo)) {
            self::$memo[$key] = $this->calculators[$strategy]->calculate(
                $employeeId,
                $unitId,
                $month,
                $year,
            );
        }

        return self::$memo[$key];
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
    public function calculateForEmployee(
        int $employeeId,
        int $unitId,
        string $month,
        string $year,
        string $context = "default",
        ?string $date = null
    ) {
        $date = $date ?? sprintf("%04d-%02d-15", (int) $year, (int) $month);

        // Target lookup: konteks sudah tunggal ('default') — konteks pemanggil
        // tidak lagi menentukan baris target (semua baris sama).

        $positionId = $this->getPositionOfEmployee($employeeId);

        $weights = $this->weightModel->getByPosition($positionId, $date, "kpi");

        $items = [];
        $omzetInfo = null; // cache ringkasan omzet SPV (target/actual per cabang) utk display
        $digitalData = null; // cache data target/actual KPI Digital Marketing (jabatan 43)
        $financeData = null; // cache data target/actual KPI Finance (jabatan 0)
        $managerData = null; // cache data target/actual KPI Manager (jabatan 34)
        foreach ($weights as $w) {
            $component = $this->componentById((int) $w->kpi_component_id);
            if (!$component || !(int) $component->is_active) {
                continue;
            }

            // ==== SUPERVISOR / SPV (jabatan 40): 7 komponen area (dihitung di SupervisorKpiService) ====
            if (
                $positionId === 40 &&
                in_array($component->code, SupervisorKpiService::CODES, true)
            ) {
                $achievement = $this->supervisorService()->achievement(
                    $component->code,
                    $employeeId,
                    $unitId,
                    (int) $month,
                    (int) $year,
                    $context,
                    $context,
                    $date,
                );
                // ==== DIGITAL MARKETING / KEPALA DIVISI (jabatan 43): 7 KPI Digital Marketing
                // (OMZET_GLOBAL/LEADS_QUALITY/CONVERSION/CPL/CAMPAIGN_PERFORMANCE/REPORTING/BUDGETING)
                // + IMPROVEMENT untuk periode sebelum bobotnya diganti Budgeting (2026-10-01) ====
            } elseif (
                $positionId === 43 &&
                (
                    in_array($component->code, DigitalMarketingKpiService::CODES, true) ||
                    in_array($component->code, DigitalMarketingKpiService::HISTORICAL_CODES, true)
                )
            ) {
                $digitalData = $this->digitalMarketingService()->componentData(
                    $component->code,
                    (int) $employeeId,
                    (int) $unitId,
                    (int) $month,
                    (int) $year,
                    $context,
                    $date,
                );
                $achievement = $digitalData["achievement"];
                // ==== MULTIMEDIA / CREATIVE (jabatan 44): 6 KPI Owner (MultimediaKpiService) ====
            } elseif (
                $positionId === 44 &&
                in_array($component->code, MultimediaKpiService::CODES, true)
            ) {
                $achievement = $this->multimediaService()->achievement(
                    $component->code,
                    $employeeId,
                    $unitId,
                    (int) $month,
                    (int) $year,
                );
                // ==== FINANCE (jabatan 0): 7 KPI Finance (rata-rata antar-cabang) ====
                // Nilai tiap KPI = rata-rata skor cabang dari scorecard Dashboard
                // Finance. Bobot mengikuti kpi_weights posisi 0 (Config\Finance).
            } elseif (
                $positionId === 0 &&
                in_array($component->code, FinanceKpiService::CODES, true)
            ) {
                $financeData = $this->financeKpiService()->componentData(
                    $component->code,
                    (int) $month,
                    (int) $year,
                );
                $achievement = $financeData["achievement"];
                // ==== MANAGER (jabatan 34): 7 komponen perusahaan (ManagerKpiService) ====
                // Omzet Global (rumus sama Kadiv), Profit & Kesehatan (Finance),
                // Pencapaian Cabang (semua cabang), Efektivitas Organisasi (KPI SPV),
                // SDM & Leadership (KPI bawahan langsung), dan 2 komponen manual.
            } elseif (
                $positionId === 34 &&
                in_array($component->code, ManagerKpiService::CODES, true)
            ) {
                $managerData = $this->managerService()->componentData(
                    $component->code,
                    (int) $employeeId,
                    (int) $unitId,
                    (int) $month,
                    (int) $year,
                    $context,
                    $date,
                );
                $achievement = $managerData["achievement"];
                // ==== CLOSING RATE (otomatis): prospek lead CLOSING ÷ prospek rekap harian ====
            } elseif ($component->code === "CLOSING_RATE") {
                $achievement = (new \App\Services\Kpi\Calculators\ClosingRateCalculator())->calculate(
                    $employeeId,
                    $unitId,
                    (int) $month,
                    (int) $year,
                );
            } elseif (
                $component->type === "automatic" &&
                $component->calculation_strategy
            ) {
                $calculator =
                    $this->calculators[$component->calculation_strategy] ??
                    null;
                if ($calculator === null) {
                    continue; // strategy belum terdaftar
                }
                // Unit sumber omset: default unit penempatan, kecuali CS (unit 1).
                $omsetUnitId = $this->resolveOmsetUnitId(
                    $positionId,
                    $component->code,
                    (int) $unitId,
                );
                $actualValue = $this->calculatorValue(
                    $component->calculation_strategy,
                    (int) $employeeId,
                    $omsetUnitId,
                    (int) $month,
                    (int) $year,
                );
                $target = $this->targetModel->getTargetByKpiAndUnit(
                    $component->id,
                    $omsetUnitId,
                    $context,
                    $date,
                );
                if (!$target) {
                    continue; // belum ada target utk KPI ini
                }

                // OMSET (Toko/Cabang): threshold tanpa tier — actual < target → 0; actual ≥ target → 100 (cap).
                if (
                    in_array($component->code, ["OMSET_TOKO", "OMSET_CABANG"])
                ) {
                    $targetValue = (float) $target->target_value;
                    $achievement =
                        $actualValue >= $targetValue
                            ? $this->scoreService()->achievementScore(
                                $actualValue,
                                $targetValue,
                            )
                            : 0.0;
                } elseif ($component->code === "OMSET_TEKNISI") {
                    // OMSET TEKNISI: realisasi HANYA omzet service yg dikerjakan teknisi
                    // (OmsetTeknisiCalculator: service.service_by, status selesai, bulan tsb).
                    // Threshold tanpa tier: actual < target → 0; actual ≥ target → 100 (cap).
                    $targetValue = (float) $target->target_value;
                    $achievement =
                        $actualValue >= $targetValue
                            ? $this->scoreService()->achievementScore(
                                $actualValue,
                                $targetValue,
                            )
                            : 0.0;
                } elseif ($component->code === "CUSTOMER_COUNT") {
                    // target_value DB = target pelanggan UNIT (full): BWI 220 / JBR 180 /
                    // BWI 350 / Pandaan 250. Target per pegawai = target_value ÷ 2
                    // (dibagi di kode, bukan di DB) utk teknisi & kepala toko.
                    // KECUALI Kepala Toko unit 1: realisasi = akumulasi teknisi unit
                    // (skala unit), jadi target memakai nilai PENUH (lihat customerTarget).
                    // Nilai KPI 0 jika aktual < target; jika tercapai → min(ratio, 100).
                    $effectiveTarget = $this->customerTarget(
                        $positionId,
                        (int) $unitId,
                        (float) $target->target_value,
                    );

                    if (in_array($positionId, [36, 41], true)) {
                        // TEKNISI & KEPALA TOKO: aktual = service yg ditangani.
                        // Khusus Kepala Toko unit 1: akumulasi service para teknisi
                        // unit tsb (lihat customerHandledActual) — TIDAK menghitung
                        // seluruh pelanggan unit.
                        $handled = $this->customerHandledActual(
                            $positionId,
                            $employeeId,
                            (int) $unitId,
                            (int) $month,
                            (int) $year,
                        );
                        $achievement =
                            $effectiveTarget > 0 && $handled >= $effectiveTarget
                                ? min(
                                    ($handled / $effectiveTarget) * 100.0,
                                    100.0,
                                )
                                : 0.0;
                    } else {
                        // Lainnya: capped ratio terhadap target efektif,
                        // tanpa threshold. 0 jika belum mencapai target.
                        $achievement =
                            $effectiveTarget > 0 &&
                            $actualValue >= $effectiveTarget
                                ? $this->scoreService()->achievementScore(
                                    $actualValue,
                                    $effectiveTarget,
                                )
                                : 0.0;
                    }
                } else {
                    // Other automatic: capped ratio
                    $achievement = $this->scoreService()->achievementScore(
                        $actualValue,
                        (float) $target->target_value,
                    );
                }
            } else {
                // ==== MANUAL KPI ====
                $achievement = 0.0;
                $attendanceCodes = [
                    "KEHADIRAN",
                    "KEBERSIHAN",
                    "SERAGAM",
                    "KEPATUHAN_SOP",
                ];

                if (in_array($component->code, $attendanceCodes)) {
                    if (
                        $positionId === 35 &&
                        $component->code === "KEHADIRAN"
                    ) {
                        // ADMIN/KASIR: KPI "Kehadiran" = kelengkapan input absen harian
                        // untuk dirinya, Teknisi, Kepala Toko, & CS (satu unit).
                        $achievement = $this->adminAttendanceInputCoverage(
                            $employeeId,
                            $unitId,
                            $month,
                            $year,
                        );
                    } else {
                        // Attendance: dari kpi_evaluations via AttendanceAggregationService
                        $attendanceResult = $this->attendanceAggregationService->calculateMonthlyAttendance(
                            $employeeId,
                            $unitId,
                            $month,
                            $year,
                            $context,
                        );

                        if (
                            isset(
                                $attendanceResult["components"][
                                    $component->code
                                ],
                            )
                        ) {
                            $achievement =
                                $attendanceResult["components"][
                                    $component->code
                                ]["normalized"];
                        }
                    }
                } elseif (
                    $component->code === "OPERASIONAL" &&
                    $positionId === 40
                ) {
                    // OPERASIONAL: SPV uses cabang-aman logic (count units meeting threshold)
                    $operasionalCalc = new \App\Services\Kpi\Calculators\OperasionalCalculator();
                    $achievement = $operasionalCalc->calculate(
                        $employeeId,
                        $positionId,
                        $unitId,
                        $month,
                        $year,
                        $date,
                    );
                } elseif ($component->code === "KONTROL_ASET") {
                    // Kontrol Aset = hasil audit bulanan SPV FINAL + lengkap (AsetKpiService).
                    // null = Belum Diaudit (belum final atau belum lengkap, tanpa fallback).
                    $achievement = $this->kontrolAsetScore(
                        (int) $unitId,
                        (int) $month,
                        (int) $year,
                    );
                } else {
                    // Manual non-attendance: gunakan ManualKpiScorer
                    $scorer = new ManualKpiScorer();
                    $scorerCtx = [
                        "employee_id" => $employeeId,
                        "unit" => $unitId,
                        "month" => $month,
                        "year" => $year,
                        "context" => $context,
                        "date" => $date,
                    ];
                    $achievement = $scorer->achievement(
                        $component->code,
                        $scorerCtx,
                    );
                }
            }

            $achievementVal =
                $achievement === null ? null : round($achievement, 4);
            $weightedVal =
                $achievement === null
                    ? null
                    : round(($achievement / 100) * (float) $w->weight, 4);

            // Info target/realisasi utk tampilan (read-only, tidak mempengaruhi nilai).
            $targetInfo = null;
            if (
                $positionId === 40 &&
                in_array(
                    $component->code,
                    ["OMZET_WILAYAH", "TARGET_CABANG"],
                    true,
                )
            ) {
                if ($omzetInfo === null) {
                    $omzetInfo = $this->supervisorService()->omzetDetail(
                        $employeeId,
                        $unitId,
                        (int) $month,
                        (int) $year,
                        $context,
                        $date,
                    );
                }
                if ($omzetInfo) {
                    if ($component->code === "TARGET_CABANG") {
                        // Hanya cabang yang punya target yang dihitung, sama
                        // seperti SupervisorKpiService::targetCabang(). Cabang
                        // tanpa target (mis. Unit 5 yang belum punya
                        // kpi_targets) tidak boleh tampil sebagai "kurang",
                        // karena nilainya memang tidak pernah masuk skor.
                        $terhitung = array_values(
                            array_filter(
                                $omzetInfo["cabang"],
                                static fn($cb) => $cb["target_ho"] !== null,
                            ),
                        );
                        $n = count($terhitung);
                        $reached = 0;
                        foreach ($terhitung as $cb) {
                            if (!empty($cb["reached"])) {
                                $reached++;
                            }
                        }
                        $targetInfo = [
                            "unit_count" => $n,
                            "reached" => $reached,
                            "shortfall" => max($n - $reached, 0),
                            "ho" => true,
                            "cabang" => $terhitung,
                        ];
                    } else {
                        $targetInfo = [
                            "target" => $omzetInfo["target_ho_total"],
                            "actual" => $omzetInfo["actual_total"],
                            "shortfall" => $omzetInfo["shortfall_ho"],
                            "ho" => true,
                            "cabang" => $omzetInfo["cabang"],
                        ];
                    }
                }
            } elseif (
                $component->code === "CUSTOMER_COUNT" &&
                isset($actualValue) &&
                isset($target)
            ) {
                // CUSTOMER_COUNT: target per pegawai = target_value(unit, full) ÷ 2,
                // kecuali Kepala Toko unit 1 (target penuh — lihat customerTarget).
                // Aktual teknisi & kepala toko = service yg ditangani (service_by);
                // aktual lainnya = TotalCustomer (service + penjualan ber-id pelanggan).
                $effectiveTarget = $this->customerTarget(
                    $positionId,
                    (int) $unitId,
                    (float) $target->target_value,
                );
                if (in_array($positionId, [36, 41], true)) {
                    $handled = $this->customerHandledActual(
                        $positionId,
                        $employeeId,
                        (int) $unitId,
                        (int) $month,
                        (int) $year,
                    );
                    $targetInfo = [
                        "target" => round($effectiveTarget, 2),
                        "actual" => $handled,
                        "shortfall" => max($effectiveTarget - $handled, 0.0),
                        "ho" => false,
                        "cabang" => null,
                    ];
                } else {
                    $targetInfo = [
                        "target" => round($effectiveTarget, 2),
                        "actual" => (float) $actualValue,
                        "shortfall" => max(
                            $effectiveTarget - (float) $actualValue,
                            0.0,
                        ),
                        "ho" => false,
                        "cabang" => null,
                    ];
                }
            } elseif (
                in_array(
                    $component->code,
                    ["OMSET_TOKO", "OMSET_CABANG", "OMSET_TEKNISI"],
                    true,
                ) &&
                isset($actualValue) &&
                isset($target)
            ) {
                // Non-SPV: target biasa dari kpi_targets (non HO).
                $targetInfo = [
                    "target" => (float) $target->target_value,
                    "actual" => (float) $actualValue,
                    "shortfall" => max(
                        (float) $target->target_value - (float) $actualValue,
                        0.0,
                    ),
                    "ho" => false,
                    "cabang" => null,
                ];
            } elseif ($digitalData !== null) {
                // KPI Digital Marketing (jabatan 43): target/actual dari
                // DigitalMarketingKpiService::componentData (divisi/HO scope).
                $targetInfo = [
                    "target" => $digitalData["target"],
                    "actual" => $digitalData["actual"],
                    "shortfall" => $digitalData["shortfall"],
                    "ho" => true,
                    "cabang" => null,
                ];
            } elseif ($financeData !== null) {
                // KPI Finance (jabatan 0): target = skor 100, realisasi =
                // rata-rata skor antar-cabang (FinanceKpiService::componentData).
                $targetInfo = [
                    "target" => $financeData["target"],
                    "actual" => $financeData["actual"],
                    "shortfall" => $financeData["shortfall"],
                    "ho" => true,
                    "cabang" => null,
                ];

                // Kesehatan Uang ditampilkan per cabang (pola sama seperti
                // Omset Wilayah/Target Cabang milik SPV): rasio + skor tiap
                // cabang, supaya Finance tahu cabang mana yang menahan nilai.
                if ($component->code === "FIN_KESEHATAN_UANG") {
                    $targetInfo[
                        "cabang"
                    ] = $this->financeKpiService()->kesehatanUangBreakdown(
                        (int) $month,
                        (int) $year,
                    );
                } elseif ($component->code === "FIN_AKURASI") {
                    // Akurasi Laporan Keuangan: jumlah hari rekonsiliasi
                    // terverifikasi tanpa selisih per cabang.
                    $targetInfo[
                        "cabang"
                    ] = $this->financeKpiService()->akurasiBreakdown(
                        (int) $month,
                        (int) $year,
                    );
                } elseif ($component->code === "FIN_CASH_FLOW") {
                    // Net Cash Flow: NCF% tiap cabang vs target 20% + skornya,
                    // supaya target bisnis terlihat (bukan hanya skor 0–100).
                    $targetInfo[
                        "cabang"
                    ] = $this->financeKpiService()->cashflowBreakdown(
                        (int) $month,
                        (int) $year,
                    );
                }
            } elseif ($managerData !== null) {
                // KPI Manager (jabatan 34): target/realisasi dari ManagerKpiService.
                // TARGET_CABANG membawa rincian per-cabang (unit_count/reached/cabang).
                $targetInfo = [
                    "target" => $managerData["target"] ?? null,
                    "actual" => $managerData["actual"] ?? null,
                    "shortfall" => $managerData["shortfall"] ?? null,
                    "unit_count" => $managerData["unit_count"] ?? null,
                    "reached" => $managerData["reached"] ?? null,
                    "ho" => true,
                    "cabang" => $managerData["cabang"] ?? null,
                ];
            }

            $items[] = [
                "kpi_component_id" => $component->id,
                "code" => $component->code,
                "name" => $component->name,
                "type" => $component->type,
                "calculation_strategy" => $component->calculation_strategy,
                "weight" => (float) $w->weight,
                "achievement" => $achievementVal,
                "weighted_score" => $weightedVal,
                "target" => $targetInfo["target"] ?? null,
                "actual" => $targetInfo["actual"] ?? null,
                "shortfall" => $targetInfo["shortfall"] ?? null,
                "unit_count" => $targetInfo["unit_count"] ?? null,
                "reached" => $targetInfo["reached"] ?? null,
                "ho" => $targetInfo["ho"] ?? null,
                "cabang" => $targetInfo["cabang"] ?? null,
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
            $context,
        );
        $skorTotal2 = $attendance["attendance_score"];

        return [
            "employee_id" => $employeeId,
            "unit_id" => $unitId,
            "position_id" => $positionId,
            "context" => $context,
            "period" => sprintf("%04d-%02d", (int) $year, (int) $month),
            "items" => $items,
            "total_score" => round(
                $scoreService->totalWeightedScore(
                    array_map(
                        fn($i) => [
                            "nilai" => $i["achievement"],
                            "bobot" => $i["weight"],
                        ],
                        $items,
                    ),
                ),
                4,
            ),
            "skor_total2" => round($skorTotal2, 4),
            "attendance" => $attendance,
            "detail_absen" => $attendance["components"] ?? [],
            "weight_valid" => $scoreService->validateWeights(
                array_map(fn($i) => ["bobot" => $i["weight"]], $items),
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

    public function digitalMarketingService(): DigitalMarketingKpiService
    {
        if ($this->digitalMarketingService === null) {
            $this->digitalMarketingService = new DigitalMarketingKpiService();
        }
        return $this->digitalMarketingService;
    }

    public function financeKpiService(): FinanceKpiService
    {
        if ($this->financeKpiService === null) {
            $this->financeKpiService = new FinanceKpiService();
        }
        return $this->financeKpiService;
    }

    public function managerService(): ManagerKpiService
    {
        if ($this->managerKpiService === null) {
            $this->managerKpiService = new ManagerKpiService();
        }
        return $this->managerKpiService;
    }

    protected function getPositionOfEmployee(int $employeeId): ?int
    {
        $key = \App\Libraries\ReadCache::kunci("pos", $employeeId);
        if (!array_key_exists($key, self::$memo)) {
            $model = new \App\Models\ModelAuth();
            $emp = $model->where("ID_AKUN", $employeeId)->first();
            self::$memo[$key] = $emp ? (int) $emp->ID_JABATAN : null;
        }

        return self::$memo[$key];
    }

    /**
     * Unit sumber untuk perhitungan omset sebuah komponen.
     *
     * Default memakai unit penempatan employee. Pengecualian: Customer Service
     * (jabatan 42) memakai unit 1 untuk OMSET_TOKO karena unit penempatannya
     * Head Office (50) tidak bertransaksi penjualan.
     *
     * Dipakai untuk BOTH actual dan target supaya pembandingnya konsisten:
     * actual omset unit 1 harus dibandingkan dengan target unit 1.
     */
    protected function resolveOmsetUnitId(
        ?int $positionId,
        string $componentCode,
        int $unitId
    ): int {
        if (
            $positionId === self::CS_POSITION_ID &&
            $componentCode === "OMSET_TOKO"
        ) {
            return self::CS_OMSET_UNIT_ID;
        }

        return $unitId;
    }

    /**
     * Aktual Total Pelanggan untuk pegawai yang dinilai dari service yang
     * DITANGANI (teknisi 36 & kepala toko 41), bukan total pelanggan unit.
     *
     * Kepala Toko unit 1 (merangkap Admin, tidak mengerjakan service) memakai
     * akumulasi service para teknisi unit tsb; teknisi dan Kepala Toko unit lain
     * tetap memakai service yang ditangani sendiri.
     */
    protected function customerHandledActual(
        int $positionId,
        int $employeeId,
        int $unitId,
        int $month,
        int $year
    ): float {
        $customerCalc = $this->calculators["customer_count"];

        if (
            $positionId === 41 &&
            $unitId === self::KEPALA_TOKO_TEKNISI_UNIT_ID
        ) {
            return (float) $customerCalc->handledByUnitTeknisi(
                $unitId,
                $month,
                $year,
            );
        }

        return (float) $customerCalc->handledByTeknisi(
            $employeeId,
            $unitId,
            $month,
            $year,
        );
    }

    /**
     * Target Total Pelanggan yang efektif.
     *
     * Default: target pelanggan UNIT ÷ 2 (dibagi di kode, bukan di DB) untuk
     * teknisi & kepala toko. Khusus Kepala Toko unit 1 — realisasi-nya akumulasi
     * teknisi se-unit (skala unit) — target memakai nilai PENUH (tidak dibagi 2).
     */
    protected function customerTarget(
        int $positionId,
        int $unitId,
        float $targetValue
    ): float {
        if (
            $positionId === 41 &&
            $unitId === self::KEPALA_TOKO_TEKNISI_UNIT_ID
        ) {
            return $targetValue;
        }

        return $targetValue / 2.0;
    }

    /**
     * Unit CABANG (seluruh unit master kecuali Head Office), urut idunit.
     *
     * Dipakai untuk ringkasan "Rincian Omset Cabang" / "Omset Global" pada
     * halaman penilaian kinerja. Daftar diambil dari master `unit`, bukan
     * id hardcoded, supaya cabang yang baru terdaftar (Unit 5 ICLEAR Genteng)
     * otomatis ikut tanpa perlu mengubah kode.
     *
     * @return list<array{unit:int, nama:string}>
     */
    public function branchUnits(): array
    {
        $key = \App\Libraries\ReadCache::kunci("branch_units");
        static $memo = [];

        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $rows = \Config\Database::connect()
            ->table("unit")
            ->select("idunit, NAMA_UNIT")
            ->where("NAMA_UNIT !=", self::HO_UNIT_NAME)
            ->where("idunit >", 0)
            ->orderBy("idunit", "ASC")
            ->get()
            ->getResultArray();

        $cache = [];
        foreach ($rows as $row) {
            $cache[] = [
                "unit" => (int) $row["idunit"],
                "nama" => (string) $row["NAMA_UNIT"],
            ];
        }

        return $memo[$key] = $cache;
    }

    public function calculateAchievement($actualValue, $targetValue)
    {
        return $this->scoreService()->achievementScore(
            (float) $actualValue,
            (float) $targetValue,
        );
    }

    public function validatePositionWeights($positionId, $date = null)
    {
        $result = $this->weightModel->validateAllGroups($positionId, $date);
        return $result["kpi"]["is_valid"] ?? false;
    }

    public function getWeightsByPosition(
        $positionId,
        $date = null,
        $group = null
    ) {
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
        string $context = "gaji",
        ?string $date = null,
        ?string $periodeDate = null
    ): array {
        $date = $date ?? sprintf("%04d-%02d-15", (int) $year, (int) $month);
        $periodeDate =
            $periodeDate ?? sprintf("%04d-%02d-15", (int) $year, (int) $month);
        $bulan = (int) $month;
        $tahun = (int) $year;

        // ── Employee & Position ────────────────────────────────
        $authModel = new \App\Models\ModelAuth();
        $karyawanObj = $authModel->where("ID_AKUN", $employeeId)->first();
        $karyawan = $karyawanObj ? (array) $karyawanObj : [];
        $positionId = $karyawanObj ? (int) $karyawanObj->ID_JABATAN : null;
        $unit = $karyawanObj ? (int) $karyawanObj->ID_UNIT : 0;

        // ── Penempatan (location allowance) ────────────────────
        $akun = $this->queryAkun($employeeId);
        $akun->tunjangan_penempatan = $akun->penempatan == 0 ? 350000 : 0;

        // ── KPI items (group kpi) ──────────────────────────────
        $kpiResult = $this->calculateForEmployee(
            $employeeId,
            $unit,
            $month,
            $year,
            $context,
            $date,
        );

        $skorTotal = min($kpiResult["total_score"], 100.0);
        $skorTotal2 = $kpiResult["skor_total2"];

        // Build detail_kpi for views: array of ['nama','bobot','nilai', 'target', 'actual', ...]
        $detailKpi = [];
        foreach ($kpiResult["items"] as $item) {
            $detailKpi[] = [
                "nama" => $item["name"],
                "bobot" => $item["weight"],
                "nilai" => $item["achievement"],
                "target" => $item["target"] ?? null,
                "actual" => $item["actual"] ?? null,
                "shortfall" => $item["shortfall"] ?? null,
                "unit_count" => $item["unit_count"] ?? null,
                "reached" => $item["reached"] ?? null,
                "ho" => $item["ho"] ?? null,
                "cabang" => $item["cabang"] ?? null,
                "format" =>
                    in_array($item["code"], ["CONVERSION", "CAMPAIGN_PERFORMANCE", "REPORTING"], true)
                        ? "percent"
                        : ($item["code"] === "FIN_KESEHATAN_UANG"
                            ? "health"
                            : ($item["code"] === "FIN_AKURASI"
                                ? "accuracy"
                                : ($item["code"] === "FIN_CASH_FLOW"
                                    ? "cashflow"
                                    : (in_array(
                                        $item["code"],
                                        [
                                            "OMSET_TOKO",
                                            "OMSET_CABANG",
                                            "OMSET_TEKNISI",
                                            "OPERASIONAL",
                                            "OMZET_WILAYAH",
                                            "TARGET_CABANG",
                                            "OMZET_GLOBAL",
                                            "CPL",
                                            "BUDGETING",
                                        ],
                                        true,
                                    )
                                        ? "currency"
                                        : "number")))),
            ];
        }

        // Build detail_absen for views: list of ['nama','bobot','nilai'].
        // Sumber tunggal: komponen absen yang SUDAH dihitung
        // AttendanceAggregationService (bobot 40/20/20/20) — TIDAK lagi
        // membaca kpi_weights, supaya pegawai tanpa mapping bobot absen
        // (mis. jabatan Finance/0) tetap menampilkan rincian.
        $detailAbsen = AttendanceAggregationService::detailRows(
            $kpiResult["attendance"] ?? [],
        );

        // ── Omset per unit (for views) ────────────────────────
        // Daftar cabang diambil dari master `unit` (kecuali Head Office),
        // bukan daftar id hardcoded, supaya cabang baru — misalnya Unit 5
        // ICLEAR Genteng — langsung ikut terhitung tanpa ubah kode.
        $omsetCalc = new OmsetTokoCalculator();
        $aktualOmset = [];
        $omsetCabang = [];
        foreach ($this->branchUnits() as $branch) {
            $branchUnit = $branch["unit"];
            $branchOmset = $omsetCalc->calculate(
                $employeeId,
                $branchUnit,
                $month,
                $year,
            );
            $aktualOmset[$branchUnit] = $branchOmset;
            $omsetCabang[] = [
                "unit" => $branchUnit,
                "nama" => $branch["nama"],
                "omset" => $branchOmset,
            ];
        }

        // ── Incentive via IncentiveCalculationService ──────────
        $insentif = $this->computeIncentive(
            $employeeId,
            $unit,
            $month,
            $year,
            $date,
        );

        // ── Gaji pokok from salary_structures ──────────────────
        $gajiPokok = $this->getGajiPokok(
            $positionId,
            $unit,
            $context,
            $periodeDate,
        );

        // ── Placeholder; computed by SalaryCalculationService ──
        $tunjanganKinerja = 0.0;
        $tunjanganAbsen = 0.0;
        $gaji = 0.0;

        return [
            "karyawan" => $karyawan,
            "akun" => $akun,
            "jabatan" => $positionId,
            "unit" => $unit,
            "aktual_omset_unit" => $aktualOmset,
            "omset_cabang" => $omsetCabang,
            "detail_kpi" => $detailKpi,
            "detail_absen" => $detailAbsen,
            "skor_total" => round($skorTotal, 2),
            "skor_total2" => round($skorTotal2, 2),
            "tunjangan_kinerja" => $tunjanganKinerja,
            "tunjangan_absen" => $tunjanganAbsen,
            "insentif" => round($insentif, 2),
            "gaji_pokok" => $gajiPokok,
            "gaji" => $gaji,
        ];
    }

    /** Query akun + penempatan case. */
    protected function queryAkun(int $employeeId)
    {
        $db = \Config\Database::connect();
        $akun = $db
            ->table("akun")
            ->select("NAMA_AKUN, ALAMAT, ID_UNIT")
            ->where("ID_AKUN", $employeeId)
            ->get()
            ->getRow();

        if (!$akun) {
            return null;
        }

        $akun->penempatan = \App\Services\Payroll\PenempatanService::penempatan(
            $akun->ALAMAT,
            $akun->ID_UNIT,
        );

        return $akun;
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
    protected function kontrolAsetScore(
        int $unitId,
        int $bulan,
        int $tahun
    ): ?float {
        return (new \App\Services\Kpi\AsetKpiService())->kpiScore(
            $unitId,
            $bulan,
            $tahun,
        );
    }

    /**
     * Compute incentive via DB-driven IncentiveCalculationService.
     *
     * Business rule:
     *   - KEPALA_TOKO (division_method 'per_member'): setiap anggota menerima
     *     rate% × omset (mis. 1%), TANPA dibagi jumlah anggota.
     *   - DIGITAL_DIVISION (division_method 'member_count'): pool = rate% × omset,
     *     dibagi rata jumlah anggota aktif.
     *   - SPV: 0.5% per cabang yang capai target.
     * SPV/other jabatan → checks incentive_members membership.
     */
    public function computeIncentive(
        int $employeeId,
        int $unit,
        string $month,
        string $year,
        string $date
    ): float {
        $memberModel = new \App\Models\ModelIncentiveMember();
        $groupModel = new \App\Models\ModelIncentiveGroup();
        $ruleModel = new \App\Models\ModelIncentiveRule();
        $omsetCalc = new OmsetTokoCalculator();
        $date = $date ?? date("Y-m-d");

        // Aturan baru Oktober 2026: HO & Probolinggo TIDAK lagi memakai
        // incentive_members (membership lama menempatkan sebagian HO di unit 1).
        if ($date >= self::INCENTIVE_RULE_V2_START) {
            if ($unit === self::HO_UNIT_ID) {
                return $this->hoPoolIncentive($employeeId, $month, $year, $date);
            }
            if ($unit === self::PROBOLINGGO_UNIT_ID) {
                return $this->probolinggoPoolIncentive(
                    $employeeId,
                    $month,
                    $year,
                    $date,
                );
            }
        }

        $member = $memberModel
            ->where("employee_id", $employeeId)
            ->where("is_active", 1)
            ->first();
        if (!$member) {
            return 0.0;
        }

        $group = $groupModel->where("id", $member->incentive_group_id)->first();
        if (!$group || !(int) $group->is_active) {
            return 0.0;
        }

        $rule = $ruleModel
            ->where("incentive_group_id", $group->id)
            ->where("effective_from <=", $date)
            ->groupStart()
            ->where("effective_to >=", $date)
            ->orWhere("effective_to IS NULL")
            ->groupEnd()
            ->first();
        if (!$rule) {
            return 0.0;
        }

        // =========================================================
        // SPECIAL: SPV (0.5% per branch meeting target)
        // =========================================================
        if ($group->code === "SPV") {
            $db = \Config\Database::connect();
            $totalIncentive = 0.0;
            // Scope SPV (jabatan 40) = unit dari spv_units per supervisor,
            // BUKAN semua unit. SPV 49 → [2,3,5], SPV 56 → [1,4].
            $units = $this->supervisorService()->scopeUnits($employeeId, $unit);

            foreach ($units as $uId) {
                $actualOmset = $omsetCalc->calculate(0, $uId, $month, $year);
                $target = $this->targetModel->getTargetByKpiAndUnit(
                    $rule->kpi_component_id,
                    $uId,
                    "default",
                    $date,
                );

                if ($target && $actualOmset >= (float) $target->target_value) {
                    $totalIncentive +=
                        $actualOmset * ((float) $rule->base_value / 100.0);
                }
            }
            return $totalIncentive;
        }

        // =========================================================
        // DEFAULT: Group Pool (KT, Digital, etc.)
        // Wajib mencapai minimal capaian target (minimum_achievement).
        // =========================================================
        $omsetToko = $omsetCalc->calculate($employeeId, $unit, $month, $year);
        $minAch = (float) ($rule->minimum_achievement ?? 0);

        if ($minAch > 0) {
            $target = $this->targetModel->getTargetByKpiAndUnit(
                (int) $rule->kpi_component_id,
                $unit,
                "default",
                $date,
            );
            if (!$target || (float) $target->target_value <= 0) {
                return 0.0;
            }
            $achievement = ($omsetToko / (float) $target->target_value) * 100;
            if ($achievement < $minAch) {
                return 0.0;
            }
        } elseif ($omsetToko <= 0) {
            return 0.0;
        }

        $memberCount = $memberModel->countActiveMembers(
            (int) $group->id,
            $unit,
            $date,
        );

        if ($memberCount <= 0) {
            return 0.0;
        }

        // KEPALA_TOKO: business rule 1% × omset per anggota, tidak dibagi
        // jumlah anggota dan tidak boleh lebih. Guard ini menjaga data rule
        // lama (3% pool / member_count) agar unit dengan <3 anggota tidak
        // mendapat 1,5% dan unit dengan >3 anggota tidak mendapat 0,75%.
        if ($group->code === "KEPALA_TOKO") {
            return 0.01 * $omsetToko;
        }

        $rate = (float) $rule->base_value / 100.0;

        // NEW RULE (KEPALA_TOKO, division_method = 'per_member'):
        // setiap anggota menerima rate% × omset, TIDAK dibagi jumlah anggota.
        if (($rule->division_method ?? "") === "per_member") {
            return $rate * $omsetToko;
        }

        // Rule diwarisi: pool = rate% × omset, dibagi rata ke anggota.
        return ($rate * $omsetToko) / $memberCount;
    }

    /**
     * Insentif HO (Oktober 2026+).
     *
     * Pool = 1% × gross profit tiap cabang yang mencapai target OMSET_TOKO,
     * dibagi rata ke seluruh karyawan HO aktif yang menerima gaji.
     */
    private function hoPoolIncentive(
        int $employeeId,
        string $month,
        string $year,
        string $date
    ): float {
        $ids = $this->activePaidEmployeeIds(self::HO_UNIT_ID);
        if (!$ids || !in_array($employeeId, $ids, true)) {
            return 0.0;
        }

        $component = $this->componentModel->where("code", "OMSET_TOKO")->first();
        if (!$component) {
            return 0.0;
        }

        $omsetCalc = new OmsetTokoCalculator();
        $pool = 0.0;
        foreach ($this->branchUnits() as $branch) {
            $branchUnit = (int) $branch["unit"];
            $actual = $omsetCalc->calculate(0, $branchUnit, $month, $year);
            $target = $this->targetModel->getTargetByKpiAndUnit(
                (int) $component->id,
                $branchUnit,
                "default",
                $date,
            );

            if ($target && $actual >= (float) $target->target_value) {
                $pool += $actual * 0.01;
            }
        }

        return $pool / count($ids);
    }

    /**
     * Insentif Probolinggo (Oktober 2026+).
     *
     * Pool = 3% × gross profit unit 1 bila target OMSET_TOKO tercapai, dibagi
     * rata ke staff toko aktif yang menerima gaji (KT, teknisi, admin, CS).
     */
    private function probolinggoPoolIncentive(
        int $employeeId,
        string $month,
        string $year,
        string $date
    ): float {
        $ids = $this->activePaidEmployeeIds(
            self::PROBOLINGGO_UNIT_ID,
            self::STORE_POSITION_IDS,
        );
        if (!$ids || !in_array($employeeId, $ids, true)) {
            return 0.0;
        }

        $component = $this->componentModel->where("code", "OMSET_TOKO")->first();
        if (!$component) {
            return 0.0;
        }

        $actual = (new OmsetTokoCalculator())->calculate(
            0,
            self::PROBOLINGGO_UNIT_ID,
            $month,
            $year,
        );
        $target = $this->targetModel->getTargetByKpiAndUnit(
            (int) $component->id,
            self::PROBOLINGGO_UNIT_ID,
            "default",
            $date,
        );

        if (!$target || $actual < (float) $target->target_value) {
            return 0.0;
        }

        return ($actual * 0.03) / count($ids);
    }

    /**
     * ID akun aktif & menerima gaji pada satu unit, opsional filter jabatan.
     *
     * @param list<int>|null $positions
     * @return list<int>
     */
    private function activePaidEmployeeIds(
        int $unitId,
        ?array $positions = null
    ): array {
        $builder = \Config\Database::connect()
            ->table("akun")
            ->select("ID_AKUN")
            ->where("ID_UNIT", $unitId)
            ->where("STATUS_PEGAWAI", 1)
            ->where("TERIMA_GAJI", 1)
            ->groupStart()
            ->where("deleted", null)
            ->orWhere("deleted", 0)
            ->groupEnd();

        if ($positions !== null) {
            $builder->whereIn("ID_JABATAN", $positions);
        }

        return array_map(
            "intval",
            array_column($builder->get()->getResultArray(), "ID_AKUN"),
        );
    }

    /**
     * Skor kelengkapan input absen harian ADMIN/KASIR (jabatan 35).
     *
     * Tanggungan: jabatan yang boleh dinilai admin KEHADIRAN sesuai matriks
     * EvaluatorAuthorizationService (35, 36, 41, 42) pada unit yang sama.
     * Coverage = jumlah pasangan (pegawai, tanggal) yang SUDAH di-input admin
     * dibagi jumlah hari penuh bulan × jumlah pegawai tanggungan, lalu ×100.
     */
    protected function adminAttendanceInputCoverage(
        int $employeeId,
        int $unitId,
        string $month,
        string $year
    ): float {
        $db = \Config\Database::connect();

        $allowed = \App\Services\Kpi\EvaluatorAuthorizationService::allowedTargetJabatans(
            35,
        );
        if (empty($allowed)) {
            return 100.0;
        }

        $responsible = $db
            ->table("akun")
            ->select("ID_AKUN")
            ->where("STATUS_PEGAWAI", 1)
            ->where("ID_UNIT", $unitId)
            ->whereIn("ID_JABATAN", $allowed)
            ->get()
            ->getResultArray();
        $responsibleIds = array_column($responsible, "ID_AKUN");
        if (empty($responsibleIds)) {
            return 100.0;
        }

        $daysInMonth = (int) date(
            "t",
            strtotime(sprintf("%04d-%02d-01", (int) $year, (int) $month)),
        );
        $expected = $daysInMonth * count($responsibleIds);

        $kehadiran = $this->componentModel->where("code", "KEHADIRAN")->first();
        $kiid = $kehadiran ? (int) $kehadiran->id : -1;

        $covered = [];

        // Input nyata (raw_score) yang tercatat atas nama admin sebagai evaluator.
        $evals = $db
            ->table("kpi_evaluations")
            ->select("employee_id, evaluation_date")
            ->distinct()
            ->where("evaluator_id", $employeeId)
            ->whereIn("employee_id", $responsibleIds)
            ->where("kpi_component_id", $kiid)
            ->where("period_year", (int) $year)
            ->where("period_month", (int) $month)
            ->get()
            ->getResultArray();
        foreach ($evals as $r) {
            $covered[
                (int) $r["employee_id"] . "|" . $r["evaluation_date"]
            ] = true;
        }

        // Hari yang ditandai OFF oleh admin (input lengkap tanpa nilai harian).
        $offs = $db
            ->table("kpi_attendance_off")
            ->select("employee_id, evaluation_date")
            ->distinct()
            ->where("evaluator_id", $employeeId)
            ->whereIn("employee_id", $responsibleIds)
            ->where("period_year", (int) $year)
            ->where("period_month", (int) $month)
            ->get()
            ->getResultArray();
        foreach ($offs as $r) {
            $covered[
                (int) $r["employee_id"] . "|" . $r["evaluation_date"]
            ] = true;
        }

        $actual = count($covered);
        if ($expected <= 0) {
            return 100.0;
        }

        return min(round(($actual / $expected) * 100, 2), 100.0);
    }

    /** GAJI_POKOK from salary_structures. */
    protected function getGajiPokok(
        int $positionId,
        int $unit,
        string $context,
        string $date
    ): float {
        $salaryService = new \App\Services\Payroll\SalaryCalculationService();
        $structure = $salaryService->getSalaryStructure(
            $positionId,
            $unit,
            $context,
            $date,
        );
        foreach ($structure as $struct) {
            if ($struct->code === "GAJI_POKOK") {
                return (float) $struct->base_value;
            }
        }
        return 1500000.0;
    }

    public function getWeightValidationResult(
        $positionId,
        $date = null,
        $group = "kpi"
    ) {
        $weights = $this->weightModel->getByPosition(
            $positionId,
            $date,
            $group,
        );
        $total = array_reduce(
            $weights,
            fn($sum, $w) => $sum + (float) $w->weight,
            0.0,
        );

        return [
            "weights" => $weights,
            "total_weight" => $total,
            "is_valid" => abs($total - 100) < 0.01,
            "difference" => abs($total - 100),
        ];
    }
}
