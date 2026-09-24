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
$Campaign = new \App\Models\ModelContentCampaign();
$smokeCampId = $Campaign->insert([
    'nama' => '[SMOKE] Campaign Rendering',
    'period_month' => (int)date('m'), 'period_year' => (int)date('Y'),
    'target_jumlah_konten' => 1, 'target_deadline' => date('Y-m-d', strtotime('+14 days')),
    'status' => 'active', 'created_by' => 63,
]);
$db->table('contents')->where('id', $contentId)->update(['campaign_id' => (int)$smokeCampId]);

try {
    $ctrl = new \App\Controllers\Konten();
    $ctrl->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());

    $html = (string)$ctrl->dashboard();
    ok('dashboard() render — judul Dashboard Multimedia', strpos($html, 'Dashboard Multimedia') !== false);
    ok('dashboard() render — breadcrumb Digital Marketing', strpos($html, 'Digital Marketing') !== false);
    ok('dashboard() render — ada kartu Total Content', strpos($html, 'Total Content') !== false, substr($html, 0, 80));
    ok('dashboard() render — ada Ringkasan KPI Multimedia (Owner)', strpos($html, 'Ringkasan KPI Multimedia (Owner)') !== false);
    ok('dashboard() render — section Pertumbuhan Channel', strpos($html, 'Pertumbuhan Channel') !== false);
    ok('dashboard() render — link Input Performa Channel', strpos($html, 'konten/channel') !== false);
    ok('dashboard() render — KPI owner memuat 6 KPI (bobot 100)', strpos($html, 'Ketepatan Deadline') !== false && strpos($html, 'Kualitas Output') !== false && strpos($html, 'Kesesuaian Brief') !== false && strpos($html, 'Produktivitas') !== false && strpos($html, 'Support Campaign') !== false && strpos($html, 'Improvement') !== false);
    ok('dashboard() render — tanpa Performa Konten', strpos($html, 'Performa Konten') === false);

    $html = (string)$ctrl->channel();
    ok('channel() render — judul Performa Channel', strpos($html, 'Performa Channel') !== false);
    ok('channel() render — form Tambah Performa (Channel/Metric/Actual)', strpos($html, 'name="channel_id"') !== false && strpos($html, 'name="metric_id"') !== false && strpos($html, 'name="actual"') !== false);
    ok('channel() render — dropdown metric per channel', strpos($html, 'CHANNEL_METRICS') !== false);

    $html = (string)$ctrl->index();
    ok('index() render — ada tabel DataTables', strpos($html, 'kontenTable') !== false);
    ok('index() render — kolom Dibuat', strpos($html, '<th>Dibuat</th>') !== false);
    ok('index() render — kolom Campaign', strpos($html, '<th>Campaign</th>') !== false);
    ok('index() render — kolom Kesesuaian Brief', strpos($html, '<th>Kesesuaian Brief</th>') !== false);

    // dt() — JSON server-side DataTables harus memuat campaign & kesusaian brief
    // (bug: controller dt() sebelumnya tidak mengirim kolom ini → tampil "-").
    $_GET = ['draw' => 1, 'start' => 0, 'length' => 100, 'order' => [['column' => 3, 'dir' => 'desc']]];
    $json = $ctrl->dt()->getBody();
    $dtData = json_decode($json, true);
    $hit = null;
    foreach (($dtData['data'] ?? []) as $row) {
        if ((int)$row['id'] === (int)$contentId) { $hit = $row; break; }
    }
    ok('dt() — baris membawa campaign_name (bukan "-")', $hit && isset($hit['campaign_name']) && $hit['campaign_name'] === '[SMOKE] Campaign Rendering', json_encode($hit));
    ok('dt() — baris membawa field brief_sesuai', $hit && array_key_exists('brief_sesuai', $hit), json_encode($hit));

    $html = (string)$ctrl->form(null);
    ok('form() tambah render — ada field judul', strpos($html, 'Judul Konten') !== false);
    ok('form() tambah render — pilihan Jenis Konten Regular/Iklan', strpos($html, 'Iklan (ADS)') !== false && strpos($html, 'name="jenis_konten"') !== false);
    ok('form() tambah render — picker Talent select2', strpos($html, 'id="talentSelect"') !== false && strpos($html, 'class="form-select select2"') !== false);
    ok('form() tambah render — picker Multimedia select2', strpos($html, 'id="creativeSelect"') !== false);
    ok('form() tambah render — tanpa Isi Brief & Requirement Brief', strpos($html, 'Isi Brief') === false && strpos($html, 'Requirement Brief') === false && strpos($html, 'name="isi_brief"') === false);
    $html = (string)$ctrl->form($contentId);
    ok('form() edit render — value judul terisi', strpos($html, '[SMOKE] Konten Rendering') !== false);

    $html = (string)$ctrl->detail($contentId);
    ok('detail() render — tanpa section Brand Checklist', strpos($html, 'Brand Checklist') === false);
    ok('detail() render — tanpa section Publikasi', strpos($html, 'Publikasi') === false && strpos($html, 'pubForm') === false);
    ok('detail() render — kartu Kesesuaian Brief ada', strpos($html, 'Kesesuaian Brief') !== false);
    ok('detail() render — status awal Belum dinilai', strpos($html, 'Belum dinilai') !== false);
    ok('detail() render — form penilaian brief (kadiv/admin) tampil', strpos($html, 'name="sesuai"') !== false && strpos($html, 'konten/brief/verdict') !== false);
    ok('detail() render — QC action muncul (status QC)', strpos($html, 'Tindakan QC') !== false);
    ok('detail() render — banner Sedang QC (menunggu approve/revisi kadiv)', strpos($html, 'Sedang QC') !== false && strpos($html, 'menunggu keputusan') !== false);
    ok('detail() render — badge nama visible (bg-info-subtle/bg-primary-subtle)', strpos($html, 'bg-info-subtle text-info') !== false && strpos($html, 'bg-primary-subtle text-primary') !== false);
    ok('detail() render — histori QC', strpos($html, 'Histori QC') !== false);
    ok('detail() render — tanpa Input Performa Publikasi', strpos($html, 'Input Performa Publikasi') === false && strpos($html, 'perfForm') === false);

    // Role testing: kadiv (43) read-only –
    $session->set(['ID_JABATAN' => 43, 'ID_AKUN' => 55]);
    $ctrl2 = new \App\Controllers\Konten();
    $ctrl2->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $html = (string)$ctrl2->dashboard();
    ok('dashboard() role 43 ter-render (monitoring)', strpos($html, 'Ringkasan KPI Multimedia (Owner)') !== false);
    $resp = $ctrl2->form($contentId); // harus redirect (write denied)
    ok('role 43 tidak bisa buka form (redirect)', $resp instanceof \CodeIgniter\HTTP\RedirectResponse, get_class($resp));

    // Role multimedia (44): dashboard KPI penuh divisi, CRUD tetap boleh.
    $session->set(['ID_JABATAN' => 44, 'ID_AKUN' => 63, 'ID_UNIT' => 1]);
    $ctrl3 = new \App\Controllers\Konten();
    $ctrl3->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $html = (string)$ctrl3->dashboard();
    ok('dashboard() role 44 ter-render (KPI berlaku utk multimedia)', strpos($html, 'Ringkasan KPI Multimedia (Owner)') !== false && strpos($html, 'Divisi Multimedia (semua cabang)') !== false);
    $formHtml = (string)$ctrl3->form(null);
    ok('role 44 dapat buka form tambah (operasional)', strpos($formHtml, 'Judul Konten') !== false);
    $respEdit = $ctrl3->form($contentId);
    ok('role 44 — form EDIT konten existing DITOLAK (redirect)', $respEdit instanceof \CodeIgniter\HTTP\RedirectResponse, get_class($respEdit));
    $respDel = $ctrl3->hapus($contentId);
    ok('role 44 — HAPUS konten DITOLAK (redirect)', $respDel instanceof \CodeIgniter\HTTP\RedirectResponse, get_class($respDel));
    ok('role 44 — konten uji masih ada (tidak terhapus)', $Content->find($contentId) !== null);
    $detail44 = (string)$ctrl3->detail($contentId);
    ok('detail() role 44 — kartu brief ada, form penilaian TIDAK tampil', strpos($detail44, 'Kesesuaian Brief') !== false && strpos($detail44, 'name="sesuai"') === false);
    ok('detail() role 44 — tanpa aksi QC (bukan approver)', strpos($detail44, 'Tindakan QC') === false);
    ok('detail() role 44 — tanpa tombol status APPROVED/REVISION (QC via form saja)', strpos($detail44, 'value="APPROVED"') === false && strpos($detail44, 'value="REVISION"') === false);
    ok('detail() role 44 — tanpa link Edit (hanya detail + ubah status)', strpos($detail44, 'konten/edit/' . $contentId) === false);

    // Role manager 34 (jabatan Manager): boleh QC approval & menilai brief.
    $session->set(['ID_JABATAN' => 34, 'ID_AKUN' => 55, 'ID_UNIT' => 1]);
    $ctrl4 = new \App\Controllers\Konten();
    $ctrl4->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $detail34 = (string)$ctrl4->detail($contentId);
    ok('role 34 (Manager) — aksi QC tampil', strpos($detail34, 'Tindakan QC') !== false);
    ok('role 34 (Manager) — form penilaian brief tampil', strpos($detail34, 'konten/brief/verdict') !== false);

    // Role 2 (Direktur): lihat boleh, QC/brief TIDAK (hanya root/manager/kadiv).
    $session->set(['ID_JABATAN' => 2, 'ID_AKUN' => 55, 'ID_UNIT' => 1]);
    $ctrl5 = new \App\Controllers\Konten();
    $ctrl5->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $detail2 = (string)$ctrl5->detail($contentId);
    ok('role 2 (Direktur) — aksi QC TIDAK tampil', strpos($detail2, 'Tindakan QC') === false);
    ok('role 2 (Direktur) — form penilaian brief TIDAK tampil', strpos($detail2, 'konten/brief/verdict') === false);
} catch (\Throwable $e) {
    echo "  CRASH " . get_class($e) . ": {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n";
    $fail++;
}

// Bersihkan.
$Content->delete((int)$contentId);
if (!empty($smokeCampId)) {
    $Campaign->delete((int)$smokeCampId);
}
$db->query("DELETE FROM contents WHERE judul LIKE '[SMOKE]%'");

echo "\n============================================================\n";
echo "SMOKE PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);