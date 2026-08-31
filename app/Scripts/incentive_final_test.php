<?php
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
$date = '2026-08-28';

$pass=0;$fail=0;
function chk($l,$e,$a,$tol=0.001){global $pass,$fail; if(abs($e-$a)<=$tol){echo "[PASS] $l = $a\n";$pass++;}else{echo "[FAIL] $l = $a (expected $e)\n";$fail++;}}

echo "=== CASE 1: omzet 30jt, rate 3%, pool 900k, per member 300k ===\n";
$r = $service->calculateGroupIncentive('KEPALA_TOKO', 2, 30000000, 100, $date);
chk('member count', 3, $r['member_count']);
chk('pool', 900000, $r['pool_amount']);
chk('per member (3)', 300000, $r['incentive_amount']);

echo "\n=== CASE 2: omzet 50jt, rate 3%, pool 1.5jt, per member 500k ===\n";
$r = $service->calculateGroupIncentive('KEPALA_TOKO', 2, 50000000, 100, $date);
chk('pool', 1500000, $r['pool_amount']);
chk('per member (3)', 500000, $r['incentive_amount']);

echo "\n=== CASE 3: temporary 4th member (fixture, bukan anggota org) ===\n";
$group = $db->table('incentive_groups')->where('code','KEPALA_TOKO')->get()->getFirstRow();
// pilih employee nyata yang BELUM menjadi member di GROUP MANAPUN adalah kunci:
// ambil employee bukan anggota KEPALA_TOKO di unit 2 — pilih dari unit yang beda & bukan member
$allMembers = $db->table('incentive_members')->where('incentive_group_id',$group->id)
    ->select('employee_id')->get()->getResultArray();
$exIds = array_column($allMembers, 'employee_id');
$extraEmp = $db->table('akun')->where('STATUS_PEGAWAI',1)
    ->whereNotIn('ID_AKUN', array_map('intval',$exIds))->get()->getFirstRow();
if ($extraEmp) {
    $db->table('incentive_members')->insert([
        'incentive_group_id'=>$group->id,'employee_id'=>$extraEmp->ID_AKUN,'unit_id'=>2,
        'effective_from'=>'2026-08-01','effective_to'=>null,'is_active'=>1]);
    $newId = $db->insertID();
    $r = $service->calculateGroupIncentive('KEPALA_TOKO', 2, 30000000, 100, $date);
    chk('member count (4)', 4, $r['member_count']);
    chk('per member (4 dynamic)', 225000, $r['incentive_amount']);
    // cleanup PRESISI via insertID
    $db->table('incentive_members')->where('id',$newId)->delete();
    chk('cleanup row', 0, $db->table('incentive_members')->where('id',$newId)->countAllResults());
    echo "(fixture cleaned)\n";
} else { echo "[SKIP] no spare employee\n"; }

$after = $db->table('incentive_members')->where('incentive_group_id',$group->id)->where('unit_id',2)->where('is_active',1)->countAllResults();
chk('member count after cleanup', 3, $after);

echo "\n=== Verifikasi komposisi KEPALA_TOKO unit2 (KT+Teknisi+Admin) ===\n";
$list = $db->query("SELECT a.ID_JABATAN, j.NAMA_JABATAN FROM incentive_members im
    JOIN akun a ON a.ID_AKUN=im.employee_id JOIN jabatan j ON j.ID_JABATAN=a.ID_JABATAN
    WHERE im.incentive_group_id={$group->id} AND im.unit_id=2 AND im.is_active=1 ORDER BY a.ID_JABATAN")->getResultArray();
foreach($list as $m){ echo "  jab {$m['ID_JABATAN']} {$m['NAMA_JABATAN']}\n"; }

echo "\nRESULT: PASS=$pass FAIL=$fail\n";
exit($fail===0?0:1);
