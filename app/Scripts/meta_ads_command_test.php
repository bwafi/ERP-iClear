<?php
/**
 * Uji command `meta:pull` (Task 4).
 *
 * Menguji lewat CLI sungguhan (`php spark`), tapi hanya jalur VALIDASI yang
 * tidak memanggil jaringan. Jalur tarikan nyata diuji manual (Task 4 step 2–3)
 * karena butuh token & koneksi ke Meta.
 *
 * Usage: php74 app/Scripts/meta_ads_command_test.php
 */

$fail = 0;
$pass = 0;

function ok($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}  " . ($detail !== '' ? "→ {$detail}" : '') . "\n";
    }
}

$root = realpath(__DIR__ . '/../..');

function runSpark(string $args): array
{
    global $root;
    $out  = [];
    $code = 0;
    exec('cd ' . escapeshellarg($root) . ' && php74 spark ' . $args . ' 2>&1', $out, $code);

    return ['code' => (int)$code, 'out' => implode("\n", $out)];
}

echo "Uji command meta:pull\n";
echo "------------------------------------------------------------\n";

// ── Terdaftar ───────────────────────────────────────────────────────
$list = runSpark('list');
ok('command meta:pull terdaftar di spark', strpos($list['out'], 'meta:pull') !== false);

/*
 * Kedua bentuk penulisan opsi harus jalan.
 *
 * Bentuk `--dari=X` penting: CodeIgniter 4.4 TIDAK memecahnya sendiri, jadi
 * kalau command tidak menanganinya, opsinya hilang tanpa pesan dan perintah
 * diam-diam memakai rentang 7 hari terakhir. Uji di bawah memastikan opsi
 * benar-benar terbaca — ditandai dengan validasi yang jalan SEBELUM panggilan
 * jaringan ("Menarik insight" tidak pernah tercetak).
 */
$bentuk = [
    '='     => '--dari=%s --sampai=%s',
    'spasi' => '--dari %s --sampai %s',
];

foreach ($bentuk as $label => $tpl) {
    $bad = runSpark('meta:pull ' . sprintf($tpl, '01-10-2026', '2026-10-02'));
    ok("[{$label}] opsi terbaca — format salah ditolak", $bad['code'] !== 0, 'exit=' . $bad['code']);
    ok("[{$label}] pesan menyebut YYYY-MM-DD", strpos($bad['out'], 'YYYY-MM-DD') !== false, substr($bad['out'], 0, 200));
    ok(
        "[{$label}] validasi jalan sebelum panggilan Meta",
        strpos($bad['out'], 'Menarik insight') === false,
        substr($bad['out'], 0, 200)
    );
}

// ── Tanggal tidak nyata (13-45) → error ─────────────────────────────
$bad2 = runSpark('meta:pull --dari=2026-13-45 --sampai=2026-14-01');
ok('tanggal tidak nyata ditolak', $bad2['code'] !== 0, 'exit=' . $bad2['code']);

// ── dari > sampai → error ───────────────────────────────────────────
$bad3 = runSpark('meta:pull --dari=2026-10-05 --sampai=2026-10-01');
ok('dari > sampai ditolak', $bad3['code'] !== 0, 'exit=' . $bad3['code']);

// ── Tidak ada efek samping saat validasi gagal ──────────────────────
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

$db = \Config\Database::connect();
ok('validasi gagal tidak menulis log tarikan', (int)$db->query(
    "SELECT COUNT(*) c FROM meta_pull_log WHERE created_at >= NOW() - INTERVAL 1 MINUTE"
)->getRow()->c === 0);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
