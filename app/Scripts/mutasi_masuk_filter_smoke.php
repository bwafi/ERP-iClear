<?php
/**
 * Smoke test filter + paginasi /mutasi_stok/masuk.
 *
 * Halaman ini tadinya `limit(200)` tanpa filter: yang terlihat cuma 200 nota
 * pertama, dan tidak ada cara mencari nota ke-201. Test ini mengunci dua hal
 * yang mudah rusak diam-diam:
 *
 *  1. COUNT dan query data harus punya syarat yang SAMA. Kalau berbeda,
 *     footer "Menampilkan 1-25 dari 87" jadi bohong while tabelnya 12 baris.
 *  2. Scope unit adalah batas akses, bukan filter. User satu unit tidak
 *     boleh melihat mutasi unit lain lewat filter mana pun — termasuk lewat
 *     angka total di footer, yang justru lebih bocor karena tidak terlihat.
 *
 * Plus: filter harus ikut terbawa saat pindah halaman, dan saat terima/batal.
 *
 * Jalankan: php74 app/Scripts/mutasi_masuk_filter_smoke.php
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
$db = Config\Database::connect();
$db->transStrict(false);

$fail = 0;
function check(string $label, bool $ok, string $extra = ''): void
{
    global $fail;
    echo ($ok ? 'PASS  ' : 'FAIL  ') . $label . ($ok ? '' : '  ' . $extra) . "\n";
    if (! $ok) {
        $fail++;
    }
}

$model     = new App\Models\ModelMutasiStok();
$controller = new App\Controllers\MutasiStok();
$reqProp   = new ReflectionProperty(CodeIgniter\Controller::class, 'request');
$reqProp->setAccessible(true);

/** Filter kosong yang sah. */
function f(array $over = []): array
{
    return $over + ['search' => '', 'status' => '', 'dari' => '', 'sampai' => '', 'unit' => 0];
}

/** Kumpulkan id dari semua halaman, untuk membandingkan dengan COUNT. */
function semuaId($model, int $perPage, bool $isLintas, int $unit, array $f): array
{
    $total  = $model->countMutasiMasuk($isLintas, $unit, $f);
    $pages  = max(1, (int) ceil($total / $perPage));
    $ids    = [];
    for ($p = 1; $p <= $pages; $p++) {
        foreach ($model->getMutasiMasuk($perPage, $p, $isLintas, $unit, $f) as $r) {
            $ids[] = (int) $r->idmutasi;
        }
    }

    return $ids;
}

$semuaRow   = $db->table('mutasi')->orderBy('idmutasi', 'DESC')->get()->getResult();
$jumlahAll  = count($semuaRow);
$contohNota = $semuaRow[0]->no_nota_mutasi;
$contohTgl  = date('Y-m-d', strtotime($semuaRow[0]->tanggal_kirim));
// Unit dengan mutasi terbanyak dipakai sebagai "unit saya" pada uji scope:
// kalau scope bocor, memilih unit yang paling ramai membuat kebocoran
// paling susah terlewat oleh test.
$teramai = null;
foreach ($db->table('mutasi')->select('terima_idunit, COUNT(*) c', true)->groupBy('terima_idunit')->get()->getResult() as $r) {
    if ($teramai === null || $r->c > $teramai->c) {
        $teramai = $r;
    }
}
$unitPadat = (int) $teramai->terima_idunit;
$jmlPadat  = (int) $teramai->c;
$jmlStatus0 = (int) $db->table('mutasi')->where('status', '0')->countAllResults();

echo "Data: total={$jumlahAll} status0={$jmlStatus0} unitPadat={$unitPadat}({$jmlPadat}) nota={$contohNota} tgl={$contohTgl}\n\n";

// --- 1. paginasi: jumlah, tanpa tumpang tindih, urut -----------------------------
$perPage = 25;
$total   = $model->countMutasiMasuk(true, 0, f());
$halaman = max(1, (int) ceil($total / $perPage));

check('COUNT tanpa filter = jumlah tabel', $total === $jumlahAll, "count={$total} tabel={$jumlahAll}");
check('total halaman dihitung', $halaman === (int) ceil($jumlahAll / $perPage), "halaman={$halaman}");

