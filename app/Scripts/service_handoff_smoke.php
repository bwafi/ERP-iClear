<?php
/**
 * Smoke test alur /service untuk teknisi (36) dan admin/kasir cabang (35).
 *
 * Keduanya boleh menyelesaikan langkah 1-4 (Pelanggan, Kerusakan, Sparepart,
 * Pembayaran) di halaman yang sama, jadi TIDAK ada lagihandoff wajib ke
 * /proses_service setelah langkah 2. Yang diuji di sini:
 *
 *   1. konstanta jabatan cocok dengan tabel `jabatan` (kasir 35, teknisi 36)
 *   2. rail /service tidak mengunci langkah 3-4 untuk jabatan 35 maupun 36
 *   3. session 'idservice' TETAP setelah langkah 2 supaya bisa lanjut 3-4
 *   4. service/clear_session hanya melepas session, tidak menghapus ticket
 *   5. form /service menyunting ticket yang sedang jalan (endpoint update)
 *      dan prefill tipe_hp supaya tidak wiped saat disimpan ulang
 *
 * Jalankan: php app/Scripts/service_handoff_smoke.php
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
$db       = Config\Database::connect();
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

$controller = new App\Controllers\Service();
$reqProp   = new ReflectionProperty(CodeIgniter\Controller::class, 'request');
$reqProp->setAccessible(true);
$kerusakanM = new ReflectionMethod($controller, 'insert_kerusakan');
$kerusakanM->setAccessible(true);
$clearM     = new ReflectionMethod($controller, 'clear_session');
$clearM->setAccessible(true);

$fungsiIds = array_column($db->table('fungsi')->orderBy('idfungsi', 'ASC')->limit(2)->get()->getResultArray(), 'idfungsi');
if (count($fungsiIds) < 1) {
    echo "SKIP: tabel fungsi kosong, tidak bisa membuat kerusakan\n";
    exit(0);
}

/** Ticket + pelanggan sementara; kembalikan [idservice, idpelanggan]. */
function buatTicket($db, array $fungsiIds): array
{
    $db->table('pelanggan')->insert(['nama' => 'Smoke Handoff', 'no_hp' => '081200000001', 'deleted' => 0]);
    $pid = (int) $db->insertID();
    $db->table('service')->insert([
        'no_service'              => 'SRV-HANDOFF-' . random_int(100000, 999999),
        'no_hp'                   => '081200000001',
        'tipe_hp'                 => 'Smoke Tipe',
        'pelanggan_id_pelanggan'  => $pid,
        'unit_idunit'             => 1,
        'status_service'          => 1,
        'status_proses'           => 1,
        'created_at'              => date('Y-m-d H:i:s'),
    ]);

    return [(int) $db->insertID(), $pid];
}

function bersihkan($db, int $idservice, int $pid): void
{
    $db->table('service_kerusakan')->where('service_idservice', $idservice)->delete();
    $db->table('service')->where('idservice', $idservice)->delete();
    $db->table('pelanggan')->where('id_pelanggan', $pid)->delete();
}

// ------------------------- 1. konstanta jabatan cocok dengan tabel jabatan
$jabKasir   = (int) $db->table('jabatan')->where('NAMA_JABATAN', 'ADMIN / KASIR')->select('ID_JABATAN')->get()->getRow('ID_JABATAN');
$jabTeknisi = (int) $db->table('jabatan')->where('NAMA_JABATAN', 'Teknisi')->select('ID_JABATAN')->get()->getRow('ID_JABATAN');

check('konstanta JABATAN_KASIR = ' . $jabKasir . ' (cocok DB)', JABATAN_KASIR === $jabKasir, 'konstanta=' . JABATAN_KASIR);
check('konstanta JABATAN_TEKNISI = ' . $jabTeknisi . ' (cocok DB)', JABATAN_TEKNISI === $jabTeknisi, 'konstanta=' . JABATAN_TEKNISI);
check('kasir dan teknisi punya ID berbeda', JABATAN_KASIR !== JABATAN_TEKNISI);

// ------------------------- 2. rail /service tidak mengunci step 3-4 per jabatan
$viewService = file_get_contents(APPPATH . 'Views/transaksi/service.php');
// Step 1 (pelanggan) memang tidak pernah terkunci: formnya adalah entry point
// itself, jadi hanya langkah 2-4 yang punya syarat "belum ada ticket".
$polaLock = 'empty($idservice) ? \'disabled-tab is-locked\' : \'\' ?>';

check(
    'rail: is-locked hanya bergantung pada ada/tidaknya ticket, bukan jabatan',
    substr_count($viewService, $polaLock) === 3 && strpos($viewService, 'ID_JABATAN') === false,
    'sisa rujukan ID_JABATAN: ' . substr_count($viewService, 'ID_JABATAN')
);
check('rail: step 1 (pelanggan) selalu terbuka', strpos($viewService, 'service-rail-item is-active" data-step="pelanggan') !== false);
check('rail: tidak ada lagi blok JS role-lock', strpos($viewService, 'isRestricted') === false);

// ---------------------------------------- 3. teknisi (jabatan 36) lanjut 1-4
[$sid, $pid] = buatTicket($db, $fungsiIds);

