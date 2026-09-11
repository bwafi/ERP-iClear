<?php
/**
 * Unit test AttendanceScoreCalculator.
 *
 * Verifikasi semua aturan scoring:
 * - Normal: ≤0 menit (5), 1-3 (4), 4-6 (3), 7-10 (2), 11-14 (1), ≥15 (0)
 * - Izin Telat: 1-5 (5), 6-15 (4), 16-30 (3), 31-40 (1), >40 (0)
 * - PS: pagi + sore dihitung terpisah
 * - Jam schedule per shift
 *
 * Usage: php74 app/Scripts/attendance_score_test.php
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
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

use App\Services\Kpi\AttendanceScoreCalculator as Calc;

$pass = 0;
$fail = 0;
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

echo "============================================================\n";
echo "TEST: AttendanceScoreCalculator\n";
echo "============================================================\n";

echo "\n== SCHEDULED TIME ==\n";
ok('PAGI → 08:45', Calc::getScheduledTime('PAGI', 'FULL') === '08:45');
ok('SIANG → 12:45', Calc::getScheduledTime('SIANG', 'FULL') === '12:45');
ok('PS PAGI → 08:45', Calc::getScheduledTime('PS', 'PAGI') === '08:45');
ok('PS SORE → 17:00', Calc::getScheduledTime('PS', 'SORE') === '17:00');

echo "\n== NORMAL SCORING (Pagi 08:45) ==\n";
$tests = [
    ['actual' => '08:44', 'late' => 0, 'score' => 5, 'desc' => '1 menit lebih awal → 5'],
    ['actual' => '08:45', 'late' => 0, 'score' => 5, 'desc' => 'Tepat waktu → 5'],
    ['actual' => '08:46', 'late' => 1, 'score' => 4, 'desc' => 'Telat 1 menit → 4'],
    ['actual' => '08:47', 'late' => 2, 'score' => 4, 'desc' => 'Telat 2 menit → 4'],
    ['actual' => '08:48', 'late' => 3, 'score' => 4, 'desc' => 'Telat 3 menit → 4'],
    ['actual' => '08:49', 'late' => 4, 'score' => 3, 'desc' => 'Telat 4 menit → 3'],
    ['actual' => '08:50', 'late' => 5, 'score' => 3, 'desc' => 'Telat 5 menit → 3'],
    ['actual' => '08:51', 'late' => 6, 'score' => 3, 'desc' => 'Telat 6 menit → 3'],
    ['actual' => '08:52', 'late' => 7, 'score' => 2, 'desc' => 'Telat 7 menit → 2'],
    ['actual' => '08:54', 'late' => 9, 'score' => 2, 'desc' => 'Telat 9 menit → 2'],
    ['actual' => '08:55', 'late' => 10, 'score' => 2, 'desc' => 'Telat 10 menit → 2'],
    ['actual' => '08:56', 'late' => 11, 'score' => 1, 'desc' => 'Telat 11 menit → 1'],
    ['actual' => '08:59', 'late' => 14, 'score' => 1, 'desc' => 'Telat 14 menit → 1'],
    ['actual' => '09:00', 'late' => 15, 'score' => 0, 'desc' => 'Telat 15 menit → 0'],
    ['actual' => '09:15', 'late' => 30, 'score' => 0, 'desc' => 'Telat 30 menit → 0'],
];

foreach ($tests as $t) {
    $r = Calc::calculate('PAGI', 'FULL', 'NORMAL', '08:45', $t['actual']);
    ok(
        $t['desc'],
        $r['late_minutes'] === $t['late'] && $r['auto_score'] === (float)$t['score'],
        "late={$r['late_minutes']} score={$r['auto_score']}"
    );
}

echo "\n== IZIN TELAT SCORING ==\n";
$izinTests = [
    ['izin' => 1, 'score' => 5, 'desc' => 'Izin 1 menit → 5'],
    ['izin' => 5, 'score' => 5, 'desc' => 'Izin 5 menit → 5'],
    ['izin' => 6, 'score' => 4, 'desc' => 'Izin 6 menit → 4'],
    ['izin' => 10, 'score' => 4, 'desc' => 'Izin 10 menit → 4'],
    ['izin' => 15, 'score' => 4, 'desc' => 'Izin 15 menit → 4'],
    ['izin' => 16, 'score' => 3, 'desc' => 'Izin 16 menit → 3'],
    ['izin' => 25, 'score' => 3, 'desc' => 'Izin 25 menit → 3'],
    ['izin' => 30, 'score' => 3, 'desc' => 'Izin 30 menit → 3'],
    ['izin' => 31, 'score' => 1, 'desc' => 'Izin 31 menit → 1'],
    ['izin' => 40, 'score' => 1, 'desc' => 'Izin 40 menit → 1'],
    ['izin' => 41, 'score' => 0, 'desc' => 'Izin 41 menit → 0'],
    ['izin' => 60, 'score' => 0, 'desc' => 'Izin 60 menit → 0'],
];

foreach ($izinTests as $t) {
    $actualTime = date('H:i', strtotime('08:45') + $t['izin'] * 60);
    $r = Calc::calculate('PAGI', 'FULL', 'IZIN_TELAT', '08:45', $actualTime);
    ok(
        $t['desc'],
        $r['late_minutes'] === $t['izin'] && $r['auto_score'] === (float)$t['score'],
        "izin={$r['late_minutes']} score={$r['auto_score']}"
    );
}

echo "\n== PS DUAL SESSION ==\n";
$psPagi = Calc::calculate('PS', 'PAGI', 'NORMAL', '08:45', '08:50');
ok('PS pagi telat 5 menit → score 3', $psPagi['late_minutes'] === 5 && $psPagi['auto_score'] === 3.0, "late={$psPagi['late_minutes']} score={$psPagi['auto_score']}");

$psSore = Calc::calculate('PS', 'SORE', 'NORMAL', '17:00', '17:05');
ok('PS sore telat 5 menit → score 3', $psSore['late_minutes'] === 5 && $psSore['auto_score'] === 3.0, "late={$psSore['late_minutes']} score={$psSore['auto_score']}");

$totalPsLate = $psPagi['late_minutes'] + $psSore['late_minutes'];
ok('PS total keterlambatan = 10 menit (5 pagi + 5 sore)', $totalPsLate === 10, "total={$totalPsLate}");

echo "\n== SIANG SHIFT (12:45) ==\n";
$siang = Calc::calculate('SIANG', 'FULL', 'NORMAL', '12:45', '12:52');
ok('Siang telat 7 menit → score 2', $siang['late_minutes'] === 7 && $siang['auto_score'] === 2.0, "late={$siang['late_minutes']} score={$siang['auto_score']}");

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";

exit($fail > 0 ? 1 : 0);