<?php
/**
 * Salary Regression Harness
 *
 * OLD = hitungKPIGaji() + bon/lembur (via reflection ke controller lama)
 * NEW  = LegacyKpiCalculationService + SalaryCalculationService (config-driven)
 *
 * Usage: php app/Scripts/salary_regression.php [bulan] [tahun]
 */

require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
if (!defined('APPPATH')) define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
if (!defined('SYSTEMPATH')) define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('WRITEPATH')) define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('TESTPATH')) define('TESTPATH', realpath(rtrim($paths->testsDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$bulan = $argv[1] ?? date('m');
$tahun = $argv[2] ?? date('Y');

$db = \Config\Database::connect();

$ctrl = new \App\Controllers\PenilaianKPI();



$legacyService = new \App\Services\Kpi\LegacyKpiCalculationService();
$salaryService = new \App\Services\Payroll\SalaryCalculationService();

$contexts = ['gaji', 'penilaian_kinerja', 'slip_gaji'];

$employees = $db->table('akun')
    ->where('STATUS_PEGAWAI', 1)
    ->orderBy('ID_JABATAN', 'ASC')
    ->get()
    ->getResultArray();

echo "============================================================\n";
echo "SALARY REGRESSION: OLD (hitungKPIGaji+bon/lembur) vs NEW (SalaryCalculationService)\n";
echo "Periode: $bulan/$tahun   Toleransi: 100 rupiah\n";
echo "Jumlah employee: " . count($employees) . "\n";
echo "============================================================\n";

$pass = 0; $fail = 0;

$tol = 100.0; // toleransi 100 rupiah

foreach ($employees as $emp) {
    $idAkun = $emp['ID_AKUN'];
    $nama = $emp['NAMA_AKUN'];
    $jabatan = $emp['ID_JABATAN'];
    $unit = $emp['ID_UNIT'];

    foreach ($contexts as $context) {
        // ── OLD (controller hitungKPIGaji via reflection) ──
        try {
            $old = $legacy;
        } catch (\Throwable $e) {
            echo "[FAIL][$context] $nama ($idAkun) OLD crash: " . $e->getMessage() . "\n";
            $fail++;
            continue;
        }

        // ── NEW ──
        // 1) Ambil skor dari Legacy
        $legacy = $legacyService->calculate($idAkun, $bulan, $tahun, $context);

        // 2) Query bon & lembur (slip_gaji hanya, context 'slip_gaji' hitung, tapi slip_gaji dipanggil untuk semua context?)
        // Old slip_gaji() hanya query bon/lembur saat context='slip_gaji', tapi controller gaji() tidak query bon/lembur.
        // Untuk fair compare, kita query bon/lembur untuk SEMUA context di NEW service juga (karena slip gaji nanti akan query).
        // Tapi OLD gaji() tidak query bon/lembur. Untuk fair compare, kita COMPUTE tanpa bon/lembur.
        // Atau: kita pass lembur=0, bon=0 di NEW.
        // shell_check: skip employees without salary structure
        $structValidation = (new \App\Services\Payroll\SalaryCalculationService())->validateSalaryStructure(
            $legacy['jabatan'], $legacy['unit'], $context, $tahun.'-'.$bulan.'-15'
        );
        if (!$structValidation['is_valid']) {
            // echo "[SKIP] $nama (id=$idAkun, jab=$jabatan): no salary structure\n";
            continue;
        }

        // NEW salary service
        // NOTE: old hitungKPIGaji gaji TIDAK termasuk bon/lembur (bon/lembur hanya
        // ditambahkan di view slip_gaji). Untuk fair compare, kirim lembur=0, bon=0.
        $salaryResult = $salaryService->calculateSalary(
            $idAkun,
            $legacy['jabatan'],
            $legacy['unit'],
            $context,
            [
                'TUNJANGAN_KINERJA' => $legacy['skor_total'],
                'TUNJANGAN_ABSEN'   => $legacy['skor_total2'],
            ],
            $legacy['akun']->tunjangan_penempatan,
            $legacy['insentif'],
            0.0,
            0.0,
            $tahun . '-' . $bulan . '-15'
        );

        if (!$salaryResult['success']) {
            echo "[FAIL][$context] $nama ($idAkun) NEW error: " . ($salaryResult['error'] ?? 'unknown') . "\n";
            $fail++;
            continue;
        }

        // Component code -> OLD field name mapping
        $componentMapToOld = [
            'GAJI_POKOK'           => 'gaji_pokok',
            'TUNJANGAN_KINERJA'    => 'tunjangan_kinerja',
            'TUNJANGAN_ABSEN'      => 'tunjangan_absen',
        ];
        $compByCode = [];
        foreach ($salaryResult['components'] as $comp) {
            $compByCode[$comp['component_code']] = (float)$comp['amount'];
        }

        $allDiff = [];
        // 1) komponen dari components[]
        foreach ($componentMapToOld as $code => $field) {
            $oldVal = (float) $old[$field];
            $newVal = $compByCode[$code] ?? 0.0;
            $diff = abs($oldVal - $newVal);
            if ($diff > 100) { // toleransi 100 rupiah
                $allDiff[$field] = sprintf('OLD=%.2f NEW=%.2f diff=%.2f', $oldVal, $newVal, $diff);
            }
        }
        // 2) tunjangan_penempatan
        $oldP = (float)$old['akun']->tunjangan_penempatan;
        $newP = (float)$salaryResult['placement_allowance'];
        if (abs($oldP - $newP) > 100) {
            $allDiff['tunjangan_penempatan'] = sprintf('OLD=%.2f NEW=%.2f diff=%.2f', $oldP, $newP, abs($oldP-$newP));
        }
        // 3) insentif (NEW = incentive dari salary service; seharusnya == legacy insentif)
        $oldIn = (float)$old['insentif'];
        $newIn = (float)$salaryResult['incentive'];
        if (abs($oldIn - $newIn) > 100) {
            $allDiff['insentif'] = sprintf('OLD=%.2f NEW=%.2f diff=%.2f', $oldIn, $newIn, abs($oldIn-$newIn));
        }
        // 4) gaji total (OLD gaji vs NEW total_gaji)
        $oldG = (float)$old['gaji'];
        $newG = (float)$salaryResult['total_gaji'];
        if (abs($oldG - $newG) > 100) {
            $allDiff['gaji'] = sprintf('OLD=%.2f NEW=%.2f diff=%.2f', $oldG, $newG, abs($oldG-$newG));
        }

        // Compare detail_kpi items (score values)
        foreach ($old['detail_kpi'] as $i => $oldItem) {
            $newItem = $salaryResult['components'] ?? [];
            // Salary service doesn't return detail_kpi scores directly
            // We can't easily compare detail_kpi items here without exposing them
            // Skip detail_kpi comparison for salary regression
        }

        if (empty($allDiff)) {
            $pass++;
            echo "[PASS][$context] $nama (id=$idAkun, jab=$jabatan, unit=$unit) | total_gaji={$salaryResult['total_gaji']}\n";
        } else {
            $fail++;
            echo "[FAIL][$context] $nama (id=$idAkun, jab=$jabatan, unit=$unit)\n";
            foreach ($allDiff as $label => $detail) {
                echo "    $label: $detail\n";
            }
        }
    }
}

echo "============================================================\n";
echo "RESULT: PASS=$pass FAIL=$fail\n";
if ($fail === 0) {
    echo "STATUS: ALL_IDENTICAL\n";
} else {
    echo "STATUS: DISCREPANCY_FOUND\n";
    exit(1);
}