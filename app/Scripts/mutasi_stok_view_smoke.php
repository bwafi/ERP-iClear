<?php
/**
 * Smoke render halaman /mutasi_stok.
 *
 * Mengunci hal-hal yang gampang rusak diam-diam saat view diubah:
 *  1. View tetap bisa dirender penuh lewat controller (tidak ada variabel hilang).
 *  2. Kolom stok dan suplier benar-benar terisi dari peta per unit.
 *  3. Modal picker masih membawa data yang dibutuhkan JS (harga, stok, suplier).
 *
 * Jalankan: php74 app/Scripts/mutasi_stok_view_smoke.php
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

$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . ($ok ? '' : '  ' . $extra) . "\n";
    if (! $ok) {
        $fail++;
    }
}

$db = Config\Database::connect();
$unit = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getRow();
if (! $unit) {
    fwrite(STDERR, "Unit master tidak tersedia.\n");
    exit(2);
}

$akun = $db->table('akun')->orderBy('ID_AKUN', 'ASC')->get()->getRow();

$session = Config\Services::session();
$session->set([
    'ID_AKUN'    => (int) ($akun->ID_AKUN ?? 1),
    'ID_JABATAN' => 1,
    'ID_UNIT'    => (int) $unit->idunit,
    'NAMA_UNIT'  => $unit->NAMA_UNIT,
    'NAMED'      => 'smoke',
    'logged_in'  => true,
]);

$stokModel = new App\Models\ModelBarang();
$stokMap = $stokModel->getStokPerUnit();
$suplierMap = $stokModel->getSuplierPerUnit();

check('peta stok terisi', $stokMap !== [], 'kosong');
check('peta suplier terisi', $suplierMap !== [], 'kosong');
check(
    'setiap unit di peta stok punya entri',
    isset($stokMap[(int) $unit->idunit]) && count($stokMap[(int) $unit->idunit]) > 0,
    'unit ' . $unit->idunit . ' kosong'
);

$config = new \Config\App();
$uri = new \CodeIgniter\HTTP\SiteURI($config, 'mutasi_stok', 'localhost', 'http');
$request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
\CodeIgniter\Config\Services::injectMock('request', $request);

$controller = new App\Controllers\MutasiStok();

ob_start();
try {
    $html = $controller->index();
    $err = '';
} catch (\Throwable $e) {
    $err = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    $html = '';
}
ob_end_clean();

check('view terender tanpa error', $err === '' && is_string($html) && $html !== '', $err);
check('judul halaman ada', strpos($html, 'Mutasi Stok') !== false);
check('panel rute ada', strpos($html, 'Rute pengiriman') !== false);
check('panel barang ada', strpos($html, 'Barang yang dikirim') !== false);
check('tombol simpan ada di dalam form', strpos($html, 'Simpan mutasi') !== false);
check('modal picker ada', strpos($html, 'produk-modal-table') !== false);
check('kolom suplier dirender', strpos($html, 'data-suplier-cell') !== false);
check('kolom stok dirender', strpos($html, 'data-stock-cell') !== false);
check('peta stok dikirim ke JS', strpos($html, 'const stokUnit') !== false);
check(
    'picker hanya menampilkan produk berstok',
    strpos($html, 'Number(cb.dataset.stok || 0) > 0') !== false
        && strpos($html, 'Tidak ada produk dengan stok tersedia.') !== false,
    'filter stok picker tidak ditemukan'
);
check('hidden unit pengirim ada', strpos($html, 'id_unit1_text') !== false);
check('harga mutasi tetap dikirim', strpos($html, 'harga-mutasi-hidden') !== false);
check('form tidak meminta jumlah terima', strpos($html, '[jumlah_terima]') === false);
check('form tidak meminta tanggal terima', strpos($html, 'name="tanggal_terima"') === false);

// Minimal satu baris picker harus punya suplier nyata, kalau datanya ada.
$adaSuplierDb = false;
foreach ($suplierMap as $perBarang) {
    foreach ($perBarang as $nama) {
        if (trim((string) $nama) !== '') {
            $adaSuplierDb = true;
            break 2;
        }
    }
}
check(
    'baris picker membawa suplier',
    ! $adaSuplierDb || strpos($html, 'data-suplier="') !== false,
    'tidak ada suplier sama sekali di HTML'
);

$petaDari = static function (string $html): array {
    if (! preg_match('/const stokUnit\s*=\s*(\{.*\});\s*\n\s*const suplierUnit/s', $html, $m)) {
        return [];
    }
    $map = json_decode($m[1], true);

    return is_array($map) ? $map : [];
};

$petaLintas = $petaDari($html);
check('admin root menerima peta banyak unit', count($petaLintas) > 1, 'unit: ' . count($petaLintas));

// User cabang tidak boleh menerima stok/suplier unit lain lewat source HTML.
$session->set('ID_JABATAN', 44);
ob_start();
try {
    $htmlCabang = (new App\Controllers\MutasiStok())->index();
    $errCabang = '';
} catch (\Throwable $e) {
    $errCabang = $e->getMessage();
    $htmlCabang = '';
}
ob_end_clean();

check('view unit cabang terender', $errCabang === '' && $htmlCabang !== '', $errCabang);
$petaCabang = $petaDari($htmlCabang);
check('user cabang hanya menerima peta unitnya', count($petaCabang) === 1 && isset($petaCabang[$unit->idunit]), 'unit: ' . implode(',', array_keys($petaCabang)));

preg_match('/id="unit1".*?<\/select>/s', $htmlCabang, $mUnit1);
check(
    'unit pengirim cabang dikunci ke unitnya',
    isset($mUnit1[0]) && substr_count($mUnit1[0], '<option') === 1
        && strpos($htmlCabang, 'Anda hanya bisa mengirim dari unit Anda.') !== false,
    'opsi: ' . substr_count($mUnit1[0] ?? '', '<option')
);


echo $fail === 0 ? "\nSEMUA PASS\n" : "\n{$fail} FAIL\n";
exit($fail === 0 ? 0 : 1);
