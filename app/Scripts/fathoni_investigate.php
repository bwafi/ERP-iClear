<?php
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

$ctrl = new \App\Controllers\PenilaianKPI();


$svc = new \App\Services\Kpi\LegacyKpiCalculationService();
$sal = new \App\Services\Payroll\SalaryCalculationService();

$old = $ref->invoke($ctrl, 55, '07', '2026', 'gaji');
$legacy = $svc->calculate(55, '07', '2026', 'gaji');

echo "=== fathoni (j43 u1) periode 07/2026 context gaji ===\n";
echo "detail_kpi:\n";
foreach ($old['detail_kpi'] as $k) {
    printf("  %-12s bobot=%d nilai=%.4f\n", $k['nama'], $k['bobot'], $k['nilai']);
}
echo "skor_total OLD = {$old['skor_total']}\n";
echo "skor_total NEW = {$legacy['skor_total']}\n";
echo "tunj_kinerja OLD = {$old['tunjangan_kinerja']}\n";

// Salary service: percent_of_kpi cap 100
$result = $sal->calculateSalary(55, 43, 1, 'gaji',
    ['TUNJANGAN_KINERJA'=>$legacy['skor_total'],'TUNJANGAN_ABSEN'=>$legacy['skor_total2']],
    $legacy['akun']->tunjangan_penempatan, $legacy['insentif'], 0.0, 0.0, '2026-07-15');
$comp=[];
foreach($result['components'] as $c) $comp[$c['component_code']]=$c['amount'];
echo "tunj_kinerja NEW = " . ($comp['TUNJANGAN_KINERJA'] ?? 0) . "\n";

// Penjelasan: skor_total 135 untuk Pengiklan (Budgeting 15% + ROAS 15% + Omset 70%)
// Budgeting & ROAS bisa > 100 (karena nilai raw tinggi × 100/20)
echo "\nKaplog: skor>100 => SalaryService cap 100 (min) sedangkan legacy TIDAK cap.\n";
echo "Ini PRE-EXISTING di SalaryCalculationService, TIDAK terkait HPP.\n";
