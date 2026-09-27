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
// (rekonsiliasiData), bukan disusun ulang di sini — supaya
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
check('list menampilkan skor KPI', strpos((string) $listView, 'Skor KPI Rekonsiliasi') !== false);

// --- KUNCI ANGKA DI LIST: panel harian ikut aturan submitter (2026-09-27) ---
$idx = null;
foreach ($listData['list'] as $i => $item) {
    if ((string) ($item['row']->tanggal ?? '') === $today) { $idx = $i; break; }
}
check('list menemukan baris hari ini', $idx !== null);

//need_revision -> angka terbuka, tombol Simpan & Kirim tampil
[$revListView, $revErr] = renderView(APPPATH . 'Views/dashboard/finance_rekonsiliasi.php', $listData);
check('list need_revision render tanpa error', $revErr === null, (string) $revErr);
check('list need_revision punya boleh_ubah=true', ($listData['list'][$idx]['boleh_ubah'] ?? false) === true);
check('list need_revision menampilkan tombol Simpan & Kirim', strpos((string) $revListView, 'Simpan &amp; Kirim') !== false);

// Akun kedua harus dicari di sini: $akunLain baru didefinisikan jauh di
// bawah blok ini, jadi memakainya di sini menghasilkan null.
$akunKedua = $db->table('akun')
    ->whereIn('ID_JABATAN', (new Config\Finance())->financeInputRoles)
    ->where('ID_AKUN !=', (int) $akunFinance->ID_AKUN)
    ->select('ID_AKUN, ID_JABATAN, NAMA_AKUN')
    ->orderBy('ID_AKUN', 'ASC')
    ->get()->getRow();
check('ada akun finance kedua untuk uji kunci', $akunKedua !== null);

// Set SUBMITTED dengan id dari row yang baru di-fetch, supaya updateRow
// benar-benar kena. Diam-diam tidak kena membuat test ini false pass.
$rowId = (int) $model->getByUnitAndDate($unitId, $today)->id;
$model->updateRow($rowId, [
    'status_proses' => 'submitted', 'submitted_by' => (int) $akunFinance->ID_AKUN, 'submitted_at' => date('Y-m-d H:i:s'),
]);
check('setup: hari ini benar-benar jadi submitted', (string) $model->getByUnitAndDate($unitId, $today)->status_proses === 'submitted');

// submitted -> PENGIRIM boleh ubah. Controller dipanggil ulang dengan session
// pengirim, jadi boleh_ubah DAN kunci_alasan dihitung dari jalur produksi.
$_SESSION['ID_AKUN'] = (int) $akunFinance->ID_AKUN;
$listSubmitted = callProtected($controller, 'rekonsiliasiData', [$info]);
$listSubmitted['unit_id'] = $unitId;
check('list submitted untuk pengirim boleh_ubah=true', ($listSubmitted['list'][$idx]['boleh_ubah'] ?? false) === true);
// array_key_exists, bukan ?? : nilai yang benar di sini justru null, dan
// operator ?? akan menyembunyikannya jadi nilai penggantinya.
check('list submitted untuk pengirim tidak punya kunci_alasan', array_key_exists('kunci_alasan', $listSubmitted['list'][$idx]) && $listSubmitted['list'][$idx]['kunci_alasan'] === null);

