<?php

namespace App\Services\Kpi;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKpiTarget;
use App\Models\ModelKpiComponent;
use App\Models\ModelKpiEvaluation;

/**
 * SupervisorKpiService — KPI Area Supervisor / SPV (jabatan 40).
 *
 * Mengikuti pola existing (AsetKpiService / KpiCalculationService):
 *   - Scope area Supervisor dari session hierarki SPV → cabang:
 *     spv_units (spv_id = ID_AKUN), fallback unit sendiri.
 *   - REUSE nilai KPI existing (tidak menghitung ulang KPI bawahan
 *     dari data mentah jika nilai sudah ada).
 *   - Periode (bulan/tahun) selalu DITERUSKAN oleh caller — tidak memakai
 *     date() sebagai periode KPI.
 *
 * Komponen & Sumber data:
 *   OMZET_WILAYAH           sum(actual omzet cabang) / sum(target omzet cabang)
 *   TARGET_CABANG           avg(actual/target tiap cabang)
 *   PRODUKTIVITAS_CABANG    unique customer bulan berjalan / bulan sebelumnya × 100
 *   SOP                     avg(KPI Kepatuhan SOP Kepala Toko) — nilai existing
 *   KINERJA_KEPALA_TOKO     avg(total KPI Kepala Toko, tanpa absensi)
 *   KEDISIPLINAN_TEAM       avg(nilai kedisiplinan seluruh team cabang)
 *   CUSTOMER_SATISFACTION   manual input 0-100 (kpi_evaluations)
 */
class SupervisorKpiService
{
    /** 7 komponen KPI Area Supervisor / SPV. */
    public const CODES = [
        'OMZET_WILAYAH',
        'TARGET_CABANG',
        'PRODUKTIVITAS_CABANG',
        'SOP',
        'KINERJA_KEPALA_TOKO',
        'KEDISIPLINAN_TEAM',
        'CUSTOMER_SATISFACTION',
    ];

    /** Jabatan "team cabang" di bawah Supervisor (termasuk Kepala Toko). */
    public const TEAM_JABATANS = [35, 36, 41, 42, 43, 44, 45];

    protected $db;
    protected $targetModel;
    protected $componentModel;
    protected $evaluationModel;

    public function __construct()
    {
        $this->db              = Database::connect();
        $this->targetModel     = new ModelKpiTarget();
        $this->componentModel  = new ModelKpiComponent();
        $this->evaluationModel = new ModelKpiEvaluation();
    }

    /**
     * Unit-area Supervisor: cabang dari spv_units (session/ID_AKUN), fallback unit sendiri.
     *
     * @return int[]
     */
    public function scopeUnits(int $supervisorId, int $fallbackUnit): array
    {
        $rows = $this->db->table('spv_units')
            ->where('spv_id', $supervisorId)
            ->get()
            ->getResultArray();

        $units = !empty($rows)
            ? array_map('intval', array_column($rows, 'unit_id'))
            : [(int)$fallbackUnit];

        return array_values(array_unique($units));
    }

    /**
     * Nilai achievement satu komponen KPI Supervisor.
     *
     * @param string      $code           kode komponen (SupervisorKpiService::CODES)
     * @param int         $supervisorId   ID_AKUN Supervisor (target yang dinilai)
     * @param int         $ownUnit        unit sendiri (fallback scope)
     * @param int         $month          1-12
     * @param int         $year           YYYY
     * @param string      $context        konteks pemanggil (gaji/penilaian_kinerja/...)
     * @param string      $targetContext  konteks pencarian target omzet
     * @param string|null $date           periode anchor (Y-m-d)
     *
     * @return float|null null = data belum tersedia (N/A, ikut pola null system).
     */
    public function achievement(
        string $code,
        int $supervisorId,
        int $ownUnit,
        int $month,
        int $year,
        string $context,
        string $targetContext,
        ?string $date = null
    ): ?float {
        switch ($code) {
            case 'OMZET_WILAYAH':
                return $this->omzetWilayah($supervisorId, $ownUnit, $month, $year, $targetContext, $date);
            case 'TARGET_CABANG':
                return $this->targetCabang($supervisorId, $ownUnit, $month, $year, $targetContext, $date);
            case 'PRODUKTIVITAS_CABANG':
                return $this->produktivitasCabang($supervisorId, $ownUnit, $month, $year);
            case 'SOP':
                return $this->sop($supervisorId, $ownUnit, $month, $year, $context);
            case 'KINERJA_KEPALA_TOKO':
                return $this->kinerjaKepalaToko($supervisorId, $ownUnit, $month, $year, $context, $date);
            case 'KEDISIPLINAN_TEAM':
                return $this->kedisiplinanTeam($supervisorId, $ownUnit, $month, $year, $context);
            case 'CUSTOMER_SATISFACTION':
                return $this->customerSatisfaction($supervisorId, $month, $year);
        }

        return null;
    }

