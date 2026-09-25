<?php
/**
 * Smoke test render halaman KPI Digital Marketing (Marketing controller).
 *
 * Session palsu role Kepala Divisi (43) → render dashboard, rekap, leads,
 * ads_performa, kampanye, laporan. Memvalidasi halaman ter-render tanpa
 * exception + penanda shell desain dm-surface (page-header/kicker/filter).
 * Tidak mengubah data produksi.
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

    // ── Dashboard ────────────────────────────────────────────────
    $html = (string)$ctrl->index();
    ok('dashboard render — wrapper dm-surface aktif', strpos($html, 'dm-surface') !== false);
    ok('dashboard render — judul Dashboard Digital Marketing', strpos($html, 'Dashboard Digital Marketing') !== false);
    ok('dashboard render — page-header + kicker', strpos($html, 'dm-page-header') !== false && strpos($html, 'dm-page-kicker') !== false);
    ok('dashboard render — filter-card', strpos($html, 'dm-filter-card') !== false);
    ok('dashboard render — metric card', strpos($html, 'dm-metric-card') !== false);
    ok('dashboard render — tabel 7 KPI (Ringkasan)', strpos($html, 'Ringkasan KPI Digital Marketing') !== false);
    ok('dashboard render — link Lead & Campaign & Ads', strpos($html, 'marketing/leads') !== false && strpos($html, 'marketing/campaign') !== false && strpos($html, 'marketing/ads_performa') !== false);

    // ── Rekap Harian ─────────────────────────────────────────────
    $html = (string)$ctrl->rekap();
    ok('rekap render — page-header + filter-card', strpos($html, 'dm-page-header') !== false && strpos($html, 'dm-filter-card') !== false);
    ok('rekap render — judul Rekap Marketing Harian', strpos($html, 'Rekap Marketing Harian') !== false);
    ok('rekap render — form tanggal & cabang', strpos($html, 'name="tanggal"') !== false && strpos($html, 'name="unit_id"') !== false);
    ok('rekap render — kolom Non Iklan/Iklan/Prospek/Datang', strpos($html, 'Non Iklan') !== false && strpos($html, 'Prospek') !== false && strpos($html, 'Datang') !== false);
    ok('rekap render — simpan memakai POST rekap/simpan', strpos($html, 'marketing/rekap/simpan') !== false);
    ok('rekap render — daftar rekap bulanan tampil', strpos($html, 'Daftar Rekap Marketing') !== false);

    // ── Detail Prospek (leads) ───────────────────────────────────
    $html = (string)$ctrl->leads();
    ok('leads render — page-header + filter-bar', strpos($html, 'dm-page-header') !== false && strpos($html, 'dm-filter-bar') !== false);
    ok('leads render — judul Detail Prospek', strpos($html, 'Detail Prospek') !== false);
    ok('leads render — tabel Data Prospek', strpos($html, 'Data Prospek') !== false);
    ok('leads render — tombol Tambah Prospek (role 43)', strpos($html, 'Tambah Prospek') !== false);

    // ── Performa Ads ─────────────────────────────────────────────
    $html = (string)$ctrl->ads_performa();
    ok('ads_performa render — page-header + filter-card', strpos($html, 'dm-page-header') !== false && strpos($html, 'dm-filter-card') !== false);
    ok('ads_performa render — judul Performa Ads (Iklan)', strpos($html, 'Performa Ads (Iklan)') !== false);
    ok('ads_performa render — tabel Data Performa Ads', strpos($html, 'Data Performa Ads') !== false);
    ok('ads_performa render — tombol Lihat Campaign', strpos($html, 'Lihat Campaign') !== false);
    ok('ads_performa render — simpan memakai POST ads_performa/simpan', strpos($html, 'marketing/ads_performa/simpan') !== false);

    // ── Campaign ─────────────────────────────────────────────────
    $html = (string)$ctrl->campaign();
    ok('campaign render — page-header + filter-card', strpos($html, 'dm-page-header') !== false && strpos($html, 'dm-filter-card') !== false);
    ok('campaign render — judul Campaign Digital Marketing', strpos($html, 'Campaign Digital Marketing') !== false);
    ok('campaign render — daftar campaign + simpan POST', strpos($html, 'Daftar Campaign Digital Marketing') !== false && strpos($html, 'marketing/campaign/simpan') !== false);

    // ── Laporan ──────────────────────────────────────────────────
    $html = (string)$ctrl->laporan();
    ok('laporan render — page-header + filter-card', strpos($html, 'dm-page-header') !== false && strpos($html, 'dm-filter-card') !== false);
    ok('laporan render — judul Laporan Digital Marketing', strpos($html, 'Laporan Digital Marketing') !== false);
    $laporanAdaData = strpos($html, 'Performa Harian') !== false && strpos($html, 'Insight &amp; Evaluasi') !== false;
    $laporanEmpty = strpos($html, 'Belum ada data Ads') !== false;
    ok('laporan render — isi (performa harian & insight / empty state)', $laporanAdaData || $laporanEmpty, $laporanAdaData ? 'ada-data' : 'empty');

    // ── Role 48 (Talent) tidak berhak ─────────────────────────────
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