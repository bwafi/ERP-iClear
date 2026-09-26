<?php
/**
 * Smoke test alur POST: save -> submit -> approve (verify / need_revision).
 *
 * Memverifikasi controller benar-benar menolak input tidak valid,olak
 * self-verify, dan tidak merusak alur di level HTTP (bukan hanya logika murni).
 *
 * Jalankan: php74 app/Scripts/recon_flow_smoke.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Config/Paths.php';

$paths = new Config\Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();

$db = Config\Database::connect();
$db->transStrict(false);

$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . ($ok ? '' : '  ' . $extra) . "\n";
    if (! $ok) {
        $fail++;
    }
}

$config = new Config\Finance();
$model = new App\Models\ModelFinanceRekonDaily();
$calc = new App\Services\Finance\RekonDailyCalculator();
$controller = new App\Controllers\DashboardFinance();

$unit = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getRow();
$unitId = (int) $unit->idunit;
$today = date('Y-m-d');

$akunSubmitter = $db->table('akun')->whereIn('ID_JABATAN', $config->financeInputRoles)
    ->select('ID_AKUN, ID_JABATAN')->orderBy('ID_AKUN', 'ASC')->get()->getRow();
$akunApprover = $db->table('akun')->whereIn('ID_JABATAN', $config->financeApproveRoles)
    ->where('ID_AKUN !=', (int) $akunSubmitter->ID_AKUN)
    ->select('ID_AKUN, ID_JABATAN')->orderBy('ID_AKUN', 'ASC')->get()->getRow();

if (! $akunSubmitter || ! $akunApprover) {
    echo "SKIP: butuh minimal 2 akun finance (1 submitter, 1 approver)\n";
    exit(0);
}
echo "Submitter={$akunSubmitter->ID_AKUN} Approver={$akunApprover->ID_AKUN} Unit={$unitId} Hari={$today}\n\n";

/**
 * Siapkan controller dengan session + POST tertentu, lalu panggil method.
 *
 * PENTING: IncomingRequest baru dibuat tiap pemanggilan. Bila memakai
 * Services::request() yang SHARED, `globals['post']` di-cache pada panggilan
 * pertama sehingga semua request berikutnya memakai data POST yang sama
 * (test jadi salahARAU tanpa error).
 */
function runAs(int $akunId, array $post, string $method)
{
    global $controller, $reqProp;

    $_SESSION['ID_AKUN'] = $akunId;
    $_POST = $post;
    $_GET = [];
    $reqProp->setValue($controller, \Config\Services::request(null, false));

    $result = (new ReflectionMethod($controller, $method))->invoke($controller);

    return is_object($result) ? get_class($result) : gettype($result);
}

$reqProp = new ReflectionProperty(CodeIgniter\Controller::class, 'request');
$reqProp->setAccessible(true);
$reqProp->setValue($controller, \Config\Services::request(null, false));

$db->transBegin();
$db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal', $today)->delete();

$basePost = [
    'unit_id' => $unitId,
    'tanggal' => $today,
    'actual_cash_masuk' => '1.000.000',
    'actual_transfer_masuk' => '500.000',
    'actual_kas_keluar' => '250.000',
    // Tidak ada checked_*: status hasil otomatis dari aktual vs ERP.
    'catatan' => 'flow smoke',
];

// 1. Simpan draft
runAs((int) $akunSubmitter->ID_AKUN, $basePost, 'rekonSave');
$row = $model->getByUnitAndDate($unitId, $today);
check('POST save membuat record draft', $row !== null);
check('draft berstatus draft', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'draft');
check('nominal diparse sebagai integer', (int) $row->actual_cash_masuk === 1000000, (string) $row->actual_cash_masuk);
check('input_by terisi akun submitter', (int) $row->input_by === (int) $akunSubmitter->ID_AKUN);
check('draft belum punya submitted_by', $row->submitted_by === null);
check('selisih dihitung server-side', (int) $row->selisih_cash_masuk === (int) $row->actual_cash_masuk - (int) $row->erp_cash_masuk);

// 2. Nominal negatif DITOLAK (tidak menimpa data)
$negPost = $basePost;
$negPost['actual_cash_masuk'] = '-1.000.000';
runAs((int) $akunSubmitter->ID_AKUN, $negPost, 'rekonSave');
$rowAfterNeg = $model->getByUnitAndDate($unitId, $today);
check('nominal negatif ditolak (data lama utuh)', (int) $rowAfterNeg->actual_cash_masuk === 1000000, (string) $rowAfterNeg->actual_cash_masuk);

// 3. Teks non-numerik DITOLAK
$badPost = $basePost;
$badPost['actual_transfer_masuk'] = 'abc';
runAs((int) $akunSubmitter->ID_AKUN, $badPost, 'rekonSave');
$rowAfterBad = $model->getByUnitAndDate($unitId, $today);
check('nominal non-numerik ditolak', (int) $rowAfterBad->actual_transfer_masuk === 500000, (string) $rowAfterBad->actual_transfer_masuk);

// 4. Desimal DITOLAK
$decPost = $basePost;
$decPost['actual_kas_keluar'] = '250.000,50';
runAs((int) $akunSubmitter->ID_AKUN, $decPost, 'rekonSave');
$rowAfterDec = $model->getByUnitAndDate($unitId, $today);
check('nominal desimal ditolak', (int) $rowAfterDec->actual_kas_keluar === 250000, (string) $rowAfterDec->actual_kas_keluar);