    /* ════════════════════ 1. OMZET WILAYAH ════════════════════ */

    /**
     * SUM(actual omzet seluruh cabang) ÷ SUM(target omzet seluruh cabang) × 100.
     */
    public function omzetWilayah(int $supervisorId, int $ownUnit, int $month, int $year, string $targetContext, ?string $date): ?float
    {
        $units = $this->scopeUnits($supervisorId, $ownUnit);
        if (empty($units)) {
            return null;
        }

        $comp = $this->component('OMSET_CABANG');
        if (!$comp) {
            return null;
        }

        $omset     = new OmsetCabangCalculator();
        $actualSum = 0.0;
        $targetSum = 0.0;
        $hasTarget = false;

        foreach ($units as $unit) {
            $actualSum += $omset->calculate(0, (int)$unit, $month, $year);

            $target = $this->cabangTarget((int)$comp->id, (int)$unit, $targetContext, $date);
            if ($target !== null) {
                $targetSum += $target;
                $hasTarget = true;
            }
        }

        if ($targetSum <= 0 || !$hasTarget) {
            return null;
        }

        return round(($actualSum / $targetSum) * 100, 4);
    }

    /* ════════════════════ 2. TARGET CABANG ════════════════════ */

    /**
     * avg(actual omzet cabang ÷ target omzet cabang × 100) tiap cabang.
     * Bukan sum÷sum (itu Omzet Wilayah).
     */
    public function targetCabang(int $supervisorId, int $ownUnit, int $month, int $year, string $targetContext, ?string $date): ?float
    {
        $units = $this->scopeUnits($supervisorId, $ownUnit);
        if (empty($units)) {
            return null;
        }

        $comp = $this->component('OMSET_CABANG');
        if (!$comp) {
            return null;
        }

        $omset = new OmsetCabangCalculator();
        $sum   = 0.0;
        $n     = 0;

        foreach ($units as $unit) {
            $target = $this->cabangTarget((int)$comp->id, (int)$unit, $targetContext, $date);
            if ($target === null || $target <= 0) {
                continue; // cabang tanpa target tidak ikut rata-rata.
            }
            $actual = $omset->calculate(0, (int)$unit, $month, $year);
            $sum   += ($actual / $target) * 100;
            $n++;
        }

        return $n > 0 ? round($sum / $n, 4) : null;
    }

    /* ════════════════════ 3. PRODUKTIVITAS CABANG ════════════════════ */

    /**
     * unique customer bulan berjalan ÷ bulan sebelumnya × 100.
     * null bila bulan sebelumnya 0 (tanpa pembagian nol / tanpa dummy angka).
     */
    public function produktivitasCabang(int $supervisorId, int $ownUnit, int $month, int $year): ?float
    {
        $units = $this->scopeUnits($supervisorId, $ownUnit);
        if (empty($units)) {
            return null;
        }

        $current = $this->uniqueCustomers($units, $month, $year);

        $prevMonth = $month - 1;
        $prevYear  = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        $previous = $this->uniqueCustomers($units, $prevMonth, $prevYear);

        if ($previous <= 0) {
            return null; // bulan sebelumnya 0 pelanggan → N/A, hindari ÷ 0.
        }

        return round(($current / $previous) * 100, 4);
    }

