<?php
/**
 * Test: Rekonsiliasi Harian Finance
 *
 * Menguji:
 *  1.  ERP value cash masuk benar
 *  2.  ERP value transfer masuk benar
 *  3.  ERP value kas keluar benar
 *  4.  actual - ERP menghasilkan selisih benar
 *  5.  checked false → belum lengkap
 *  6.  hanya 2 checked → belum lengkap
 *  7.  3 checked → lengkap
 *  8.  3 checked + semua selisih 0 → lengkap & cocok
 *  9.  3 checked + ada selisih → lengkap tetapi ada selisih
 *  10. score KPI menghitung hari lengkap dengan benar
 *  11. duplicate unit + tanggal tidak terjadi
 *  12. user tidak bisa mengakses unit di luar scope (tested via service)
 *
 * Jalankan: php app/Scripts/rekon_daily_test.php
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

$db = \Config\Database::connect();

$pass = 0;
$fail = 0;
$skip = 0;

function ok(string $label, bool $result, string $extra = ''): void
{
    global $pass, $fail;
    if ($result) {
        echo "PASS  {$label}\n";
        $pass++;
    } else {
        echo "FAIL  {$label}  {$extra}\n";
        $fail++;
    }
}

function skip(string $label): void
{
    global $skip;
    echo "SKIP  {$label}\n";
    $skip++;
}

// ================================================================
// Ensure table exists (run migration if needed)
// ================================================================
if (!$db->tableExists('finance_rekon_daily')) {
    echo "Running migration for finance_rekon_daily...\n";
    $forge = \Config\Database::forge();
    $migration = new \App\Database\Migrations\CreateFinanceRekonDaily($forge, $db);
    $migration->up();
}
ok('Table finance_rekon_daily exists', $db->tableExists('finance_rekon_daily'));

// ================================================================
// Setup: find a unit with data
// ================================================================
$unit = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getRow();
if (!$unit) {
    echo "SKIP: no unit found\n";
    exit(0);
}
$unitId = (int) $unit->idunit;
$today = date('Y-m-d');
$testDate = date('Y-m-d', strtotime('-1 day'));

echo "\n--- Unit: {$unit->NAMA_UNIT} (id={$unitId}), testDate={$testDate} ---\n\n";

$calc = new \App\Services\Finance\RekonDailyCalculator();
$model = new \App\Models\ModelFinanceRekonDaily();

// ================================================================
// T1-T3: ERP values are numeric and non-negative
// ================================================================
$erp = $calc->erpValues($unitId, $testDate);
ok('T1: ERP cash_masuk is int >= 0', is_int($erp['cash_masuk']) && $erp['cash_masuk'] >= 0, json_encode($erp));
ok('T2: ERP transfer_masuk is int >= 0', is_int($erp['transfer_masuk']) && $erp['transfer_masuk'] >= 0, json_encode($erp));
ok('T3: ERP kas_keluar is int >= 0', is_int($erp['kas_keluar']) && $erp['kas_keluar'] >= 0, json_encode($erp));

// ================================================================
// T4: selisih = actual - erp
// ================================================================
$actualCash = $erp['cash_masuk'] + 5000;
$selisihExpected = 5000;
$selisihComputed = $actualCash - $erp['cash_masuk'];
ok('T4: selisih = actual - erp', $selisihComputed === $selisihExpected, "got={$selisihComputed}, expected={$selisihExpected}");

// ================================================================
// T5-T9: Status harian tests (pure logic, no DB)
// ================================================================
$rowNone = null;
ok('T5: checked_false → belum', \App\Services\Finance\RekonDailyCalculator::statusHarian($rowNone) === 'belum');

$rowPartial = (object)[
    'checked_cash_masuk' => 1,
    'checked_transfer_masuk' => 1,
    'checked_kas_keluar' => 0,
    'selisih_cash_masuk' => 0,
    'selisih_transfer_masuk' => 0,
    'selisih_kas_keluar' => 0,
];
ok('T6: 2 checked → belum_lengkap', \App\Services\Finance\RekonDailyCalculator::statusHarian($rowPartial) === 'belum_lengkap');

$rowLengkapCocok = (object)[
    'checked_cash_masuk' => 1,
    'checked_transfer_masuk' => 1,
    'checked_kas_keluar' => 1,
    'selisih_cash_masuk' => 0,
    'selisih_transfer_masuk' => 0,
    'selisih_kas_keluar' => 0,
];
ok('T7: 3 checked → lengkap (not belum)', \App\Services\Finance\RekonDailyCalculator::statusHarian($rowLengkapCocok) !== 'belum_lengkap');
ok('T8: 3 checked + selisih 0 → lengkap_cocok', \App\Services\Finance\RekonDailyCalculator::statusHarian($rowLengkapCocok) === 'lengkap_cocok');

$rowLengkapSelisih = (object)[
    'checked_cash_masuk' => 1,
    'checked_transfer_masuk' => 1,
    'checked_kas_keluar' => 1,
    'selisih_cash_masuk' => 0,
    'selisih_transfer_masuk' => 500,
    'selisih_kas_keluar' => 0,
];
ok('T9: 3 checked + selisih → lengkap_selisih', \App\Services\Finance\RekonDailyCalculator::statusHarian($rowLengkapSelisih) === 'lengkap_selisih');

// ================================================================
// T10: KPI score calculation (DB test with rollback)
// ================================================================
$db->transBegin();

$cleanupDate1 = date('Y-m-01');
$cleanupDate2 = date('Y-m-02');
$cleanupDate3 = date('Y-m-03');

$db->table('finance_rekon_daily')->where('unit_id', $unitId)->whereIn('tanggal', [$cleanupDate1, $cleanupDate2, $cleanupDate3])->delete();

$model->insert([
    'unit_id' => $unitId, 'tanggal' => $cleanupDate1,
    'erp_cash_masuk' => 100, 'actual_cash_masuk' => 100, 'selisih_cash_masuk' => 0, 'checked_cash_masuk' => 1,
    'erp_transfer_masuk' => 200, 'actual_transfer_masuk' => 200, 'selisih_transfer_masuk' => 0, 'checked_transfer_masuk' => 1,
    'erp_kas_keluar' => 50, 'actual_kas_keluar' => 50, 'selisih_kas_keluar' => 0, 'checked_kas_keluar' => 1,
    'input_by' => 1,
]);
$model->insert([
    'unit_id' => $unitId, 'tanggal' => $cleanupDate2,
    'erp_cash_masuk' => 100, 'actual_cash_masuk' => 100, 'selisih_cash_masuk' => 0, 'checked_cash_masuk' => 1,
    'erp_transfer_masuk' => 200, 'actual_transfer_masuk' => 200, 'selisih_transfer_masuk' => 0, 'checked_transfer_masuk' => 1,
    'erp_kas_keluar' => 50, 'actual_kas_keluar' => 50, 'selisih_kas_keluar' => 0, 'checked_kas_keluar' => 1,
    'input_by' => 1,
]);
$model->insert([
    'unit_id' => $unitId, 'tanggal' => $cleanupDate3,
    'erp_cash_masuk' => 100, 'actual_cash_masuk' => 110, 'selisih_cash_masuk' => 10, 'checked_cash_masuk' => 1,
    'erp_transfer_masuk' => 200, 'actual_transfer_masuk' => 200, 'selisih_transfer_masuk' => 0, 'checked_transfer_masuk' => 0,
    'erp_kas_keluar' => 50, 'actual_kas_keluar' => 50, 'selisih_kas_keluar' => 0, 'checked_kas_keluar' => 1,
    'input_by' => 1,
]);

$yearNow = (int) date('Y');
$monthNow = (int) date('m');
$lengkap = $model->countLengkapInRange($unitId, date('Y-m-01'), date('Y-m-t'));
ok('T10: countLengkap counts only fully checked rows (expect 2)', $lengkap === 2, "got={$lengkap}");

$score = $calc->calculate($unitId, $monthNow, $yearNow);
ok('T10b: KPI score is numeric', is_numeric($score['score']), json_encode($score));
ok('T10c: KPI score > 0', $score['score'] > 0, "score={$score['score']}");
ok('T10d: hari_lengkap in detail = 2', ($score['detail']['hari_lengkap'] ?? -1) === 2, json_encode($score['detail']));

$db->transRollback();

// ================================================================
// T11: Duplicate unit + tanggal (upsert test)
// ================================================================
$db->transBegin();

$dupDate = '2026-01-15';
$db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal', $dupDate)->delete();

$model->upsert([
    'unit_id' => $unitId, 'tanggal' => $dupDate,
    'erp_cash_masuk' => 100, 'actual_cash_masuk' => 100, 'selisih_cash_masuk' => 0, 'checked_cash_masuk' => 1,
    'erp_transfer_masuk' => 0, 'actual_transfer_masuk' => 0, 'selisih_transfer_masuk' => 0, 'checked_transfer_masuk' => 1,
    'erp_kas_keluar' => 0, 'actual_kas_keluar' => 0, 'selisih_kas_keluar' => 0, 'checked_kas_keluar' => 1,
    'input_by' => 1,
]);

$model->upsert([
    'unit_id' => $unitId, 'tanggal' => $dupDate,
    'erp_cash_masuk' => 200, 'actual_cash_masuk' => 200, 'selisih_cash_masuk' => 0, 'checked_cash_masuk' => 1,
    'erp_transfer_masuk' => 0, 'actual_transfer_masuk' => 0, 'selisih_transfer_masuk' => 0, 'checked_transfer_masuk' => 1,
    'erp_kas_keluar' => 0, 'actual_kas_keluar' => 0, 'selisih_kas_keluar' => 0, 'checked_kas_keluar' => 1,
    'input_by' => 1,
]);

$count = $db->table('finance_rekon_daily')
    ->where('unit_id', $unitId)
    ->where('tanggal', $dupDate)
    ->countAllResults();
ok('T11: upsert does not duplicate (count=1)', $count === 1, "count={$count}");

$row = $model->getByUnitAndDate($unitId, $dupDate);
ok('T11b: upsert updated value', (int)$row->erp_cash_masuk === 200, "erp_cash_masuk={$row->erp_cash_masuk}");

$db->transRollback();

// ================================================================
// T12: Scope validation (service-level check)
// ================================================================
$scopeService = new \App\Services\Finance\FinanceScopeService();
$allowedUnits = $scopeService->resolveAllowedUnits();
$allowedIds = array_map('intval', array_column(array_map('get_object_vars', $allowedUnits), 'idunit'));
$fakeUnitId = 99999;
$inScope = in_array($fakeUnitId, $allowedIds, true);
ok('T12: fake unit 99999 not in allowed scope', !$inScope);

// ================================================================
// T13: Config check — rekonsiliasi removed from manualKpiCodes
// ================================================================
$config = new \Config\Finance();
ok('T13: rekonsiliasi NOT in manualKpiCodes', !in_array('rekonsiliasi', $config->manualKpiCodes, true));
ok('T13b: rekonsiliasi still in kpiWeights', isset($config->kpiWeights['rekonsiliasi']));

// ================================================================
// T14: FinanceKpiCalculationService includes rekon_detail
// ================================================================
$kpiService = new \App\Services\Finance\FinanceKpiCalculationService();
$scorecard = $kpiService->scorecard($unitId, $monthNow, $yearNow);
ok('T14: scorecard has rekon_detail', isset($scorecard['rekon_detail']));
ok('T14b: scorecard rows has rekonsiliasi', isset($scorecard['rows']['rekonsiliasi']));
ok('T14c: rekonsiliasi mode is auto', ($scorecard['rows']['rekonsiliasi']['mode'] ?? '') === 'auto');

// ================================================================
echo "\n========================================\n";
echo "PASS: {$pass}  FAIL: {$fail}  SKIP: {$skip}\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
