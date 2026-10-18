<?php

namespace App\Services\Kpi;

/**
 * ManagerKpiService — KPI Manager (jabatan 34), 7 komponen perusahaan.
 *
 *   OMZET_GLOBAL           50  rumus sama Kepala Divisi (43)
 *   FIN_KESEHATAN_UANG     15  nilai Finance apa adanya
 *   TARGET_CABANG          10  versi semua cabang
 *   EFEKTIVITAS_ORGANISASI 10  rata-rata KPI SPV (40)
 *   SDM_LEADERSHIP          5  rata-rata KPI bawahan (40/43/45/0)
 *   IMPROVEMENT_PERUSAHAAN  5  input manual bulanan
 *   REPORTING_GOVERNANCE    5  input manual bulanan
 *
 * Aturan angka (desain 2026-10-18):
 *   - Anggota tanpa nilai dihitung 0 (pembagi = semua anggota aktif).
 *   - SDM & Leadership dirata-rata PER JABATAN dulu, baru antar jabatan.
 *   - Tiap skor kontributor di-cap 100 sebelum dirata-rata.
 */
class ManagerKpiService
{
    /** 7 komponen KPI Manager. */
    public const CODES = [
        'OMZET_GLOBAL',
        'FIN_KESEHATAN_UANG',
        'TARGET_CABANG',
        'EFEKTIVITAS_ORGANISASI',
        'SDM_LEADERSHIP',
        'IMPROVEMENT_PERUSAHAAN',
        'REPORTING_GOVERNANCE',
    ];

    /** Efektivitas Organisasi = rata-rata KPI SPV (jabatan 40). */
    public const EFEKTIVITAS_JABATANS = [40];

    /** SDM & Leadership = rata-rata KPI bawahan langsung Manager. */
    public const SDM_JABATANS = [40, 43, 45, 0];

    protected $componentModel;
    protected $targetModel;

    /** @var array<string,mixed> cache per-periode (KPI berantai Manager→SPV) */
    protected $cache = [];

    public function __construct()
    {
        $this->componentModel = new \App\Models\ModelKpiComponent();
        $this->targetModel    = new \App\Models\ModelKpiTarget();
    }