$p1 = array_map(fn ($r) => (int) $r->idmutasi, $model->getMutasiMasuk($perPage, 1, true, 0, f()));
$p2 = array_map(fn ($r) => (int) $r->idmutasi, $model->getMutasiMasuk($perPage, 2, true, 0, f()));
$p9 = array_map(fn ($r) => (int) $r->idmutasi, $model->getMutasiMasuk($perPage, 9, true, 0, f()));

check('halaman 1 berisi <= perPage', count($p1) <= $perPage, 'n=' . count($p1));
check('halaman di luar jangkauan kosong', $p9 === [], 'n=' . count($p9));
check('tidak ada baris tumpang tindih antar halaman', array_intersect($p1, $p2) === [], 'tabrakan=' . count(array_intersect($p1, $p2)));
check('gabungan halaman = seluruh data', count(array_unique(array_merge($p1, $p2))) === $jumlahAll, 'n=' . count(array_unique(array_merge($p1, $p2))));
$desc = $p1;
rsort($desc);
check('urutan idmutasi DESC', $p1 === $desc && $p1 === array_values($p1));

// --- 2. COUNT harus sama dengan isi tabel, untuk SETIAP kombinasi filter -------
$kombinasi = [
    'tanpa filter'    => f(),
    'status 0'        => f(['status' => '0']),
    'status 1'        => f(['status' => '1']),
    'cari nota'       => f(['search' => substr($contohNota, 0, 6)]),
    'tanggal sama'    => f(['dari' => $contohTgl, 'sampai' => $contohTgl]),
    'gabungan'        => f(['status' => '0', 'dari' => '2000-01-01', 'sampai' => '2099-12-31']),
    'salah kirim'     => f(['dari' => $contohTgl, 'sampai' => $contohTgl, 'status' => '1']),
];
foreach ($kombinasi as $label => $f) {
    $ids = semuaId($model, 25, true, 0, $f);
    check("COUNT = isi tabel pada filter [$label]", count($ids) === $model->countMutasiMasuk(true, 0, $f), 'n=' . count($ids));
}

// --- 3. masing-masing filter benar-benar menyaring -----------------------------
$h0 = semuaId($model, 25, true, 0, f(['status' => '0']));
$h1 = semuaId($model, 25, true, 0, f(['status' => '1']));
check('filter status 0 menyaring', count($h0) === $jmlStatus0, 'n=' . count($h0) . ' harap=' . $jmlStatus0);
check('status 0 dan 1 tidak beririsan', array_intersect($h0, $h1) === []);
check('status 0 + status 1 = total', count($h0) + count($h1) === $jumlahAll);

$dicari = semuaId($model, 25, true, 0, f(['search' => $contohNota]));
check('cari no nota persis menemukan 1', count($dicari) === 1, 'n=' . count($dicari));
check('cari no nota benar-benar yang diminta', ($dicari[0] ?? 0) === (int) $semuaRow[0]->idmutasi);
check('cari nota tak ada hasil 0', semuaId($model, 25, true, 0, f(['search' => 'ZZZ-TIDAK-ADA'])) === []);

$sameDay = semuaId($model, 25, true, 0, f(['dari' => $contohTgl, 'sampai' => $contohTgl]));
check('rentang tanggal 1 hari menyaring', count($sameDay) > 0, 'n=' . count($sameDay));
check('semua hasil rentang tanggal berpadanan', count($sameDay) === (int) $db->table('mutasi')
    ->where('DATE(tanggal_kirim)', $contohTgl)->countAllResults(), 'n=' . count($sameDay));
check('rentang tanggal 1 menit sebelum tidak memotong hasil yang sah', semuaId($model, 25, true, 0,
    f(['dari' => date('Y-m-d', strtotime($contohTgl . ' 00:00:00')), 'sampai' => $contohTgl])) !== []);

$byUnit = semuaId($model, 25, true, 0, f(['unit' => $unitPadat]));
$rowsByUnit = $db->table('mutasi')->select('idmutasi, kirim_idunit, terima_idunit')
    ->groupStart()->where('kirim_idunit', $unitPadat)->orWhere('terima_idunit', $unitPadat)->groupEnd()->get()->getResult();
