<?php
/**
 * Daily Attendance Storage tests (CASE 1-5) — READ-WRITE pada tabel kpi_evaluations,
 * dengan CLEANUP penuh agar tidak meninggalkan data test.
 * Usage: php app/Scripts/daily_eval_test.php
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

$svc = new \App\Services\Kpi\KpiEvaluationService();
$model = new \App\Models\ModelKpiEvaluation();
$db = \Config\Database::connect();

$pass = 0; $fail = 0;
function ok($label, $cond) { global $pass, $fail; if ($cond) { echo "[PASS] $label\n"; $pass++; } else { echo "[FAIL] $label\n"; $fail++; } }

// Employee 48 (jab 36), komponen KEHADIRAN (id=9) & KEBERSIHAN (id=10)
$emp = 48;
$kh = 9;   // kehadiran
$kb = 10;  // kebersihan
$d1 = '2026-08-01';
$d2 = '2026-08-02';
$d3 = '2026-08-03';

// ── CASE 1: insert 5 pada 08-01 → 1 row ──
$r = $svc->recordEvaluation(['employee_id'=>$emp,'kpi_component_id'=>$kh,'evaluator_id'=>43,'evaluation_date'=>$d1,'raw_score'=>5]);
ok('CASE1 insert sukses', $r['success']);
$cnt = $model->where('employee_id',$emp)->where('kpi_component_id',$kh)->where('evaluation_date',$d1)->countAllResults();
ok('CASE1 1 row', $cnt === 1);

// ── CASE 2: insert ulang 08-01 raw=4 → tetap 1 row, raw=4 ──
$r = $svc->recordEvaluation(['employee_id'=>$emp,'kpi_component_id'=>$kh,'evaluator_id'=>43,'evaluation_date'=>$d1,'raw_score'=>4]);
ok('CASE2 update sukses', $r['success']);
$cnt = $model->where('employee_id',$emp)->where('kpi_component_id',$kh)->where('evaluation_date',$d1)->countAllResults();
$row = $model->where('employee_id',$emp)->where('kpi_component_id',$kh)->where('evaluation_date',$d1)->first();
ok('CASE2 tetap 1 row', $cnt === 1);
ok('CASE2 raw=4', $row && (float)$row->raw_score === 4.0);

// ── CASE 3: 08-02 raw=5 → 2 row ──
$r = $svc->recordEvaluation(['employee_id'=>$emp,'kpi_component_id'=>$kh,'evaluator_id'=>43,'evaluation_date'=>$d2,'raw_score'=>5]);
$cnt = $model->where('employee_id',$emp)->where('kpi_component_id',$kh)->whereIn('evaluation_date',[$d1,$d2])->countAllResults();
ok('CASE3 2 row (2 tanggal)', $cnt === 2);

// ── CASE 4: komponen beda (kebersihan) tanggal sama d1 → row terpisah ──
$r = $svc->recordEvaluation(['employee_id'=>$emp,'kpi_component_id'=>$kb,'evaluator_id'=>43,'evaluation_date'=>$d1,'raw_score'=>5]);
$cntKh = $model->where('employee_id',$emp)->where('kpi_component_id',$kh)->where('evaluation_date',$d1)->countAllResults();
$cntKb = $model->where('employee_id',$emp)->where('kpi_component_id',$kb)->where('evaluation_date',$d1)->countAllResults();
ok('CASE4 kehadiran 1 row di d1', $cntKh === 1);
ok('CASE4 kebersihan 1 row di d1 (terpisah)', $cntKb === 1);

// ── CASE 5: employee beda (misal 61 Anggun) komponen sama tanggal sama → terpisah ──
$emp2 = 61;
$r = $svc->recordEvaluation(['employee_id'=>$emp2,'kpi_component_id'=>$kh,'evaluator_id'=>43,'evaluation_date'=>$d1,'raw_score'=>3]);
$cntE1 = $model->where('employee_id',$emp)->where('kpi_component_id',$kh)->where('evaluation_date',$d1)->countAllResults();
$cntE2 = $model->where('employee_id',$emp2)->where('kpi_component_id',$kh)->where('evaluation_date',$d1)->countAllResults();
ok('CASE5 employee 48 tetap 1 row', $cntE1 === 1);
ok('CASE5 employee 61 1 row terpisah', $cntE2 === 1);

// ── Verifikasi period derived ──
$row = $model->where('employee_id',$emp2)->where('kpi_component_id',$kh)->where('evaluation_date',$d1)->first();
ok('evaluation_date benar', $row && $row->evaluation_date === $d1);
ok('period_year=2026', $row && (int)$row->period_year === 2026);
ok('period_month=8', $row && (int)$row->period_month === 8);

// ── Validasi: tanggal invalid ditolak ──
$r = $svc->recordEvaluation(['employee_id'=>$emp,'kpi_component_id'=>$kh,'evaluator_id'=>41,'evaluation_date'=>'not-a-date','raw_score'=>5]);
ok('tanggal invalid ditolak', !$r['success']);

// ── CLEANUP SEMUA DATA TEST ──
$db->table('kpi_evaluations')->where('employee_id',$emp)->whereIn('kpi_component_id',[$kh,$kb])->delete();
$db->table('kpi_evaluations')->where('employee_id',$emp2)->where('kpi_component_id',$kh)->delete();
$leftover = $db->table('kpi_evaluations')->countAllResults();
ok('cleanup selesai (0 row tersisa)', $leftover === 0);

echo "\nRESULT: PASS=$pass FAIL=$fail\n";
exit($fail === 0 ? 0 : 1);