$_SESSION = ['ID_JABATAN' => 36, 'ID_AKUN' => 1, 'ID_UNIT' => 1, 'idservice' => $sid];
$_POST     = ['idservice_k' => (string) $sid, 'fungsi' => $fungsiIds, 'keterangan' => []];
$_GET      = [];
$reqProp->setValue($controller, Config\Services::request(null, false));
$resTeknisi = $kerusakanM->invoke($controller);

check('teknisi: session idservice TETAP (bisa lanjut step 3-4)', (int) session('idservice') === $sid, var_export(session('idservice'), true));
check('teknisi: ticket tidak dihapus', $db->table('service')->where('idservice', $sid)->countAllResults() === 1);
check(
    'teknisi: kerusakan tersimpan',
    $db->table('service_kerusakan')->where('service_idservice', $sid)->countAllResults() === count($fungsiIds)
);
check(
    'teknisi: tetap di alur /service, bukan lempar ke /proses_service',
    strpos((string) $resTeknisi->getHeaderLine('Location'), '/cetak/invoice_service/') !== false,
    (string) $resTeknisi->getHeaderLine('Location')
);

// ---------------------------------------- 2. kasir/admin cabang session tetap
[$sid2, $pid2] = buatTicket($db, $fungsiIds);

$_SESSION = ['ID_JABATAN' => 35, 'ID_AKUN' => 1, 'ID_UNIT' => 1, 'idservice' => $sid2];
$_POST     = ['idservice_k' => (string) $sid2, 'fungsi' => $fungsiIds, 'keterangan' => []];
$reqProp->setValue($controller, Config\Services::request(null, false));
$resKasir = $kerusakanM->invoke($controller);

check('kasir cabang: session idservice tetap', (int) session('idservice') === $sid2, var_export(session('idservice'), true));
check('kasir cabang: kerusakan tetap tersimpan', $db->table('service_kerusakan')->where('service_idservice', $sid2)->countAllResults() === count($fungsiIds));
check(
    'kasir cabang: redirect invoice, langkah 3-4 menyusul',
    strpos((string) $resKasir->getHeaderLine('Location'), '/cetak/invoice_service/') !== false,
    (string) $resKasir->getHeaderLine('Location')
);

// ---------------------------------------- 3. clear_session tidak destruktif
$_SESSION = ['ID_JABATAN' => 36, 'ID_AKUN' => 1, 'ID_UNIT' => 1, 'idservice' => $sid2];
$_POST     = [];
$_GET      = [];
$reqProp->setValue($controller, Config\Services::request(null, false));
$clearM->invoke($controller);

check('clear_session: session lepas', session('idservice') === null);
check('clear_session: ticket tetap ada', $db->table('service')->where('idservice', $sid2)->countAllResults() === 1);
check(
    'clear_session: pesan menyebut ticket tidak dihapus',
    is_string(session('sukses')) && strpos(session('sukses'), 'tidak dihapus') !== false,
    var_export(session('sukses'), true)
);

// ---------------------------------------- 4. form /service pindah ke update
function renderFormPelanggan($idservice, $service): string
{
    $akun = (object) ['ID_AKUN' => 1, 'NAMA_AKUN' => 'Smoke'];
    $provinsi = [(object) ['name' => 'Jawa Timur']];

    return view('transaksi/table/pelanggan_table', [
        'akun'                  => $akun,
        'provinsi'              => $provinsi,
        'idservice'             => $idservice,
        'old_service_pelanggan' => $service,
    ]);
}

$svc = $db->table('service')->where('idservice', $sid)->get()->getRow();
$svc->nama       = 'Smoke Handoff';
$svc->no_hp      = '081200000001';
$svc->provinsi   = 'Jawa Timur';
$svc->kabupaten  = 'Probolinggo';
$svc->kecamatan  = 'Probolinggo';
$svc->id_pelanggan = $pid;

$htmlBaru = renderFormPelanggan(null, null);
check('form baru -> insert/pelanggan_service', strpos($htmlBaru, 'insert/pelanggan_service') !== false);
check('form baru -> bukan update', strpos($htmlBaru, 'update/pelanggan_service') === false);

$htmlJalan = renderFormPelanggan($sid, $svc);
check('form ticket jalan -> update/pelanggan_service', strpos($htmlJalan, 'update/pelanggan_service') !== false);
check('form ticket jalan -> bukan insert', strpos($htmlJalan, 'action="' . base_url('insert/pelanggan_service') . '"') === false);
check(
    'form ticket jalan: tipe_hp terisi ulang (tidak wiped saat simpan)',
    strpos($htmlJalan, 'name="tipe_hp"') !== false && strpos($htmlJalan, 'value="Smoke Tipe"') !== false
);
check('form ticket jalan: return_to=service terkirim', strpos($htmlJalan, 'name="return_to" value="service"') !== false);

bersihkan($db, $sid, $pid);
bersihkan($db, $sid2, $pid2);

echo $fail === 0 ? "\nSemua PASS.\n" : "\n{$fail} FAIL.\n";
exit($fail === 0 ? 0 : 1);