    /* ════════════════════ 4. SOP ════════════════════ */

    /**
     * avg KPI "Kepatuhan SOP" Kepala Toko (nilai existing system).
     * Tidak menghitung ulang checklist SOP.
     */
    public function sop(int $supervisorId, int $ownUnit, int $month, int $year, string $context): ?float
    {
        $units = $this->scopeUnits($supervisorId, $ownUnit);
        $kts   = $this->scopeEmployees($units, [41]);
        if (empty($kts)) {
            return null;
        }

        $attendance = new AttendanceAggregationService();
        $sum = 0.0;
        $n   = 0;

        foreach ($kts as $emp) {
            $res = $attendance->calculateMonthlyAttendance(
                (int)$emp['ID_AKUN'],
                (int)$emp['ID_UNIT'],
                (string)$month,
                (string)$year,
                $context
            );
            $val = $res['components']['KEPATUHAN_SOP']['normalized'] ?? null;
            if ($val !== null && is_numeric($val)) {
                $sum += (float)$val;
                $n++;
            }
        }

        return $n > 0 ? round($sum / $n, 4) : null;
    }

    /* ════════════════════ 5. KINERJA KEPALA TOKO ════════════════════ */

    /**
     * avg total KPI Kepala Toko (kpi-group, TIDAK termasuk absensi).
     * Reuse nilai KPI Kepala Toko yang sudah dihitung system.
     */
    public function kinerjaKepalaToko(int $supervisorId, int $ownUnit, int $month, int $year, string $context, ?string $date): ?float
    {
        $units = $this->scopeUnits($supervisorId, $ownUnit);
        $kts   = $this->scopeEmployees($units, [41]);
        if (empty($kts)) {
            return null;
        }

        $kpi = new KpiCalculationService();
        $sum = 0.0;
        $n   = 0;

        foreach ($kts as $emp) {
            $res = $kpi->calculateForEmployee(
                (int)$emp['ID_AKUN'],
                (int)$emp['ID_UNIT'],
                (string)$month,
                (string)$year,
                $context,
                $date
            );
            $val = $res['total_score'] ?? null;
            if ($val !== null && is_numeric($val)) {
                $sum += (float)$val;
                $n++;
            }
        }

        return $n > 0 ? round($sum / $n, 4) : null;
    }

    /* ════════════════════ 6. KEDISIPLINAN TEAM ════════════════════ */

    /**
     * avg nilai kedisiplinan (attendance_score) seluruh team cabang
     * dalam area Supervisor (Kepala Toko + Teknisi + Admin + team lain).
     */
    public function kedisiplinanTeam(int $supervisorId, int $ownUnit, int $month, int $year, string $context): ?float
    {
        $units = $this->scopeUnits($supervisorId, $ownUnit);
        $teams = $this->scopeEmployees($units, self::TEAM_JABATANS);
        if (empty($teams)) {
            return null;
        }

        $attendance = new AttendanceAggregationService();
        $sum = 0.0;
        $n   = 0;

        foreach ($teams as $emp) {
            $res = $attendance->calculateMonthlyAttendance(
                (int)$emp['ID_AKUN'],
                (int)$emp['ID_UNIT'],
                (string)$month,
                (string)$year,
                $context
            );
            $val = $res['attendance_score'] ?? null;
            if ($val !== null && is_numeric($val)) {
                $sum += (float)$val;
                $n++;
            }
        }

        return $n > 0 ? round($sum / $n, 4) : null;
    }

    /* ════════════════════ 7. CUSTOMER SATISFACTION (MANUAL) ════════════════════ */