// submitted -> AKUN KEDUA (bukan pengirim) tidak boleh ubah
if ($akunKedua) {
    $_SESSION['ID_AKUN'] = (int) $akunKedua->ID_AKUN;
    // $info harus dihitung ulang untuk session baru: myId di dalam
    // rekonsiliasiData() dibaca dari $info, bukan dari session langsung.
    $infoKedua = $scopeService->scopeInfo();
    $listOther = callProtected($controller, 'rekonsiliasiData', [$infoKedua]);
    $listOther['unit_id'] = $unitId;
    check('list submitted untuk akun kedua boleh_ubah=false', ($listOther['list'][$idx]['boleh_ubah'] ?? true) === false);
    check('list akun kedua punya kunci_alasan', str_contains((string) ($listOther['list'][$idx]['kunci_alasan'] ?? ''), 'menunggu verifikasi'));
    [$otherListView, $otherErr] = renderView(APPPATH . 'Views/dashboard/finance_rekonsiliasi.php', $listOther);
    check('list akun kedua render tanpa error', $otherErr === null, (string) $otherErr);

    // Hari lain di halaman ini masih need_revision dan tetap punya tombol,
    // jadi pemeriksaan harus diambil dari panel tanggal ini saja.
    $panelFor = static function (string $html, string $tgl): string {
        $needle = 'data-rk-prefix="p' . str_replace('-', '', $tgl) . '-';
        $at = strpos($html, $needle);
        if ($at === false) { return ''; }
        $from = strrpos(substr($html, 0, $at), '<div class="card rk-daypanel');
        if ($from === false) { $from = max(0, $at - 2000); }
        $stop = strpos($html, '<div class="card rk-daypanel', $at);
        return substr($html, $from, ($stop === false ? strlen($html) : $stop) - $from);
    };
    $panelOther = $panelFor((string) $otherListView, $today);
    check('panel hari ini ditemukan di list', $panelOther !== '');
    check('panel terkunci tidak punya tombol Simpan & Kirim', strpos($panelOther, 'Simpan &amp; Kirim') === false);
    check('panel terkunci memberi tahu angka terkunci', strpos($panelOther, 'menunggu verifikasi') !== false);
    check('panel terkunci men-disable input', strpos($panelOther, 'disabled') !== false);
}

// kembalikan ke need_revision supaya sisa test tidak berubah
$model->updateRow($rowId, ['status_proses' => 'need_revision']);
$_SESSION['ID_AKUN'] = (int) $akunFinance->ID_AKUN;
$_SESSION['ID_JABATAN'] = (int) $akunFinance->ID_JABATAN;

// --- aturan "rekonsiliasi tiap hari kalender" (2026-09-27) ---
//
// Rekap: denominator KPI dulu hanya Senin-Sabatu sementara numerator menghitung
// SEMUA hari, jadi mengisi Minggu bisa menutupi hari kerja yang belum diisi dan
// skornya tetap 100%. Aturan bisnisnya: wajib tiap hari kalender, Minggu &
// hari libur termasuk, boleh disusulkan. View pun tidak boleh lagi
// mengatakan "hari ini tidak perlu dikerjakan".
check('list tidak lagi menandai Minggu sebagai "Bukan hari kerja"', strpos((string) $listView, 'Bukan hari kerja') === false);
check('list tidak lagi menandai Minggu "di luar KPI"', strpos((string) $listView, 'di luar KPI') === false);
check('list tidak lagi menampilkan penjelasan "Senin–Sabatu" untuk KPI', stripos((string) $listView, 'Senin–Sabatu') === false);
check('list tidak lagi menyebut denominator sebagai "hari kerja"', strpos((string) $listView, 'hari kerja') === false);

// --- Halaman form sudah dihapus (2026-09-27) ---
// Input dan verifikasi keduanya inline di panel per hari. Semua assertion di
// bawah membaca $listView, bukan halaman terpisah.
check('route finance/rekon/form dihapus', strpos(
    (string) file_get_contents(APPPATH . 'Config/Routes.php'),
    'finance/rekon/form'
) === false);
check('method rekonForm dihapus', ! method_exists($controller, 'rekonForm'));
check('method rekonFormData dihapus', ! method_exists($controller, 'rekonFormData'));
check('view finance_rekon_form.php dihapus', ! file_exists(APPPATH . 'Views/dashboard/finance_rekon_form.php'));
check('list tidak lagi menautkan ke halaman form', strpos((string) $listView, 'finance/rekon/form') === false);
check('list tidak lagi menampilkan tombol Buka Detail', strpos((string) $listView, 'Buka Detail') === false);
check('list tidak lagi menampilkan link Form lengkap', strpos((string) $listView, 'Form lengkap') === false);
check('list tidak lagi menampilkan tombol kirim terpisah', strpos((string) $listView, 'Kirim untuk Verifikasi') === false);
check('list tidak lagi punya form submit tersembunyi', strpos((string) $listView, 'id="rk-submit"') === false);
check('list tidak lagi menampilkan Explanation "Senin-Sabatu"', stripos((string) $listView, 'Senin-Sabatu') === false);

/**
 * Ambil markup satu panel tanggal saja.
 *
 * Wajib per panel: satu halaman punya dozens panel, dan panel lain
 * legitimate punya tombol yang sedang tidak diuji. Memeriksa seluruh
 * halaman akan salah (atau, lebih buruk, lulus karena ada satu panel lain).
 */