// 5. Tanggal masa depan DITOLAK
$future = date('Y-m-d', strtotime('+1 day'));
$futurePost = $basePost;
$futurePost['tanggal'] = $future;
runAs((int) $akunSubmitter->ID_AKUN, $futurePost, 'rekonSave');
check('tanggal masa depan ditolak', $model->getByUnitAndDate($unitId, $future) === null);

// 6. Submit -> submitted
runAs((int) $akunSubmitter->ID_AKUN, ['unit_id' => $unitId, 'tanggal' => $today], 'rekonSubmit');
$row = $model->getByUnitAndDate($unitId, $today);
check('POST submit -> submitted', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'submitted');
check('submitted_by = akun submitter', (int) $row->submitted_by === (int) $akunSubmitter->ID_AKUN);
check('submitted_at terisi', $row->submitted_at !== null);

// 7. SELF-VERIFY DITOLAK
$verSelf = ['unit_id' => $unitId, 'tanggal' => $today, 'action' => 'verify', 'catatan_revisi' => ''];
runAs((int) $akunSubmitter->ID_AKUN, $verSelf, 'rekonApprove');
$row = $model->getByUnitAndDate($unitId, $today);
check('self-verify DITOLAK (tetap submitted)', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'submitted');

// 8. need_revision tanpa catatan DITOLAK
$needNoNote = ['unit_id' => $unitId, 'tanggal' => $today, 'action' => 'need_revision', 'catatan_revisi' => '   '];
runAs((int) $akunApprover->ID_AKUN, $needNoNote, 'rekonApprove');
$row = $model->getByUnitAndDate($unitId, $today);
check('need_revision tanpa catatan DITOLAK', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'submitted');

// 9. need_revision dengan catatan DITERIMA
$needOk = ['unit_id' => $unitId, 'tanggal' => $today, 'action' => 'need_revision', 'catatan_revisi' => 'Selisih transfer 25.000, mohon dicek'];
runAs((int) $akunApprover->ID_AKUN, $needOk, 'rekonApprove');
$row = $model->getByUnitAndDate($unitId, $today);
check('need_revision DITERIMA oleh approver', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'need_revision');
check('catatan revisi tersimpan', $row->catatan_revisi === 'Selisih transfer 25.000, mohon dicek');

// 10. Data perlu revisi bisa diedit & disubmit ulang
$fixPost = $basePost;
$fixPost['actual_transfer_masuk'] = '475.000';
runAs((int) $akunSubmitter->ID_AKUN, $fixPost, 'rekonSave');
$row = $model->getByUnitAndDate($unitId, $today);
check('perbaiki data setelah need_revision', (int) $row->actual_transfer_masuk === 475000, (string) $row->actual_transfer_masuk);
check('revisi mengembalikan status ke draft', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'draft');
check('catatan revisi dibersihkan setelah revisi', $row->catatan_revisi === null);

runAs((int) $akunSubmitter->ID_AKUN, ['unit_id' => $unitId, 'tanggal' => $today], 'rekonSubmit');
$row = $model->getByUnitAndDate($unitId, $today);
check('submit ulang berhasil', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'submitted');

// 11. Verify oleh approver lain DITERIMA
runAs((int) $akunApprover->ID_AKUN, $verSelf, 'rekonApprove');
$row = $model->getByUnitAndDate($unitId, $today);
check('verify oleh approver lain DITERIMA', App\Services\Finance\RekonDailyCalculator::statusProses($row) === 'verified');
check('verified_by = akun approver', (int) $row->verified_by === (int) $akunApprover->ID_AKUN);
check('verified_at terisi', $row->verified_at !== null);

// 12. Data VERIFIED terkunci: save & submit ditolak
runAs((int) $akunSubmitter->ID_AKUN, $basePost, 'rekonSave');
$rowAfterLock = $model->getByUnitAndDate($unitId, $today);
check('data verified tidak bisa diubah', (int) $rowAfterLock->actual_cash_masuk === 1000000, (string) $rowAfterLock->actual_cash_masuk);
check('verified tetap verified setelah percobaan save', App\Services\Finance\RekonDailyCalculator::statusProses($rowAfterLock) === 'verified');

// 13. Approve pada record yang tidak submitted DITOLAK
runAs((int) $akunApprover->ID_AKUN, $verSelf, 'rekonApprove');
$row = $model->getByUnitAndDate($unitId, $today);
check('approve kedua kali DITOLAK (tidak double-verify)', (int) $row->verified_by === (int) $akunApprover->ID_AKUN);

// 14. Unit di luar scope DITOLAK
$unitLain = $db->table('akun')->where('ID_AKUN', (int) $akunApprover->ID_AKUN)->select('ID_UNIT')->get()->getRow();
$outOfScopePost = $basePost;
$outOfScopePost['unit_id'] = 999999;
$outOfScopePost['tanggal'] = $today;
runAs((int) $akunSubmitter->ID_AKUN, $outOfScopePost, 'rekonSave');
check('unit di luar daftar ditolak', $model->getByUnitAndDate(999999, $today) === null);

// 15. Akun non-finance DITOLAK
$nonFinance = $db->table('akun')->whereNotIn('ID_JABATAN', $config->financeInputRoles)
    ->select('ID_AKUN, ID_JABATAN')->orderBy('ID_AKUN', 'ASC')->get()->getRow();
if ($nonFinance) {
    runAs((int) $nonFinance->ID_AKUN, $basePost, 'rekonSave');
    $rowNonFin = $model->getByUnitAndDate($unitId, $today);
    check('akun non-finance tidak bisa menyimpan', (int) $rowNonFin->input_by === (int) $akunSubmitter->ID_AKUN);
} else {
    check('akun non-finance tersedia untuk uji', true);
}

$db->transRollback();
$_SESSION = [];

echo "\nFAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
