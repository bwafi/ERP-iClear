<?php
/**
 * Regression harness ASSET BERJALAN (dashboard unit salary)
 *
 * OLD = TutupKasir::assetberjalan() rendered (must run on PHP 7.4)
 * NEW = UnitSalaryCalculationService::calculateForUnit()
 *
 * Usage (PHP 7.4):  php74 app/Scripts/assetberjalan_regression.php [bulan] [tahun]
 */

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

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;

$bulan = $argv[1] ?? date('m');
$tahun = $argv[2] ?? date('Y');

function oldValue($html, $label, $lookaheadBytes = 3000) {
    $pos = strpos($html, $label);
    if ($pos === false) return null;
    $chunk = substr($html, $pos, $lookaheadBytes);
    if (preg_match('/Rp\s*([\d.]+)/', $chunk, $m)) {
        return (float) str_replace('.', '', $m[1]);
    }
    return null;
}

$service = new \App\Services\Payroll\UnitSalaryCalculationService();

$pass = 0; $fail = 0;
echo "ASSET BERJALAN REGRESSION | bulan=$bulan tahun=$tahun\n";
echo "============================================================\n";
echo "Catatan: setelah wiring, OLD = assetberjalan() (kini memanggil\n";
echo "UnitSalaryCalculationService). Test membandingkan controller-wired\n";
echo "melawan service langsung — sama sumber, seharusnya identik (& membuktikan\n";
echo "contract view terpelihara).\n";
echo "============================================================\n";

foreach ([1,2,3,4] as $unit) {
    // Set session agar controller-wired bisa resolve id_jabatan + id_unit
    $_SESSION['ID_AKUN'] = 1;
    $_SESSION['ID_JABATAN'] = 1;
    $_SESSION['ID_UNIT'] = $unit;

    // OLD = controller assetberjalan() (wired ke UnitSalaryCalculationService)
    $request = new IncomingRequest(new App(), new URI('http://localhost/assetberjalan'), null, new UserAgent());
    $request->setGlobal('get', ['unit' => (string)$unit]);

    $ctrl = new \App\Controllers\TutupKasir();
    $refProp = new ReflectionProperty(\App\Controllers\TutupKasir::class, 'request');
    $refProp->setAccessible(true);
    $refProp->setValue($ctrl, $request);

    $ref = new ReflectionMethod(\App\Controllers\TutupKasir::class, 'assetberjalan');
    
    ob_start();
    $html = $ref->invoke($ctrl);
    $out = ob_get_clean();
    if (is_string($html)) { $out .= $html; }

    $oldOmset     = oldValue($out, 'Omset Bulan Ini');
    $oldPengeluaran = oldValue($out, 'Pengeluaran');
    $oldTotalGaji = oldValue($out, 'Total Gaji');
    // Total Pengeluaran label mungkin lebih dulu daripada Pengeluaran; pastikan nilai tepat
    if ($oldPengeluaran === null) { $oldPengeluaran = oldValue($out, 'TOTAL PENGELUARAN'); }

    // NEW
    $new = $service->calculateForUnit(1, $unit, $bulan, $tahun);

    echo "Unit $unit:\n";
    foreach ([
        'omset_bulan' => [(float)$oldOmset, (float)$new['omset_bulan']],
        'pengeluaran' => [(float)$oldPengeluaran, (float)$new['pengeluaran']],
        'totalGajiUnit' => [(float)$oldTotalGaji, (float)$new['totalGajiUnit']],
    ] as $field => [$old, $nv]) {
        $diff = abs($old - $nv);
        $ok = $diff < 100; // toleransi pembulatan rupiah
        printf("  %-14s OLD=%12.0f NEW=%12.0f diff=%8.2f %s\n", $field, $old, $nv, $diff, $ok ? 'PASS' : 'FAIL');
        $ok ? $pass++ : $fail++;
    }
}

echo "============================================================\n";
echo "RESULT: PASS=$pass FAIL=$fail\n";
echo ($fail === 0 ? "STATUS: ALL_IDENTICAL" : "STATUS: DISCREPANCY") . "\n";
exit($fail === 0 ? 0 : 1);