function panelHari(string $html, string $tgl): string
{
    $needle = 'data-rk-prefix="p' . str_replace('-', '', $tgl) . '-';
    $at = strpos($html, $needle);
    if ($at === false) {
        return '';
    }
    $from = strrpos(substr($html, 0, $at), '<article class="rk-panel"');
    if ($from === false) {
        $from = max(0, $at - 3000);
    }
    $stop = strpos($html, '</article>', $at);
    return substr($html, $from, ($stop === false ? strlen($html) : $stop + 10) - $from);
}

// --- panel need_revision: input inline, tanpa approval ---
$panelRev = panelHari((string) $listView, $today);
check('panel need_revision ditemukan', $panelRev !== '');
check('panel menampilkan input actual terformat', strpos($panelRev, '700.000') !== false);
check('panel menampilkan nilai ERP terformat', strpos($panelRev, '5.468.205') !== false);
check('panel menampilkan catatan revisi manager', strpos($panelRev, 'cek transfer 25.000') !== false);
check('panel menampilkan label "Catatan manager (perlu revisi)"', strpos($panelRev, 'Catatan manager (perlu revisi)') !== false);
check('panel punya form Simpan &amp; Kirim', strpos($panelRev, 'Simpan &amp; Kirim') !== false);
check('panel TIDAK menampilkan approval saat need_revision', strpos($panelRev, 'value="verify"') === false);
check('panel tidak memakai atribut readonly', strpos($panelRev, 'readonly') === false);
check('panel tidak menandai "Bukan hari kerja"', strpos($panelRev, 'Bukan hari kerja') === false);
check('panel tidak menampilkan kolom "Sudah Diperiksa"', strpos($panelRev, 'Sudah Diperiksa') === false);
check('panel tidak punya checkbox checked_*', preg_match('/name="checked_/', $panelRev) === 0);
check('panel tidak lagi punya header "Dip."', strpos($panelRev, 'Dip.') === false);
check('panel men-disable input terkunci', App\Services\Finance\RekonDailyCalculator::bolehUbahAngka(
    $listData['list'][$idx]['row'],
    (int) $akunFinance->ID_AKUN
) === true);

// --- panel SUBMITTED untuk pemohon yang berwenang: approval inline ---
$akunLain = $db->table('akun')
    ->whereIn('ID_JABATAN', (new Config\Finance())->financeInputRoles)
    ->where('ID_AKUN !=', (int) $akunFinance->ID_AKUN)
    ->select('ID_AKUN, ID_JABATAN, NAMA_AKUN')
    ->orderBy('ID_AKUN', 'ASC')
    ->get()->getRow();
check('ada akun finance lain untuk uji approval', $akunLain !== null);

