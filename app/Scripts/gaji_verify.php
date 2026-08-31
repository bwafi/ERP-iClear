<?php
/**
 * gaji() functional verification — OLD hitungKPIGaji vs NEW service chain
 *
 * Bandingkan seluruh variabel view penilaian/gaji untuk beberapa employee:
 * Admin, Teknisi, KT, PIC, Pengiklan, Multimedia.
 *
 * Usage: php app/Scripts/gaji_verify.php [bulan] [tahun]
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

$bulan = $argv[1] ?? '08';
$tahun = $argv[2] ?? '2026';
$db = \Config\Database::connect();

$ctrl = new \App\Controllers\PenilaianKPI();


$legacyService = new \App\Services\Kpi\LegacyKpiCalculationService();
$salaryService = new \App\Services\Payroll\SalaryCalculationService();

// pilih minimal 1 employee per jabatan
$jabatanTarget = [35 => 'Admin', 36 => 'Teknisi', 41 => 'KepalaToko', 42 => 'CS', 43 => 'Pengiklan', 44 => 'Multimedia', 46 => 'PIC'];
$employees = [];
foreach ($jabatanTarget as $jab => $label) {
    $row = $db->table('akun')->select('ID_AKUN, NAMA_AKUN')
        ->where('STATUS_PEGAWAI', 1)->where('ID_JABATAN', $jab)
        ->get()->getFirstRow();
    if ($row) { $employees[$jab] = ['id' => (int)$row->ID_AKUN, 'nama' => $row->NAMA_AKUN, 'label' => $label]; }
}

$pass = 0; $fail = 0;
echo "GAJI() FUNCTIONAL VERIFY | {$bulan}/{$tahun} | context=gaji\n";
echo "============================================================\n";

foreach ($employees as $jab => $emp) {
    $idakun = $emp['id'];

    // OLD
    $old = $ref->invoke($ctrl, $idakun, $bulan, $tahun, 'gaji');

    // NEW chain (persis seperti controller gaji() baru)
    $legacy = $legacyService->calculate($idakun, $bulan, $tahun, 'gaji');
    $periodDate = "$tahun-$bulan-15";
    $result = $salaryService->calculateSalary(
        $idakun,
        $legacy['jabatan'],
        $legacy['unit'],
        'gaji',
        ['TUNJANGAN_KINERJA' => $legacy['skor_total'], 'TUNJANGAN_ABSEN' => $legacy['skor_total2']],
        $legacy['akun']->tunjangan_penempatan,
        $legacy['insentif'],
        0.0, 0.0,
        $periodDate
    );
    $comp = [];
    foreach ($result['components'] as $c) { $comp[$c['component_code']] = (float)$c['amount']; }

    $checks = [
        'skor_total'        => [(float)$old['skor_total'], (float)$legacy['skor_total']],
        'gaji_pokok'        => [(float)$old['gaji_pokok'], $comp['GAJI_POKOK'] ?? 0],
        'tunjangan_kinerja' => [(float)$old['tunjangan_kinerja'], $comp['TUNJANGAN_KINERJA'] ?? 0],
        'tunjangan_absen'   => [(float)$old['tunjangan_absen'], $comp['TUNJANGAN_ABSEN'] ?? 0],
        'tunjangan_penempatan' => [(float)$old['akun']->tunjangan_penempatan, (float)$result['placement_allowance']],
        'insentif'          => [(float)$old['insentif'], (float)$result['incentive']],
        'gaji'              => [(float)$old['gaji'], (float)$result['total_gaji']],
    ];

    // detail_kpi compare
    $kpiDiff = false;
    foreach ($old['detail_kpi'] as $i => $oi) {
        $ni = $legacy['detail_kpi'][$i] ?? null;
        if (!$ni) { $kpiDiff = true; break; }
        foreach (['nama','bobot','nilai'] as $k) {
            if (abs((float)$oi[$k] - (float)$ni[$k]) > 0.01) { $kpiDiff = true; }
        }
    }

    echo "{$emp['label']} - {$emp['nama']} (id={$idakun}, jab={$jab})\n";
    $itemFail = false;
    foreach ($checks as $label => [$ov, $nv]) {
        $diff = abs($ov - $nv);
        $ok = $diff < 100;
        if (!$ok) $itemFail = true;
        printf("  %-20s OLD=%12.2f NEW=%12.2f diff=%9.2f %s\n", $label, $ov, $nv, $diff, $ok ? 'PASS' : 'FAIL');
    }
    if ($kpiDiff) { echo "  detail_kpi: MISMATCH FAIL\n"; $itemFail = true; }
    else { echo "  detail_kpi:  MATCH PASS\n"; }
    $itemFail ? $fail++ : $pass++;
    echo "  ---\n";
}

echo "============================================================\n";
echo "RESULT: PASS=$pass FAIL=$fail\n";
echo ($fail === 0 ? "STATUS: ALL_IDENTICAL" : "STATUS: DISCREPANCY_FOUND") . "\n";
exit($fail === 0 ? 0 : 1);