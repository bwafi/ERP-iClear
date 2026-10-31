<?php

namespace App\Services\Payroll;

use Config\Database;

/**
 * HoSalaryDistributionService
 *
 * Menghitung pembagian gaji HO (Head Office) ke seluruh unit.
 *
 * Aturan pembagi:
 * - Semua unit "pribadi" dihitung sebagai 1 bagian (gabungan)
 * - Setiap unit "franchise" dihitung sebagai 1 bagian per unit
 * - Unit trial / non-operasional tidak menanggung gaji HO
 * - SPV (jabatan 40) TIDAK ikut pool global: gajinya dibagi hanya ke unit
 *   wilayahnya (tabel spv_units) yang non-trial & operasional. Kalau SPV tidak
 *   punya wilayah valid, gajinya jatuh ke pool global supaya tidak hilang.
 *
 * Menghasilkan 2 informasi per karyawan HO:
 * 1. perkiraan_gaji — dihitung seperti slip_gaji (SalaryCalculationService)
 * 2. gaji_dipotong  — nominal aktual dari finance_payroll (payroll)
 */
class HoSalaryDistributionService
{
    /** Umur cache lintas request (detik). */
    private const TTL = 300;

    /** ID jabatan SPV. */
    private const JABATAN_SPV = 40;

    protected $db;
    protected $salaryService;

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
     *   'divisor' => int (pembagi global non-SPV),
     *   'pribadi_count' => int,
     *   'franchise_count' => int,
     *   'unit_detail' => array[],
     *   'per_unit' => array[], // bagian tiap unit (non-SPV + SPV wilayah)
     *   'employees' => array[], // karyawan HO dengan perkiraan & dipotong
     *   'total_perkiraan' => float,
     *   'total_dipotong' => float,
     * ]
     */
    public function calculateDistribution(string $bulan, ?string $tahun = null): array
    {
        [$tahun, $bln] = self::parseBulan($bulan, $tahun);

        $cacheKey = self::cacheKey($bulan);
        $tersimpan = cache()->get($cacheKey);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        // 1. Ambil semua unit selain HO. Unit trial tetap diambil untuk
        // ditampilkan di rincian, tetapi tidak dihitung sebagai pembagi.
        $units = $this->db->table('unit')
            ->select('idunit, NAMA_UNIT, jenis, is_trial, is_operational')
            ->where('idunit !=', 50) // exclude HO itself
            ->orderBy('idunit', 'ASC')
            ->get()
            ->getResultArray();

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
            $aktual = (float)($payrollActual[$id]['total_bersih'] ?? 0);
            if ($aktual <= 0) {
                // Baris legacy bisa punya total_bersih 0; pakai total bruto.
                $aktual = (float)($payrollActual[$id]['total'] ?? 0);
            }
            $statusPayroll = (string)($payrollActual[$id]['status'] ?? 'belum_ada');
            $dibayar = $statusPayroll === 'dibayar' && $aktual > 0;
            $dipotong = $dibayar ? $aktual : 0.0;

            $employees[] = [
                'id'            => $id,
                'nama'          => $emp['NAMA_AKUN'],
                'jabatan'       => $positionId,
                'perkiraan_gaji'=> round($perkiraanGaji, 2),
                'gaji_dipotong' => round($dipotong, 2),
                // Yang sudah dibayar memakai nominal aktual (termasuk bonus),
                // sisanya tetap perkiraan — sama seperti aturan Total Gaji.
                'gaji_dibebankan' => round($dibayar ? $aktual : $perkiraanGaji, 2),
                'payroll_status'=> $statusPayroll,
                'components'    => $calc['components'],
                'placement'     => $placement,
                'insentif'      => $calc['incentive'],
            ];

            $totalPerkiraan += $perkiraanGaji;
            $totalDipotong  += $dipotong;
        }

        // 5. Wilayah SPV (spv_units). Hanya untuk karyawan HO berjabatan SPV.
        $spvIds = [];
        foreach ($hoEmployees as $emp) {
            if ((int)$emp['ID_JABATAN'] === self::JABATAN_SPV) {
                $spvIds[] = (int)$emp['ID_AKUN'];
            }
        }

        $wilayahSpv = [];
        if ($spvIds) {
            $rows = $this->db->table('spv_units')
                ->select('spv_id, unit_id')
                ->whereIn('spv_id', $spvIds)
                ->get()
                ->getResultArray();

            foreach ($rows as $r) {
                $wilayahSpv[(int)$r['spv_id']][] = (int)$r['unit_id'];
            }
        }

        // 6. Bagi gaji ke unit: non-SPV global, SPV per wilayah non-trial.
        $alokasi = self::alokasiKeUnit($units, $employees, $wilayahSpv);

        $unitDetail = [];
        foreach ($alokasi['per_unit'] as $u) {
            $unitDetail[] = [
                'idunit'    => $u['idunit'],
                'nama'      => $u['nama'],
                'jenis'     => $u['jenis'],
                'is_counted'=> in_array($u['idunit'], $alokasi['counted_unit_ids'], true),
                'perkiraan' => $u['total_perkiraan'],
                'dipotong'  => $u['total_dipotong'],
                'catatan'   => $u['catatan'],
            ];
        }

        $hasil = [
            'divisor'           => $alokasi['divisor'],
            'pribadi_count'     => $alokasi['pribadi_count'],
            'franchise_count'   => $alokasi['franchise_count'],
            // Unit pusat (pribadi pertama) menanggung tunjangan penempatan HO.
            'pribadi_unit_id'   => $alokasi['pribadi_unit_id'],
            'unit_detail'       => $unitDetail,
            'per_unit'          => $alokasi['per_unit'],
            'employees'         => $alokasi['employees'],
            'total_perkiraan'   => round($totalPerkiraan, 2),
            'total_dipotong'    => round($totalDipotong, 2),
            // Beban yang benar-benar ditanggung unit: aktual menggantikan
            // perkiraan milik karyawan yang sama, bukan dijumlah.
            'total_dibebankan'  => round(array_sum(array_column($alokasi['per_unit'], 'total_dibebankan')), 2),
            'total_non_spv_perkiraan' => $alokasi['total_non_spv_perkiraan'],
            'total_non_spv_dipotong'  => $alokasi['total_non_spv_dipotong'],
            'total_spv_perkiraan'     => $alokasi['total_spv_perkiraan'],
            'total_spv_dipotong'      => $alokasi['total_spv_dipotong'],
            // Bagian per unit untuk pool non-SPV (pembagi global).
            'per_employee_share'=> $alokasi['divisor'] > 0
                ? round($alokasi['total_non_spv_perkiraan'] / $alokasi['divisor'], 2)
                : 0,
            'per_employee_actual'=> $alokasi['divisor'] > 0
                ? round($alokasi['total_non_spv_dipotong'] / $alokasi['divisor'], 2)
                : 0,
        ];

        cache()->save($cacheKey, $hasil, self::TTL);

        return $hasil;
    }

    /**
     * Bagi nominal gaji karyawan ke unit tujuan.
     *
     * Karyawan non-SPV memakai pembagi global. Karyawan SPV memakai pembagi
     * wilayahnya sendiri (spv_units, hanya unit non-trial & operasional).
     *
     * Method murni (tanpa query) supaya aturan pembagian bisa diuji langsung.
     *
     * @param array[] $unitRows    baris unit: idunit, NAMA_UNIT, jenis, is_trial, is_operational
     * @param array[] $employees   karyawan: id, jabatan, perkiraan_gaji, gaji_dipotong
     * @param array<int,int[]> $wilayahSpv  spv_id => daftar unit_id
     */
    public static function alokasiKeUnit(array $unitRows, array $employees, array $wilayahSpv = []): array
    {
        $countable = [];
        $pribadiCount = 0;
        $franchiseCount = 0;

        foreach ($unitRows as $u) {
            $id = (int)($u['idunit'] ?? 0);
            if ($id <= 0 || $id === 50) {
                continue;
            }
            if ((int)($u['is_trial'] ?? 0) === 1 || (int)($u['is_operational'] ?? 1) !== 1) {
                continue;
            }

            $countable[$id] = $u;
            $jenis = (string)($u['jenis'] ?? '');
            if ($jenis === 'pribadi') {
                $pribadiCount = 1;
            } elseif ($jenis === 'franchise') {
                $franchiseCount++;
            }
        }

        [$divisor, $globalCounted] = self::bagianUnit($countable);
        $globalCountedFlip = array_fill_keys($globalCounted, true);

        // Unit pribadi pertama = pusat (Probolinggo). Tunjangan penempatan HO
        // dibayar pusat, tidak ikut dibagi ke cabang.
        $pribadiUnitId = null;
        foreach ($countable as $uid => $u) {
            if ((string) ($u['jenis'] ?? '') === 'pribadi') {
                $pribadiUnitId = (int) $uid;
                break;
            }
        }

        $perUnit = [];
        foreach ($unitRows as $u) {
            $id = (int)($u['idunit'] ?? 0);
            if ($id <= 0 || $id === 50) {
                continue;
            }
            $perUnit[$id] = [
                'idunit'             => $id,
                'nama'               => (string)($u['NAMA_UNIT'] ?? ''),
                'jenis'              => (string)($u['jenis'] ?? ''),
                'is_trial'           => (int)($u['is_trial'] ?? 0),
                'is_operasional'     => (int)($u['is_operational'] ?? 1),
                'non_spv_perkiraan'  => 0.0,
                'spv_perkiraan'      => 0.0,
                'non_spv_dipotong'   => 0.0,
                'spv_dipotong'       => 0.0,
                'non_spv_dibebankan' => 0.0,
                'spv_dibebankan'     => 0.0,
                'placement_perkiraan'  => 0.0,
                'placement_dipotong'   => 0.0,
                'placement_dibebankan' => 0.0,
            ];
        }

        $totalNonSpvPerkiraan = 0.0;
        $totalNonSpvDipotong  = 0.0;
        $totalSpvPerkiraan    = 0.0;
        $totalSpvDipotong     = 0.0;
        $hasilEmployees       = [];

        foreach ($employees as $emp) {
            $employeeId = (int)($emp['id'] ?? 0);
            $jabatan    = (int)($emp['jabatan'] ?? $emp['ID_JABATAN'] ?? 0);
            $isSpv      = $jabatan === self::JABATAN_SPV;

            $scope       = 'global';
            $counted     = $globalCounted;
            $scopeDiv    = $divisor;
            $wilayahIds  = [];

            if ($isSpv) {
                $wilayahIds = array_map('intval', $wilayahSpv[$employeeId] ?? []);
                $wilayahUnits = [];
                foreach ($wilayahIds as $uid) {
                    if (isset($countable[$uid])) {
                        $wilayahUnits[$uid] = $countable[$uid];
                    }
                }

                if ($wilayahUnits) {
                    [$scopeDiv, $counted] = self::bagianUnit($wilayahUnits);
                    $scope = 'wilayah';
                }
            }

            $perkiraan = (float)($emp['perkiraan_gaji'] ?? 0);
            $dipotong  = (float)($emp['gaji_dipotong'] ?? 0);
            // Beban yang dipakai invoice: aktual bila sudah dibayar, kalau
            // belum memakai perkiraan. Test lama tanpa kunci ini memakai
            // dipotong>0 sebagai aktual.
            $dibebankan = (float)($emp['gaji_dibebankan']
                ?? ($dipotong > 0 ? $dipotong : $perkiraan));

            // Tunjangan penempatan menempel ke pusat: keluarkan dari pool
            // cabang, lalu bebankan penuh ke unit pribadi pertama.
            $placement = max(0.0, (float) ($emp['placement'] ?? 0));
            $kePusat   = $pribadiUnitId !== null && $placement > 0;
            $dasarPerkiraan  = $kePusat ? max(0.0, $perkiraan - $placement) : $perkiraan;
            $dasarDipotong   = $kePusat ? max(0.0, $dipotong - $placement) : $dipotong;
            $dasarDibebankan = $kePusat ? max(0.0, $dibebankan - $placement) : $dibebankan;

            $bagianPerkiraan  = $scopeDiv > 0 ? $dasarPerkiraan / $scopeDiv : 0.0;
            $bagianDipotong   = $scopeDiv > 0 ? $dasarDipotong / $scopeDiv : 0.0;
            $bagianDibebankan = $scopeDiv > 0 ? $dasarDibebankan / $scopeDiv : 0.0;

            foreach ($counted as $uid) {
                if (! isset($perUnit[$uid])) {
                    continue;
                }
                if ($isSpv && $scope === 'wilayah') {
                    $perUnit[$uid]['spv_perkiraan']  += $bagianPerkiraan;
                    $perUnit[$uid]['spv_dipotong']   += $bagianDipotong;
                    $perUnit[$uid]['spv_dibebankan'] += $bagianDibebankan;
                } else {
                    $perUnit[$uid]['non_spv_perkiraan']  += $bagianPerkiraan;
                    $perUnit[$uid]['non_spv_dipotong']   += $bagianDipotong;
                    $perUnit[$uid]['non_spv_dibebankan'] += $bagianDibebankan;
                }
            }

            if ($kePusat && isset($perUnit[$pribadiUnitId])) {
                $perUnit[$pribadiUnitId]['placement_perkiraan']  += $placement;
                $perUnit[$pribadiUnitId]['placement_dipotong']   += min($placement, $dipotong);
                $perUnit[$pribadiUnitId]['placement_dibebankan'] += min($placement, $dibebankan);
            }

            if ($isSpv && $scope === 'wilayah') {
                $totalSpvPerkiraan += $perkiraan;
                $totalSpvDipotong  += $dipotong;
            } else {
                $totalNonSpvPerkiraan += $perkiraan;
                $totalNonSpvDipotong  += $dipotong;
            }

            $hasilEmployees[] = $emp + [
                'divisor'           => $scopeDiv,
                'scope'             => $scope,
                'wilayah_unit_ids'  => $wilayahIds,
            ];
        }

        foreach ($perUnit as &$u) {
            $u['non_spv_perkiraan']  = round($u['non_spv_perkiraan'], 2);
            $u['spv_perkiraan']      = round($u['spv_perkiraan'], 2);
            $u['non_spv_dipotong']   = round($u['non_spv_dipotong'], 2);
            $u['spv_dipotong']       = round($u['spv_dipotong'], 2);
            $u['non_spv_dibebankan'] = round($u['non_spv_dibebankan'], 2);
            $u['spv_dibebankan']     = round($u['spv_dibebankan'], 2);
            $u['placement_perkiraan']  = round($u['placement_perkiraan'], 2);
            $u['placement_dipotong']   = round($u['placement_dipotong'], 2);
            $u['placement_dibebankan'] = round($u['placement_dibebankan'], 2);
            $u['total_perkiraan']    = round($u['non_spv_perkiraan'] + $u['spv_perkiraan'] + $u['placement_perkiraan'], 2);
            $u['total_dipotong']     = round($u['non_spv_dipotong'] + $u['spv_dipotong'] + $u['placement_dipotong'], 2);
            $u['total_dibebankan']   = round($u['non_spv_dibebankan'] + $u['spv_dibebankan'] + $u['placement_dibebankan'], 2);

            // Basis yang dipakai kartu/invoice: payroll penuh, sebagian, atau
            // masih perkiraan. Aktual menggantikan perkiraan karyawan yang
            // sudah dibayar, jadi `total_dibebankan` selalu dipakai lebih dulu.
            if ($u['total_dibebankan'] <= 0.005) {
                $u['basis'] = 'nihil';
            } elseif ($u['total_dipotong'] > 0.005
                && abs($u['total_dibebankan'] - $u['total_dipotong']) > 0.005) {
                $u['basis'] = 'sebagian';
            } elseif ($u['total_dipotong'] > 0.005) {
                $u['basis'] = 'payroll';
            } else {
                $u['basis'] = 'perkiraan';
            }

            if ($u['is_trial'] === 1) {
                $u['catatan'] = 'Unit trial — tidak menanggung gaji HO';
            } elseif ($u['is_operasional'] !== 1) {
                $u['catatan'] = 'Unit non-operasional — tidak menanggung gaji HO';
            } elseif ($u['jenis'] === 'pribadi' && ! isset($globalCountedFlip[$u['idunit']])) {
                $u['catatan'] = 'Unit pribadi tambahan — bagian non-SPV digabung ke unit pribadi pertama';
            } else {
                $u['catatan'] = '-';
            }
        }
        unset($u);

        return [
            'divisor'      => $divisor,
            'pribadi_count'=> $pribadiCount,
            'franchise_count' => $franchiseCount,
            'counted_unit_ids' => $globalCounted,
            'pribadi_unit_id'  => $pribadiUnitId,
            'per_unit'     => array_values($perUnit),
            'employees'    => $hasilEmployees,
            'total_non_spv_perkiraan' => round($totalNonSpvPerkiraan, 2),
            'total_non_spv_dipotong'  => round($totalNonSpvDipotong, 2),
            'total_spv_perkiraan'     => round($totalSpvPerkiraan, 2),
            'total_spv_dipotong'      => round($totalSpvDipotong, 2),
        ];
    }

    /**
     * Hitung pembagi dari sekumpulan unit: pribadi = 1 bagian gabungan,
     * franchise = 1 bagian per unit.
     *
     * @return array{0:int,1:int[]} [divisor, unit id yang menanggung bagian]
     */
    private static function bagianUnit(array $units): array
    {
        $counted = [];
        $pribadiSudah = false;

        foreach ($units as $u) {
            $jenis = (string)($u['jenis'] ?? '');
            if ($jenis === 'pribadi') {
                if (! $pribadiSudah) {
                    $counted[] = (int)$u['idunit'];
                    $pribadiSudah = true;
                }
            } elseif ($jenis === 'franchise') {
                $counted[] = (int)$u['idunit'];
            }
        }

        return [count($counted), $counted];
    }

    /**
     * Ambil detail gaji HO per unit (bagaimana tiap unit mendapat bagian).
     */
    public function getUnitShares(string $bulan, ?string $tahun = null): array
    {
        $dist = $this->calculateDistribution($bulan, $tahun);

        $shares = [];
        foreach ($dist['per_unit'] as $u) {
            $shares[$u['idunit']] = [
                'unit_id'     => $u['idunit'],
                'unit_nama'   => $u['nama'],
                'unit_jenis'  => $u['jenis'],
                'share_pct'   => $dist['total_perkiraan'] > 0
                    ? round(100 * $u['total_perkiraan'] / $dist['total_perkiraan'], 2)
                    : 0,
                'share_nominal_perkiraan' => $u['total_perkiraan'],
                'share_nominal_actual'    => $u['total_dipotong'],
                'non_spv_perkiraan'       => $u['non_spv_perkiraan'],
                'spv_perkiraan'           => $u['spv_perkiraan'],
                'note'                    => $u['catatan'] !== '-' ? $u['catatan'] : null,
            ];
        }

        return $shares;
    }

    /**
     * Kunci cache lintas request untuk satu periode.
     *
     * Nama database ikut jadi bagian kunci karena `erp_local` dan `erp_test`
     * (dipakai PHPUnit) berbagi `writable/cache`.
     */
    public static function cacheKey(string $bulan, ?string $tahun = null): string
    {
        [$thn, $bln] = self::parseBulan($bulan, $tahun);

        return 'ho_dist_v2_' . \Config\Database::connect()->database
            . sprintf('_%04d-%02d', $thn, $bln);
    }

    /** Hapus cache periode ini; dipanggil saat payroll/KPI periode itu berubah. */
    public static function invalidateCache(string $bulan, ?string $tahun = null): void
    {
        cache()->delete(self::cacheKey($bulan, $tahun));
    }

    private static function parseBulan(string $bulan, ?string $tahun): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $bulan, $m)) {
            return [(int)$m[1], (int)$m[2]];
        }
        $bln = (int)($bulan ?? date('m'));
        $thn = (int)($tahun ?? date('Y'));
        return [$thn, $bln];
    }
}
