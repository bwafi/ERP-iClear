<?php

namespace App\Services\Payroll;

use Config\Database;

/**
 * HoSalaryDistributionService
 *
 * Menghitung pembagian gaji HO (Head Office) ke seluruh unit
 * berdasarkan aturan:
 * - Semua unit "pribadi" dihitung sebagai 1 (gabungan)
 * - Setiap unit "franchise" dihitung sebagai 1 per unit
 * - Contoh: 2 pribadi + 3 franchise = dibagi 4
 * - Contoh: 1 pribadi + 3 franchise = dibagi 4
 *
 * Menghasilkan 2 informasi per karyawan HO:
 * 1. perkiraan_gaji — dihitung seperti slip_gaji (SalaryCalculationService)
 * 2. gaji_dipotong  — nominal aktual dari finance_payroll (payroll)
 */
class HoSalaryDistributionService
{
    protected $db;
    protected $salaryService;

    /**
     * Cache per-request. calculateDistribution() sangat mahal (menghitung KPI
     * tiap pegawai HO) dan getUnitShares() memakai hasil yang sama; tanpa
     * cache, /asset_berjalan menjalankan seluruhnya dua kali.
     */
    private static $memo = [];

    public function __construct()
    {
        $this->db = Database::connect();
        $this->salaryService = new SalaryCalculationService();
    }

    /**
     * Hitung pembagian gaji HO untuk periode tertentu.
     *
     * @param string $bulan  'YYYY-MM'
     * @param string $tahun  'YYYY' (optional, diambil dari bulan)
     * @return array [
     *   'divisor' => int (total pembagi),
     *   'pribadi_count' => int,
     *   'franchise_count' => int,
     *   'unit_detail' => array[],
     *   'employees' => array[], // karyawan HO dengan perkiraan & dipotong
     *   'total_perkiraan' => float,
     *   'total_dipotong' => float,
     * ]
     */
    public function calculateDistribution(string $bulan, ?string $tahun = null): array
    {
        [$tahun, $bln] = $this->parseBulan($bulan, $tahun);

        $key = \App\Libraries\ReadCache::kunci('dist', $tahun, $bln);
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        // 1. Ambil unit pribadi & franchise (non-trial, non-HO)
        $units = $this->db->table('unit')
            ->select('idunit, NAMA_UNIT, jenis, is_trial, is_operational')
            ->where('idunit !=', 50) // exclude HO itself
            ->where('is_trial', 0)
            ->where('is_operational', 1)
            ->get()
            ->getResultArray();

        $pribadiIds = [];
        $franchiseIds = [];
        foreach ($units as $u) {
            if (($u['jenis'] ?? '') === 'pribadi') {
                $pribadiIds[] = (int)$u['idunit'];
            } elseif (($u['jenis'] ?? '') === 'franchise') {
                $franchiseIds[] = (int)$u['idunit'];
            }
        }

        $pribadiCount = count($pribadiIds) > 0 ? 1 : 0;
        $franchiseCount = count($franchiseIds);
        $divisor = $pribadiCount + $franchiseCount;

        // 2. Ambil karyawan HO (unit_id = 50) yang aktif & menerima gaji
        // TERIMA_GAJI=1: akun tanpa gaji (mis. Direktur) tidak ikut dibagi.
        $hoEmployees = $this->db->table('akun')
            ->select('ID_AKUN, NAMA_AKUN, ID_JABATAN, ALAMAT')
            ->where('ID_UNIT', 50)
            ->where('STATUS_PEGAWAI', 1)
            ->where('TERIMA_GAJI', 1)
            ->groupStart()
            ->where('deleted', null)
            ->orWhere('deleted', 0)
            ->groupEnd()
            ->get()
            ->getResultArray();

        // 3. Ambil data payroll aktual (finance_payroll) untuk HO karyawan
        $from = $bulan . '-01';
        $to   = date('Y-m-t', strtotime($from));
        $hoIds = array_column($hoEmployees, 'ID_AKUN');

        $payrollActual = [];
        if ($hoIds) {
            $rows = $this->db->table('finance_payroll')
                ->select('pegawai_id, total, potongan_kasbon, total_bersih, status')
                ->whereIn('pegawai_id', $hoIds)
                ->where('due_date >=', $from)
                ->where('due_date <=', $to)
                ->get()
                ->getResultArray();

            foreach ($rows as $r) {
                $payrollActual[(int)$r['pegawai_id']] = [
                    'total'          => (float)$r['total'],
                    'potongan_kasbon'=> (float)($r['potongan_kasbon'] ?? 0),
                    'total_bersih'   => (float)($r['total_bersih'] ?? $r['total']),
                    'status'         => $r['status'],
                ];
            }
        }

        // 4. Hitung perkiraan gaji (pakai SalaryCalculationService sama seperti slip_gaji)
        $kpiService = new \App\Services\Kpi\KpiCalculationService();
        $employees = [];
        $totalPerkiraan = 0.0;
        $totalDipotong  = 0.0;

        foreach ($hoEmployees as $emp) {
            $id = (int)$emp['ID_AKUN'];
            $positionId = (int)$emp['ID_JABATAN'];

            // KPI scores untuk periode ini
            $periodeDate = $tahun . '-' . str_pad((string)$bln, 2, '0', STR_PAD_LEFT) . '-15';
            try {
                $kpi = $kpiService->calculateForSalary($id, (string)$bln, (string)$tahun, 'gaji', $periodeDate, $periodeDate);
            } catch (\Throwable $e) {
                $kpi = [
                    'skor_total'  => 0,
                    'skor_total2' => 0,
                    'insentif'    => 0,
                    'akun'        => (object)['tunjangan_penempatan' => 0],
                    'jabatan'     => $positionId,
                    'unit'        => 50,
                ];
            }

            $placement = $kpi['akun']->tunjangan_penempatan ?? 0;

            $calc = $this->salaryService->calculateSalary(
                $id,
                $positionId,
                50, // unit HO
                'gaji',
                [
                    'TUNJANGAN_KINERJA' => $kpi['skor_total'] ?? null,
                    'TUNJANGAN_ABSEN'   => $kpi['skor_total2'] ?? null,
                ],
                $placement,
                (float)($kpi['insentif'] ?? 0),
                0.0, // lembur - HO biasanya tidak lembur
                0.0, // bon
                $periodeDate
            );

            $perkiraanGaji = (float)$calc['total_gaji'];
            $dipotong = $payrollActual[$id]['total_bersih'] ?? $payrollActual[$id]['total'] ?? 0.0;

            $employees[] = [
                'id'            => $id,
                'nama'          => $emp['NAMA_AKUN'],
                'jabatan'       => $positionId,
                'perkiraan_gaji'=> round($perkiraanGaji, 2),
                'gaji_dipotong' => round($dipotong, 2),
                'payroll_status'=> $payrollActual[$id]['status'] ?? 'belum_ada',
                'components'    => $calc['components'],
                'placement'     => $placement,
                'insentif'      => $calc['incentive'],
            ];

            $totalPerkiraan += $perkiraanGaji;
            $totalDipotong  += $dipotong;
        }

        // 5. Unit detail untuk info
        $unitDetail = [];
        foreach ($units as $u) {
            $unitDetail[] = [
                'idunit'    => (int)$u['idunit'],
                'nama'      => $u['NAMA_UNIT'],
                'jenis'     => $u['jenis'],
                'is_counted'=> ($u['jenis'] === 'pribadi' && $pribadiCount === 1) || $u['jenis'] === 'franchise',
            ];
        }

        return self::$memo[$key] = [
            'divisor'           => $divisor,
            'pribadi_count'     => $pribadiCount,
            'franchise_count'   => $franchiseCount,
            'unit_detail'       => $unitDetail,
            'employees'         => $employees,
            'total_perkiraan'   => round($totalPerkiraan, 2),
            'total_dipotong'    => round($totalDipotong, 2),
            'per_employee_share'=> $divisor > 0 ? round($totalPerkiraan / $divisor, 2) : 0,
            'per_employee_actual'=> $divisor > 0 ? round($totalDipotong / $divisor, 2) : 0,
        ];
    }

