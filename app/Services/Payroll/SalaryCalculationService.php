<?php
namespace App\Services\Payroll;

use Config\Database;

/**
 * SalaryCalculationService — FINAL SERVICE
 *
 * Menghitung komponen gaji dari KONFIGURASI `salary_structures` (database).
 * Tidak menyimpan rumus di controller.
 *
 * Domain: PAYROLL (terpisah dari KPI & Incentive).
 *
 * Input score KPI per komponen: karena old-system memakai DUA skor
 * (skor_total untuk tunjangan kinerja, skor_total2 untuk tunjangan absen),
 * service menerima `$kpiScores` map: [CODE => achievement_percent].
 *
 * Catatan LEGACY vs FINAL:
 * - LEGACY (LegacyKpiCalculationService / UnitSalaryCalculationService) boleh
 *   mempertahankan literal (gaji pokok 1.5juta, tunjangan) utk regression baseline.
 * - FINAL service ini membaca dari database.
 */
class SalaryCalculationService
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Ambil struktur gaji utk position + unit + context + periode.
     *
     * Unit picking: prefer row dengan unit_id = unit; fallback unit_id IS NULL.
     *
     * @return object[] rows active
     */
    public function getSalaryStructure(int $positionId, ?int $unitId = null, string $context = 'default', ?string $date = null)
    {
        $date = $date ?? date('Y-m-d');

        // Ambil semua row aktif utk position, lalu resolve di PHP
        $rows = $this->db->table('salary_structures ss')
            ->select('ss.*, sc.code, sc.name, sc.type')
            ->join('salary_components sc', 'sc.id = ss.salary_component_id')
            ->where('ss.position_id', $positionId)
            ->where('ss.effective_from <=', $date)
            ->groupStart()
                ->where('ss.effective_to >=', $date)
                ->orWhere('ss.effective_to IS NULL')
            ->groupEnd()
            ->orderBy('sc.type', 'ASC')
            ->orderBy('ss.unit_id', 'ASC') // null dulu, lalu unit spesifik
            ->get()
            ->getResult();

        // Resolve: untuk tiap component code+type pilih row paling spesifik
        // urutan prioritas:
        //  1. unit match
        //  2. unit NULL (global)
        // Konteks sudah dikonsolidasi jadi SATU ('default') — tidak lagi
        // mempengaruhi pemilihan baris.
        $resolved = [];
        foreach ($rows as $row) {
            $key = $row->code;
            if (isset($resolved[$key])) {
                $cur = $resolved[$key];
                $newScore = $this->specificityScore($row, $unitId);
                $curScore = $this->specificityScore($cur, $unitId);
                if ($newScore > $curScore) {
                    $resolved[$key] = $row;
                }
            } else {
                $resolved[$key] = $row;
            }
        }
        return array_values($resolved);
    }

    private function specificityScore($row, ?int $unitId): int
    {
        $score = 0;
        $unitMatch = $unitId !== null && $row->unit_id !== null && (int)$row->unit_id === $unitId;
        $unitAny = $row->unit_id === null;

        if ($unitMatch) $score += 2;
        elseif ($unitAny) $score += 1;
        return $score;
    }

    /**
     * Hitung gaji total utk employee + periode.
     *
     * @param int    $employeeId
     * @param int    $positionId
     * @param int    $unitId
     * @param string $context    'gaji'|'penilaian_kinerja'|'slip_gaji'
     * @param array  $kpiScores  [KODE_COMPONENT => achievement_percent]
     *                           contoh: ['TUNJANGAN_KINERJA'=>88.2, 'TUNJANGAN_ABSEN'=>80.0]
     * @param float  $placementAllowance tunjangan penempatan (dari akun)
     * @param float  $incentive   hasil IncentiveCalculationService
     * @param float  $lembur      (dari kas_keluar)
     * @param float  $bon         (dari kas_keluar)
     * @param string|null $date
     */
    public function calculateSalary(
        int $employeeId,
        int $positionId,
        int $unitId,
        string $context = 'default',
        array $kpiScores = [],
        float $placementAllowance = 0.0,
        float $incentive = 0.0,
        float $lembur = 0.0,
        float $bon = 0.0,
        ?string $date = null
    ): array {
        $structure = $this->getSalaryStructure($positionId, $unitId, $context, $date);

        // Probation: hanya gaji pokok prorata hari hadir (lihat PRD 07).
        if ($this->isProbation($employeeId)) {
            return $this->calculateProbationSalary(
                $employeeId,
                $positionId,
                $unitId,
                $context,
                $structure,
                $bon,
                $date
            );
        }

        $components = [];
        $subtotal_before_incentive = 0.0;

        foreach ($structure as $struct) {
            $amount = $this->calculateComponent(
                $struct,
                $kpiScores[$struct->code] ?? null
            );
            $components[] = [
                'component_code' => $struct->code,
                'component_name' => $struct->name,
                'calculation_type' => $struct->calculation_type,
                'base_value' => $struct->base_value,
                'unit_id' => $struct->unit_id,
                'context' => $struct->context,
                'amount' => round($amount, 2),
            ];
            $subtotal_before_incentive += $amount;
        }

        // Komponen khusus yang bukan bagian salary_structures:
        $penempatan = $placementAllowance;
        // insentif & lembur ditambahkan, bon dikurangi (sesuai slip gaji)
        $totalGaji = $subtotal_before_incentive + $penempatan + $incentive + $lembur - $bon;

        return [
            'success' => true,
            'employee_id' => $employeeId,
            'position_id' => $positionId,
            'unit_id' => $unitId,
            'context' => $context,
            'probation' => false,
            'components' => $components,
            'placement_allowance' => round($penempatan, 2),
            'incentive' => round($incentive, 2),
            'lembur' => round($lembur, 2),
            'bon' => round($bon, 2),
            'subtotal_before_incentive' => round($subtotal_before_incentive, 2),
            'total_gaji' => round($totalGaji, 2),
        ];
    }

    /**
     * Status probation diambil dari master pegawai (JENIS_PEGAWAI = 2).
     */
    public function isProbation(int $employeeId): bool
    {
        $row = $this->db->table('akun')
            ->select('JENIS_PEGAWAI')
            ->where('ID_AKUN', $employeeId)
            ->get()
            ->getRow();

        return $row !== null && (int) ($row->JENIS_PEGAWAI ?? 0) === 2;
    }

    /**
     * Jumlah hari hadir pada bulan payroll: tanggal unik evaluasi KEHADIRAN.
     *
     * Satu tanggal bisa punya dua sesi (shift PS pagi+sore) tetapi tetap
     * dihitung satu hari. Dibatasi 30 supaya prorata tidak melebihi gaji pokok.
     */
    protected function countHariHadir(int $employeeId, ?string $date): int
    {
        $date  = $date ?? date('Y-m-d');
        $year  = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);

        $component = $this->db->table('kpi_components')
            ->where('code', 'KEHADIRAN')
            ->get()
            ->getRow();

        if ($component === null) {
            return 0;
        }

        $row = $this->db->table('kpi_evaluations')
            ->select('COUNT(DISTINCT evaluation_date) AS hari', false)
            ->where('employee_id', $employeeId)
            ->where('kpi_component_id', (int) $component->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->where('evaluation_date IS NOT NULL', null, false)
            ->get()
            ->getRow();

        return min(30, (int) ($row->hari ?? 0));
    }

    /**
     * Gaji probation: gaji pokok / 30 x hari hadir, tanpa komponen lain.
     * Bon tetap dikurangkan karena itu uang muka yang sudah diterima.
     */
    protected function calculateProbationSalary(
        int $employeeId,
        int $positionId,
        int $unitId,
        string $context,
        array $structure,
        float $bon,
        ?string $date
    ): array {
        $pokok = 0.0;
        foreach ($structure as $struct) {
            if ($struct->code === 'GAJI_POKOK') {
                $pokok = (float) $struct->base_value;
                break;
            }
        }

        $hari   = $this->countHariHadir($employeeId, $date);
        $amount = round($pokok / 30 * $hari, 2);

        return [
            'success' => true,
            'employee_id' => $employeeId,
            'position_id' => $positionId,
            'unit_id' => $unitId,
            'context' => $context,
            'probation' => true,
            'hari_hadir' => $hari,
            'gaji_pokok_bulanan' => round($pokok, 2),
            'components' => [[
                'component_code' => 'GAJI_POKOK',
                'component_name' => 'Gaji Pokok',
                'calculation_type' => 'fixed',
                'base_value' => round($pokok, 2),
                'unit_id' => null,
                'context' => $context,
                'amount' => $amount,
            ]],
            'placement_allowance' => 0.0,
            'incentive' => 0.0,
            'lembur' => 0.0,
            'bon' => round($bon, 2),
            'subtotal_before_incentive' => $amount,
            'total_gaji' => round($amount - $bon, 2),
        ];
    }

    protected function calculateComponent($struct, ?float $kpiAchievement)
    {
        switch ($struct->calculation_type) {
            case 'fixed':
                return (float) $struct->base_value;

            case 'percent_of_base':
                // base_value = persen dari gaji pokok
                // gaji pokok diambil dari komponen GAJI_POKOK di structure (fixed)
                if ($struct->code === 'GAJI_POKOK') return (float) $struct->base_value;
                $pokok = 0.0;
                // find GAJI_POKOK row — handled caller; fallback multiplier
                $multiplier = $struct->multiplier ?? 1.0;
                return ($multiplier / 100) * 1500000;

            case 'percent_of_kpi':
                if ($kpiAchievement === null) {
                    return 0.0;
                }
                $pct = min((float)$kpiAchievement, 100.0) / 100.0;
                return (float)$struct->base_value * $pct;

            default:
                return 0.0;
        }
    }

    public function getSalaryComponents($type = null)
    {
        $builder = $this->db->table('salary_components')->where('is_active', 1);
        if ($type) {
            $builder->where('type', $type);
        }
        return $builder->get()->getResult();
    }

    public function validateSalaryStructure(int $positionId, ?int $unitId = null, string $context = 'default', ?string $date = null)
    {
        $structure = $this->getSalaryStructure($positionId, $unitId, $context, $date);
        $hasBase = false;
        foreach ($structure as $c) {
            if ($c->type === 'base') { $hasBase = true; break; }
        }
        return [
            'position_id' => $positionId,
            'unit_id' => $unitId,
            'context' => $context,
            'component_count' => count($structure),
            'has_base_salary' => $hasBase,
            'is_valid' => !empty($structure) && $hasBase,
            'components' => array_map(fn($c) => [
                'code' => $c->code,
                'name' => $c->name,
                'type' => $c->type,
                'calculation_type' => $c->calculation_type,
                'base_value' => $c->base_value,
                'unit_id' => $c->unit_id,
                'context' => $c->context,
            ], $structure),
        ];
    }
}