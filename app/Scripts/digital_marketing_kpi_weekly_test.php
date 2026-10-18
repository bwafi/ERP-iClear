<?php
/**
 * Uji integrasi KPI REPORTING: periode 2026-11+ memakai laporan mingguan.
 *
 * Usage: php74 app/Scripts/digital_marketing_kpi_weekly_test.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Config/Paths.php';
use Config\Paths;
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
(new \CodeIgniter\Config\DotEnv(ROOTPATH))->load();

use App\Services\Marketing\DigitalMarketingKpiService;

$fail = 0;
$pass = 0;

function ok($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}  " . ($detail !== '' ? "→ {$detail}" : '') . "\n";
    }
}

echo "Uji KPI REPORTING mingguan\n";
echo "------------------------------------------------------------\n";

$svc = new DigitalMarketingKpiService();

$nov = $svc->componentData('REPORTING', 55, 1, 11, 2026, 'default');
$okt = $svc->componentData('REPORTING', 55, 1, 10, 2026, 'default');

ok('Nov 2026 pakai sumber laporan mingguan', ($nov['source'] ?? '') === 'Laporan mingguan tepat waktu', (string) ($nov['source'] ?? '-'));
ok('Okt 2026 tetap rumus lama', ($okt['source'] ?? '') === 'Campaign selesai / total campaign', (string) ($okt['source'] ?? '-'));
ok('Nov 2026 target 100', ($nov['target'] ?? null) === 100);

$w = $svc->reportingWeekly(11, 2026);
ok('reportingWeekly mengembalikan skor', array_key_exists('skor', $w));
ok('reportingWeekly mengembalikan daftar terkirim', array_key_exists('terkirim', $w));

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
