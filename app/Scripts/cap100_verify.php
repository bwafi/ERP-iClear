<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
foreach (['APPPATH'=>'appDirectory','SYSTEMPATH'=>'systemDirectory','WRITEPATH'=>'writableDirectory','TESTPATH'=>'testsDirectory'] as $c=>$p) {
    if (!defined($c)) { $v = $c==='ROOTPATH' ? realpath(APPPATH.'../') : realpath(rtrim($paths->$p,'\\/ ')); define($c,$v.DIRECTORY_SEPARATOR); }
}
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH.'../') . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$svc = new \App\Services\Kpi\LegacyKpiCalculationService();
$ctrl = new \App\Controllers\PenilaianKPI();



$pass=0;$fail=0;
function chk($l,$e,$a,$tol=0.1){global $pass,$fail; if(abs($e-$a)<=$tol){echo "[PASS] $l = $a\n";$pass++;}else{echo "[FAIL] $l = $a (expected $e)\n";$fail++;}}

echo "=== fathoni (id=55, jab 43) 07/2026 — legacy skor 135 ===\n";
$legacy = $svc->calculate(55, '07', '2026', 'gaji');
$old = $ref->invoke($ctrl, 55, '07', '2026', 'gaji');
chk('skor_total (cap 100)', 100, $legacy['skor_total']);
chk('OLD == NEW skor_total', $old['skor_total'], $legacy['skor_total']);
chk('tunjangan_kinerja = 1jt', 1000000, $legacy['tunjangan_kinerja']);
chk('OLD == NEW tunj_kinerja', $old['tunjangan_kinerja'], $legacy['tunjangan_kinerja']);
foreach ($legacy['detail_kpi'] as $k) { if ($k['nama']==='Budgeting') chk('detail_kpi Budgeting tetap 500', 500, $k['nilai']); }

echo "\n=== fathoni 06/2026 — legacy skor 220 ===\n";
$legacy = $svc->calculate(55, '06', '2026', 'gaji');
$old = $ref->invoke($ctrl, 55, '06', '2026', 'gaji');
chk('skor_total (cap 100)', 100, $legacy['skor_total']);
chk('OLD == NEW skor_total', $old['skor_total'], $legacy['skor_total']);
chk('tunjangan_kinerja = 1jt', 1000000, $legacy['tunjangan_kinerja']);

echo "\n=== Radit A (id=48, jab 36) 06/2026 — absen 100.62 ===\n";
$legacy = $svc->calculate(48, '06', '2026', 'penilaian_kinerja');
$old = $ref->invoke($ctrl, 48, '06', '2026', 'penilaian_kinerja');
chk('skor_total2 (cap 100)', 100, $legacy['skor_total2']);
chk('OLD == NEW skor_total2', $old['skor_total2'], $legacy['skor_total2']);

echo "\nRESULT: PASS=$pass FAIL=$fail\n";
exit($fail===0?0:1);
