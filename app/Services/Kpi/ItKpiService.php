<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiComponent;
use App\Models\ModelKpiItMetric;

/**
 * ItKpiService — KPI Team IT (jabatan 45).
 *
 *   IT_DEVELOPMENT  25  commit `feat` bulan ini (target 15)
 *   IT_BUG_FIX      25  commit `fix` bulan ini (target 30)
 *   IT_BACKUP       25  hari backup sukses / hari dimonitor × 100
 *   IT_UPTIME       15  hari uptime OK / hari dimonitor × 100
 *   IT_DOCS         10  commit `docs` bulan ini (target 5)
 *
 * Data mentah bulanan dikumpulkan `spark it:kpi-sync` (ItKpiSyncService) dan
 * disimpan sebagai snapshot `kpi_it_metrics`, sehingga halaman penilaian tidak
 * memanggil GitHub dan skor bulan lalu tidak berubah. Uptime dibaca dari ledger
 * `it:uptime-check`, bukan API pihak ketiga.
 *
 * Komponen tanpa data (belum sync, ledger kosong, API gagal) bernilai null
 * dan dikecualikan dari total — BUKAN 0. Hari tanpa entri backup dihitung
 * gagal, sesuai aturan bisnis backup harian.
 */
class ItKpiService
{
    /** ID_JABATAN Team IT / IT & System. */
    public const POSITION_ID = 45;

    /** Urutan tampil komponen. */
    public const CODES = [
        'IT_DEVELOPMENT',
        'IT_BUG_FIX',
        'IT_BACKUP',
        'IT_UPTIME',
        'IT_DOCS',
    ];

    /** Bobot referensi (sumber final: kpi_weights posisi 45). */
    public const WEIGHTS = [
        'IT_DEVELOPMENT' => 25.0,
        'IT_BUG_FIX'     => 25.0,
        'IT_BACKUP'      => 25.0,
        'IT_UPTIME'      => 15.0,
        'IT_DOCS'        => 10.0,
    ];

    /** Target default bila baris kpi_targets belum ada. */
    public const DEFAULT_TARGETS = [
        'IT_DEVELOPMENT' => 15.0,
        'IT_BUG_FIX'     => 30.0,
        'IT_BACKUP'      => 100.0,
        'IT_UPTIME'      => 100.0,
        'IT_DOCS'        => 5.0,
    ];

    protected $metricModel;
    protected $componentModel;

    public function __construct(
        ?ModelKpiItMetric $metricModel = null,
        ?ModelKpiComponent $componentModel = null
    ) {
        $this->metricModel    = $metricModel ?? new ModelKpiItMetric();
        $this->componentModel = $componentModel ?? new ModelKpiComponent();
    }

    /**
     * Data satu komponen untuk engine KPI.
     *
     * @return array{achievement:?float,target:?float,actual:?float,shortfall:?float,source:string}
     */
    public function componentData(string $code, int $month, int $year): array
    {
        if (! in_array($code, self::CODES, true)) {
            return $this->emptyResult();
        }

        $metrics = $this->metrics($year, $month);
        $target  = $this->targetFor($code, $year, $month);

        return self::evaluate($code, $metrics, $target);
    }

