<?php
/**
 * Smoke test Fase 5 — posting jurnal & integrasi (Hutang Piutang).
 *
 * Menguji:
 *  - createPosition memposting jurnal berimbang (Dr/Kr).
 *  - bayar memposting jurnal per metode (tunai/bank).
 *  - settleKasbonFromPayroll memposting jurnal potongan gaji.
 *  - syncFromPiutangLegacy membuat baris projection.
 * Data uji ditandai SMOKE-HP5 dan dihapus (termasuk jurnal).
 *
 * Usage: php74 app/Scripts/hutang_piutang_jurnal_smoke.php
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

$svc = new HutangPiutangService();
$inputBy = (int) $pegawai->ID_AKUN;
$unitId = (int) $unit->idunit;

$refs = []; // [tabel_referensi => [id, ...]]
function jurnalRows($db, $tabel, $id)
{
    return $db->table('jurnal')->where('tabel_referensi', $tabel)->where('id_referensi', $id)->get()->getResult();
}
function balanced(array $rows): bool
{
    $d = 0; $k = 0;
    foreach ($rows as $r) { $d += (int) $r->debet; $k += (int) $r->kredit; }
    return $d === $k && $d > 0;
}

// ------------------------------------------------------------------
// 1. createPosition piutang pelanggan -> jurnal Dr Piutang / Cr Pendapatan
// ------------------------------------------------------------------
$c1 = $svc->createPosition([
    'jenis' => 'piutang',
    'sumber_tipe' => HutangPiutangService::SUMBER_PIUTANG_PELANGGAN,
    'pihak_tipe' => 'pelanggan',
    'pihak_id' => (int) $pelanggan->id_pelanggan,
    'tanggal' => date('Y-m-d'),
    'total' => 750000,
    'uraian' => 'SMOKE-HP5',
    'unit_id' => $unitId,
], $inputBy);
ok('createPosition piutang', !empty($c1['success']), $c1['message'] ?? '');
$id1 = (int) ($c1['id'] ?? 0);
$j1 = jurnalRows($db, 'hutang_piutang', $id1);
ok('  jurnal piutang input 2 baris', count($j1) === 2, 'rows=' . count($j1));
ok('  jurnal piutang berimbang', balanced($j1));
$akunJ1 = array_map(static fn($r) => $r->no_akun, $j1);
ok('  akun sesuai (1020101000 & 7019900000)', in_array('1020101000', $akunJ1) && in_array('7019900000', $akunJ1), implode(',', $akunJ1));

// ------------------------------------------------------------------
// 2. bayar partial tunai + bank -> jurnal per metode
// ------------------------------------------------------------------
$pay = $svc->bayar($id1, [
    'tanggal_bayar' => date('Y-m-d'),
    'jumlah_bayar' => 300000,
    'bayar_tunai' => 200000,
    'bayar_bank' => 100000,
    'keterangan' => 'SMOKE-HP5',
], $inputBy);
ok('bayar partial', !empty($pay['success']), $pay['message'] ?? '');
$payId = (int) ($db->table('pembayaran_hutang_piutang')->where('hutang_piutang_id', $id1)->orderBy('id', 'DESC')->get()->getRow()->id ?? 0);
$j2 = jurnalRows($db, 'pembayaran_hutang_piutang', $payId);
ok('  jurnal bayar 4 baris (2 metode x 2)', count($j2) === 4, 'rows=' . count($j2));
ok('  jurnal bayar berimbang', balanced($j2));

// ------------------------------------------------------------------
// 2b. bayar tanpa rincian metode -> default tunai, jurnal tetap ada
// ------------------------------------------------------------------
$pay2 = $svc->bayar($id1, [
    'tanggal_bayar' => date('Y-m-d'),
    'jumlah_bayar' => 100000,
    'keterangan' => 'SMOKE-HP5 tanpa rincian',
], $inputBy);
ok('bayar tanpa rincian metode', !empty($pay2['success']), $pay2['message'] ?? '');
$payId2 = (int) ($db->table('pembayaran_hutang_piutang')->where('hutang_piutang_id', $id1)->orderBy('id', 'DESC')->get()->getRow()->id ?? 0);
$j2b = jurnalRows($db, 'pembayaran_hutang_piutang', $payId2);
ok('  jurnal default tunai 2 baris & berimbang', count($j2b) === 2 && balanced($j2b), 'rows=' . count($j2b));

// ------------------------------------------------------------------
// 3. createPosition kasbon -> jurnal Dr Piutang Pegawai / Cr Kas
// ------------------------------------------------------------------
$c2 = $svc->createPosition([
    'jenis' => 'piutang',
    'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
    'pihak_tipe' => 'pegawai',
    'pihak_id' => $inputBy,
    'tanggal' => date('Y-m-d'),
    'total' => 400000,
    'uraian' => 'SMOKE-HP5',
    'unit_id' => $unitId,
], $inputBy);
ok('createPosition kasbon', !empty($c2['success']), $c2['message'] ?? '');
$id2 = (int) ($c2['id'] ?? 0);
$j3 = jurnalRows($db, 'hutang_piutang', $id2);
ok('  jurnal kasbon berimbang', balanced($j3));

// ------------------------------------------------------------------
// 4. settle kasbon payroll -> jurnal Dr Utang Gaji / Cr Piutang Pegawai
// ------------------------------------------------------------------
$payrollId = 99000000 + random_int(1, 999);
$set = $svc->settleKasbonFromPayroll($payrollId, $inputBy, $unitId, $inputBy);
ok('settle kasbon payroll', !empty($set['success']), $set['message'] ?? '');
$setPayId = (int) ($db->table('pembayaran_hutang_piutang')->where('referensi_tipe', 'finance_payroll')->where('referensi_id', $payrollId)->get()->getRow()->id ?? 0);
$j4 = jurnalRows($db, 'pembayaran_hutang_piutang', $setPayId);
ok('  jurnal potong payroll berimbang', balanced($j4), 'rows=' . count($j4));

// ------------------------------------------------------------------
// 5. syncFromPiutangLegacy -> baris projection
// ------------------------------------------------------------------
$legacy = $db->table('piutang')->orderBy('idpiutang', 'ASC')->get()->getRow();
if ($legacy) {
    $svc->syncFromPiutangLegacy((int) $legacy->idpiutang);
    $projId = (int) ($db->table('hutang_piutang')->where('sumber_tipe', 'piutang_legacy')->where('sumber_id', (int) $legacy->idpiutang)->get()->getRow()->id ?? 0);
    ok('syncFromPiutangLegacy projection', $projId > 0);
} else {
    echo "  SKIP  syncFromPiutangLegacy (tidak ada data piutang)\n";
}

// ------------------------------------------------------------------
// 6. syncFromPembelian -> baris projection hutang supplier
// ------------------------------------------------------------------
$suplier = $db->table('suplier')->orderBy('id_suplier', 'ASC')->get()->getRow();
$pbId = 0;
if ($suplier) {
    $db->table('pembelian')->insert([
        'no_nota_supplier'    => 'SMOKE-HP5-PB',
        'tanggal_masuk'       => date('Y-m-d'),
        'jatuh_tempo'         => date('Y-m-d', strtotime('+14 day')),
        'sisa'                => 500000,
        'status'              => 'Belum Lunas',
        'total_bayar'         => 500000,
        'bayar'               => 0,
        'suplier_id_suplier'  => (int) $suplier->id_suplier,
        'unit_idunit'         => $unitId,
    ]);
    $pbId = (int) $db->insertID();
    $svc->syncFromPembelian($pbId);
    $projPb = $db->table('hutang_piutang')->where('sumber_tipe', 'pembelian')->where('sumber_id', $pbId)->get()->getRow();
    ok('syncFromPembelian projection', $projPb && (int) $projPb->is_projection === 1 && (int) $projPb->sisa === 500000);
} else {
    echo "  SKIP  syncFromPembelian (tidak ada data suplier)\n";
}

// ------------------------------------------------------------------
// cleanup
// ------------------------------------------------------------------
$registryIds = array_filter([$id1, $id2]);
if ($registryIds) {
    $payIds = array_column($db->table('pembayaran_hutang_piutang')->select('id')->whereIn('hutang_piutang_id', $registryIds)->get()->getResultArray(), 'id');
    if ($payIds) {
        $db->table('jurnal')->where('tabel_referensi', 'pembayaran_hutang_piutang')->whereIn('id_referensi', $payIds)->delete();
        $db->table('pembayaran_hutang_piutang')->whereIn('id', $payIds)->delete();
    }
    $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->whereIn('id_referensi', $registryIds)->delete();
    $db->table('hutang_piutang')->whereIn('id', $registryIds)->delete();
}
if ($setPayId) {
    $db->table('jurnal')->where('tabel_referensi', 'pembayaran_hutang_piutang')->where('id_referensi', $setPayId)->delete();
    $db->table('pembayaran_hutang_piutang')->where('id', $setPayId)->delete();
}
if (!empty($pbId)) {
    $db->table('hutang_piutang')->where('sumber_tipe', 'pembelian')->where('sumber_id', $pbId)->delete();
    $db->table('pembelian')->where('idpembelian', $pbId)->delete();
}
echo "  CLEAN  data uji & jurnal dihapus\n";

echo "\n=== RESULT: {$pass} PASS / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