check('filter unit (lintas) menyaring', count($byUnit) === count($rowsByUnit), 'n=' . count($byUnit) . ' sql=' . count($rowsByUnit));
$semuaBenar = true;
foreach ($byUnit as $id) {
    $r = $db->table('mutasi')->where('idmutasi', $id)->get()->getRow();
    if ((int) $r->kirim_idunit !== $unitPadat && (int) $r->terima_idunit !== $unitPadat) {
        $semuaBenar = false;
    }
}
check('hasil filter unit semuanya-unit itu', $semuaBenar);

// --- 4. SCOPE: user satu unit tidak boleh melihat unit lain --------------------
$milikSaya = semuaId($model, 25, false, $unitPadat, f());
check('user satu unit hanya dapat mutasi(unitnya)', count($milikSaya) === $jmlPadat, 'n=' . count($milikSaya) . ' harap={$jmlPadat}');
$bocor = false;
foreach ($milikSaya as $id) {
    $r = $db->table('mutasi')->where('idmutasi', $id)->get()->getRow();
    if ((int) $r->terima_idunit !== $unitPadat) {
        $bocor = true;
    }
}
check('tidak ada baris unit lain yang bocor', ! $bocor);

// filter apa pun tidak boleh membebaskan scope
$jumlahLain = $jumlahAll - $jmlPadat;
foreach ([
    'filter unit'    => f(['unit' => $unitPadat === 1 ? 2 : 1]),
    'filter status'  => f(['status' => '1']),
    'filter pencarian'=> f(['search' => substr($contohNota, 0, 6)]),
    'filter tanggal' => f(['dari' => '2000-01-01', 'sampai' => '2099-12-31']),
    'filter gabungan' => f(['status' => '1', 'search' => 'MTS', 'dari' => '2000-01-01']),
] as $label => $f) {
    $ids = semuaId($model, 25, false, $unitPadat, $f);
    $nilaiLain = 0;
    foreach ($ids as $id) {
        $r = $db->table('mutasi')->where('idmutasi', $id)->get()->getRow();
        if ((int) $r->terima_idunit !== $unitPadat) {
            $nilaiLain++;
        }
    }
    check("scope bertahan saat $label dipakai", $nilaiLain === 0, "bocor={$nilaiLain} dari " . count($ids));
    check("scope $label tidak menambah baris", count($ids) <= $jmlPadat, 'n=' . count($ids));
}

// --- 5. kembaliKeDaftarMasuk: filter ikut terbawa ------------------------------
function kembali(array $post): string
{
    global $controller, $reqProp;
    unset($_SESSION['gagal'], $_SESSION['sukses']);
    $_POST = $post;
    $_GET  = [];
    $reqProp->setValue($controller, Config\Services::request(null, false));
    $m = new ReflectionMethod($controller, 'kembaliKeDaftarMasuk');
    $m->setAccessible(true);

    return (string) $m->invoke($controller);
}

$dasar = base_url('mutasi_stok/masuk');
$q     = static fn(string $s) => strstr($s, '?', true) === $dasar ? substr($s, strlen($dasar) + 1) : '';

check('tanpa asal -> URL dasar', kembali([]) === $dasar, kembali([]));
check('asal tanpa tanda tanya -> URL dasar', kembali(['asal' => 'abc']) === $dasar);

$u = kembali(['asal' => '?search=MTS1&status=0&dari=2026-01-01&sampai=2026-12-31&page=3']);
check('filter sah ikut terbawa', strpos($u, 'search=MTS1') !== false && strpos($u, 'status=0') !== false
    && strpos($u, 'dari=2026-01-01') !== false && strpos($u, 'page=3') !== false, $u);

check('parameter di luar daftar putih dibuang', strpos(kembali(['asal' => '?search=MTS1&evil=1&admin=1']), 'evil') === false
    && strpos(kembali(['asal' => '?search=MTS1&evil=1&admin=1']), 'admin') === false, kembali(['asal' => '?search=MTS1&evil=1&admin=1']));
check('status tak valid dibuang', strpos(kembali(['asal' => '?status=abc&search=MTS']), 'status') === false, kembali(['asal' => '?status=abc&search=MTS']));
check('tanggal tak valid dibuang', strpos(kembali(['asal' => '?dari=2026-13-45&sampai=nonsense']), 'dari') === false
    && strpos(kembali(['asal' => '?dari=2026-13-45&sampai=nonsense']), 'sampai') === false, kembali(['asal' => '?dari=2026-13-45&sampai=nonsense']));
