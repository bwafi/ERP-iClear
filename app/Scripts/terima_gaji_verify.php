<?php
/**
 * Self-check kolom akun.TERIMA_GAJI (penentu per-akun "digaji atau tidak").
 *
 * Dijalankan manual: php app/Scripts/terima_gaji_verify.php
 * Exit 0 = semua PASS. Bootstrap mengikuti assetberjalan_debug.php.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
foreach (['APPPATH'=>'appDirectory','ROOTPATH'=>'','SYSTEMPATH'=>'systemDirectory','WRITEPATH'=>'writableDirectory','TESTPATH'=>'testsDirectory'] as $c=>$p) {
    if (!defined($c)) { $v = $c==='ROOTPATH' ? realpath(APPPATH.'../') : realpath(rtrim($paths->$p,'\\/ ')); define($c, $v.DIRECTORY_SEPARATOR); }
}
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$gagal = 0;
function check(string $nama, bool $kondisi): void
{
    global $gagal;
    echo ($kondisi ? 'PASS' : 'FAIL') . ' ' . $nama . "\n";
    if (! $kondisi) {
        $gagal++;
    }
}

$db = \Config\Database::connect();

// B: ModelAuth menegakkan allowedFields -> update TERIMA_GAJI harus tersimpan.
// Catatan: CI4 melempar DataException kalau data kosong setelah difilter
// allowedFields, jadi exception di sini = field belum diizinkan = FAIL.
$db->transBegin();
try {
    $id = 43;
    try {
        (new \App\Models\ModelAuth())->update($id, ['TERIMA_GAJI' => 0]);
        $val = (int) $db->table('akun')->where('ID_AKUN', $id)->get()->getRow()->TERIMA_GAJI;
        check('ModelAuth menyimpan TERIMA_GAJI=0', $val === 0);
    } catch (\Throwable $e) {
        check('ModelAuth menyimpan TERIMA_GAJI=0', false);
    }
} finally {
    $db->transRollback();
}

echo $gagal === 0 ? "\nSEMUA PASS\n" : "\n$gagal GAGAL\n";
exit($gagal === 0 ? 0 : 1);