    /**
     * Ambil detail gaji HO per unit (bagaimana tiap unit mendapat bagian).
     */
    public function getUnitShares(string $bulan, ?string $tahun = null): array
    {
        $dist = $this->calculateDistribution($bulan, $tahun);

        $shares = [];
        foreach ($dist['unit_detail'] as $u) {
            if ($u['is_counted']) {
                $shares[$u['idunit']] = [
                    'unit_id'     => $u['idunit'],
                    'unit_nama'   => $u['nama'],
                    'unit_jenis'  => $u['jenis'],
                    'share_pct'   => $dist['divisor'] > 0 ? round(100 / $dist['divisor'], 2) : 0,
                    'share_nominal_perkiraan' => $dist['per_employee_share'],
                    'share_nominal_actual'    => $dist['per_employee_actual'],
                ];
            } else {
                // Pribadi ke-2, ke-3 dst tidak mendapat share tambahan
                $shares[$u['idunit']] = [
                    'unit_id'     => $u['idunit'],
                    'unit_nama'   => $u['nama'],
                    'unit_jenis'  => $u['jenis'],
                    'share_pct'   => 0,
                    'share_nominal_perkiraan' => 0,
                    'share_nominal_actual'    => 0,
                    'note'        => 'Unit pribadi tambahan (sudah terwakili unit pribadi pertama)',
                ];
            }
        }

        return $shares;
    }

    private function parseBulan(string $bulan, ?string $tahun): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $bulan, $m)) {
            return [(int)$m[1], (int)$m[2]];
        }
        $bln = (int)($bulan ?? date('m'));
        $thn = (int)($tahun ?? date('Y'));
        return [$thn, $bln];
    }
}