<?php
/**
 * Evaluator Authorization Test – PHP 7.4 compatible
 */
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
if (!defined('APPPATH')) define('APPPATH', realpath(rtrim($paths->appDirectory, '\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
if (!defined('SYSTEMPATH')) define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('WRITEPATH')) define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$svc = new \App\Services\Kpi\KpiEvaluationService();
$db  = \Config\Database::connect();

$component = 9; // KEHADIRAN component id (exists in seed)
$date = '2026-08-01';
$raw = 5;

$cases = [
    // [evaluator_id, employee_id, expected_success, description]
    [43, 48, true,  'KT -> Teknisi'],
    [43, 44, true,  'KT -> Admin'],
    [43, 41, false, 'KT -> SPV'],
    [41, 43, true,  'SPV -> KT'],
    [41, 48, false, 'SPV -> Teknisi'],
    [55, 54, true,  'KD -> Customer Service'],
    [55, 63, true,  'KD -> Multimedia'],
    [55, 43, false, 'KD -> KT'],
    [44, 48, false, 'Admin evaluator not allowed'],
    [44, 44, true,  'Self evaluation (Admin)'],
];

$pass = 0; $fail = 0;
foreach ($cases as $c) {
    list($evaluator, $employee, $exp, $desc) = $c;
    $res = $svc->recordEvaluation([
        'employee_id' => $employee,
        'kpi_component_id' => $component,
        'evaluator_id' => $evaluator,
        'evaluation_date' => $date,
        'raw_score' => $raw,
    ]);
    $ok = ($res['success'] === $exp);
    if ($ok) { echo "[PASS] $desc\n"; $pass++; }
    else { echo "[FAIL] $desc (expected " . ($exp ? 'success' : 'failure') . ")\n"; $fail++; }
    // cleanup row if inserted
    $db->table('kpi_evaluations')->where('employee_id', $employee)->where('kpi_component_id', $component)->where('evaluation_date', $date)->delete();
}

echo "\nRESULT: PASS=$pass FAIL=$fail\n";
?>