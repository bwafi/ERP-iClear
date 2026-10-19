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
    $id = 9900;
    $db->table('akun')->insert([
        'ID_AKUN' => $id, 'ID_JABATAN' => 36, 'ID_UNIT' => 1, 'NOID' => "TG$id",
        'PASSWORD' => 'x', 'NAMA_AKUN' => "TEMP TG $id", 'JENIS_KELAMIN' => 'Laki-Laki',
        'HP' => '0', 'STATUS_PEGAWAI' => 1, 'TERIMA_GAJI' => 1,
    ]);
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

// C: akun TERIMA_GAJI=0 tidak ikut di totalGajiUnit; TERIMA_GAJI=1 ikut.
$db->transBegin();
try {
    foreach ([9901 => 0, 9902 => 1] as $idAkun => $flag) {
        $db->table('akun')->insert([
            'ID_AKUN' => $idAkun, 'ID_JABATAN' => 36, 'ID_UNIT' => 2, 'NOID' => "TG$idAkun",
            'PASSWORD' => 'x', 'NAMA_AKUN' => "TEMP TG $idAkun", 'JENIS_KELAMIN' => 'Laki-Laki',
            'HP' => '0', 'STATUS_PEGAWAI' => 1, 'TERIMA_GAJI' => $flag,
        ]);
    }
    $svc = new \App\Services\Payroll\UnitSalaryCalculationService();
    $hasil = $svc->calculateForUnit(1, 2, date('m'), date('Y'));
    $idAda = array_keys($hasil['employeeData']);
    check('akun TERIMA_GAJI=0 tidak ikut Total Gaji', ! in_array(9901, $idAda, true));
    check('akun TERIMA_GAJI=1 tetap ikut Total Gaji', in_array(9902, $idAda, true));
} finally {
    $db->transRollback();
}

// E: PayrollGenerator melewati akun TERIMA_GAJI=0 (bukan hanya jabatan) & melaporkannya.
$db->transBegin();
try {
    // Akun temp jabatan 36 (BUKAN jabatan 1/2) supaya check ini benar-benar
    // gagal sebelum sumbernya dipindah dari jabatan ke kolom.
    $db->table('akun')->insert([
        'ID_AKUN' => 9903, 'ID_JABATAN' => 36, 'ID_UNIT' => 1, 'NOID' => 'TG9903',
        'PASSWORD' => 'x', 'NAMA_AKUN' => 'TEMP TG 9903', 'JENIS_KELAMIN' => 'Laki-Laki',
        'HP' => '0', 'STATUS_PEGAWAI' => 1, 'TERIMA_GAJI' => 0,
    ]);
    $hasil = (new \App\Services\Payroll\PayrollGenerator())->generate(date('Y-m'), ['unit_ids' => [1]]);
    $excluded = array_filter($hasil['rows'], fn($r) => $r['status'] === 'excluded');
    $nama = array_column($excluded, 'nama');
    check('akun TERIMA_GAJI=0 (jabatan biasa) dilaporkan excluded', in_array('TEMP TG 9903', $nama, true));
} finally {
    $db->transRollback();
}

// F: distribusi gaji HO tidak memuat akun TERIMA_GAJI=0.
$db->transBegin();
try {
    foreach ([9904 => 0, 9905 => 1] as $idAkun => $flag) {
        $db->table('akun')->insert([
            'ID_AKUN' => $idAkun, 'ID_JABATAN' => 36, 'ID_UNIT' => 50, 'NOID' => "TG$idAkun",
            'PASSWORD' => 'x', 'NAMA_AKUN' => "TEMP TG $idAkun", 'JENIS_KELAMIN' => 'Laki-Laki',
            'HP' => '0', 'STATUS_PEGAWAI' => 1, 'TERIMA_GAJI' => $flag,
        ]);
    }
    $dist = (new \App\Services\Payroll\HoSalaryDistributionService())->calculateDistribution(date('Y-m'));
    $idDist = array_map('intval', array_column($dist['employees'], 'id'));
    check('HO TERIMA_GAJI=0 tidak ikut distribusi', ! in_array(9904, $idDist, true));
    check('HO TERIMA_GAJI=1 tetap ikut distribusi', in_array(9905, $idDist, true));
} finally {
    $db->transRollback();
}

echo $gagal === 0 ? "\nSEMUA PASS\n" : "\n$gagal GAGAL\n";
exit($gagal === 0 ? 0 : 1);