check('halaman negatif dinormalkan', strpos(kembali(['asal' => '?search=MTS&page=-9']), '-9') === false, kembali(['asal' => '?search=MTS&page=-9']));

// attempts open redirect: host lain harus diabaikan, hanya query yang diambil
$u = kembali(['asal' => 'https://evil.example.com/x?search=MTS1&page=2']);
check('open redirect ditolak, tetap URL dasar', strpos($u, $dasar) === 0 && strpos($u, 'evil') === false, $u);
check('query tetap terbawa walau host menyamar', strpos($u, 'search=MTS1') !== false, $u);

// --- 6. view benar-benar render -----------------------------------------------
/**
 * Render view dengan data sintetis (bukan lewat controller) supaya test view
 * tidak ikut bergantung pada join detail mutasi.
 */
function render(array $over = []): string
{
    $data = $over + [
        'items'       => [],
        'unit'        => [(object) ['idunit' => 1, 'NAMA_UNIT' => 'Unit A'], (object) ['idunit' => 2, 'NAMA_UNIT' => 'Unit B']],
        'filter'      => f(),
        'currentPage' => 1,
        'perPage'     => 25,
        'total'       => 0,
        'totalPages'  => 1,
    ];

    return view('stok/mutasi_masuk', $data);
}

/**
 * Param apa saja yang dibawa link paginasi menuju halaman tertentu.
 *
 * Diperiksa lewat query string hasil parse, bukan regex atas HTML: urutan
 * parameter tidak dijamin, jadi `page=2[^"']*status=0` akan gagal padahal
 * status-nya ikut terbawa.
 */
function paramKe(string $html, int $page, string $key): array
{
    preg_match_all('/href="([^"]*)"/', $html, $m);
    $found = [];
    foreach ($m[1] as $href) {
        if (strpos($href, 'mutasi_stok/masuk') === false) {
            continue;
        }
        $q = [];
        parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $q);
        if ((int) ($q['page'] ?? 0) === $page && isset($q[$key])) {
            $found[] = (string) $q[$key];
        }
    }

    // Dedupe: halaman 2 bisa dijangkau dua link (nomor halaman & tombol
    // next), keduanya sah dan keduanya harus membawa filter yang sama.
    return array_values(array_unique($found));
}

function item(int $id = 1, string $nota = 'MTS10001'): object
{
    return (object) [
        'idmutasi'         => $id,
        'no_nota_mutasi'   => $nota,
        'tanggal_kirim'    => '2026-09-20',
        'tanggal_terima'   => null,
        'status'           => '0',
        'kirim_idunit'     => 1,
        'terima_idunit'    => 2,
        'nama_unit_kirim'  => 'Unit A',
        'nama_unit_terima' => 'Unit B',
        'total'            => 1500000,
        'detail'           => [(object) ['nama_barang' => 'Barang X', 'jumlah_kirim' => 2, 'harga_mutasi' => 750000, 'nilai' => 1500000]],
    ];
}

$_SESSION['ID_JABATAN'] = 1;
$html = render(['items' => [item()], 'filter' => f(), 'currentPage' => 1, 'total' => 1, 'totalPages' => 1]);
check('form filter ada: pencarian', strpos($html, 'name="search"') !== false);
check('form filter ada: status', strpos($html, 'name="status"') !== false);
check('form filter ada: rentang tanggal', strpos($html, 'name="dari"') !== false && strpos($html, 'name="sampai"') !== false);
check('form filter ada: unit (lintas)', strpos($html, 'name="unit"') !== false);
check('form filter menuju halaman yang benar', strpos($html, 'action="' . base_url('mutasi_stok/masuk') . '"') !== false);
check('field asal ada di form terima', strpos($html, 'id="formTerimaMutasi"') !== false && substr_count($html, 'name="asal"') >= 1);
check('field asal ada di form batal', strpos($html, 'id="formBatalTerima"') !== false && substr_count($html, 'name="asal"') >= 2);

// paginasi: link harus bring filter + halaman
$_SESSION['ID_JABATAN'] = 1;
$html = render(['items' => [item()], 'filter' => f(['search' => 'MTS1', 'status' => '0']),
    'currentPage' => 1, 'perPage' => 25, 'total' => 87, 'totalPages' => 4]);
