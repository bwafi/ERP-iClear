<?php

/**
 * Smoke render halaman /mutasi_stok/masuk.
 *
 * Mengunci hal-hal yang gampang rusak diam-diam saat view diubah:
 *  1. View bisa dirender penuh lewat controller (tidak ada variabel hilang).
 *  2. Filter chip "Belum diterima" (nilai 0) tidak tertelan falsy-PHP.
 *  3. Modal terima/batal masih membawa data yang dibutuhkan JS (detail JSON, asal).
 *
 * Jalankan: php app/Scripts/mutasi_masuk_view_smoke.php
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

$config = new \Config\App();

$html = static function (string $query = '') use ($config): string {
    $uri = new \CodeIgniter\HTTP\SiteURI($config, 'mutasi_stok/masuk', 'localhost', 'http');
    $_GET = [];
    if ($query !== '') {
        $uri->setQuery($query);
        parse_str($query, $_GET);
    }
    $_SERVER['QUERY_STRING'] = $query;

    $request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
    \CodeIgniter\Config\Services::injectMock('request', $request);

    $controller = new App\Controllers\MutasiStok();
    $prop = new ReflectionProperty(App\Controllers\BaseController::class, 'request');
    $prop->setAccessible(true);
    $prop->setValue($controller, $request);

    ob_start();
    $html = $controller->masuk();
    ob_end_clean();

    return $html;
};

$db  = Config\Database::connect();
$akun = $db->table('akun')->orderBy('ID_AKUN', 'ASC')->get()->getRow();
$unit = $db->table('unit')->where('idunit', 1)->get()->getRow();

$session = Config\Services::session();
$session->set([
    'ID_AKUN'    => (int) ($akun->ID_AKUN ?? 1),
    'ID_JABATAN' => 1,
    'ID_UNIT'    => (int) ($unit->idunit ?? 1),
    'NAMA_UNIT'  => $unit->NAMA_UNIT ?? 'Unit 1',
    'NAMED'      => 'smoke',
    'logged_in'  => true,
]);

$html0 = $html();
check('view terender tanpa error', $html0 !== '');

check('kepala & konteks unit ada', strpos($html0, 'Unit aktif') !== false);
check('panel filter ada', strpos($html0, 'mm-filter') !== false);
check('tabel antrean ada', strpos($html0, 'mm-table') !== false);
check('baris tabel membawa label mobile', strpos($html0, 'data-label="Rute"') !== false);
check('chip status belum ada', strpos($html0, 'id="mm-status-belum"') !== false);

$detailAttr = '';
if (preg_match('/data-detail="([^"]+)"/', $html0, $mDetail)) {
    $detailAttr = html_entity_decode($mDetail[1]);
}
$detail = json_decode($detailAttr, true);
check('detail JSON valid di tombol Terima', is_array($detail) && $detail !== []);
check('detail JSON berisi barang & nilai', isset($detail[0]['nama'], $detail[0]['nilai']));

check('modal terima ada', strpos($html0, 'id="modalTerimaMutasi"') !== false);
check('modal batal ada (admin)', strpos($html0, 'id="modalBatalTerima"') !== false);
check('alasan pembatalan ada', strpos($html0, 'id="alasan_batal"') !== false);
check('asal dibawa sebagai URL query', strpos($html0, 'name="asal" value="&#x3F;') !== false);
check('paginasi ada', strpos($html0, 'mm-pagination') !== false);
check('footer rentang tampil ada', strpos($html0, 'Menampilkan') !== false);

// Format tanggal lokal mengikuti baris terbaru di DB.
$terakhir = $db->table('mutasi')->orderBy('idmutasi', 'DESC')->get()->getRow();
if ($terakhir && $terakhir->tanggal_kirim) {
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts   = strtotime($terakhir->tanggal_kirim);
    $harapan = date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    check('tanggal dirender format lokal', strpos($html0, esc($harapan)) !== false, 'mencari ' . $harapan);
}

// Filter status '0' harus tetap sampai ke view (string '0' itu falsy).
$pending = (int) $db->table('mutasi')->where('status', '0')->countAll();
$htmlPending = $html('status=0');
check(
    'chip belum diterima terpilih',
    preg_match('/id="mm-status-belum"\s+value="0" checked/', $htmlPending) === 1,
    'value tidak checked'
);
if ($pending > 0) {
    // Label eksak baris status, bukan string kelas (yang juga ada di CSS).
    check(
        'filter status 0 tidak menampilkan baris Diterima',
        strpos($htmlPending, 'mm-status mm-status--done">Diterima</span>') === false
    );
}
if (preg_match('/name="asal" value="([^"]+)"/', $htmlPending, $mAsal)) {
    check(
        'asal mempertahankan filter status 0',
        $mAsal[1] === '&#x3F;status&#x3D;0&amp;page&#x3D;1',
        'asal: ' . $mAsal[1]
    );
} else {
    check('asal mempertahankan filter status 0', false, 'field asal tidak ditemukan');
}

// User cabang: kontrol lintas & modal batal tidak boleh muncul.
$session->set('ID_JABATAN', 44);
$htmlCabang = $html();
check('view unit cabang terender', $htmlCabang !== '');
check(
    'filter unit hilang untuk user cabang',
    strpos($htmlCabang, 'id="mm-unit"') === false,
    'select unit ikut dirender'
);
check(
    'modal batal tidak dirender untuk bukan admin',
    strpos($htmlCabang, 'id="modalBatalTerima"') === false
);
check(
    'status chip tetap ada',
    strpos($htmlCabang, 'mm-chip-input') !== false
);

echo $fail === 0 ? "\nSEMUA PASS\n" : "\n{$fail} FAIL\n";
exit($fail === 0 ? 0 : 1);
