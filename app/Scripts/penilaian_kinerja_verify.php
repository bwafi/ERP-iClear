<?php
/**
 * penilaian_kinerja() functional verification — OLD hitungKPIGaji vs NEW service chain
 *
 * Bandingkan seluruh variabel view penilaian/penilaian_kinerja untuk beberapa employee.
 * Usage: php app/Scripts/penilaian_kinerja_verify.php [bulan] [tahun]
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

$jabatanTarget = [35 => 'Admin', 36 => 'Teknisi', 41 => 'KepalaToko', 43 => 'Pengiklan', 44 => 'Multimedia', 46 => 'PIC'];
$employees = [];
foreach ($jabatanTarget as $jab => $label) {
    $row = $db->table('akun')->select('ID_AKUN, NAMA_AKUN')
        ->where('STATUS_PEGAWAI', 1)->where('ID_JABATAN', $jab)
        ->get()->getFirstRow();
    if ($row) { $employees[$jab] = ['id' => (int)$row->ID_AKUN, 'nama' => $row->NAMA_AKUN, 'label' => $label]; }
}

$pass=0; $fail=0;
echo "PENILAIAN_KINERJA() FUNCTIONAL VERIFY | {$bulan}/{$tahun} | context=penilaian_kinerja\n";
echo "============================================================\n";

// compare helper for arrays
function arrDiff($a, $b, $tol=0.01) {
    if (count($a) !== count($b)) return true;
    foreach ($a as $i => $rowA) {
        $rowB = $b[$i] ?? null;
        if (!$rowB) return true;
        foreach (['nama','bobot','nilai'] as $k) {
            if (abs((float)$rowA[$k] - (float)$rowB[$k]) > $tol) return true;
        }
    }
    return false;
}

foreach ($employees as $jab => $emp) {
    $idakun = $emp['id'];

    // OLD
    $old = $ref->invoke($ctrl, $idakun, $bulan, $tahun, 'penilaian_kinerja');
    // NEW chain (pola persis controller)
    $legacy = $legacyService->calculate($idakun, $bulan, $tahun, 'penilaian_kinerja');
    $periodDate = "$tahun-$bulan-15";
    $result = $salaryService->calculateSalary(
        $idakun, $legacy['jabatan'], $legacy['unit'], 'penilaian_kinerja',
        ['TUNJANGAN_KINERJA'=>$legacy['skor_total'], 'TUNJANGAN_ABSEN'=>$legacy['skor_total2']],
        $legacy['akun']->tunjangan_penempatan, $legacy['insentif'], 0.0, 0.0, $periodDate
    );
    $comp = [];
    foreach ($result['components'] as $c) { $comp[$c['component_code']] = (float)$c['amount']; }

    $checks = [
        'skor_total'        => [(float)$old['skor_total'], (float)$legacy['skor_total']],
        'skor_total2'       => [(float)$old['skor_total2'], (float)$legacy['skor_total2']],
        'gaji_pokok'        => [(float)$old['gaji_pokok'], $comp['GAJI_POKOK'] ?? 0],
        'tunjangan_kinerja' => [(float)$old['tunjangan_kinerja'], $comp['TUNJANGAN_KINERJA'] ?? 0],
        'tunjangan_absen'   => [(float)$old['tunjangan_absen'], $comp['TUNJANGAN_ABSEN'] ?? 0],
        'tunjangan_penempatan' => [(float)$old['akun']->tunjangan_penempatan, (float)$result['placement_allowance']],
        'insentif'          => [(float)$old['insentif'], (float)$result['incentive']],
        'gaji'              => [(float)$old['gaji'], (float)$result['total_gaji']],
    ];

    $omsetOK = true;
    foreach (range(1,4) as $u) {
        if (abs((float)$old['aktual_omset_unit'][$u] - (float)$legacy['aktual_omset_unit'][$u]) > 100) $omsetOK = false;
    }
    $kpiOK = !arrDiff($old['detail_kpi'], $legacy['detail_kpi']);
    $absenOK = !arrDiff($old['detail_absen'], $legacy['detail_absen']);

    echo "{$emp['label']} - {$emp['nama']} (id={$idakun}, jab={$jab})\n";
    $itemFail = false;
    foreach ($checks as $label => [$ov, $nv]) {
        $diff = abs($ov - $nv);
        $ok = $diff < 100;
        if (!$ok) $itemFail = true;
        printf("  %-22s OLD=%12.2f NEW=%12.2f diff=%9.2f %s\n", $label, $ov, $nv, $diff, $ok ? 'PASS':'FAIL');
    }
    printf("  aktual_omset_unit        %s\n", $omsetOK ? 'MATCH PASS':'MISMATCH FAIL');
    printf("  detail_kpi               %s\n", $kpiOK ? 'MATCH PASS':'MISMATCH FAIL');
    printf("  detail_absen             %s\n", $absenOK ? 'MATCH PASS':'MISMATCH FAIL');
    if (!$omsetOK||!$kpiOK||!$absenOK) $itemFail=true;
    $itemFail ? $fail++ : $pass++;
    echo "  ---\n";
}

echo "============================================================\n";
echo "RESULT: PASS=$pass FAIL=$fail\n";
echo ($fail===0 ? "STATUS: ALL_IDENTICAL" : "STATUS: DISCREPANCY_FOUND")."\n";
exit($fail===0?0:1);