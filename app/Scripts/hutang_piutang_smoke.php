<?php
/**
 * Smoke test core HutangPiutangService (Fase 3).
 *
 * Menguji: createPosition, validasi bayar (overpay / lunas / partial),
 * settlement kasbon payroll + idempotency, dan ringkasan anti double counting.
 * Data uji ditandai 'SMOKE-HP' dan dihapus di akhir.
 *
 * Usage: php74 app/Scripts/hutang_piutang_smoke.php
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
    fwrite(STDERR, "Data master (unit/pelanggan/akun) tidak tersedia untuk uji.\n");
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

$svc = new HutangPiutangService();
$createdIds = [];

try {
    // ---------- createPosition: piutang pelanggan ----------
    $r1 = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_PIUTANG_PELANGGAN,
        'pihak_tipe' => 'pelanggan',
        'pihak_id' => (int) $pelanggan->id_pelanggan,
        'tanggal' => date('Y-m-d'),
        'jatuh_tempo' => date('Y-m-d', strtotime('+14 days')),
        'total' => '1.000.000',
        'uraian' => 'SMOKE-HP piutang pelanggan',
    ], (int) $pegawai->ID_AKUN);
    ok('createPosition piutang pelanggan', $r1['success'] && !empty($r1['id']), $r1['message']);
    if (!empty($r1['id'])) { $createdIds[] = $r1['id']; }

    $pos1 = $r1['id'] ? $svc->getById($r1['id']) : null;
    ok('  status awal belum_lunas & sisa=total', $pos1 && $pos1->status === HutangPiutangService::STATUS_BELUM && (int) $pos1->sisa === 1000000);

    // ---------- createPosition: kasbon pegawai ----------
    $r2 = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
        'pihak_tipe' => 'pegawai',
        'pihak_id' => (int) $pegawai->ID_AKUN,
        'tanggal' => date('Y-m-d'),
        'jatuh_tempo' => null,
        'total' => 500000,
        'uraian' => 'SMOKE-HP kasbon',
    ], (int) $pegawai->ID_AKUN);
    ok('createPosition kasbon', $r2['success'] && !empty($r2['id']), $r2['message']);
    if (!empty($r2['id'])) { $createdIds[] = $r2['id']; }

    // ---------- validasi: overpay ----------
    $over = $svc->bayar($r1['id'], ['jumlah_bayar' => 1200000, 'bayar_tunai' => 1200000]);
    ok('bayar overpay ditolak', !$over['success'], $over['message']);

    // ---------- validasi: nominal 0 ----------
    $zero = $svc->bayar($r1['id'], ['jumlah_bayar' => 0]);
    ok('bayar nominal 0 ditolak', !$zero['success'], $zero['message']);

    // ---------- bayar partial ----------
    $p1 = $svc->bayar($r1['id'], ['jumlah_bayar' => 400000, 'bayar_tunai' => 400000]);
    ok('bayar partial sukses', $p1['success'] && $p1['sisa'] === 600000 && $p1['status'] === HutangPiutangService::STATUS_SEBAGIAN, $p1['message']);

    // ---------- bayar pelunasan ----------
    $p2 = $svc->bayar($r1['id'], ['jumlah_bayar' => 600000, 'bayar_bank' => 600000, 'bank_idbank' => null]);
    ok('bayar pelunasan sukses', $p2['success'] && $p2['sisa'] === 0 && $p2['status'] === HutangPiutangService::STATUS_LUNAS, $p2['message']);

    // ---------- transaksi lunas tidak boleh dibayar ----------
    $p3 = $svc->bayar($r1['id'], ['jumlah_bayar' => 100]);
    ok('bayar setelah lunas ditolak', !$p3['success'], $p3['message']);

    // ---------- settlement kasbon dari payroll (potong penuh) ----------
    $payrollId = 999000001;
    $s1 = $svc->settleKasbonFromPayroll($payrollId, (int) $pegawai->ID_AKUN, (int) $unit->idunit, (int) $pegawai->ID_AKUN);
    ok('settle kasbon potong penuh', $s1['success'] && $s1['potongan'] === 500000, $s1['message']);

    $kasbonAfter = $svc->getById($r2['id']);
    ok('  kasbon jadi lunas', $kasbonAfter && $kasbonAfter->status === HutangPiutangService::STATUS_LUNAS && (int) $kasbonAfter->sisa === 0);

    // ---------- idempotency payroll ----------
    $s2 = $svc->settleKasbonFromPayroll($payrollId, (int) $pegawai->ID_AKUN, (int) $unit->idunit, (int) $pegawai->ID_AKUN);
    ok('settle payroll kedua tidak double', $s2['success'] && $s2['potongan'] === 0, $s2['message']);

    $bayarRows = $db->table('pembayaran_hutang_piutang')->where('referensi_id', $payrollId)->countAllResults();
    ok('  hanya 1 baris pembayaran payroll', $bayarRows === 1, 'rows=' . $bayarRows);

    // ---------- ringkasan anti double counting ----------
    $before = $svc->getRingkasan([(int) $unit->idunit]);
    $r3 = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_PIUTANG_PELANGGAN,
        'pihak_tipe' => 'pelanggan',
        'pihak_id' => (int) $pelanggan->id_pelanggan,
        'tanggal' => date('Y-m-d'),
        'total' => 2000000,
        'uraian' => 'SMOKE-HP ringkasan',
    ], (int) $pegawai->ID_AKUN);
    if (!empty($r3['id'])) { $createdIds[] = $r3['id']; }
    $after = $svc->getRingkasan([(int) $unit->idunit]);
    ok('ringkasan: +2jt tepat sekali (tanpa double)', $after['total_piutang'] - $before['total_piutang'] === 2000000, 'delta=' . ($after['total_piutang'] - $before['total_piutang']));

    // ---------- listPositions ----------
    $list = $svc->listPositions(['unit_id' => (int) $unit->idunit, 'q' => 'SMOKE-HP']);
    ok('listPositions menemukan data uji', count($list) >= 3, 'count=' . count($list));
} catch (\Throwable $e) {
    ok('exception', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    // Bersihkan data uji
    if (!empty($createdIds)) {
        $payIds = array_column($db->table('pembayaran_hutang_piutang')->select('id')->whereIn('hutang_piutang_id', $createdIds)->get()->getResultArray(), 'id');
        if ($payIds) {
            $db->table('jurnal')->where('tabel_referensi', 'pembayaran_hutang_piutang')->whereIn('id_referensi', $payIds)->delete();
        }
        $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->whereIn('id_referensi', $createdIds)->delete();
        $db->table('pembayaran_hutang_piutang')->whereIn('hutang_piutang_id', $createdIds)->delete();
        $db->table('hutang_piutang')->whereIn('id', $createdIds)->delete();
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
