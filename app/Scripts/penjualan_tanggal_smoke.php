<?php
/**
 * Smoke test input tanggal di /penjualan.
 *
 * Field tanggal penjualan dulu dikunci per unit: hanya unit 1 yang boleh
 * memilih tanggal, unit lain dapat <input type="date" readonly min="today"
 * max="today"> sehingga secara teknis tidak bisa mengisi tanggal sendiri,
 * dan tanggalnya terkunci ke hari ini.
 *
 * Test ini merender view-nya sungguhan untuk beberapa unit, lalu memastikan
 * field tanggal:
 *   - ada tepat satu (bukan dua cabang if/else yang duplikat)
 *   - tidak readonly, jadi bisa diketik/dipilih
 *   - tidak punya min/max, jadi backdate dan tanggal depan sama-sama bisa
 *   - default-nya tetap hari ini, dan tetap required
 *
 * Sisi server (Controllers/Penjualan.php) memakai tanggal dari POST apa adanya
 * tanpa memaksa ke hari ini, jadi membuka field ini cukup.
 *
 * Jalankan: php app/Scripts/penjualan_tanggal_smoke.php
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

$_SESSION = [];

$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . ($ok ? '' : '  ' . $extra) . "\n";
    if (! $ok) {
        $fail++;
    }
}

/** Ambil blok markup untuk satu input tanggal. */
function blokTanggal(string $html): string
{
    preg_match('/<input[^>]*id="tanggal_masuk"[^>]*>/s', $html, $m);

    return $m[0] ?? '';
}

$db      = Config\Database::connect();
$akun    = $db->table('akun')->select('ID_AKUN, ID_UNIT, NAMA_AKUN')->get()->getRowArray();
$unitIds = array_values(array_unique(array_column($db->table('unit')->get()->getResultArray(), 'idunit')));
$unitIds[] = 1; // pastikan unit 1 ikut teruji walau tidak ada di tabel
$unitIds = array_slice(array_values(array_unique($unitIds)), 0, 6);

$data = [
    'akun'      => (object) $akun,
    'produk'    => [],
    'frontliner' => [],
    'bank'      => [],
    'kategori'  => [],
    'suplier'   => [],
    'pelanggan' => [],
    'bundle'    => [],
    'provinsi'  => [],
];

$hariIni = date('Y-m-d');

foreach ($unitIds as $uid) {
    $_SESSION['ID_UNIT'] = (int) $uid;
    $data['akun']->ID_UNIT = (int) $uid;

    $html = view('transaksi/penjualan', $data);
    $blok = blokTanggal($html);

    $okAda    = $blok !== '';
    $okSatu   = substr_count($html, 'id="tanggal_masuk"') === 1;
    $okFree   = $okAda && stripos($blok, 'readonly') === false && stripos($blok, 'disabled') === false;
    $okNoMin  = $okAda && stripos($blok, 'min=') === false && stripos($blok, 'max=') === false;
    $okDefault = $okAda && strpos($blok, 'value="' . $hariIni . '"') !== false;
    $okReq    = $okAda && strpos($blok, 'required') !== false;

    check(
        "unit {$uid}: field tanggal ada, tunggal, bisa diisi, tanpa min/max, default hari ini, required",
        $okAda && $okSatu && $okFree && $okNoMin && $okDefault && $okReq,
        'ada=' . var_export($okAda, true)
            . ' tunggal=' . var_export($okSatu, true)
            . ' free=' . var_export($okFree, true)
            . ' noMinMax=' . var_export($okNoMin, true)
            . ' default=' . var_export($okDefault, true)
            . ' required=' . var_export($okReq, true)
    );
}

// Sisi server tidak boleh memaksa tanggal kembali ke hari ini.
$ctrl = file_get_contents(APPPATH . 'Controllers/Penjualan.php');
check(
    'server memakai tanggal dari POST tanpa memaksa ke hari ini',
    strpos($ctrl, "\$tanggal = \$this->request->getPost('tanggal_masuk');") !== false
        && strpos($ctrl, '!empty($tanggal) ? $tanggal : date') !== false
);

echo $fail === 0 ? "\nSemua PASS.\n" : "\n{$fail} FAIL.\n";
exit($fail === 0 ? 0 : 1);
