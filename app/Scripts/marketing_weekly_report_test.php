<?php
/**
 * Uji rentang minggu, deadline, dan skor laporan mingguan.
 *
 * Usage: php74 app/Scripts/marketing_weekly_report_test.php
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

use App\Services\Marketing\WeeklyReport;

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

echo "Uji laporan mingguan\n";
echo "------------------------------------------------------------\n";

ok('minggu 1 = 1–7', WeeklyReport::range(11, 2026, 1) === ['mulai' => '2026-11-01', 'selesai' => '2026-11-07'], json_encode(WeeklyReport::range(11, 2026, 1)));
ok('minggu 4 = 22–30', WeeklyReport::range(11, 2026, 4) === ['mulai' => '2026-11-22', 'selesai' => '2026-11-30'], json_encode(WeeklyReport::range(11, 2026, 4)));
ok('Feb 2026 minggu 4 = 22–28', WeeklyReport::range(2, 2026, 4)['selesai'] === '2026-02-28', json_encode(WeeklyReport::range(2, 2026, 4)));
ok('deadline minggu 1 = tgl 9', WeeklyReport::deadline(11, 2026, 1) === '2026-11-09', WeeklyReport::deadline(11, 2026, 1));
ok('deadline minggu 4 = tgl 2 bulan berikut', WeeklyReport::deadline(11, 2026, 4) === '2026-12-02', WeeklyReport::deadline(11, 2026, 4));

ok('minggu 1 belum berakhir tgl 7', WeeklyReport::sudahBerakhir(11, 2026, 1, '2026-11-07') === false);
ok('minggu 1 berakhir tgl 8', WeeklyReport::sudahBerakhir(11, 2026, 1, '2026-11-08') === true);

ok('masa tenggang: tgl 8 belum dihitung', WeeklyReport::score(11, 2026, [], '2026-11-08') === null, var_export(WeeklyReport::score(11, 2026, [], '2026-11-08'), true));
ok('telat: skor 0 dari 1', WeeklyReport::score(11, 2026, [], '2026-11-10') == 0.0, var_export(WeeklyReport::score(11, 2026, [], '2026-11-10'), true));
ok('tepat: skor 100', WeeklyReport::score(11, 2026, [1 => '2026-11-08 09:00:00'], '2026-11-10') == 100.0, var_export(WeeklyReport::score(11, 2026, [1 => '2026-11-08 09:00:00'], '2026-11-10'), true));
ok('telat kirim tetap 0', WeeklyReport::score(11, 2026, [1 => '2026-11-10 09:00:00'], '2026-11-10') == 0.0, var_export(WeeklyReport::score(11, 2026, [1 => '2026-11-10 09:00:00'], '2026-11-10'), true));
ok('2 dari 4 = 50', WeeklyReport::score(11, 2026, [1 => '2026-11-08 09:00:00', 2 => '2026-11-16 09:00:00'], '2026-12-03') == 50.0, var_export(WeeklyReport::score(11, 2026, [1 => '2026-11-08 09:00:00', 2 => '2026-11-16 09:00:00'], '2026-12-03'), true));
ok('bulan mendatang null', WeeklyReport::score(12, 2026, [], '2026-11-10') === null, var_export(WeeklyReport::score(12, 2026, [], '2026-11-10'), true));

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
