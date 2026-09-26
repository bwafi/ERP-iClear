<?php
/**
 * Smoke test render view modul Hutang Piutang (Fase 4).
 *
 * Merender setiap view body langsung (tanpa template global) untuk menangkap
 * error runtime: variabel hilang, method salah, dsb. Data uji 'SMOKE-HP-VIEW'
 * dibuat lalu dihapus.
 *
 * Usage: php74 app/Scripts/hutang_piutang_view_smoke.php
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

$config = new \Config\App();
$uri = new \CodeIgniter\HTTP\SiteURI($config, 'hutangpiutang', 'localhost', 'http');
$request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
\CodeIgniter\Config\Services::injectMock('request', $request);
$session = \Config\Services::session();
$db = \Config\Database::connect();

$unit = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getRow();
$pelanggan = $db->table('pelanggan')->orderBy('id_pelanggan', 'ASC')->get()->getRow();
$pegawai = $db->table('akun')->groupStart()->where('deleted', 0)->orWhere('deleted IS NULL')->groupEnd()->orderBy('ID_AKUN', 'ASC')->get()->getRow();

if (!$unit || !$pelanggan || !$pegawai) {
    fwrite(STDERR, "Data master tidak tersedia.\n");
    exit(2);
}

$session->set([
    'ID_AKUN'    => (int) $pegawai->ID_AKUN,
    'ID_JABATAN' => 0,
    'ID_UNIT'    => (int) $unit->idunit,
    'NAMA_UNIT'  => $unit->NAMA_UNIT,
    'NAMED'      => 'smoke',
    'logged_in'  => true,
]);

use App\Services\Finance\HutangPiutangService;

$service = new HutangPiutangService();

// --- data uji ---
$create = $service->createPosition([
    'jenis' => 'piutang',
    'sumber_tipe' => HutangPiutangService::SUMBER_PIUTANG_PELANGGAN,
    'pihak_tipe' => 'pelanggan',
    'pihak_id' => (int) $pelanggan->id_pelanggan,
    'tanggal' => date('Y-m-d'),
    'jatuh_tempo' => date('Y-m-d', strtotime('+7 day')),
    'total' => '1.500.000',
    'uraian' => 'SMOKE-HP-VIEW',
    'keterangan' => 'uji render',
    'unit_id' => (int) $unit->idunit,
], (int) $pegawai->ID_AKUN);

ok('create data uji', !empty($create['success']), $create['message'] ?? '');
$id = (int) ($create['id'] ?? 0);

// bayar sebagian agar view pembayaran terisi
if ($id) {
    $service->bayar($id, [
        'tanggal_bayar' => date('Y-m-d'),
        'jumlah_bayar' => 500000,
        'bayar_tunai' => 500000,
        'bayar_bank' => 0,
        'keterangan' => 'cicilan uji',
    ], (int) $pegawai->ID_AKUN);
}

function render($body, array $data)
{
    ob_start();
    try {
        $html = view($body, $data);
        ob_end_clean();
        return $html;
    } catch (\Throwable $e) {
        ob_end_clean();
        return 'ERROR: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    }
}

$units = $service->getUnitOptions();
$bank = $service->getBankOptions();
$pelangganOpt = $service->getPelangganOptions();
$pegawaiOpt = $service->getPegawaiOptions();
$suplierOpt = $service->getSuplierOptions();
$teknisiOpt = $service->getTeknisiOptions();
$ringkasan = $service->getRingkasan();
$row = $id ? $service->getById($id) : null;

$cases = [
    'hutangpiutang/dashboard' => [
        'ringkasan' => $ringkasan,
        'recent' => $service->listPositions(['bulan' => date('Y-m')]),
        'units' => $units,
        'unit_id' => null,
        'bulan' => date('Y-m'),
        'can_input' => true,
    ],
    'hutangpiutang/index (piutang)' => [
        'jenis' => 'piutang',
        'rows' => $service->listPositions(['jenis' => 'piutang']),
        'filters' => ['jenis' => 'piutang'],
        'units' => $units,
        'unit_id' => null,
        'can_input' => true,
        'input_types' => HutangPiutangService::inputTypes(),
        'pelanggan' => $pelangganOpt,
        'pegawai' => $pegawaiOpt,
        'suplier' => $suplierOpt,
        'teknisi' => $teknisiOpt,
        'bank' => $bank,
    ],
    'hutangpiutang/index (hutang)' => [
        'jenis' => 'hutang',
        'rows' => $service->listPositions(['jenis' => 'hutang']),
        'filters' => ['jenis' => 'hutang'],
        'units' => $units,
        'unit_id' => null,
        'can_input' => true,
        'input_types' => HutangPiutangService::inputTypes(),
        'pelanggan' => $pelangganOpt,
        'pegawai' => $pegawaiOpt,
        'suplier' => $suplierOpt,
        'teknisi' => $teknisiOpt,
        'bank' => $bank,
    ],
    'hutangpiutang/form' => [
        'input_types' => HutangPiutangService::inputTypes(),
        'pelanggan' => $pelangganOpt,
        'pegawai' => $pegawaiOpt,
        'suplier' => $suplierOpt,
        'teknisi' => $teknisiOpt,
        'units' => $units,
        'unit_id' => (int) $unit->idunit,
    ],
    'hutangpiutang/detail' => [
        'row' => $row,
        'unit' => $unit,
        'pembayaran' => $id ? $service->getPembayaran($id) : [],
        'kompensasi' => $id ? $service->getKompensasi($id) : [],
        'lawan_kompensasi' => $row ? $service->getLawanKompensasi($row) : [],
        'bank' => $bank,
        'can_input' => true,
    ],
    'hutangpiutang/riwayat' => [
        'rows' => $service->getRiwayatPembayaran(['bulan' => date('Y-m')]),
        'filters' => ['bulan' => date('Y-m')],
        'units' => $units,
        'unit_id' => null,
    ],
    'cetak/bukti_hutang_piutang' => [
        'row' => $row,
        'unit' => $unit,
        'pembayaran' => $id ? $service->getPembayaran($id) : [],
    ],
];

foreach ($cases as $body => $data) {
    $path = $body;
    if (strpos($body, ' ') !== false) {
        $path = explode(' ', $body)[0];
    }
    $html = render($path, $data);
    $bad = strpos($html, 'ERROR:') === 0;
    ok($body . ' render', !$bad && strlen(trim($html)) > 0, $bad ? $html : '');
}

// --- cleanup ---
if ($id) {
    $payIds = array_column($db->table('pembayaran_hutang_piutang')->select('id')->where('hutang_piutang_id', $id)->get()->getResultArray(), 'id');
    if ($payIds) {
        $db->table('jurnal')->where('tabel_referensi', 'pembayaran_hutang_piutang')->whereIn('id_referensi', $payIds)->delete();
    }
    $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->where('id_referensi', $id)->delete();
    $db->table('pembayaran_hutang_piutang')->where('hutang_piutang_id', $id)->delete();
    $db->table('hutang_piutang')->where('id', $id)->delete();
    echo "  CLEAN  data uji #{$id} dihapus\n";
}

echo "\n=== RESULT: {$pass} PASS / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