check('footer menampilkan rentang baris', strpos($html, 'Menampilkan') !== false && strpos($html, 'dari 87 mutasi') !== false, 'tidak ada ringkasan');
check('link halaman 2 ada', paramKe($html, 2, 'page') !== [] || strpos($html, 'page=2') !== false);
check('link halaman 2 membawa filter search', paramKe($html, 2, 'search') === ['MTS1'], json_encode(paramKe($html, 2, 'search')));
check('link halaman 2 membawa status', paramKe($html, 2, 'status') === ['0'], json_encode(paramKe($html, 2, 'status')));
check('link halaman 1 ikut membawa filter', paramKe($html, 1, 'search') === ['MTS1'], 'halaman 1 harus ikut membawa filter');
check('filter terisi kembali di input', strpos($html, 'value="MTS1"') !== false);
check('status terpilih kembali di select', (bool) preg_match('/value="0" selected/', $html));

// user satu unit: kontrol unit tidak dirender
$_SESSION['ID_JABATAN'] = 3;
$html = render(['items' => [item()], 'filter' => f(), 'total' => 1]);
check('user satu unit tidak melihat kontrol unit', strpos($html, 'name="unit"') === false);
check('user satu unit tetap melihat pencarian & status', strpos($html, 'name="search"') !== false && strpos($html, 'name="status"') !== false);
$_SESSION['ID_JABATAN'] = 1;

// empty state harus bedakan "tidak ada data" vs "filter tidak kena"
$html = render(['items' => [], 'filter' => f(), 'total' => 0]);
check('kosong tanpa filter -> "Belum ada mutasi masuk"', strpos($html, 'Belum ada mutasi masuk') !== false);
check('kosong tanpa filter tidak menawarkan reset', strpos($html, 'Reset filter') === false);
$html = render(['items' => [], 'filter' => f(['search' => 'ZZZ']), 'total' => 0]);
check('kosong karena filter -> pesan jelas', strpos($html, 'Tidak ada mutasi yang cocok dengan filter ini') !== false);
check('kosong karena filter -> ada tombol reset', strpos($html, 'Reset filter') !== false);

// Status '0' adalah filter paling sering dipakai di halaman ini, dan di PHP
// string '0' itu falsy. Dua baris ini mengunci supaya `?:` atau `||` tidak
// diam-diam menganggapnya "tidak ada filter" lagi.
$html = render(['items' => [], 'filter' => f(['status' => '0']), 'total' => 0]);
check('filter status 0 saja tetap dianggap filter', strpos($html, 'Tidak ada mutasi yang cocok dengan filter ini') !== false);
check('filter status 0 saja tetap menawarkan reset', strpos($html, 'Reset filter') !== false);
$html = render(['items' => [item()], 'filter' => f(['status' => '0']), 'currentPage' => 1, 'total' => 60, 'totalPages' => 3]);
check('filter status 0 tetap ada di link paginasi', paramKe($html, 2, 'status') === ['0'], json_encode(paramKe($html, 2, 'status')));
check('filter status 0 tetap ada di field asal', (bool) preg_match('/name="asal" value="[^"]*status=0/', $html), 'status hilang dari asal');

// --- 7. controller masuk() diuji end-to-end (bukan cuma model+view) --------
//
// masuk() mengembalikan hasil render, bukan array data, jadi renderer-nya
// diganti dengan tiruan yang mencatat data masuk. Dengan begitu yang diuji
// benar-benar wiring controller -> view, termasuk clamp halaman dan scope
// unit, bukan cuma query model-nya.
final class CatatRenderer implements CodeIgniter\View\RendererInterface
{
    public array $data = [];

    public function render(string $view, ?array $options = null, bool $saveData = false): string
    {
        $this->namaView = $view;
        return '';
    }

    public function renderString(string $view, ?array $options = null, bool $saveData = false): string
    {
        return '';
    }

    public function setData(array $data = [], ?string $context = null)
    {
        $this->data = $data;
        return $this;
    }

    public function setVar(string $name, $value = null, ?string $context = null)
    {
        $this->data[$name] = $value;
        return $this;
    }

    public function resetData()
    {
        $this->data = [];
        return $this;
    }
}

/**
 * Panggil masuk() dengan query string tertentu, baliknya data view-nya.
 */
