<?php
/**
 * Integration test AttendanceInputService.
 *
 * Verifikasi:
 * - Save attendance (jam masuk) → hitung auto_score → insert ke kpi_evaluations + detail
 * - PS dual session (pagi + sore)
 * - Update existing
 * - Backward-compatible read (auto vs manual)
 *
 * Usage: php74 app/Scripts/attendance_input_test.php
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

use App\Services\Kpi\AttendanceInputService;

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

function near($a, $b, $eps = 0.1)
{
    return abs($a - $b) < $eps;
}

echo "============================================================\n";
echo "TEST: AttendanceInputService (Integration)\n";
echo "============================================================\n";

$db = \Config\Database::connect();
$svc = new AttendanceInputService();

// Setup test employee & evaluator
$testEmpId = 999;
$testEvaluatorId = 1;
$testDate = '2026-09-15';

// Cleanup existing test data
$db->query("DELETE FROM kpi_attendance_detail WHERE evaluation_id IN (SELECT id FROM kpi_evaluations WHERE employee_id = {$testEmpId})");
$db->query("DELETE FROM kpi_evaluations WHERE employee_id = {$testEmpId} AND evaluation_date = '{$testDate}'");

echo "\n== SAVE ATTENDANCE (PAGI, NORMAL, 08:50 → 5 menit telat) ==\n";
$r1 = $svc->saveAttendance($testEmpId, $testDate, 'PAGI', 'FULL', 'NORMAL', '08:50', $testEvaluatorId);
ok('Evaluation ID created', $r1['evaluation_id'] > 0, "eval_id={$r1['evaluation_id']}");
ok('Auto score = 3 (telat 5 menit)', near($r1['auto_score'], 3.0), "score={$r1['auto_score']}");
ok('Late minutes = 5', $r1['late_minutes'] === 5, "late={$r1['late_minutes']}");

$daily1 = $svc->getDailyScore($testEmpId, $testDate);
ok('getDailyScore → score 3', near($daily1['score'], 3.0), "score={$daily1['score']}");
ok('getDailyScore → is_auto=true', $daily1['is_auto'] === true);
ok('getDailyScore → shift=PAGI', $daily1['shift'] === 'PAGI');

echo "\n== UPDATE ATTENDANCE (08:45 → tepat waktu, score 5) ==\n";
$r2 = $svc->saveAttendance($testEmpId, $testDate, 'PAGI', 'FULL', 'NORMAL', '08:45', $testEvaluatorId);
ok('Evaluation ID unchanged (update)', $r2['evaluation_id'] === $r1['evaluation_id'], "eval_id={$r2['evaluation_id']}");
ok('Auto score updated = 5', near($r2['auto_score'], 5.0), "score={$r2['auto_score']}");
ok('Late minutes = 0', $r2['late_minutes'] === 0);

$daily2 = $svc->getDailyScore($testEmpId, $testDate);
ok('getDailyScore after update → score 5', near($daily2['score'], 5.0), "score={$daily2['score']}");

echo "\n== PS DUAL SESSION ==\n";
$testDatePs = '2026-09-16';
$db->query("DELETE FROM kpi_attendance_detail WHERE evaluation_id IN (SELECT id FROM kpi_evaluations WHERE employee_id = {$testEmpId} AND evaluation_date = '{$testDatePs}')");
$db->query("DELETE FROM kpi_evaluations WHERE employee_id = {$testEmpId} AND evaluation_date = '{$testDatePs}'");

// PS sekaligus: pagi 08:50 (telat 5) + sore 17:05 (telat 5).
// Total telat = 5 + 5 = 10 menit => nilai 2 (aturan NORMAL 7-10 menit).
$rPs = $svc->saveDailyAttendance($testEmpId, $testDatePs, $testEvaluatorId, [
    ['shift' => 'PS', 'session' => 'PAGI', 'attendance_type' => 'NORMAL', 'actual_time' => '08:50'],
    ['shift' => 'PS', 'session' => 'SORE', 'attendance_type' => 'NORMAL', 'actual_time' => '17:05'],
]);
ok('PS evaluation created (satu baris per hari)', $rPs['evaluation_id'] > 0, "evaluation_id={$rPs['evaluation_id']}");
ok('PS daily score = 2 (dari total telat 10 menit)', near($rPs['auto_score'], 2.0), "score={$rPs['auto_score']}");
ok('PS total late = 10 menit', $rPs['late_minutes'] === 10, "late={$rPs['late_minutes']}");
ok('PS memiliki 2 detail sesi', count($rPs['sessions']) === 2, "sessions=" . count($rPs['sessions']));

$dailyPs = $svc->getDailyScore($testEmpId, $testDatePs);
ok('PS getDailyScore → nilai 2', near($dailyPs['score'], 2.0), "score={$dailyPs['score']}");
ok('PS total late_minutes = 10 (5 pagi + 5 sore)', $dailyPs['late_minutes'] === 10, "late={$dailyPs['late_minutes']}");

echo "\n== IZIN TELAT ==\n";
$testDateIzin = '2026-09-17';
$db->query("DELETE FROM kpi_attendance_detail WHERE evaluation_id IN (SELECT id FROM kpi_evaluations WHERE employee_id = {$testEmpId} AND evaluation_date = '{$testDateIzin}')");
$db->query("DELETE FROM kpi_evaluations WHERE employee_id = {$testEmpId} AND evaluation_date = '{$testDateIzin}'");

// Izin telat 10 menit → score 4
$rIzin = $svc->saveAttendance($testEmpId, $testDateIzin, 'PAGI', 'FULL', 'IZIN_TELAT', '08:55', $testEvaluatorId);
ok('Izin telat 10 menit → score 4', near($rIzin['auto_score'], 4.0), "score={$rIzin['auto_score']}");
ok('Izin telat → late_minutes 10', $rIzin['late_minutes'] === 10);

$dailyIzin = $svc->getDailyScore($testEmpId, $testDateIzin);
ok('getDailyScore izin telat → score 4', near($dailyIzin['score'], 4.0), "score={$dailyIzin['score']}");
ok('getDailyScore → attendance_type = IZIN_TELAT', $dailyIzin['attendance_type'] === 'IZIN_TELAT');

echo "\n== BACKWARD-COMPATIBLE: Manual Score (tanpa detail) ==\n";
$testDateManual = '2026-09-18';
$kehadiranComp = $db->query("SELECT id FROM kpi_components WHERE code='KEHADIRAN' LIMIT 1")->getRow();
if ($kehadiranComp) {
    // Insert manual evaluation (nilai 4) tanpa kpi_attendance_detail
    $db->query("INSERT INTO kpi_evaluations (employee_id, kpi_component_id, evaluator_id, evaluation_date, raw_score, max_score, normalized_score, weighted_score, period_year, period_month, created_at) VALUES ({$testEmpId}, {$kehadiranComp->id}, {$testEvaluatorId}, '{$testDateManual}', 4.0, 5.0, 80.0, 0.0, 2026, 9, NOW())");
    
    $dailyManual = $svc->getDailyScore($testEmpId, $testDateManual);
    ok('Manual score (tanpa detail) → score 4', near($dailyManual['score'], 4.0), "score={$dailyManual['score']}");
    ok('Manual score → is_auto=false', $dailyManual['is_auto'] === false);
    ok('Manual score → shift=null', $dailyManual['shift'] === null);
}

echo "\n== CLEANUP ==\n";
$db->query("DELETE FROM kpi_attendance_detail WHERE evaluation_id IN (SELECT id FROM kpi_evaluations WHERE employee_id = {$testEmpId})");
$db->query("DELETE FROM kpi_evaluations WHERE employee_id = {$testEmpId}");
ok('Test data cleaned up', true);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";

exit($fail > 0 ? 1 : 0);