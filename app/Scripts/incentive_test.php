<?php
/**
 * Incentive Calculation Test
 * Verifikasi business rule CONFIRMED insentif group-based.
 *
 * Usage: php app/Scripts/incentive_test.php
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
$service = new \App\Services\Incentive\IncentiveCalculationService();
$memberModel = new \App\Models\ModelIncentiveMember();

$pass = 0;
$fail = 0;

function check($label, $expected, $actual, $tol = 0.01) {
    global $pass, $fail;
    if (abs($expected - $actual) <= $tol) {
        echo "[PASS] $label => $actual (expected $expected)\n";
        $pass++;
    } else {
        echo "[FAIL] $label => $actual (expected $expected)\n";
        $fail++;
    }
}

echo "============================================================\n";
echo "TEST INCENTIVE GROUP (business rule CONFIRMED)\n";
echo "============================================================\n";

$date = '2026-08-28';

// ===== TEST 1: KEPALA_TOKO, omset 100jt, 3 member =====
echo "\n--- Test 1: KEPALA_TOKO (omset 100jt, 3 member di unit 1) ---\n";
$group = $memberModel->where('unit_id', 1)
    ->join('incentive_groups', 'incentive_groups.id = incentive_members.incentive_group_id')
    ->where('incentive_groups.code', 'KEPALA_TOKO')
    ->where('incentive_members.is_active', 1)
    ->countAllResults();
echo "member count: $group\n";
$r = $service->calculateGroupIncentive('KEPALA_TOKO', 1, 100000000, 100, $date);
check('KT pool = 3% x 100jt', 3000000, $r['pool_amount']);
check('KT individual = pool/3', 1000000, $r['incentive_amount']);

// ===== TEST 2: DIGITAL_DIVISION, omset 100jt, 4 member =====
echo "\n--- Test 2: DIGITAL_DIVISION (omset 100jt, 4 member) ---\n";
$r = $service->calculateGroupIncentive('DIGITAL_DIVISION', 1, 100000000, 100, $date);
check('DD pool = 1% x 100jt', 1000000, $r['pool_amount']);
check('DD individual = pool/4', 250000, $r['incentive_amount']);

// ===== TEST 3: Dynamic membership (tambah member KT ke 4) =====
echo "\n--- Test 3: Dynamic membership (tambah 1 member KT unit 1 => 4) ---\n";
$ktGroup = $db->table('incentive_groups')->where('code', 'KEPALA_TOKO')->get()->getFirstRow();
// tambahkan employee sementara (id 9999 — dummy, non-aktif utk validasi FK? Cek ketersediaan id)
// kita gunakan employee nyata dari unit 1 yg bukan KT/Teknisi/Admin, misal jab 44 (Multimedia) id 63
$extraEmp = $db->table('akun')->where('ID_UNIT', 1)->where('ID_JABATAN', 44)->get()->getFirstRow();
if ($extraEmp) {
    $db->table('incentive_members')->insert([
        'incentive_group_id' => $ktGroup->id,
        'employee_id'        => $extraEmp->ID_AKUN,
        'unit_id'            => 1,
        'effective_from'     => '2026-08-01',
        'effective_to'       => null,
        'is_active'          => 1,
    ]);
    $groupNow = $memberModel->countActiveMembers((int)$ktGroup->id, 1, $date);
    echo "member count now: $groupNow\n";
    $r2 = $service->calculateGroupIncentive('KEPALA_TOKO', 1, 100000000, 100, $date);
    check('KT dynamic pool', 3000000, $r2['pool_amount']);
    check('KT individual = pool/4 (dinamis, tanpa ubah code)', 750000, $r2['incentive_amount']);
    // cleanup: hapus member test
    $db->table('incentive_members')->where('employee_id', $extraEmp->ID_AKUN)->where('incentive_group_id', $ktGroup->id)->delete();
} else {
    echo "[SKIP] tidak ada employee unit 1 jab 44 untuk test dynamic\n";
}

// ===== TEST 4: Unit isolation =====
echo "\n--- Test 4: Unit isolation (unit 2 KT = 3 member) ---\n";
$unit2Count = $memberModel->where('unit_id', 2)
    ->join('incentive_groups', 'incentive_groups.id = incentive_members.incentive_group_id')
    ->where('incentive_groups.code', 'KEPALA_TOKO')
    ->where('incentive_members.is_active', 1)
    ->countAllResults();
echo "unit 2 member count: $unit2Count\n";
$r3 = $service->calculateGroupIncentive('KEPALA_TOKO', 2, 100000000, 100, $date);
check('KT unit2 pool', 3000000, $r3['pool_amount']);
check('KT unit2 individual = pool/3', 1000000, $r3['incentive_amount']);

// ===== TEST 5: Achievement < minimum =====
echo "\n--- Test 5: achievement 80% < minimum 100% ---\n";
$r5 = $service->calculateGroupIncentive('KEPALA_TOKO', 1, 100000000, 80, $date);
check('incentive = 0 (below min)', 0, $r5['incentive_amount']);
check('success = false (return false)', 0, $r5['success'] ? 1 : 0, 0);

// ===== TEST 6: member_count = 0 (unit tanpa member group) => no div by zero =====
echo "\n--- Test 6: unit tanpa member => 0 (no div by zero) ---\n";
$r6 = $service->calculateGroupIncentive('KEPALA_TOKO', 5, 100000000, 100, $date);
check('incentive = 0 (no members)', 0, $r6['incentive_amount']);
echo "reason: {$r6['reason']}\n";

echo "\n============================================================\n";
echo "RESULT: PASS=$pass FAIL=$fail\n";
echo ($fail === 0 ? "STATUS: ALL_PASS" : "STATUS: HAS_FAILURE") . "\n";
exit($fail === 0 ? 0 : 1);