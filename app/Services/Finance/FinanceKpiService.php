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
 * $kpiWeights: 30/20/15/10/10/10/5) dan ditulis ke kpi_weights posisi 0 —
 * bukan di-hardcode di sini. KPI "Improvement Finance" dihapus 2026-10-16;
 * bobotnya (5) dipindah ke Payroll (jadi 10).
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
    ];

    /** NAMA_UNIT Head Office — unit pusat, bukan cabang. */
    public const HO_UNIT_NAME = 'Head Office';

    private FinanceKpiCalculationService $calc;

    /** @var array<string,array<int,array<string,array>>> cache rows[ym][unit][code] */
    private array $scorecards = [];

    /** @var array<string,array<int,array<string,mixed>>> cache scorecard penuh[ym][unit] */
    private array $scorecardsFull = [];

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
            $this->scorecards[$ym][$unit]     = $scorecard['rows'] ?? [];
            $this->scorecardsFull[$ym][$unit] = $scorecard;
        }

        return $this->scorecards[$ym][$unit];
    }

    /**
     * Rincian Kesehatan Uang PER CABANG untuk ditampilkan di halaman penilaian
     * kinerja (pola sama seperti "Omset Wilayah"/"Target Cabang" milik SPV).
     *
     * Memakai cache scorecard yang sudah dihitung componentData(), jadi tidak
     * mengulang query per cabang.
     *
     * @return array<int,array{unit:int,nama:string,target_rasio:float,rasio:?float,
     *   skor:?float,kontribusi:?float,masuk:int,masuk_tunai:int,masuk_transfer:int,
     *   keluar:int,hpp:int,kas_keluar:int,payroll:int,target_masuk:int,
     *   selisih_masuk:int,status:string,reached:bool,shortfall:?float}>
     */
    public function kesehatanUangBreakdown(int $month, int $year): array
    {
        $ym    = sprintf('%04d-%02d', $year, $month);
        $nama  = $this->branchUnitNames();
        $out   = [];

        foreach ($this->branchUnits() as $unit) {
            $this->rowsFor($unit, $month, $year); // pastikan cache terisi

            $detail = $this->scorecardsFull[$ym][$unit]['kesehatan_uang_detail'] ?? null;
            if ($detail === null) {
                continue;
            }

            $d    = $detail['detail'] ?? [];
            $skor = $detail['score'] ?? null;

            $masuk       = (int) ($d['total_masuk'] ?? 0);
            $keluar      = (int) ($d['total_keluar'] ?? 0);
            $targetRasio = (float) ($d['target_rasio'] ?? 120);
            // Uang masuk minimal agar rasio menyentuh target: keluar x target%.
            $targetMasuk = (int) round($keluar * $targetRasio / 100);

            $out[] = [
                'unit'           => $unit,
                'nama'           => $nama[$unit] ?? '',
                'target_rasio'   => $targetRasio,
                'rasio'          => $d['rasio'] ?? null,
                'skor'           => $skor,
                'kontribusi'     => $d['kontribusi'] ?? null,
                'masuk'          => $masuk,
                'masuk_tunai'    => (int) ($d['masuk_tunai'] ?? 0),
                'masuk_transfer' => (int) ($d['masuk_transfer'] ?? 0),
                'keluar'         => $keluar,
                'hpp'            => (int) ($d['hpp'] ?? 0),
                'kas_keluar'     => (int) ($d['kas_keluar'] ?? 0),
                'payroll'        => (int) ($d['payroll'] ?? 0),
                'target_masuk'   => $targetMasuk,
                // Positif = uang masuk kurang dari target; negatif = berlebih.
                'selisih_masuk'  => $targetMasuk - $masuk,
                'status'         => (string) ($detail['status'] ?? ''),
                'reached'        => $skor !== null && (float) $skor >= 100.0,
                'shortfall'      => $skor === null ? null : round(max(100.0 - (float) $skor, 0), 2),
            ];
        }

        return $out;
    }

    /**
     * Rincian KPI Akurasi Laporan Keuangan PER CABANG untuk halaman penilaian
     * kinerja (pola sama seperti kesehatanUangBreakdown).
     *
     * Sumber: Rekonsiliasi Harian (finance_rekon_daily). Nilai tiap cabang =
     * (hari terverifikasi tanpa selisih ÷ hari terverifikasi) x 100.
     *
     * @return array<int,array{unit:int,nama:string,skor:?float,akurasi:?float,
     *   bobot:float,kontribusi:?float,hari_terekon:int,hari_cocok:int,
     *   hari_selisih:int,status:string,reached:bool,shortfall:?float}>
     */
    public function akurasiBreakdown(int $month, int $year): array
    {
        $ym   = sprintf('%04d-%02d', $year, $month);
        $nama = $this->branchUnitNames();
        $out  = [];

        foreach ($this->branchUnits() as $unit) {
            $this->rowsFor($unit, $month, $year); // pastikan cache terisi

            $detail = $this->scorecardsFull[$ym][$unit]['akurasi_rekon_detail'] ?? null;
            if ($detail === null) {
                continue;
            }

            $d        = $detail['detail'] ?? [];
            $skor     = $detail['score'] ?? null;
            $terekon  = (int) ($d['hari_terekon'] ?? 0);
            $cocok    = (int) ($d['hari_cocok'] ?? 0);

            $out[] = [
                'unit'         => $unit,
                'nama'         => $nama[$unit] ?? '',
                'skor'         => $skor,
                'akurasi'      => $d['akurasi'] ?? null,
                'bobot'        => (float) ($d['bobot'] ?? 20),
                'kontribusi'   => $d['kontribusi'] ?? null,
                'hari_terekon' => $terekon,
                'hari_cocok'   => $cocok,
                'hari_selisih' => (int) ($d['hari_selisih'] ?? max($terekon - $cocok, 0)),
                'status'       => (string) ($detail['status'] ?? ''),
                'reached'      => $skor !== null && (float) $skor >= 100.0,
                'shortfall'    => $skor === null ? null : round(max(100.0 - (float) $skor, 0), 2),
            ];
        }

        return $out;
    }

    /**
     * Rincian KPI Net Cash Flow PER CABANG untuk halaman penilaian kinerja
     * (pola sama seperti kesehatanUangBreakdown/akurasiBreakdown).
     *
     * Nilai tiap cabang = skor dari CashFlowCalculator:
     *   NCF%  = Net Cash Flow ÷ Penerimaan × 100
     *   skor  = min((NCF% ÷ target) × 100, 100)
     * sehingga target bisnis (20%) terlihat berdampingan dengan capaian tiap
     * cabang — bukan hanya skor 0–100.
     *
     * Memakai cache scorecard yang sudah dihitung componentData(), jadi tidak
     * mengulang query per cabang.
     *
     * @return array<int,array{unit:int,nama:string,target_persen:float,
     *   persen:?float,skor:?float,bobot:float,kontribusi:?float,
     *   penjualan:int,service:int,penerimaan:int,kas_keluar:int,net:int,
     *   status:string,reached:bool,shortfall:?float}>
     */
    public function cashflowBreakdown(int $month, int $year): array
    {
        $ym   = sprintf('%04d-%02d', $year, $month);
        $nama = $this->branchUnitNames();
        $bobot = (float) ($this->weights()['cash_flow'] ?? 15);
        $out  = [];

        foreach ($this->branchUnits() as $unit) {
            $this->rowsFor($unit, $month, $year); // pastikan cache terisi

            $detail = $this->scorecardsFull[$ym][$unit]['cashflow_monthly'] ?? null;
            if ($detail === null) {
                continue;
            }

            $d      = $detail['detail'] ?? [];
            $skor   = $detail['score'] ?? null;
            $persen = $d['persen'] ?? null;

            $out[] = [
                'unit'          => $unit,
                'nama'          => $nama[$unit] ?? '',
                'target_persen' => (float) ($d['target_persen'] ?? 20),
                'persen'        => $persen,
                'skor'          => $skor,
                'bobot'         => $bobot,
                'kontribusi'    => $skor === null ? null : round((float) $skor * $bobot / 100, 2),
                'penjualan'     => (int) ($d['penjualan'] ?? 0),
                'service'       => (int) ($d['service'] ?? 0),
                'penerimaan'    => (int) ($d['kas_masuk'] ?? 0),
                'kas_keluar'    => (int) ($d['kas_keluar'] ?? 0),
                'net'           => (int) ($d['net'] ?? 0),
                'status'        => (string) ($detail['status'] ?? ''),
                'reached'       => $skor !== null && (float) $skor >= 100.0,
                'shortfall'     => $skor === null ? null : round(max(100.0 - (float) $skor, 0), 2),
            ];
        }

        return $out;
    }

    /**
     * Peta idunit => NAMA_UNIT untuk cabang (tanpa Head Office).
     *
     * @return array<int,string>
     */
    private function branchUnitNames(): array
    {
        $rows = \Config\Database::connect()
            ->table('unit')
            ->select('idunit, NAMA_UNIT')
            ->where('NAMA_UNIT !=', self::HO_UNIT_NAME)
            ->where('idunit >', 0)
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['idunit']] = (string) $r['NAMA_UNIT'];
        }

        return $map;
    }
}
