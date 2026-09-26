<?php
/**
 * Smoke test HTTP: memverifikasi route/controller/view modul rekonsiliasi
 * benar-benar bisa dirender (bukan hanya lolos unit test).
 *
 * Jalankan: php74 app/Scripts/recon_http_smoke.php
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

$unit = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getRow();
$unitId = (int) $unit->idunit;
$today = date('Y-m-d');
$model = new App\Models\ModelFinanceRekonDaily();

$fail = 0;

function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . ($ok ? '' : '  ' . $extra) . "\n";
    if (! $ok) {
        $fail++;
    }
}

echo "Unit={$unitId} today={$today}\n\n";

// Siapkan 1 record verified (input) supaya list & form punya isi.
$db->transBegin();
$db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal', $today)->delete();
$model->upsert([
    'unit_id' => $unitId, 'tanggal' => $today,
    // Tanpa checked_*: status hasil otomatis dari aktual vs ERP.
    'erp_cash_masuk' => 700000, 'actual_cash_masuk' => 700000, 'selisih_cash_masuk' => 0,
    'erp_transfer_masuk' => 300000, 'actual_transfer_masuk' => 325000, 'selisih_transfer_masuk' => 25000,
    'erp_kas_keluar' => 5468205, 'actual_kas_keluar' => 5468205, 'selisih_kas_keluar' => 0,
    'catatan' => 'smoke test', 'catatan_revisi' => 'cek transfer 25.000',
    'status_proses' => 'need_revision', 'submitted_by' => 1, 'submitted_at' => date('Y-m-d H:i:s'),
    'verified_by' => null, 'verified_at' => null, 'input_by' => 1,
]);

// 1. RENDER controller+view langsung (menangkap error PHP/exception).
// Data view diambil dari method controller yang MEMANG dipakai production
// (rekonsiliasiData / rekonFormData), bukan disusun ulang di sini — supaya
// test ini menangkap ketidakcocokan controller <-> view.
$controller = new App\Controllers\DashboardFinance();
$scopeService = new App\Services\Finance\FinanceScopeService();

// Controller hanya punya $this->request setelah initController(); di CLI kita
// suntikkan request sungguhan agar rekonsiliasiData() membaca GET seperti produksi.
// Catatan: IncomingRequest::getPost()/getGet() membaca $_POST/$_GET superglobal,
// jadi harus diisi di sana (bukan properti objek).
$_GET = ['unit_id' => (string) $unitId, 'month' => date('Y-m')];
$request = \Config\Services::request(null, true);
$reqProp = new ReflectionProperty(CodeIgniter\Controller::class, 'request');
$reqProp->setAccessible(true);
$reqProp->setValue($controller, $request);

function callProtected(object $obj, string $method, array $args = [])
{
    $ref = new ReflectionMethod(get_class($obj), $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}

function renderView(string $viewPath, array $data)
{
    try {
        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;

        return [ob_get_clean(), null];
    } catch (Throwable $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return [null, $e->getMessage()];
    }
}

// --- Akun finance nyata (ID_JABATAN 1 = Admin Root) ---
$akunFinance = $db->table('akun')
    ->whereIn('ID_JABATAN', (new Config\Finance())->financeInputRoles)
    ->select('ID_AKUN, ID_JABATAN, NAMA_AKUN')
    ->orderBy('ID_AKUN', 'ASC')
    ->get()->getRow();
if (! $akunFinance) {
    echo "SKIP: tidak ada akun finance\n";
    exit(0);
}
$_SESSION['ID_AKUN'] = (int) $akunFinance->ID_AKUN;
$_SESSION['ID_JABATAN'] = (int) $akunFinance->ID_JABATAN;
$info = $scopeService->scopeInfo();
check('akun uji punya scope lintas unit', $info['isLintas'] === true, 'jabatan=' . $akunFinance->ID_JABATAN);

$unitId = (int) $unit->idunit;
$existing = $model->getByUnitAndDate($unitId, $today);
$listData = callProtected($controller, 'rekonsiliasiData', [$info]);
$listData['unit_id'] = $unitId;
check('rekonsiliasiData menghasilkan list', isset($listData['list']) && is_array($listData['list']));

[$listView, $listErr] = renderView(APPPATH . 'Views/dashboard/finance_rekonsiliasi.php', $listData);
check('view finance_rekonsiliasi.php render tanpa error', $listErr === null, (string) $listErr);
check('list menampilkan "Lengkap - Selisih" (label sesuai spek)', strpos((string) $listView, 'Lengkap - Selisih') !== false);
check('list tidak lagi menampilkan label lama', strpos((string) $listView, 'Lengkap, Ada Selisih') === false);
check('list tidak menampilkan kolom "Sudah Diperiksa"', strpos((string) $listView, 'Sudah Diperiksa') === false);
check('list menampilkan "Perlu Revisi"', strpos((string) $listView, 'Perlu Revisi') !== false);
check('list menampilkan nominal dengan pemisah ribuan', strpos((string) $listView, '700.000') !== false);
check('list menampilkan catatan revisi', strpos((string) $listView, 'cek transfer 25.000') !== false);
check('list menampilkan badge proses need_revision', strpos((string) $listView, 'bg-danger') !== false);
check('list menampilkan link detail harian', strpos((string) $listView, 'finance/rekon/form?unit_id=') !== false);
check('list menampilkan skor KPI', strpos((string) $listView, 'Skor KPI Rekonsiliasi') !== false);

// --- form harian ---
$formData = callProtected($controller, 'rekonFormData', [$info]);
check('rekonFormData menghasilkan tanggal valid', (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $formData['tanggal']), (string) $formData['tanggal']);
check('rekonFormData membaca record existing', $formData['existing'] !== null);
check('rekonFormData status need_revision', ($formData['status_proses'] ?? '') === 'need_revision', (string) $formData['status_proses']);
check('rekonFormData can_submit true (lengkap & belum submitted)', ($formData['can_submit'] ?? false) === true);

[$formView, $formErr] = renderView(APPPATH . 'Views/dashboard/finance_rekon_form.php', $formData);
check('view finance_rekon_form.php render tanpa error', $formErr === null, (string) $formErr);
check('form menampilkan alert need_revision', strpos((string) $formView, 'Catatan manager (perlu revisi)') !== false);
check('form menampilkan tombol submit ke manager', strpos((string) $formView, 'finance/rekon/submit') !== false);
check('form TIDAK menampilkan approval saat need_revision', strpos((string) $formView, 'value="verify"') === false);
check('form menampilkan nilai actual terformat', strpos((string) $formView, '700.000') !== false);
check('form menampilkan nilai ERP terformat', strpos((string) $formView, '5.468.205') !== false);

// Konsep "Sudah Diperiksa" harus benar-benar hilang dari form.
check('form ter-render (guard: assertion di bawah tidak boleh hampa)', strpos((string) $formView, 'rupiah-rekon') !== false);
check('form tidak menampilkan kolom "Sudah Diperiksa"', strpos((string) $formView, 'Sudah Diperiksa') === false);
check('form tidak menampilkan checkbox checked_*', preg_match('/name="checked_/', (string) $formView) === 0);
check('form tidak lagi punya kolom header "Dip."', strpos((string) $formView, 'Dip.') === false);

// Guard kolom menggantung: jumlah <th> harus sama dengan <td> tiap baris.
(function () use ($formView) {
    preg_match_all('/<table.*?<\/table>/s', (string) $formView, $tables);
    foreach ($tables[0] as $n => $tbl) {
        preg_match('/<thead.*?<\/thead>/s', $tbl, $head);
        $nth = $head ? preg_match_all('/<th[\s>]/', $head[0]) : 0;
        preg_match('/<tbody.*?<\/tbody>/s', $tbl, $body);
        $rows = preg_split('/<tr[\s>]/', $body[0] ?? '');
        foreach ($rows as $i => $row) {
            $ntd = preg_match_all('/<td[\s>]/', $row);
            if ($ntd > 0) {
                check('tabel form #' . ($n + 1) . ' baris ' . ($i + 1) . ": kolom sejajar (<th>={$nth} <td>={$ntd})", $nth === $ntd);
            }
        }
    }
})();
check('form tidak menampilkan input terkunci saat need_revision', strpos((string) $formView, 'readonly') === false);

// --- form saat SUBMITTED & berwenang: tombol approval harus muncul ---
$akunLain = $db->table('akun')
    ->whereIn('ID_JABATAN', (new Config\Finance())->financeApproveRoles)
    ->where('ID_AKUN !=', (int) $akunFinance->ID_AKUN)
    ->select('ID_AKUN, ID_JABATAN')
    ->orderBy('ID_AKUN', 'ASC')
    ->get()->getRow();
check('ada akun finance lain untuk uji approval', $akunLain !== null);

if ($akunLain) {
    $model->updateRow((int) $existing->id, [
        'status_proses' => 'submitted', 'submitted_by' => (int) $akunFinance->ID_AKUN,
        'submitted_at' => date('Y-m-d H:i:s'), 'catatan_revisi' => null,
    ]);
    $formDataSubmit = callProtected($controller, 'rekonFormData', [$info]);
    check('rekonFormData can_submit false saat submitted', ($formDataSubmit['can_submit'] ?? true) === false);

    // Pengirim sendiri: tidak boleh melihat tombol approval.
    check('can_approve false saat akun adalah pengirim sendiri', ($formDataSubmit['can_approve'] ?? true) === false);
    [$selfSubmitterView] = renderView(APPPATH . 'Views/dashboard/finance_rekon_form.php', $formDataSubmit);
    check('form untuk pengirim sendiri menyembunyikan approval', strpos((string) $selfSubmitterView, 'value="verify"') === false);
    check('form untuk pengirim sendiri menjelaskan aturan self-verify', strpos((string) $selfSubmitterView, 'tidak berwenang memverifikasi data ini') !== false);
    check('form untuk pengirim sendiri menyebut pengirim sendiri', strpos((string) $selfSubmitterView, 'yang Anda kirim sendiri') !== false);
    check('form untuk pengirim sendiri menyebut verifikator Manager / Admin Root', strpos((string) $selfSubmitterView, 'Manager / Admin Root') !== false);
    check('form SUBMITTED tidak menampilkan tombol submit', strpos((string) $selfSubmitterView, 'Kirim ke Manager') === false);

    // Approver lain: tombol approval harus tampil.
    $_SESSION['ID_AKUN'] = (int) $akunLain->ID_AKUN;
    $infoApprover = $scopeService->scopeInfo();
    $formDataApprover = callProtected($controller, 'rekonFormData', [$infoApprover]);
    check('can_approve true untuk approver lain', ($formDataApprover['can_approve'] ?? false) === true);

    [$approverView, $approverErr] = renderView(APPPATH . 'Views/dashboard/finance_rekon_form.php', $formDataApprover);
    check('form SUBMITTED render tanpa error', $approverErr === null, (string) $approverErr);
    check('form SUBMITTED menampilkan aksi approval', strpos((string) $approverView, 'finance/rekon/approve') !== false);
    check('form SUBMITTED menampilkan tombol verify', strpos((string) $approverView, 'value="verify"') !== false);
    check('form SUBMITTED menampilkan tombol need_revision', strpos((string) $approverView, 'value="need_revision"') !== false);
    check('form SUBMITTED menampilkan field catatan revisi', strpos((string) $approverView, 'name="catatan_revisi"') !== false);
    check('form untuk approver lain tetap bisa menyimpan draft', strpos((string) $approverView, 'Simpan Draft') !== false);
}


// --- pengirim sendiri TIDAK boleh melihat tombol approval ---
$formDataSelf = $formData;
$formDataSelf['can_approve'] = false;
[$formSelfView] = renderView(APPPATH . 'Views/dashboard/finance_rekon_form.php', $formDataSelf);
check('form tanpa can_approve menyembunyikan tombol verify', strpos((string) $formSelfView, 'value="verify"') === false);

// --- form saat VERIFIED harus terkunci ---
$model->updateRow((int) $existing->id, [
    'status_proses' => 'verified', 'verified_by' => 34, 'verified_at' => date('Y-m-d H:i:s'),
]);
$formDataLocked = callProtected($controller, 'rekonFormData', [$info]);
check('rekonFormData locked saat verified', ($formDataLocked['locked'] ?? false) === true);
check('rekonFormData can_submit false saat verified', ($formDataLocked['can_submit'] ?? true) === false);

[$lockedView, $lockedErr] = renderView(APPPATH . 'Views/dashboard/finance_rekon_form.php', $formDataLocked);
check('form VERIFIED render tanpa error', $lockedErr === null, (string) $lockedErr);
check('form VERIFIED menampilkan kunci', strpos((string) $lockedView, 'tidak dapat diubah') !== false);
check('form VERIFIED tidak menampilkan tombol submit', strpos((string) $lockedView, 'Kirim ke Manager') === false);
check('form VERIFIED men-disable input', substr_count((string) $lockedView, ' disabled') >= 3, 'jml=' . substr_count((string) $lockedView, ' disabled'));

// --- lock ditegakkan di level logika controller ---
$locked = App\Services\Finance\RekonDailyCalculator::isLocked($model->getByUnitAndDate($unitId, $today));
check('isLocked true saat verified', $locked === true);

$db->transRollback();
$_SESSION = [];

echo "\nFAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
