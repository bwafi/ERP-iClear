<?php
/**
 * Smoke test Hutang Piutang: input manual (hutang teknisi / kelebihan transfer /
 * retur barang / manual bebas) dan mekanisme kompensasi piutang<->hutang.
 *
 * Data uji ditandai 'SMOKE-HPM' dan dihapus di akhir.
 *
 * Usage: php74 app/Scripts/hutang_piutang_manual_kompensasi_check.php
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
$suplier = $db->table('suplier')->groupStart()->where('deleted', '0')->orWhere('deleted IS NULL')->groupEnd()->orderBy('id_suplier', 'ASC')->get()->getRow();
$pegawai = $db->table('akun')->groupStart()->where('deleted', 0)->orWhere('deleted IS NULL')->groupEnd()->orderBy('ID_AKUN', 'ASC')->get()->getRow();
$teknisi = $db->table('akun a')->join('jabatan j', 'j.ID_JABATAN = a.ID_JABATAN', 'left')->like('j.NAMA_JABATAN', 'teknisi')
    ->groupStart()->where('a.deleted', 0)->orWhere('a.deleted IS NULL')->groupEnd()->orderBy('a.ID_AKUN', 'ASC')->get()->getRow();

if (!$unit || !$suplier || !$pegawai) {
    fwrite(STDERR, "Data master (unit/suplier/akun) tidak tersedia untuk uji.\n");
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
$inputBy = (int) $pegawai->ID_AKUN;

try {
    // ---------- manual 'lainnya' (tanpa master) ----------
    $r1 = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_MANUAL,
        'pihak_tipe' => HutangPiutangService::PIHAK_LAINNYA,
        'pihak_id' => 0,
        'nama_pihak' => 'SMOKE-HPM Pihak Bebas',
        'tanggal' => date('Y-m-d'),
        'total' => '250.000',
        'keterangan' => 'SMOKE-HPM manual bebas',
    ], $inputBy);
    ok('createPosition manual lainnya', $r1['success'] && !empty($r1['id']), $r1['message']);
    if (!empty($r1['id'])) { $createdIds[] = $r1['id']; }

    $pos1 = $r1['id'] ? $svc->getById($r1['id']) : null;
    ok('  pihak_id null & nama bebas tersimpan', $pos1 && $pos1->pihak_id === null && $pos1->nama_pihak === 'SMOKE-HPM Pihak Bebas');
    ok('  sumber_id = id sendiri (authoritative)', $pos1 && (int) $pos1->sumber_id === (int) $pos1->id);

    // ---------- manual tanpa keterangan ditolak ----------
    $bad = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_MANUAL,
        'pihak_tipe' => HutangPiutangService::PIHAK_LAINNYA,
        'pihak_id' => 0,
        'nama_pihak' => 'SMOKE-HPM Tanpa Ket',
        'tanggal' => date('Y-m-d'),
        'total' => 10000,
    ], $inputBy);
    ok('manual tanpa keterangan ditolak', !$bad['success'], $bad['message']);

    // ---------- hutang jasa teknisi (pihak dipaksa teknisi) ----------
    if ($teknisi) {
        $rt = $svc->createPosition([
            'jenis' => 'piutang', // sengaja salah, harus dipaksa hutang
            'sumber_tipe' => HutangPiutangService::SUMBER_JASA_TEKNISI,
            'pihak_tipe' => HutangPiutangService::PIHAK_PELANGGAN, // sengaja salah
            'pihak_id' => (int) $teknisi->ID_AKUN,
            'tanggal' => date('Y-m-d'),
            'total' => 300000,
            'keterangan' => 'SMOKE-HPM jasa teknisi',
        ], $inputBy);
        ok('createPosition jasa teknisi', $rt['success'] && !empty($rt['id']), $rt['message']);
        if (!empty($rt['id'])) { $createdIds[] = $rt['id']; }
        $post = $rt['id'] ? $svc->getById($rt['id']) : null;
        ok('  jenis dipaksa hutang & pihak teknisi', $post && $post->jenis === 'hutang' && $post->pihak_tipe === 'teknisi');
    } else {
        ok('jasa teknisi: master teknisi tidak ada (skipped)', true);
    }

    // ---------- kelebihan transfer / retur barang (piutang supplier) ----------
    $r3 = $svc->createPosition([
        'jenis' => 'hutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_KELEBIHAN_TRANSFER,
        'pihak_tipe' => HutangPiutangService::PIHAK_SUPLIER,
        'pihak_id' => (int) $suplier->id_suplier,
        'tanggal' => date('Y-m-d'),
        'total' => 500000,
        'keterangan' => 'SMOKE-HPM kelebihan transfer',
    ], $inputBy);
    ok('createPosition kelebihan transfer', $r3['success'] && !empty($r3['id']), $r3['message']);
    if (!empty($r3['id'])) { $createdIds[] = $r3['id']; }
    $pos3 = $r3['id'] ? $svc->getById($r3['id']) : null;
    ok('  jenis piutang & pihak suplier', $pos3 && $pos3->jenis === 'piutang' && $pos3->pihak_tipe === 'suplier');

    $r4 = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_RETUR_BARANG,
        'pihak_tipe' => HutangPiutangService::PIHAK_SUPLIER,
        'pihak_id' => (int) $suplier->id_suplier,
        'tanggal' => date('Y-m-d'),
        'total' => 400000,
        'keterangan' => 'SMOKE-HPM retur barang',
    ], $inputBy);
    ok('createPosition retur barang', $r4['success'] && !empty($r4['id']), $r4['message']);
    if (!empty($r4['id'])) { $createdIds[] = $r4['id']; }

    // ---------- manual hutang ke supplier (lawan kompensasi) ----------
    $r5 = $svc->createPosition([
        'jenis' => 'hutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_MANUAL,
        'pihak_tipe' => HutangPiutangService::PIHAK_SUPLIER,
        'pihak_id' => (int) $suplier->id_suplier,
        'tanggal' => date('Y-m-d'),
        'total' => 300000,
        'keterangan' => 'SMOKE-HPM hutang manual supplier',
    ], $inputBy);
    ok('createPosition manual hutang supplier', $r5['success'] && !empty($r5['id']), $r5['message']);
    if (!empty($r5['id'])) { $createdIds[] = $r5['id']; }

    // ---------- getLawanKompensasi ----------
    $hutangRow = $svc->getById($r5['id']);
    $lawan = $svc->getLawanKompensasi($hutangRow);
    $lawanIds = array_map(function ($x) { return (int) $x->id; }, $lawan);
    ok('getLawanKompensasi memuat piutang supplier', in_array((int) $r3['id'], $lawanIds, true) && in_array((int) $r4['id'], $lawanIds, true), 'lawan=' . implode(',', $lawanIds));

    // ---------- kompensasi partial ----------
    $komp = $svc->kompensasi((int) $r5['id'], (int) $r3['id'], 200000, 'SMOKE-HPM kompensasi', $inputBy);
    ok('kompensasi partial sukses', $komp['success'] && !empty($komp['id']), $komp['message']);

    $hAfter = $svc->getById($r5['id']);
    $pAfter = $svc->getById($r3['id']);
    ok('  hutang berkurang & sebagian', (int) $hAfter->sisa === 100000 && $hAfter->status === HutangPiutangService::STATUS_SEBAGIAN, 'sisa=' . $hAfter->sisa);
    ok('  piutang berkurang & sebagian', (int) $pAfter->sisa === 300000 && $pAfter->status === HutangPiutangService::STATUS_SEBAGIAN, 'sisa=' . $pAfter->sisa);

    $kompRows = $db->table('kompensasi_hutang_piutang')->where('id', $komp['id'])->countAllResults();
    ok('  1 baris kompensasi tercatat', $kompRows === 1);

    $audit = $db->table('pembayaran_hutang_piutang')->where('sumber', 'kompensasi')->whereIn('hutang_piutang_id', [(int) $r5['id'], (int) $r3['id']])->countAllResults();
    ok('  2 baris audit pembayaran kompensasi', $audit === 2, 'rows=' . $audit);

    // ---------- kompensasi melebihi sisa terkecil ditolak ----------
    $over = $svc->kompensasi((int) $r5['id'], (int) $r3['id'], 999999, null, $inputBy);
    ok('kompensasi melebihi sisa ditolak', !$over['success'], $over['message']);

    // ---------- kompensasi pihak berbeda ditolak ----------
    $otherSuplier = $db->table('suplier')->groupStart()->where('deleted', '0')->orWhere('deleted IS NULL')->groupEnd()
        ->where('id_suplier !=', (int) $suplier->id_suplier)->orderBy('id_suplier', 'ASC')->get()->getRow();
    if ($otherSuplier) {
        $r6 = $svc->createPosition([
            'jenis' => 'piutang',
            'sumber_tipe' => HutangPiutangService::SUMBER_RETUR_BARANG,
            'pihak_tipe' => HutangPiutangService::PIHAK_SUPLIER,
            'pihak_id' => (int) $otherSuplier->id_suplier,
            'tanggal' => date('Y-m-d'),
            'total' => 100000,
            'keterangan' => 'SMOKE-HPM retur supplier lain',
        ], $inputBy);
        if (!empty($r6['id'])) { $createdIds[] = $r6['id']; }
        $beda = $svc->kompensasi((int) $r5['id'], (int) $r6['id'], 50000, null, $inputBy);
        ok('kompensasi pihak berbeda ditolak', !$beda['success'], $beda['message']);
    } else {
        ok('kompensasi pihak berbeda (supplier kedua tidak ada, skipped)', true);
    }

    // ---------- ringkasan memuat jenis baru ----------
    $ring = $svc->getRingkasan([(int) $unit->idunit]);
    ok('ringkasan punya total_piutang_supplier & total_hutang_lain', array_key_exists('total_piutang_supplier', $ring) && array_key_exists('total_hutang_lain', $ring));
    ok('  piutang supplier >= sisa berjalan', (int) $ring['total_piutang_supplier'] >= 300000, 'total=' . $ring['total_piutang_supplier']);

    // ---------- detail transaksi (cetak) untuk jenis baru ----------
    $detail = $svc->getDetailTransaksi($svc->getById($r3['id']));
    ok('detail cetak kelebihan transfer', ($detail['sumber_label'] ?? '') === HutangPiutangService::labelSumber('kelebihan_transfer') && !empty($detail['referensi']));
} catch (\Throwable $e) {
    ok('exception', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    if (!empty($createdIds)) {
        $db->table('kompensasi_hutang_piutang')->groupStart()->whereIn('hutang_piutang_id', $createdIds)->orWhereIn('piutang_piutang_id', $createdIds)->groupEnd()->delete();
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