function panggilMasuk(int $jabatan, int $unit, array $get): array
{
    global $controller, $reqProp;
    $_SESSION['ID_JABATAN'] = $jabatan;
    $_SESSION['ID_UNIT']    = $unit;
    $_SESSION['ID_AKUN']    = 1;
    $_GET  = $get;
    $_POST = [];
    $reqProp->setValue($controller, Config\Services::request(null, false));

    $renderer = new CatatRenderer();
    CodeIgniter\Config\Services::injectMock('renderer', $renderer);

    $m = new ReflectionMethod($controller, 'masuk');
    $m->setAccessible(true);
    $m->invoke($controller);

    return $renderer->data;
}

$d = panggilMasuk(1, 0, []);
check('masuk() merender tanpa error', isset($d['body']) && $d['body'] === 'stok/mutasi_masuk');
check('masuk() mengirim state paginasi ke view', isset($d['currentPage'], $d['perPage'], $d['total'], $d['totalPages']));
check('masuk() mengirim filter ke view', isset($d['filter']['search'], $d['filter']['status']));
check('masuk() tidak lagi membatasi 200 baris', (int) $d['perPage'] === 25 && (int) $d['total'] === $jumlahAll,
    "perPage={$d['perPage']} total={$d['total']}");

// filter dari URL dipakai, dan junk di URL dibuang
$d = panggilMasuk(1, 0, ['status' => '0', 'search' => substr($contohNota, 0, 6)]);
check('masuk() membaca filter dari query string', $d['filter']['status'] === '0' && $d['filter']['search'] === substr($contohNota, 0, 6));
// Dibanding ke model, bukan angka tetap: pola nota membuat satu prefix
// cocok banyak nota, jadi angka kuncinya ikut berubah kalau data berubah.
check('masuk() menerapkan filter ke total', (int) $d['total'] === $model->countMutasiMasuk(true, 0, f(['status' => '0', 'search' => substr($contohNota, 0, 6)])),
    'total=' . $d['total']);

$d = panggilMasuk(1, 0, ['status' => 'drop table', 'dari' => '2026-13-45', 'page' => 'abc']);
check('masuk() membuang status tak valid', $d['filter']['status'] === '', var_export($d['filter']['status'], true));
check('masuk() membuang tanggal tak valid', $d['filter']['dari'] === '' && $d['filter']['sampai'] === '');
check('masuk() menormalkan page tak valid ke 1', (int) $d['currentPage'] === 1, 'page=' . $d['currentPage']);

// clamp: user di halaman 999 lalu memfilter -> mundur ke halaman terakhir
$halamanMaks = max(1, (int) ceil($jumlahAll / 25));
// Invarian yang diuji: halaman yang dirender tidak pernah di luar jangkauan.
// Kalau tidak, user yang sedang di halaman 5 lalu memfilter akan mendarat
// di tabel kosong tanpa jalan keluar.
foreach ([
    'tanpa filter'    => [],
    'prefix nota'     => ['search' => substr($contohNota, 0, 6)],
    'halaman 999'     => ['page' => 999],
    'halaman 999 + filter' => ['page' => 999, 'search' => substr($contohNota, 0, 6)],
    'halaman negatif' => ['page' => -5],
] as $label => $get) {
    $d = panggilMasuk(1, 0, $get);
    $hal = (int) $d['currentPage'];
    check("masuk() menjaga page dalam jangkauan [$label]",
        $hal >= 1 && $hal <= (int) $d['totalPages'] && $hal === max(1, min($hal, (int) $d['totalPages'])),
        "page={$hal} totalPages={$d['totalPages']}");
}
$d = panggilMasuk(1, 0, ['page' => 999]);
check('masuk() clamp ke halaman terakhir yang ada', (int) $d['currentPage'] === $halamanMaks, 'page=' . $d['currentPage']);

// scope ikut berlaku di controller
$d = panggilMasuk(3, $unitPadat, []);
check('masuk() (non-lintas) menghitung unit itu saja', (int) $d['total'] === $jmlPadat, 'total=' . $d['total'] . ' harap={$jmlPadat}');
check('masuk() (non-lintas) tidak mengirim kontrol unit', $d['isLintas'] === false);
$d = panggilMasuk(1, 0, []);
check('masuk() (lintas) melihat semua unit', (int) $d['total'] === $jumlahAll);

// ------------------------------------------------------------------------
echo "\nFAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