    /**
     * Rata-rata skor dengan SEMUA anggota sebagai pembagi (anggota tanpa
     * nilai = 0). Tiap skor di-cap 100. null bila tidak ada anggota.
     *
     * @param array<int,float|null> $scores
     */
    public static function averageOverMembers(array $scores, int $memberCount): ?float
    {
        if ($memberCount <= 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($scores as $s) {
            if ($s === null) {
                continue;
            }
            $sum += min((float) $s, 100.0);
        }

        return round($sum / $memberCount, 4);
    }

    /**
     * Rata-rata antar-jabatan (per-jabatan dulu). null = jabatan tanpa
     * anggota → dilewati. Semua null → null.
     *
     * @param array<int,float|null> $roleAverages
     */
    public static function averageAcrossRoles(array $roleAverages): ?float
    {
        $sum = 0.0;
        $n   = 0;
        foreach ($roleAverages as $v) {
            if ($v === null) {
                continue;
            }
            $sum += (float) $v;
            $n++;
        }

        return $n > 0 ? round($sum / $n, 4) : null;
    }

    /* ════════════════════ SUMBER DATA (engine KPI) ════════════════════ */

    /**
     * Data lengkap satu komponen KPI Manager untuk engine KPI.
     *
     * @return array{achievement:?float,target:?float,actual:?float,shortfall:?float,source:string}
     */
    public function componentData(string $code, int $employeeId, int $unitId, int $month, int $year, string $context, ?string $date = null): array
    {
        switch ($code) {
            case 'OMZET_GLOBAL':
                return (new \App\Services\Marketing\DigitalMarketingKpiService())
                    ->componentData($code, $employeeId, $unitId, $month, $year, $context, $date);
            case 'FIN_KESEHATAN_UANG':
                return (new \App\Services\Finance\FinanceKpiService())->componentData($code, $month, $year);
            case 'TARGET_CABANG':
                return $this->pencapaianCabang($month, $year, $context, $date);
            case 'EFEKTIVITAS_ORGANISASI':
                return $this->groupKpiData(self::EFEKTIVITAS_JABATANS, $month, $year, $context, $date);
            case 'SDM_LEADERSHIP':
                return $this->groupKpiData(self::SDM_JABATANS, $month, $year, $context, $date);
            case 'IMPROVEMENT_PERUSAHAAN':
            case 'REPORTING_GOVERNANCE':
                return $this->manualData($code, $employeeId, $month, $year);
        }

        return ['achievement' => null, 'target' => null, 'actual' => null, 'shortfall' => null, 'source' => ''];
    }

    /**
     * Rata-rata KPI sebuah kelompok jabatan.
     * Per-jabatan dulu (anggota tanpa nilai = 0), lalu antar jabatan.
     */
    protected function groupKpiData(array $jabatans, int $month, int $year, string $context, ?string $date): array
    {
        $key = 'grp|' . implode(',', $jabatans) . "|$month|$year|$context|$date";

        if (!array_key_exists($key, $this->cache)) {
            $kpi          = new KpiCalculationService();
            $roleAverages = [];

            foreach ($jabatans as $jabatan) {
                $members = $this->activeEmployees((int) $jabatan);
                if (empty($members)) {
                    $roleAverages[] = null;
                    continue;
                }

                $scores = [];
                foreach ($members as $emp) {
                    $res = $kpi->calculateForEmployee(
                        (int) $emp['ID_AKUN'],
                        (int) $emp['ID_UNIT'],
                        (string) $month,
                        (string) $year,
                        $context,
                        $date
                    );
                    $scores[] = isset($res['total_score']) ? $res['total_score'] : null;
                }

                $roleAverages[] = self::averageOverMembers($scores, count($members));
            }

            $this->cache[$key] = self::averageAcrossRoles($roleAverages);
        }

        $value = $this->cache[$key];

        return [
            'achievement' => $value,
            'target'      => 100.0,
            'actual'      => $value,
            'shortfall'   => $value === null ? null : round(max(100.0 - $value, 0), 2),
            'source'      => 'Rata-rata KPI jabatan ' . implode(',', $jabatans),
        ];
    }

    /**
     * Pegawai AKTIF sebuah jabatan (seluruh unit).
     *
     * @return array rows: ID_AKUN, ID_JABATAN, ID_UNIT
     */
    protected function activeEmployees(int $jabatan): array
    {
        $key = 'emp|' . $jabatan;

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = \Config\Database::connect()
                ->table('akun')
                ->select('ID_AKUN, ID_JABATAN, ID_UNIT')
                ->where('ID_JABATAN', $jabatan)
                ->where('STATUS_PEGAWAI', 1)
                ->groupStart()
                    ->where('deleted', null)
                    ->orWhere('deleted', 0)
                ->groupEnd()
                ->get()
                ->getResultArray();
        }

        return $this->cache[$key];
    }

