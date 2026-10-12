<?php

namespace App\Services\Finance;

use Config\Finance;

/**
 * KPI Finance (jabatan 0) untuk engine KPI pegawai (penilaian_kinerja/gaji).
 *
 * Finance berada di Head Office dan mengelola SEMUA cabang, sehingga nilai
 * tiap KPI dihitung sebagai RATA-RATA skor antar-cabang (unit operasional,
 * kecuali HO) dari scorecard FinanceKpiCalculationService.
 *
 * Bobot mengikuti keputusan bisnis Dashboard Finance (Config\Finance::
 * $kpiWeights: 30/20/15/10/10/5/5/5) dan ditulis ke kpi_weights posisi 0 —
 * bukan di-hardcode di sini.
 *
 * Kode komponen memakai prefix FIN_ agar tidak bentrok dengan komponen
 * divisi lain (mis. IMPROVEMENT dipakai jabatan 43/44).
 */
class FinanceKpiService
{
    /** Kode komponen (kpi_components.code) khusus Finance. */
    public const CODES = [
        'FIN_KESEHATAN_UANG',
        'FIN_AKURASI',
        'FIN_CASH_FLOW',
        'FIN_HUTANG_PIUTANG',
        'FIN_REKONSILIASI',
        'FIN_PAYROLL',
        'FIN_COMPLIANCE',
        'FIN_IMPROVEMENT',
    ];

    /** NAMA_UNIT Head Office — unit pusat, bukan cabang. */
    public const HO_UNIT_NAME = 'Head Office';

    private FinanceKpiCalculationService $calc;

    /** @var array<string,array<int,array<string,array>>> cache rows[ym][unit][code] */
    private array $scorecards = [];

    public function __construct(?FinanceKpiCalculationService $calc = null)
    {
        $this->calc = $calc ?? new FinanceKpiCalculationService();
    }

    /**
     * Kode konfigurasi Finance (huruf kecil) dari kode komponen FIN_*.
     * Contoh: FIN_KESEHATAN_UANG → kesehatan_uang.
     */
    public static function configCode(string $componentCode): string
    {
        return strtolower((string) preg_replace('/^FIN_/', '', $componentCode));
    }

    /**
     * Rata-rata skor antar-cabang untuk satu kode.
     *
     * Skor null (belum ada data di cabang tsb) tidak diikutkan; jika semua
     * cabang null → null (dikecualikan dari total, bukan 0 palsu).
     *
     * @param array<int,array<string,array{score:?float}>> $rowsByUnit rows[unit][code]
     */
    public static function averageScore(array $rowsByUnit, string $configCode): ?float
    {
        $sum = 0.0;
        $n   = 0;
        foreach ($rowsByUnit as $rows) {
            $score = $rows[$configCode]['score'] ?? null;
            if ($score === null) {
                continue;
            }
            $sum += (float) $score;
            $n++;
        }

        return $n > 0 ? round($sum / $n, 2) : null;
    }

    /**
     * Daftar unit operasional (cabang) dari master `unit`, bukan HO.
     *
     * @return int[]
     */
    public function branchUnits(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $rows = \Config\Database::connect()
            ->table('unit')
            ->select('idunit')
            ->where('NAMA_UNIT !=', self::HO_UNIT_NAME)
            ->where('idunit >', 0)
            ->orderBy('idunit', 'ASC')
            ->get()
            ->getResultArray();

        $cache = array_map(static fn ($r) => (int) $r['idunit'], $rows);

        return $cache;
    }

    /**
     * Data lengkap satu komponen Finance untuk engine KPI.
     *
     * @return array{achievement:?float, target:?float, actual:?float,
     *              shortfall:?float, source:string}
     */
    public function componentData(string $code, int $month, int $year): array
    {
        $rowsByUnit = [];
        foreach ($this->branchUnits() as $unit) {
            $rowsByUnit[$unit] = $this->rowsFor($unit, $month, $year);
        }

        $achievement = self::averageScore($rowsByUnit, self::configCode($code));

        return [
            'achievement' => $achievement,
            'target'      => 100.0,
            'actual'      => $achievement,
            'shortfall'   => $achievement === null ? null : round(max(100.0 - $achievement, 0), 2),
            'source'      => 'Dashboard Finance (rata-rata ' . count($rowsByUnit) . ' cabang)',
        ];
    }

    /** Bobot KPI Finance dari Config\Finance (sumber tunggal keputusan bisnis). */
    public function weights(): array
    {
        return (new Finance())->kpiWeights;
    }

    /**
     * Ambil baris scorecard satu unit untuk satu periode, di-cache agar
     * scorecard() (yang menulis snapshot) tidak dipanggil berulang.
     *
     * @return array<string,array>
     */
    private function rowsFor(int $unit, int $month, int $year): array
    {
        $ym = sprintf('%04d-%02d', $year, $month);

        if (!isset($this->scorecards[$ym][$unit])) {
            $scorecard = $this->calc->scorecard($unit, $month, $year);
            $this->scorecards[$ym][$unit] = $scorecard['rows'] ?? [];
        }

        return $this->scorecards[$ym][$unit];
    }
}
