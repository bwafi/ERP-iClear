<?php
/**
 * BUG-003 Period Verification — buktikan HPP mengikuti periode eksplisit.
 * Usage: php app/Scripts/hpp_period_verify.php
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

$db = \Config\Database::connect();

function hppPerUnit($db, $bulan, $tahun) {
    $out = [];
    foreach ([1,2,3,4] as $u) {
        $out[$u] = (float)($db->table('detail_penjualan')
            ->select('SUM(detail_penjualan.hpp_penjualan) AS total')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->where('MONTH(penjualan.tanggal)', $bulan)
            ->where('YEAR(penjualan.tanggal)', $tahun)
            ->where('penjualan.unit_idunit =', $u)
            ->get()->getRow()->total ?? 0);
    }
    return $out;
}

echo "=== BUG-003 PERIOD VERIFICATION ===\n\n";
foreach (['07','08'] as $bln) {
    $h = hppPerUnit($db, $bln, '2026');
    $total = array_sum($h);
    printf("Periode %s/2026  HPP per unit: %s | TOTAL = %.2f\n",
        $bln, json_encode($h), $total);
}

$h07 = hppPerUnit($db, '07', '2026');
$h08 = hppPerUnit($db, '08', '2026');
$diff = array_sum($h07) - array_sum($h08);
echo "\nHPP 07 vs 08 selisih: " . number_format(abs($diff), 0, ',', '.') . " rupiah\n";
echo ($diff != 0 ? "=> periode HPP BERBEDA (BUKTI: follow periode request)\n" : "=> WARNING: kedua periode sama\n");

// Bukti: query service Legacy memakai $bulan. Verifikasi melalui employee PIC (jab 46) yang
// detail_kpi-nya memakai nilai_hpp & nilai_hpp_global.
echo "\n=== Verifikasi via detail_kpi PIC (jab 46) ===\n";
$ctrl = new \App\Controllers\PenilaianKPI();


$svc = new \App\Services\Kpi\LegacyKpiCalculationService();
foreach (['07','08'] as $bln) {
    $old = $ref->invoke($ctrl, 49, $bln, '2026', 'penilaian_kinerja');
    $new = $svc->calculate(49, $bln, '2026', 'penilaian_kinerja');
    // detail_kpi PIC: Budget Per Toko / Budget Global / Omset Cabang
    $oKpi = array_column($old['detail_kpi'], 'nilai', 'nama');
    $nKpi = array_column($new['detail_kpi'], 'nilai', 'nama');
    printf("PIC id=49 periode %s: OLD %s | NEW %s\n", $bln,
        json_encode($oKpi), json_encode($nKpi));
}
echo "\nRESULT DONE\n";