    /**
     * Pencapaian Cabang = cabang tercapai ÷ total cabang (SEMUA cabang) × 100.
     * Cabang tercapai bila omset >= target HO (OMSET_CABANG position_id 40).
     * Cabang tanpa target tidak dihitung; tanpa target sama sekali → null.
     */
    protected function pencapaianCabang(int $month, int $year, string $targetContext, ?string $date): array
    {
        $comp = $this->componentModel->where('code', 'OMSET_CABANG')->first();
        if (!$comp) {
            return ['achievement' => null, 'target' => null, 'actual' => null, 'shortfall' => null, 'unit_count' => null, 'reached' => null, 'cabang' => null, 'source' => ''];
        }

        $units   = (new KpiCalculationService())->branchUnits();
        $omset   = new OmsetCabangCalculator();
        $reached = 0;
        $n       = 0;
        $detail  = [];

        foreach ($units as $u) {
            $unit     = (int) $u['unit'];
            $target   = $this->targetModel->getTargetByKpiAndUnit((int) $comp->id, $unit, $targetContext, $date, 40);
            $targetHo = ($target && $target->target_value !== null) ? (float) $target->target_value : null;
            $actual   = $omset->calculate(0, $unit, $month, $year);

            if ($targetHo === null || $targetHo <= 0) {
                continue; // cabang tanpa target tidak ikut hitungan.
            }

            $isReached = $actual >= $targetHo;
            if ($isReached) {
                $reached++;
            }
            $n++;

            $detail[] = [
                'unit'         => $unit,
                'target_ho'    => round($targetHo),
                'actual'       => round($actual),
                'reached'      => $isReached,
                'shortfall_ho' => round(max($targetHo - $actual, 0)),
            ];
        }

        $value = $n > 0 ? round($reached / $n * 100, 4) : null;

        return [
            'achievement' => $value,
            'target'      => null, // bukan nilai rupiah: target = jumlah cabang ber-target.
            'actual'      => null,
            'shortfall'   => $n > 0 ? max($n - $reached, 0) : null,
            'unit_count'  => $n > 0 ? $n : null,
            'reached'     => $n > 0 ? $reached : null,
            'cabang'      => $detail,
            'source'      => 'Cabang tercapai / total cabang',
        ];
    }

    /** Nilai manual bulanan #6/#7 (null bila belum diisi — dikecualikan). */
    protected function manualData(string $code, int $employeeId, int $month, int $year): array
    {
        $value = $this->manualValue($employeeId, $code, $month, $year);

        return [
            'achievement' => $value,
            'target'      => 100.0,
            'actual'      => $value,
            'shortfall'   => $value === null ? null : round(max(100.0 - $value, 0), 2),
            'source'      => 'Input manual bulanan',
        ];
    }

    /** Nilai manual tersimpan (normalized_score) terbaru untuk periode ini. */
    public function manualValue(int $employeeId, string $code, int $month, int $year): ?float
    {
        $comp = $this->componentModel->where('code', $code)->first();
        if (!$comp) {
            return null;
        }

        $row = (new \App\Models\ModelKpiEvaluation())
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', (int) $comp->id)
            ->where('period_year', (int) $year)
            ->where('period_month', (int) $month)
            ->orderBy('evaluation_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        return ($row && $row->normalized_score !== null) ? min((float) $row->normalized_score, 100.0) : null;
    }

    /**
     * Simpan nilai manual bulanan 0-100 (upsert anchor tanggal 15).
     * Pola sama dengan SupervisorKpiService::saveCustomerSatisfaction().
     */
    public function saveManual(int $employeeId, string $code, int $evaluatorId, int $month, int $year, float $value): array
    {
        if ($value < 0 || $value > 100) {
            return ['success' => false, 'errors' => ['Nilai harus antara 0 s/d 100.']];
        }

        $comp = $this->componentModel->where('code', $code)->first();
        if (!$comp) {
            return ['success' => false, 'errors' => ['Komponen ' . $code . ' belum dikonfigurasi.']];
        }

        if (!EvaluatorAuthorizationService::canEvaluateComponent($evaluatorId, $employeeId, $code)) {
            return ['success' => false, 'errors' => ['Anda tidak berwenang mengisi komponen ini.']];
        }

        $anchor = sprintf('%04d-%02d-15', $year, $month);
        (new \App\Models\ModelKpiEvaluation())
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', (int) $comp->id)
            ->where('evaluator_id', $evaluatorId)
            ->where('evaluation_date', $anchor)
            ->delete();

        return (new KpiEvaluationService())->recordEvaluation([
            'employee_id'      => $employeeId,
            'kpi_component_id' => (int) $comp->id,
            'evaluator_id'     => $evaluatorId,
            'evaluation_date'  => $anchor,
            'raw_score'        => $value,
            'max_score'        => 100,
            'notes'            => $comp->name . ' (Manual)',
        ]);
    }
}
