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

$db = \Config\Database::connect();
$id = 55;
$bulan = '07';
$tahun = '2026';
$date = "$tahun-$bulan-15";

$ctrl = new \App\Controllers\PenilaianKPI();


$legacyService = new \App\Services\Kpi\LegacyKpiCalculationService();
$salaryService = new \App\Services\Payroll\SalaryCalculationService();

$old = $ref->invoke($ctrl, $id, $bulan, $tahun, 'gaji');
$legacy = $legacyService->calculate((int)$id, '07', '2026', 'gaji');
$periodDate = '2026-07-15';
$salaryService = new \App\Services\Payroll\SalaryCalculationService();
$salary = $salaryService->calculateSalary(
    $id,
    $legacy['jabatan'],
    $legacy['unit'],
    'gaji',
    ['TUNJANGAN_KINERJA'=>$legacy['skor_total'],'TUNJANGAN_ABSEN'=>$legacy['skor_total2']],
    $legacy['akun']->tunjangan_penempatan,
    $legacy['insentif'],
    0.0, 0.0,
    $periodDate
);
$comp = [];
foreach ($result['components'] as $c) $comp[$c['component_code']] = (float)$c['amount'];

echo "=== EMPLOYEE 55 (fathoni) jab=43 unit=1 ===\n";
echo "OLD skor_total: " . $old['skor_total'] . "\n";
echo "NEW skor_total: " . $legacy['skor_total'] . "\n";
echo "OLD gaji_pokok: " . $old['gaji_pokok'] . "\n";
echo "NEW gaji_pokok: " . ($comp['GAJI_POKOK'] ?? 0) . "\n";
echo "OLD tunj_kinerja: " . $old['tunjangan_kinerja'] . "\n";
echo "NEW tunj_kinerja: " . ($comp['TUNJANGAN_KINERJA'] ?? 0) . "\n";
echo "OLD tunj_absen: " . $old['tunjangan_absen'] . "\n";
echo "NEW tunj_absen: " . ($comp['TUNJANGAN_ABSEN'] ?? 0) . "\n";
echo "OLD insentif: " . $old['insentif'] . "\n";
echo "NEW insentif: " . $salary['incentive'] . "\n";
echo "OLD gaji: " . $old['gaji'] . "\n";
echo "NEW gaji: " . $result['total_gaji'] . "\n";
echo "OLD penempatan: " . $old['akun']->tunjangan_penempatan . "\n";
echo "NEW penempatan: " . $result['placement_allowance'] . "\n";
echo "OLD insentif: " . $old['insentif'] . "\n";
echo "NEW insentif: " . $salary['incentive'] . "\n";
echo "OLD lembur: " . (isset($old['lembur']) ? $old['lembur'] : 0) . "\n";
echo "NEW lembur: " . $result['lembur'] . "\n";
echo "OLD bon: " . (isset($old['bon']) ? $old['bon'] : 0) . "\n";
echo "NEW bon: " . $result['bon'] . "\n";
echo "OLD total_gaji: " . $old['gaji'] . "\n";
echo "NEW total_gaji: " . $result['total_gaji'] . "\n";

// check HPP per unit for 07/2026
$db = \Config\Database::connect();
echo "\n=== HPP per unit 07/2026 ===\n";
foreach([1,2,3,4] as $u) {
    $hpp = $db->table('detail_penjualan')
        ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
        ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
        ->where('MONTH(penjualan.tanggal)', '07')
        ->where('YEAR(penjualan.tanggal)', '2026')
        ->where('penjualan.unit_idunit =', $u)
        ->get()->getRow()->total ?? 0;
    echo "Unit $u HPP: $hpp\n";
}

// check omset_unit
echo "\n=== Omset per unit 07/2026 ===\n";
foreach([1,2,3,4] as $u) {
    $omset = $db->table('detail_penjualan')
        ->select('SUM(detail_penjualan.sub_total - detail_penjualan.hpp_penjualan) AS total')
        ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
        ->where('MONTH(penjualan.tanggal)', '07')
        ->where('YEAR(penjualan.tanggal)', '2026')
        ->where('penjualan.unit_idunit', $u)
        ->get()->getRow()->total ?? 0;
    echo "Unit $u Omset: $omset\n";
}

// cek old skor_total
$oldLegacy = $legacyService->calculate(55, '07', '2026', 'gaji');
echo "\nOLD (controller) skor_total: " . $old['skor_total'] . "\n";
echo "NEW (service) skor_total: " . $legacy['skor_total'] . "\n";

echo "\n=== DETAIL KPI OLD vs NEW ===\n";
echo "OLD detail_kpi:\n";
print_r($old['detail_kpi']);
echo "\nNEW detail_kpi:\n";
print_r($legacy['detail_kpi']);

echo "\nOLD detail_absen:\n";
print_r($old['detail_absen']);
echo "\nNEW detail_absen:\n";
print_r($legacy['detail_absen']);
