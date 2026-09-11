<?php
/**
 * Smoke test render halaman KPI Digital Marketing (Marketing controller).
 *
 * Session palsu role Kepala Divisi (43) & Multimedia (44) → render dashboard,
 * leads, ads. Validasi penanda HTML. Tidak mengubah data.
 *
 * Usage: php74 app/Scripts/marketing_smoke.php
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

// Bootstrap request (meniru WebRequest).
try {
    $config = new \Config\App();
    $uri = new \CodeIgniter\HTTP\SiteURI($config, 'marketing', 'localhost', 'http');
    $request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
    \CodeIgniter\Config\Services::injectMock('request', $request);
} catch (\Throwable $e) {
    echo "  WARN  inject request: {$e->getMessage()}\n";
}

$session = \Config\Services::session();
$session->set([
    'ID_AKUN'    => 55,
    'ID_JABATAN' => 43,
    'ID_UNIT'    => 1,
    'NAMA_UNIT'  => 'ICLEAR Probolinggo',
    'NAMA'       => 'fathoni (kadiv-test)',
    'logged_in'  => true,
]);

try {
    $ctrl = new \App\Controllers\Marketing();
    $ctrl->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());

    $html = (string)$ctrl->index();
    ok('marketing dashboard render — judul Dashboard Digital Marketing', strpos($html, 'Dashboard Digital Marketing') !== false);
    ok('marketing dashboard render — tabel 7 KPI', strpos($html, 'Ringkasan KPI Digital Marketing') !== false);
    ok('marketing dashboard render — item Lead/Customer/Conversion', strpos($html, '>Lead<') !== false && strpos($html, '>Customer<') !== false && strpos($html, '>Conversion<') !== false);
    ok('marketing dashboard render — item CPL/Omzet/ROAS', strpos($html, 'Cost Per Lead') !== false && strpos($html, 'Omzet Marketing') !== false && strpos($html, 'ROI/ROAS') !== false);
    ok('marketing dashboard render — Pertumbuhan Channel (reuse)', strpos($html, 'Pertumbuhan Channel') !== false);
    ok('marketing dashboard render — link Lead & Biaya Iklan', strpos($html, 'marketing/leads') !== false && strpos($html, 'marketing/ads') !== false);

    $html = (string)$ctrl->leads();
    ok('leads render — judul Lead Marketing', strpos($html, 'Lead Marketing') !== false);
    ok('leads render — form tambah lead (nama/no_hp/source/ads)', strpos($html, 'name="nama"') !== false && strpos($html, 'name="no_hp"') !== false && strpos($html, 'name="ads_organic"') !== false);
    ok('leads render — input tanggal tambah lead', strpos($html, 'name="tanggal"') !== false);

    $html = (string)$ctrl->rekap();
    ok('rekap render — judul Rekap Marketing Harian', strpos($html, 'Rekap Marketing Harian') !== false);
    ok('rekap render — form tanggal & cabang', strpos($html, 'name="tanggal"') !== false && strpos($html, 'name="unit_id"') !== false);
    ok('rekap render — default platform WhatsApp/Instagram/TikTok', strpos($html, 'WhatsApp') !== false && strpos($html, 'Instagram') !== false && strpos($html, 'TikTok') !== false);
    ok('rekap render — kolom Non Iklan/Iklan/Prospek/Datang', strpos($html, 'Non Iklan') !== false && strpos($html, 'Prospek') !== false && strpos($html, 'Datang') !== false);
    ok('rekap render — simpan memakai POST rekap/simpan', strpos($html, 'marketing/rekap/simpan') !== false);
    ok('rekap render — daftar rekap bulanan tampil', strpos($html, 'Daftar Rekap Marketing') !== false && strpos($html, 'name="bulan"') !== false);

    $html = (string)$ctrl->ads();
    ok('ads render — judul Biaya Iklan', strpos($html, 'Biaya Iklan (Ads Cost)') !== false);
    ok('ads render — form tambah (channel/campaign/amount)', strpos($html, 'name="campaign"') !== false && strpos($html, 'name="amount"') !== false);

    // Role non-marketing (mis. 48 Talent) tidak bisa lihat.
    $session->set('ID_JABATAN', 48);
    $ctrl2 = new \App\Controllers\Marketing();
    $ctrl2->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $out = $ctrl2->index();
    ok('Role 48 di-redirect (tidak berhak akses marketing)', is_object($out) && get_class($out) === 'CodeIgniter\HTTP\RedirectResponse', get_class($out));
} catch (\Throwable $e) {
    echo "  CRASH " . get_class($e) . ": {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n";
    $fail++;
}

echo "\nSMOKE PASS: {$pass}   FAIL: {$fail}\n";
echo "------------------------------------------------------------\n";
exit($fail > 0 ? 1 : 0);