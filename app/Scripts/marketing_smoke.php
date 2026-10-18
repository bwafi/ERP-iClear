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

/**
 * Kirim POST ke method controller.
 *
 * IncomingRequest dibuat BARU tiap panggilan: kalau memakai request bersama,
 * data POST panggilan pertama ikut ter-cache untuk panggilan berikutnya.
 */
function campaignKirim($ctrl, array $post)
{
    global $reqProp;

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $post;
    $_GET  = [];
    $reqProp->setValue($ctrl, \Config\Services::request(null, false));

    return (new ReflectionMethod($ctrl, 'campaign_simpan'))->invoke($ctrl);
}

/** Kirim POST ke aksi tarik Meta. Sama seperti campaignKirim, tapi bisa
 *  memakai metode lain untuk menguji gerbangnya. */
function tarikKirim($ctrl, array $post, string $metode = 'POST')
{
    global $reqProp;

    $_SERVER['REQUEST_METHOD'] = $metode;
    $_POST = $post;
    $_GET  = [];
    $reqProp->setValue($ctrl, \Config\Services::request(null, false));

    return (new ReflectionMethod($ctrl, 'ads_performa_tarik'))->invoke($ctrl);
}

/** Potongan HTML satu baris tabel yang memuat teks tertentu. */
function barisTabel(string $html, string $teks): string
{
    if (!preg_match('#<tr\b(?:(?!</tr>).)*?' . preg_quote($teks, '#') . '(?:(?!</tr>).)*?</tr>#s', $html, $m)) {
        return '';
    }

    return $m[0];
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

    $reqProp = new ReflectionProperty(\CodeIgniter\Controller::class, 'request');
    $reqProp->setAccessible(true);

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
    ok('campaign render — page-header', strpos($html, 'dm-page-header') !== false);
    ok('campaign render — judul Campaign Digital Marketing', strpos($html, 'Campaign Digital Marketing') !== false);
    ok('campaign render — daftar campaign + simpan POST', strpos($html, 'Daftar Campaign Digital Marketing') !== false && strpos($html, 'marketing/campaign/simpan') !== false);
    ok('campaign render — form koreksi unit', strpos($html, 'name="unit_id"') !== false);
    ok('campaign render — tanpa pembuatan manual', strpos($html, 'cp_meta_pick') === false);
    ok('campaign render — tanpa endpoint opsi Meta', strpos($html, 'campaign/meta_options') === false);

    // Tulis-menulis di bawah dibungkus transaksi → data produksi tidak berubah.
    $db = \Config\Database::connect();
    $db->transBegin();

    $units  = $db->table('unit')->where('jenis !=', 'Kantor')->orderBy('idunit', 'ASC')->limit(2)->get()->getResultArray();
    $unitA  = (int) $units[0]['idunit'];
    $unitB  = (int) $units[1]['idunit'];
    $bulan  = (int) date('n');
    $tahun  = (int) date('Y');

    $db->table('marketing_campaigns')->insert([
        'nama' => '[SMOKE] campaign unit', 'status' => 'draft',
        'period_month' => $bulan, 'period_year' => $tahun, 'unit_id' => $unitA,
    ]);
    $campId = (int) $db->insertID();
    $db->table('marketing_ads_performance')->insert([
        'period_month' => $bulan, 'period_year' => $tahun, 'tanggal' => date('Y-m-d'),
        'campaign_id' => $campId, 'unit_id' => $unitA, 'amount' => 1, 'sumber' => 'manual',
    ]);

    campaignKirim($ctrl, ['id' => $campId, 'unit_id' => $unitB]);
    $row = $db->table('marketing_campaigns')->where('id', $campId)->get()->getRow();
    ok('koreksi unit tersimpan', $row !== null && (int) $row->unit_id === $unitB, (string) ($row->unit_id ?? '-'));
    ok('sumber unit jadi manual', ($row->unit_sumber ?? '') === 'manual', (string) ($row->unit_sumber ?? '-'));
    $adsUnit = (int) $db->table('marketing_ads_performance')->where('campaign_id', $campId)->get()->getRow()->unit_id;
    ok('baris iklan ikut pindah unit', $adsUnit === $unitB, (string) $adsUnit);

    campaignKirim($ctrl, ['id' => $campId, 'unit_id' => 99999999]);
    $row = $db->table('marketing_campaigns')->where('id', $campId)->get()->getRow();
    ok('unit tak dikenal → NULL', $row->unit_id === null, var_export($row->unit_id ?? 'tidak-ada', true));

    $db->transRollback();
    $_POST = [];
    ok('data uji campaign dibersihkan', (int) $db->table('marketing_campaigns')->like('nama', '[SMOKE]')->countAllResults() === 0);

    // ── Performa Ads: tombol tarik, kolom turunan, panel limit ────
    $_POST = [];
    $_GET  = [];
    $reqProp->setValue($ctrl, \Config\Services::request(null, false));

    $db->transBegin();

    $hari = date('Y-m-d');
    $db->table('marketing_campaigns')->insert([
        'nama' => '[SMOKE] ads nol', 'status' => 'draft',
        'period_month' => $bulan, 'period_year' => $tahun,
    ]);
    $campNol = (int) $db->insertID();
    $db->table('marketing_campaigns')->insert([
        'nama' => '[SMOKE] ads normal', 'status' => 'draft',
        'period_month' => $bulan, 'period_year' => $tahun,
    ]);
    $campAda = (int) $db->insertID();

    // Baris tanpa pembagi: impression/reach/klik/hasil = 0 → kolom turunan '-'.
    $db->table('marketing_ads_performance')->insert([
        'period_month' => $bulan, 'period_year' => $tahun, 'tanggal' => $hari,
        'campaign_id' => $campNol, 'amount' => 100000, 'ppn' => 5, 'sumber' => 'manual',
        'reach' => 0, 'impression' => 0, 'klik' => 0, 'hasil' => 0,
    ]);
    // Baris dengan angka jelas → kolom turunan bisa diperiksa nilainya.
    $db->table('marketing_ads_performance')->insert([
        'period_month' => $bulan, 'period_year' => $tahun, 'tanggal' => $hari,
        'campaign_id' => $campAda, 'amount' => 200000, 'ppn' => 5, 'sumber' => 'meta',
        'ditarik_pada' => $hari . ' 03:00:00',
        'reach' => 5000, 'impression' => 10000, 'klik' => 500, 'hasil' => 25,
    ]);
    $db->table('meta_pull_log')->insert([
        'tanggal_mulai' => date('Y-m-01'), 'tanggal_sampai' => $hari,
        'jumlah_kampanye' => 6, 'jumlah_baris' => 10, 'baru' => 4, 'diperbarui' => 6, 'dilewati' => 0,
        'call_count' => 3, 'total_time' => 30, 'total_cputime' => 6,
        'app_id_util_pct' => 0.01, 'acc_id_util_pct' => 0.02,
        'tier' => 'development_access', 'status' => 'ok',
    ]);
    $logUji = (int) $db->insertID();

    $html = (string) $ctrl->ads_performa();

    ok('ads_performa render — tombol Tarik dari Meta', strpos($html, 'Tarik dari Meta') !== false);
    ok('ads_performa render — tombol menuju POST ads_performa/tarik', strpos($html, 'marketing/ads_performa/tarik') !== false);

    foreach (['Frek', 'CTR', 'CPC', 'CPM', 'CPR Platform'] as $kolom) {
        ok("ads_performa render — kolom {$kolom}", strpos($html, '>' . $kolom . '<') !== false);
    }

    $barisNol = barisTabel($html, '[SMOKE] ads nol');
    ok('baris ter-render (pencarian baris bekerja)', $barisNol !== '');
    ok(
        'pembagi nol → kolom turunan "-" (bukan error/NaN)',
        substr_count($barisNol, '>-</td>') >= 5 && substr_count($barisNol, 'Rp ') === 2,
        'sel-minus=' . substr_count($barisNol, '>-</td>') . ' rp=' . substr_count($barisNol, 'Rp ')
    );
    ok(
        'tidak ada error pembagian di halaman',
        stripos($html, 'division by zero') === false && strpos($html, 'NAN') === false && strpos($html, 'Warning') === false
    );

    $barisAda = barisTabel($html, '[SMOKE] ads normal');
    ok('Frek = impression ÷ reach (2,00)', strpos($barisAda, '2,00') !== false, substr($barisAda, 0, 120));
    ok('CTR = klik ÷ impression (5,00%)', strpos($barisAda, '5,00%') !== false);
    ok('CPC = amount ÷ klik (Rp 400)', strpos($barisAda, 'Rp 400<') !== false);
    ok('CPM = amount ÷ impression × 1000 (Rp 20.000)', strpos($barisAda, 'Rp 20.000<') !== false);
    ok('CPR Platform = amount ÷ hasil (Rp 8.000)', strpos($barisAda, 'Rp 8.000<') !== false);

    ok('badge sumber Meta + tooltip waktu tarik', stripos($barisAda, '>Meta<') !== false && stripos($barisAda, 'ditarik') !== false);
    ok('badge sumber Manual pada baris manual', stripos($barisNol, '>Manual<') !== false);

    ok('panel info limit tampil', strpos($html, 'Tarikan Meta terakhir') !== false);
    ok('panel info limit memuat tier & pemakaian kuota', strpos($html, 'development_access') !== false && strpos($html, '0,02') !== false);

    // ── Gerbang aksi "Tarik dari Meta" (tanpa panggilan jaringan) ──
    // Yang diuji hanya penolakan: ketiganya harus berhenti SEBELUM menyentuh
    // Meta, jadi jumlah baris meta_pull_log tidak boleh bertambah.
    $logAwal = (int) $db->table('meta_pull_log')->countAllResults();

    $bukanPost = tarikKirim($ctrl, [], 'GET');
    ok('tarik: bukan POST → redirect', is_object($bukanPost) && get_class($bukanPost) === 'CodeIgniter\HTTP\RedirectResponse', get_class($bukanPost));

    $periodeSalah = tarikKirim($ctrl, ['period_month' => 13, 'period_year' => $tahun]);
    ok('tarik: periode tidak valid ditolak', is_object($periodeSalah) && get_class($periodeSalah) === 'CodeIgniter\HTTP\RedirectResponse', get_class($periodeSalah));
    ok('tarik: periode tidak valid disertai pesan', (string) session()->getFlashdata('error') !== '');

    ok('tarik: penolakan terjadi sebelum memanggil Meta', (int) $db->table('meta_pull_log')->countAllResults() === $logAwal);

    $_POST = [];
    $_GET  = [];
    $reqProp->setValue($ctrl, \Config\Services::request(null, false));

    $db->transRollback();
    ok('data uji Ads dibersihkan', (int) $db->table('marketing_ads_performance')->where('period_year', $tahun)->whereIn('campaign_id', [$campNol, $campAda])->countAllResults() === 0);
    ok('data uji log dibersihkan', (int) $db->table('meta_pull_log')->where('id', $logUji)->countAllResults() === 0);

    // ── Laporan ──────────────────────────────────────────────────
    $html = (string)$ctrl->laporan();
    ok('laporan render — page-header + filter-card', strpos($html, 'dm-page-header') !== false && strpos($html, 'dm-filter-card') !== false);
    ok('laporan render — judul Laporan Digital Marketing', strpos($html, 'Laporan Digital Marketing') !== false);
    $laporanAdaData = strpos($html, 'Performa Harian') !== false && strpos($html, 'Insight &amp; Evaluasi') !== false;
    $laporanEmpty = strpos($html, 'Belum ada data Ads') !== false;
    ok('laporan render — isi (performa harian & insight / empty state)', $laporanAdaData || $laporanEmpty, $laporanAdaData ? 'ada-data' : 'empty');
    ok('laporan render — pilihan minggu', strpos($html, 'name="minggu"') !== false);

    // Kirim laporan minggu yang belum berakhir → ditolak server (bukan hanya
    // tombolnya disembunyikan).
    $bulanDepan = (int) date('n') + 1;
    $tahunDepan = (int) date('Y');
    if ($bulanDepan > 12) {
        $bulanDepan = 1;
        $tahunDepan++;
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['bulan' => $bulanDepan, 'tahun' => $tahunDepan, 'minggu' => 1];
    $_GET  = [];
    $reqProp->setValue($ctrl, \Config\Services::request(null, false));
    $tolak = (new ReflectionMethod($ctrl, 'laporan_minggu_simpan'))->invoke($ctrl);
    ok(
        'laporan minggu belum berakhir ditolak',
        is_object($tolak) && get_class($tolak) === 'CodeIgniter\HTTP\RedirectResponse'
            && (string) session()->getFlashdata('error') !== '',
        get_class($tolak) . ' / ' . var_export(session()->getFlashdata('error'), true)
    );
    $_POST = [];

    // ── Role 48 (Talent) tidak berhak ─────────────────────────────
    $session->set('ID_JABATAN', 48);
    $ctrl2 = new \App\Controllers\Marketing();
    $ctrl2->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $out = $ctrl2->index();
    ok('Role 48 di-redirect (tidak berhak akses marketing)', is_object($out) && get_class($out) === 'CodeIgniter\HTTP\RedirectResponse', get_class($out));

    // Tanpa hak tulis, tarikan tidak boleh jalan — walau POST & periodenya sah.
    $logSebelum48 = (int) $db->table('meta_pull_log')->countAllResults();
    $tarik48 = tarikKirim($ctrl2, ['period_month' => $bulan, 'period_year' => $tahun]);
    ok('Role 48 tidak bisa menarik dari Meta', is_object($tarik48) && get_class($tarik48) === 'CodeIgniter\HTTP\RedirectResponse', get_class($tarik48));
    ok('Role 48: Meta tidak dipanggil', (int) $db->table('meta_pull_log')->countAllResults() === $logSebelum48);
} catch (\Throwable $e) {
    echo "  CRASH " . get_class($e) . ": {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n";
    $fail++;
}

echo "\nSMOKE PASS: {$pass}   FAIL: {$fail}\n";
echo "------------------------------------------------------------\n";
exit($fail > 0 ? 1 : 0);