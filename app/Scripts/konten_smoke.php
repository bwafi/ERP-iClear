<?php
/**
 * Smoke test render halaman modul Konten (via request CLI, tanpa HTTP server).
 *
 * Mengisi session palsu (ID_AKUN/ID_JABATAN/ID_UNIT) sesuai role yang diuji
 * lalu memanggil metode controller; memvalidasi halaman ter-render tanpa
 * exception dan memuat penanda HTML penting. Tidak mengubah data produksi
 * selain membuat 1 content uji (dihapus kembali di akhir).
 *
 * Usage: php74 app/Scripts/konten_smoke.php
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

$fail = 0;
$pass = 0;
function ok($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  {$label}\n"; } else { $fail++; echo "  FAIL  {$label}  " . ($detail !== '' ? "→ {$detail}" : '') . "\n"; }
}

// Bootstrap request (meniru WebRequest) agar controller & session berfungsi.
try {
    $config = new \Config\App();
    $uri = new \CodeIgniter\HTTP\SiteURI($config, 'konten/dashboard', 'localhost', 'http');
    $request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
    \CodeIgniter\Config\Services::injectMock('request', $request);
} catch (\Throwable $e) {
    echo "  WARN  inject request: {$e->getMessage()}\n";
}

// Session DB driver.
$session = \Config\Services::session();
$session->set([
    'ID_AKUN'   => 63,
    'ID_JABATAN' => 1, // Admin root
    'ID_UNIT'   => 1,
    'NAMA_UNIT' => 'ICLEAR Probolinggo',
    'NAMA'      => 'Fahri (root-test)',
    'logged_in' => true,
]);

$db = \Config\Database::connect();
// Buang sisa uji, lalu buat 1 content uji.
$db->query("DELETE FROM contents WHERE judul LIKE '[SMOKE]%'");
$Content = new \App\Models\ModelContent();
$contentId = $Content->insert([
    'judul' => '[SMOKE] Konten Rendering',
    'deskripsi' => 'Uji render detail',
    'content_type_id' => (int)$db->table('content_types')->where('code', 'FEED')->get()->getRow()->id,
    'target_scope' => 'SELECTED',
    'deadline' => date('Y-m-d', strtotime('+7 days')),
    'status' => 'QC',
    'created_by' => 63,
]);
(new \App\Models\ModelContentUnit())->replaceForContent((int)$contentId, [1]);
(new \App\Models\ModelContentPerson())->replaceForContent((int)$contentId, [55], [63]);
$items = (new \App\Models\ModelBrandChecklistItem())->optionsActive();
(new \App\Models\ModelContentChecklist())->ensureItemsForContent((int)$contentId, array_map(fn($i) => $i->id, $items));
$pubId = (new \App\Models\ModelPublication())->insert(['content_id' => (int)$contentId, 'unit_id' => 1, 'platform_id' => (int)$db->table('platforms')->where('code', 'INSTAGRAM')->get()->getRow()->id, 'status' => 'PLANNED', 'created_by' => 63]);
(new \App\Models\ModelPublicationPerformance())->insert(['publication_id' => (int)$pubId, 'metric_id' => (int)$db->table('performance_metrics')->where('code', 'REACH')->get()->getRow()->id, 'period_month' => (int)date('n'), 'period_year' => (int)date('Y'), 'target' => 100, 'actual' => 120, 'achievement' => 120]);

try {
    $ctrl = new \App\Controllers\Konten();
    $ctrl->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());

    $html = (string)$ctrl->dashboard();
    ok('dashboard() render — judul Dashboard Digital Marketing', strpos($html, 'Dashboard Digital Marketing') !== false);
    ok('dashboard() render — breadcrumb Digital Marketing', strpos($html, 'Digital Marketing') !== false);
    ok('dashboard() render — ada kartu Total Content', strpos($html, 'Total Content') !== false, substr($html, 0, 80));
    ok('dashboard() render — ada Ringkasan KPI', strpos($html, 'Ringkasan KPI Creative') !== false);

    $html = (string)$ctrl->index();
    ok('index() render — ada tabel DataTables', strpos($html, 'kontenTable') !== false);
    ok('index() render — kolom Dibuat', strpos($html, '<th>Dibuat</th>') !== false);

    $html = (string)$ctrl->form(null);
    ok('form() tambah render — ada field judul', strpos($html, 'Judul Konten') !== false);
    ok('form() tambah render — pilihan Jenis Konten Regular/Iklan', strpos($html, 'Iklan (ADS)') !== false && strpos($html, 'name="jenis_konten"') !== false);
    ok('form() tambah render — picker Talent select2', strpos($html, 'id="talentSelect"') !== false && strpos($html, 'class="form-select select2"') !== false);
    ok('form() tambah render — picker Multimedia select2', strpos($html, 'id="creativeSelect"') !== false);
    $html = (string)$ctrl->form($contentId);
    ok('form() edit render — value judul terisi', strpos($html, '[SMOKE] Konten Rendering') !== false);

    $html = (string)$ctrl->detail($contentId);
    ok('detail() render — ada publikasi', strpos($html, 'PubFormTitle') !== false || strpos($html, 'pubFormTitle') !== false || strpos($html, 'Tambah Publikasi') !== false);
    ok('detail() render — QC action muncul (status QC)', strpos($html, 'Tindakan QC') !== false);
    ok('detail() render — brand checklist (single form)', strpos($html, 'checklistForm') !== false && strpos($html, 'Simpan Checklist') !== false);
    ok('detail() render — badge nama visible (bg-info-subtle/bg-primary-subtle)', strpos($html, 'bg-info-subtle text-info') !== false && strpos($html, 'bg-primary-subtle text-primary') !== false);
    ok('detail() render — input performa', strpos($html, 'Input Performa Publikasi') !== false);
    ok('detail() render — form performa (id perfForm)', strpos($html, 'id="perfForm"') !== false);
    ok('detail() render — aksi edit/hapus performa', strpos($html, 'btn-edit-perf') !== false && strpos($html, 'performance/delete') !== false);
    ok('detail() render — histori QC', strpos($html, 'Histori QC') !== false);

    // Role testing: kadiv (43) read-only –
    $session->set(['ID_JABATAN' => 43, 'ID_AKUN' => 55]);
    $ctrl2 = new \App\Controllers\Konten();
    $ctrl2->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $html = (string)$ctrl2->dashboard();
    ok('dashboard() role 43 ter-render (monitoring)', strpos($html, 'Ringkasan KPI Creative') !== false);
    $resp = $ctrl2->form($contentId); // harus redirect (write denied)
    ok('role 43 tidak bisa buka form (redirect)', $resp instanceof \CodeIgniter\HTTP\RedirectResponse, get_class($resp));

    // Role multimedia (44): dashboard KPI penuh divisi, CRUD tetap boleh.
    $session->set(['ID_JABATAN' => 44, 'ID_AKUN' => 63, 'ID_UNIT' => 1]);
    $ctrl3 = new \App\Controllers\Konten();
    $ctrl3->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $html = (string)$ctrl3->dashboard();
    ok('dashboard() role 44 ter-render (KPI berlaku utk multimedia)', strpos($html, 'Ringkasan KPI Creative') !== false && strpos($html, 'Divisi Multimedia') !== false);
    $formHtml = (string)$ctrl3->form(null);
    ok('role 44 dapat buka form tambah (operasional)', strpos($formHtml, 'Judul Konten') !== false);
} catch (\Throwable $e) {
    echo "  CRASH " . get_class($e) . ": {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n";
    $fail++;
}

// Bersihkan.
$Content->delete((int)$contentId);
if ((int)($pubId ?? 0) > 0) {
    try { $db->table('publication_performance')->where('publication_id', (int)$pubId)->delete(); $db->table('publications')->where('id', (int)$pubId)->delete(); } catch (\Throwable $e) {}
}
$db->query("DELETE FROM contents WHERE judul LIKE '[SMOKE]%'");

echo "\n============================================================\n";
echo "SMOKE PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);