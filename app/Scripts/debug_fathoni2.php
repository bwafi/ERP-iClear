<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
if (!defined('APPPATH')) define('APPPATH', realpath(rtrim($paths->appDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH.'../') . DIRECTORY_SEPARATOR);
if (!defined('SYSTEMPATH')) define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('WRITEPATH')) define('WRITEPATH', realpath(rtrim($paths->writableDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('TESTPATH')) define('TESTPATH', realpath(rtrim($paths->testsDirectory,'\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$legacyService = new \App\Services\Kpi\LegacyKpiCalculationService();
$salaryService = new \App\Services\Payroll\SalaryCalculationService();

// OLD via controller
$ctrl = new \App\Controllers\PenilaianKPI();


$old = $ref->invoke($ctrl, 55, '07', '2026', 'gaji');

// NEW via service
$legacy = $legacyService->calculate(55, '07', '2026', 'gaji');
$periodDate = '2026-07-15';
$result = $salaryService->calculateSalary(
    55, $legacy['jabatan'], $legacy['unit'], 'gaji',
    ['TUNJANGAN_KINERJA'=>$legacy['skor_total'],'TUNJANGAN_ABSEN'=>$legacy['skor_total2']],
    $legacy['akun']->tunjangan_penempatan, $legacy['insentif'],
    0.0, 0.0, $periodDate
);
$comp = [];
foreach ($result['components'] as $c) $comp[$c['component_code']] = (float)$c['amount'];

$checks = [
    'skor_total'        => [(float)$old['skor_total'], (float)$legacy['skor_total']],
    'gaji_pokok'        => [(float)$old['gaji_pokok'], $comp['GAJI_POKOK']],
    'tunjangan_kinerja' => [(float)$old['tunjangan_kinerja'], $comp['TUNJANGAN_KINERJA']],
    'tunjangan_absen'   => [(float)$old['tunjangan_absen'], $comp['TUNJANGAN_ABSEN']],
    'tunjangan_penempatan' => [(float)$old['akun']->tunjangan_penempatan, (float)$result['placement_allowance']],
    'insentif'          => [(float)$old['insentif'], (float)$result['incentive']],
    'gaji'              => [(float)$old['gaji'], (float)$result['total_gaji']],
];

foreach ($checks as $label => [$ov, $nv]) {
    $diff = abs($ov - $nv);
    printf("  %-22s OLD=%12.2f NEW=%12.2f diff=%9.2f %s\n", $label, $ov, $nv, $diff, $diff < 100 ? 'PASS' : 'FAIL');
}
echo "active members KT unit1: ";
require_once APPPATH . 'Models/ModelIncentiveMember.php';
$mm = new \App\Models\ModelIncentiveMember();
$kg = $db->table('incentive_groups')->where('code','KEPALA_TOKO')->get()->getFirstRow();
$cnt = $mm->countActiveMembers((int)$kg->id, 1, '2026-07-15');
echo $cnt."\n";
