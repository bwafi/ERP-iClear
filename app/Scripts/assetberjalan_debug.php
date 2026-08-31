<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
foreach (['APPPATH'=>'appDirectory','ROOTPATH'=>'','SYSTEMPATH'=>'systemDirectory','WRITEPATH'=>'writableDirectory','TESTPATH'=>'testsDirectory'] as $c=>$p) {
    if (!defined($c)) { $v = $c==='ROOTPATH' ? realpath(APPPATH.'../') : realpath(rtrim($paths->$p,'\\/ ')); define($c, $v.DIRECTORY_SEPARATOR); }
}
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$unit = (int)($argv[1] ?? 1);
$bulan = $argv[2] ?? date('m');
$tahun = $argv[3] ?? date('Y');

// Service baru
$svc = new \App\Services\Payroll\UnitSalaryCalculationService();
$new = $svc->calculateForUnit(1, $unit, $bulan, $tahun);

// "OLD reference" — literal loop dari assetberjalan() via reflection panggil controller
// Tidak bisa per-employee, jadi kita bandingkan agregat dari HTML OLD vs NEW via kedua run.
echo "Unit=$unit bulan=$bulan tahun=$tahun\n";
echo "NEW: omset={$new['omset_bulan']} pengeluaran={$new['pengeluaran']} totalGaji={$new['totalGajiUnit']}\n";
echo "employees: ".count($new['employeeData'])."\n";
foreach ($new['employeeData'] as $id=>$e) {
    printf("  id=%d %-22s j=%d skor=%8.2f absen=%8.2f tk=%10.2f ins=%10.2f gaji=%10.2f\n",
        $id,$e['nama'],$e['jabatan'],$e['skor_total'],$e['skor_total2'],$e['tunjangan_kinerja'],$e['insentif'],$e['gaji']);
    foreach ($e['detail_absen'] as $ab) {
        printf("      absen[%s]=%.4f (bobot %d)\n", $ab['nama'], $ab['nilai'], $ab['bobot']);
    }
}