    /**
     * Snapshot bulanan; null bila `it:kpi-sync` belum pernah jalan.
     *
     * @return array<string,mixed>|null
     */
    public function metrics(int $year, int $month): ?array
    {
        $row = $this->metricModel
            ->where('period', sprintf('%04d-%02d', $year, $month))
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Target aktif komponen IT. Baris IT memakai unit_id NULL + position_id 45
     * karena KPI ini berlaku untuk seluruh unit (Head Office).
     */
    public function targetFor(string $code, int $year, int $month): float
    {
        $fallback = self::DEFAULT_TARGETS[$code] ?? 0.0;

        $component = $this->componentModel->getByCode($code);
        if (! $component) {
            return (float) $fallback;
        }

        $date = sprintf('%04d-%02d-15', $year, $month);
        $row  = \Config\Database::connect()
            ->table('kpi_targets')
            ->where('kpi_component_id', (int) $component->id)
            ->where('position_id', self::POSITION_ID)
            ->where('unit_id', null)
            ->where('context', 'default')
            ->where('effective_from <=', $date)
            ->groupStart()
                ->where('effective_to >=', $date)
                ->orWhere('effective_to IS NULL')
            ->groupEnd()
            ->orderBy('effective_from', 'DESC')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();

        return $row && $row->target_value !== null
            ? (float) $row->target_value
            : (float) $fallback;
    }

    /* ═══════════════════════ RUMUS (murni) ═══════════════════════ */

    /**
     * Format angka untuk halaman penilaian. null = pakai format lama.
     */
    public static function displayFormat(string $code): ?string
    {
        if (in_array($code, ['IT_DEVELOPMENT', 'IT_BUG_FIX', 'IT_DOCS'], true)) {
            return 'count';
        }

        if (in_array($code, ['IT_BACKUP', 'IT_UPTIME'], true)) {
            return 'percent';
        }

        return null;
    }

    /**
     * Satuan yang ditampilkan setelah angka (commit).
     */
    public static function displayUnit(string $code): ?string
    {
        if (in_array($code, ['IT_DEVELOPMENT', 'IT_BUG_FIX', 'IT_DOCS'], true)) {
            return 'commit';
        }

        return null;
    }

    /**
     * Skor rasio commit, capped 100. Null = sumber data tidak tersedia.
     */
    public static function scoreCount(?int $actual, float $target): ?float
    {
        if ($actual === null) {
            return null;
        }
        if ($target <= 0) {
            return 0.0;
        }

        return round(min(((float) $actual / $target) * 100.0, 100.0), 4);
    }

    /**
     * Skor hari OK / hari dimonitor. Null bila belum ada hari termonitor.
     */
    public static function scoreDays(?int $okDays, ?int $checkedDays): ?float
    {
        if ($okDays === null || $checkedDays === null || $checkedDays <= 0) {
            return null;
        }

        return round(min(((float) $okDays / $checkedDays) * 100.0, 100.0), 4);
    }

    /**
     * Uji restore bulanan yang gagal membatasi skor Backup maksimal 50.
     */
    public static function applyRestoreCap(?float $score, bool $restoreFailed): ?float
    {
        if ($score === null) {
            return null;
        }

        return $restoreFailed ? min($score, 50.0) : $score;
    }

    /**
     * @param array<string,mixed>|null $metrics
     *
     * @return array{achievement:?float,target:?float,actual:?float,shortfall:?float,source:string}
     */
    public static function evaluate(string $code, ?array $metrics, ?float $target): array
    {
        $target = $target ?? (self::DEFAULT_TARGETS[$code] ?? 0.0);

        if ($metrics === null) {
            return [
                'achievement' => null,
                'target'      => (float) $target,
                'actual'      => null,
                'shortfall'   => null,
                'source'      => 'Belum disinkronkan (jalankan it:kpi-sync)',
            ];
        }

        if (in_array($code, ['IT_DEVELOPMENT', 'IT_BUG_FIX', 'IT_DOCS'], true)) {
            $field  = $code === 'IT_DEVELOPMENT'
                ? 'feat_commits'
                : ($code === 'IT_BUG_FIX' ? 'fix_commits' : 'docs_commits');
            $raw    = $metrics[$field] ?? null;
            $actual = $raw === null ? null : (float) $raw;
            $score  = self::scoreCount($raw === null ? null : (int) $raw, (float) $target);

            return [
                'achievement' => $score,
                'target'      => (float) $target,
                'actual'      => $actual,
                'shortfall'   => $actual === null
                    ? null
                    : round(max((float) $target - $actual, 0.0), 4),
                'source'      => 'Commit ' . strtolower(substr($field, 0, strpos($field, '_')))
                    . ' GitHub bulan berjalan',
            ];
        }

        $okField      = $code === 'IT_BACKUP' ? 'backup_ok_days' : 'uptime_ok_days';
        $checkedField = $code === 'IT_BACKUP' ? 'backup_checked_days' : 'uptime_checked_days';

        $ok      = isset($metrics[$okField]) && $metrics[$okField] !== null
            ? (int) $metrics[$okField]
            : null;
        $checked = isset($metrics[$checkedField]) && $metrics[$checkedField] !== null
            ? (int) $metrics[$checkedField]
            : null;

        $actual = ($ok !== null && $checked !== null && $checked > 0)
            ? round(min(($ok / $checked) * 100.0, 100.0), 2)
            : null;

        $score = self::scoreDays($ok, $checked);

        if ($code === 'IT_BACKUP') {
            $restoreFailed = isset($metrics['restore_ok'])
                && $metrics['restore_ok'] !== null
                && (int) $metrics['restore_ok'] === 0;
            $score = self::applyRestoreCap($score, $restoreFailed);
        }

        return [
            'achievement' => $score,
            'target'      => (float) $target,
            'actual'      => $actual,
            'shortfall'   => $actual === null
                ? null
                : round(max(100.0 - $actual, 0.0), 2),
            'source'      => $code === 'IT_BACKUP'
                ? 'Hari backup sukses / hari dimonitor'
                : 'Hari uptime OK / hari dimonitor',
        ];
    }

    /**
     * @return array{achievement:?float,target:?float,actual:?float,shortfall:?float,source:string}
     */
    private function emptyResult(): array
    {
        return [
            'achievement' => null,
            'target'      => null,
            'actual'      => null,
            'shortfall'   => null,
            'source'      => '',
        ];
    }
}