    /**
     * Nilai manual 0-100 dari kpi_evaluations (source MANUAL saat ini;
     * struktur tetap bisa diganti GOOGLE_BUSINESS_PROFILE nanti).
     */
    public function customerSatisfaction(int $supervisorId, int $month, int $year): ?float
    {
        $comp = $this->component('CUSTOMER_SATISFACTION');
        if (!$comp) {
            return null;
        }

        $row = $this->evaluationModel
            ->where('employee_id', $supervisorId)
            ->where('kpi_component_id', (int)$comp->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->orderBy('evaluation_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        return ($row && $row->normalized_score !== null) ? (float)$row->normalized_score : null;
    }

    /**
     * Simpan manual Customer Satisfaction (0-100) untuk periode.
     */
    public function saveCustomerSatisfaction(int $supervisorId, int $evaluatorId, int $month, int $year, float $value): array
    {
        if ($value < 0 || $value > 100) {
            return ['success' => false, 'errors' => ['Nilai Customer Satisfaction harus antara 0 s/d 100.']];
        }

        $comp = $this->component('CUSTOMER_SATISFACTION');
        if (!$comp) {
            return ['success' => false, 'errors' => ['Komponen CUSTOMER_SATISFACTION belum dikonfigurasi.']];
        }

        if (!EvaluatorAuthorizationService::canEvaluateComponent($evaluatorId, $supervisorId, 'CUSTOMER_SATISFACTION')) {
            return ['success' => false, 'errors' => ['Anda tidak berwenang mengisi Customer Satisfaction pegawai ini.']];
        }

        // Upsert per periode (evaluation_date anchor = tanggal 15 bulan tsb).
        $anchor = sprintf('%04d-%02d-15', $year, $month);
        $this->evaluationModel
            ->where('employee_id', $supervisorId)
            ->where('kpi_component_id', (int)$comp->id)
            ->where('evaluator_id', $evaluatorId)
            ->where('evaluation_date', $anchor)
            ->delete();

        return (new KpiEvaluationService())->recordEvaluation([
            'employee_id'      => $supervisorId,
            'kpi_component_id' => (int)$comp->id,
            'evaluator_id'     => $evaluatorId,
            'evaluation_date'  => $anchor,
            'raw_score'        => $value,
            'max_score'        => 100,
            'notes'            => 'Customer Satisfaction (Manual)',
        ]);
    }

    /* ════════════════════ HELPERS ════════════════════ */

    protected function component(string $code): ?object
    {
        return $this->componentModel->where('code', $code)->first();
    }

    protected function cabangTarget(int $compId, int $unit, string $targetContext, ?string $date): ?float
    {
        $target = $this->targetModel->getTargetByKpiAndUnit($compId, $unit, $targetContext, $date);
        return $target && $target->target_value !== null ? (float)$target->target_value : null;
    }

    protected function uniqueCustomers(array $units, int $month, int $year): int
    {
        if (empty($units)) {
            return 0;
        }

        $in = implode(',', array_map('intval', $units));
        $row = $this->db->query(
            "SELECT COUNT(DISTINCT id_pelanggan) AS total
             FROM penjualan
             WHERE unit_idunit IN ($in)
               AND MONTH(tanggal) = ?
               AND YEAR(tanggal) = ?
               AND id_pelanggan > 0",
            [(int)$month, (int)$year]
        )->getRow();

        return (int)($row->total ?? 0);
    }

    /**
     * Karyawan AKTIF pada unit-area untuk sejumlah jabatan.
     *
     * @return array rows: ID_AKUN, ID_JABATAN, ID_UNIT
     */
    protected function scopeEmployees(array $units, array $jabatans): array
    {
        if (empty($units) || empty($jabatans)) {
            return [];
        }

        return $this->db->table('akun')
            ->select('ID_AKUN, ID_JABATAN, ID_UNIT')
            ->whereIn('ID_UNIT', $units)
            ->whereIn('ID_JABATAN', $jabatans)
            ->where('STATUS_PEGAWAI', 1)
            ->groupStart()
            ->where('deleted', null)
            ->orWhere('deleted', 0)
            ->groupEnd()
            ->get()
            ->getResultArray();
    }
}