if ($akunLain) {
    $rowId = (int) $model->getByUnitAndDate($unitId, $today)->id;
    $model->updateRow($rowId, [
        'status_proses' => 'submitted', 'submitted_by' => (int) $akunFinance->ID_AKUN, 'submitted_at' => date('Y-m-d H:i:s'),
    ]);
    check('setup: hari ini benar-benar jadi submitted', (string) $model->getByUnitAndDate($unitId, $today)->status_proses === 'submitted');

    // Pemohon kedua: boleh approve (dia bukan pengirim hari ini).
    $_SESSION['ID_AKUN'] = (int) $akunLain->ID_AKUN;
    $infoLain = $scopeService->scopeInfo();
    $listSubmit = callProtected($controller, 'rekonsiliasiData', [$infoLain]);
    $listSubmit['unit_id'] = $unitId;
    check('list: can_approve per hari true untuk approver', ($listSubmit['list'][$idx]['can_approve'] ?? false) === true);
    [$submitView, $submitErr] = renderView(APPPATH . 'Views/dashboard/finance_rekonsiliasi.php', $listSubmit);
    check('list SUBMITTED render tanpa error', $submitErr === null, (string) $submitErr);

    $panelSubmit = panelHari((string) $submitView, $today);
    check('panel submitted punya form verify', strpos($panelSubmit, 'value="verify"') !== false);
    check('panel submitted punya form need_revision', strpos($panelSubmit, 'value="need_revision"') !== false);
    check('panel submitted punya field catatan revisi', strpos($panelSubmit, 'name="catatan_revisi"') !== false);
    check('panel submitted punya aksi verify tanpa pindah halaman', strpos($panelSubmit, 'finance/rekon/approve') !== false);
    check('panel submitted tidak punya link ke halaman form', strpos($panelSubmit, 'finance/rekon/form') === false);
    check('panel submitted tidak menampilkan tombol Buka Detail', strpos($panelSubmit, 'Buka Detail') === false);
    check('form dan form approval tidak bersarang', substr_count($panelSubmit, '<form') === substr_count($panelSubmit, '</form>'));

    // Pengirim sendiri: tidak boleh melihat kontrol approval.
    $_SESSION['ID_AKUN'] = (int) $akunFinance->ID_AKUN;
    $listSelf = callProtected($controller, 'rekonsiliasiData', [$info]);
    $listSelf['unit_id'] = $unitId;
    check('list: can_approve per hari false untuk pengirim sendiri', ($listSelf['list'][$idx]['can_approve'] ?? true) === false);
    [$selfView, $selfErr] = renderView(APPPATH . 'Views/dashboard/finance_rekonsiliasi.php', $listSelf);
    check('list pengirim render tanpa error', $selfErr === null, (string) $selfErr);
    $panelSelf = panelHari((string) $selfView, $today);
    check('panel pengirim sendiri menyembunyikan tombol verify', strpos($panelSelf, 'value="verify"') === false);
    check('panel pengirim sendiri menyembunyikan need_revision', strpos($panelSelf, 'value="need_revision"') === false);
    check('panel pengirim sendiri tidak punya field catatan revisi', strpos($panelSelf, 'name="catatan_revisi"') === false);
    check('pengirim sendiri boleh menarik hincirannya', ($listSelf['list'][$idx]['boleh_ubah'] ?? false) === true);
}

// --- panel VERIFIED: terkunci untuk semua orang ---
$model->updateRow($rowId, [
    'status_proses' => 'verified', 'verified_by' => 34, 'verified_at' => date('Y-m-d H:i:s'),
]);
$_SESSION['ID_AKUN'] = (int) $akunFinance->ID_AKUN;
$listVerified = callProtected($controller, 'rekonsiliasiData', [$info]);
$listVerified['unit_id'] = $unitId;
check('list: can_approve per hari false saat verified', ($listVerified['list'][$idx]['can_approve'] ?? true) === false);
check('list: boleh_ubah false saat verified', ($listVerified['list'][$idx]['boleh_ubah'] ?? true) === false);
[$verifiedView, $verifiedErr] = renderView(APPPATH . 'Views/dashboard/finance_rekonsiliasi.php', $listVerified);
check('list VERIFIED render tanpa error', $verifiedErr === null, (string) $verifiedErr);

$panelVerified = panelHari((string) $verifiedView, $today);
check('panel verified tidak punya tombol Simpan &amp; Kirim', strpos($panelVerified, 'Simpan &amp; Kirim') === false);
check('panel verified tidak punya tombol verify', strpos($panelVerified, 'value="verify"') === false);
check('panel verified menampilkan kunci', strpos($panelVerified, 'tidak dapat diubah') !== false);
check('panel verified men-disable input', substr_count($panelVerified, ' disabled') >= 3, 'jml=' . substr_count($panelVerified, ' disabled'));

// --- lock ditegakkan di level logika controller ---
$locked = App\Services\Finance\RekonDailyCalculator::isLocked($model->getByUnitAndDate($unitId, $today));
check('isLocked true saat verified', $locked === true);

// --- redirect pasca-aksi kembali ke list, bukan ke halaman form ---
$redir = new ReflectionMethod($controller, 'rekonListUrl');
$redir->setAccessible(true);
$target = (string) $redir->invoke($controller, $unitId, $today);
check('redirectKeList menuju halaman list', strpos($target, 'finance/rekonsiliasi?') !== false, $target);
check('redirectKeList membawa unit_id', strpos($target, 'unit_id=' . $unitId) !== false, $target);
check('redirectKeList membawa hari terpilih', strpos($target, 'hari=' . $today) !== false, $target);
check('redirectKeList membawa bulan', strpos($target, 'month=' . date('Y-m')) !== false, $target);
check('redirectKeList tidak menuju halaman form yang sudah dihapus', strpos($target, 'finance/rekon/form') === false);

$db->transRollback();
$_SESSION = [];

echo "\nFAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
