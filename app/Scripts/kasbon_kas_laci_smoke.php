<?php
/**
 * Smoke: pencairan kasbon mengurangi kas laci (baris kas_keluar) + sinkron
 * edit/hapus baris kas ke kasbon.
 *
 * Data uji ditandai 'SMOKE-KAS' dan dibersihkan di akhir.
 * Usage: php74 app/Scripts/kasbon_kas_laci_smoke.php
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
$pegawai = $db->table('akun')->groupStart()->where('deleted', 0)->orWhere('deleted IS NULL')->groupEnd()->orderBy('ID_AKUN', 'ASC')->get()->getRow();

if (!$unit || !$pegawai) {
    fwrite(STDERR, "Data master (unit/akun) tidak tersedia untuk uji.\n");
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
$createdKasbon = [];
$createdKas = [];

// Bersihkan sisa run sebelumnya (hanya baris bertanda SMOKE-KAS).
$stale = $db->table('hutang_piutang')->select('id,kas_keluar_id')->like('uraian', 'SMOKE-KAS')->get()->getResult();
if ($stale) {
    $staleIds = array_map(static fn ($r) => (int) $r->id, $stale);
    $staleKas = array_filter(array_map(static fn ($r) => (int) ($r->kas_keluar_id ?? 0), $stale));
    $stalePay = array_column($db->table('pembayaran_hutang_piutang')->select('id')->whereIn('hutang_piutang_id', $staleIds)->get()->getResultArray(), 'id');
    if ($stalePay) {
        $db->table('jurnal')->where('tabel_referensi', 'pembayaran_hutang_piutang')->whereIn('id_referensi', $stalePay)->delete();
    }
    $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->whereIn('id_referensi', $staleIds)->delete();
    $db->table('pembayaran_hutang_piutang')->whereIn('hutang_piutang_id', $staleIds)->delete();
    $db->table('hutang_piutang')->whereIn('id', $staleIds)->delete();
    if ($staleKas) {
        $db->table('kas_keluar')->whereIn('idkas_keluar', $staleKas)->delete();
    }
}

try {
    // ---------- 1. kasbon tunai -> baris kas_keluar ----------
    $tkToday = date('Y-m-d');
    $tkDef = new \App\Services\Finance\TutupKasirSourceDefinition();
    $tkCashBefore = $tkDef->kasKeluarCash((int) $unit->idunit, $tkToday);
    $beforeKas = $db->table('kas_keluar')->countAllResults();
    $r1 = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
        'pihak_tipe' => 'pegawai',
        'pihak_id' => (int) $pegawai->ID_AKUN,
        'tanggal' => date('Y-m-d'),
        'total' => 750000,
        'uraian' => 'SMOKE-KAS kasbon tunai',
    ], (int) $pegawai->ID_AKUN);
    ok('createPosition kasbon tunai', !empty($r1['success']) && !empty($r1['id']), $r1['message'] ?? '');
    if (!empty($r1['id'])) { $createdKasbon[] = (int) $r1['id']; }

    $hp1 = !empty($r1['id']) ? $svc->getById((int) $r1['id']) : null;
    $kasId1 = $hp1 ? (int) ($hp1->kas_keluar_id ?? 0) : 0;
    ok('  kas_keluar_id tersimpan di kasbon', $kasId1 > 0, 'kas_keluar_id=' . $kasId1);

    $kas1 = $kasId1 > 0 ? $db->table('kas_keluar')->where('idkas_keluar', $kasId1)->get()->getRow() : null;
    if ($kas1) { $createdKas[] = (int) $kas1->idkas_keluar; }
    ok('  baris kas_keluar dibuat (tunai, idbank NULL)', $kas1 && $kas1->idbank === null, 'idbank=' . var_export($kas1->idbank ?? null, true));
    ok('  jumlah kas = total kasbon', $kas1 && (int) $kas1->jumlah === 750000, 'jumlah=' . ($kas1->jumlah ?? 'null'));
    $kat = $kas1 ? $db->table('kategori_kas')->where('idkategori_kas', (int) $kas1->kategori_idkategori)->get()->getRow() : null;
    ok('  kategori = Kasbon', $kat && $kat->kategori === 'Kasbon', 'kategori=' . ($kat->kategori ?? 'null'));
    ok('  total baris kas bertambah 1', $db->table('kas_keluar')->countAllResults() === $beforeKas + 1);
    $tkCashAfter = $tkDef->kasKeluarCash((int) $unit->idunit, $tkToday);
    ok('  Tutup Kasir: pengeluaran kas naik sebesar kasbon', $tkCashAfter - $tkCashBefore === 750000, 'delta=' . ($tkCashAfter - $tkCashBefore));

    // ---------- 2. baris otomatis TIDAK memposting jurnal kas_keluar ----------
    $jmlKas = $kasId1 > 0 ? $db->table('jurnal')->where('tabel_referensi', 'kas_keluar')->where('id_referensi', $kasId1)->countAllResults() : -1;
    ok('  tidak ada jurnal kas_keluar utk baris otomatis', $jmlKas === 0, 'rows=' . $jmlKas);

    // ---------- 3. jurnal kasbon tetap 1 pasang (hp_kasbon_input) ----------
    $jmlHp = !empty($r1['id']) ? $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->where('id_referensi', (int) $r1['id'])->countAllResults() : -1;
    ok('  jurnal hp_kasbon_input = 2 baris', $jmlHp === 2, 'rows=' . $jmlHp);

    // ---------- 4. transfer (bila ada rekening yang berhak untuk unit) ----------
    $validator = new \App\Services\Finance\BankRekeningValidator();
    $idbankOk = null;
    foreach ($db->table('bank')->orderBy('idbank', 'ASC')->get()->getResult() as $b) {
        if ($validator->validateUntukUnit((string) $b->idbank, (int) $unit->idunit, null, false)['ok']) {
            $idbankOk = (string) $b->idbank;
            break;
        }
    }
    if ($idbankOk !== null) {
        $tkBankBefore = $tkDef->kasKeluarTransfer((int) $unit->idunit, $tkToday);
        $r2 = $svc->createPosition([
            'jenis' => 'piutang',
            'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
            'pihak_tipe' => 'pegawai',
            'pihak_id' => (int) $pegawai->ID_AKUN,
            'tanggal' => date('Y-m-d'),
            'total' => 400000,
            'metode' => 'transfer',
            'bank_idbank' => $idbankOk,
            'uraian' => 'SMOKE-KAS kasbon transfer',
        ], (int) $pegawai->ID_AKUN);
        ok('createPosition kasbon transfer', !empty($r2['success']) && !empty($r2['id']), $r2['message'] ?? '');
        if (!empty($r2['id'])) { $createdKasbon[] = (int) $r2['id']; }

        $hp2 = !empty($r2['id']) ? $svc->getById((int) $r2['id']) : null;
        $kasId2 = $hp2 ? (int) ($hp2->kas_keluar_id ?? 0) : 0;
        $kas2 = $kasId2 > 0 ? $db->table('kas_keluar')->where('idkas_keluar', $kasId2)->get()->getRow() : null;
        if ($kas2) { $createdKas[] = (int) $kas2->idkas_keluar; }
        ok('  baris kas transfer idbank terisi', $kas2 && (string) $kas2->idbank === $idbankOk, 'idbank=' . ($kas2->idbank ?? 'null'));
        $bankJ = !empty($r2['id']) ? $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->where('id_referensi', (int) $r2['id'])->where('no_akun', '1010102000')->countAllResults() : -1;
        ok('  jurnal transfer kredit Kas di Bank', $bankJ === 1, 'rows=' . $bankJ);
        $tkBankAfter = $tkDef->kasKeluarTransfer((int) $unit->idunit, $tkToday);
        ok('  Tutup Kasir: transfer naik sebesar kasbon', $tkBankAfter - $tkBankBefore === 400000, 'delta=' . ($tkBankAfter - $tkBankBefore));
    } else {
        echo "  SKIP  transfer: tidak ada rekening berhak untuk unit ini\n";
        $r2 = null;
    }

    // ---------- 5. transfer ke rekening tak berhak ditolak ----------
    $bad = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
        'pihak_tipe' => 'pegawai',
        'pihak_id' => (int) $pegawai->ID_AKUN,
        'tanggal' => date('Y-m-d'),
        'total' => 100000,
        'metode' => 'transfer',
        'bank_idbank' => 'ZZZ-999',
        'uraian' => 'SMOKE-KAS bad transfer',
    ], (int) $pegawai->ID_AKUN);
    ok('transfer rekening tak berhak ditolak', empty($bad['success']), $bad['message'] ?? '');
    ok('  kasbon tak tersimpan separuh', $db->table('hutang_piutang')->where('uraian', 'SMOKE-KAS bad transfer')->countAllResults() === 0);

    // ---------- 6. guard: jumlah < total_dibayar ditolak ----------
    if ($kasId1 > 0 && !empty($r1['id'])) {
        $part = $svc->bayar((int) $r1['id'], ['jumlah_bayar' => 100000, 'bayar_tunai' => 100000]);
        ok('  setup: bayar sebagian untuk guard', !empty($part['success']), $part['message'] ?? '');
        $g = $svc->guardKasbonKasKeluar($kasId1, 50000);
        ok('guard tolak jumlah < total_dibayar', !empty($g) && $g['ok'] === false, $g['message'] ?? '');
        $g2 = $svc->guardKasbonKasKeluar($kasId1, 700000);
        ok('guard izinkan jumlah >= total_dibayar', !empty($g2) && $g2['ok'] === true, $g2['message'] ?? '');
    }

    // ---------- 7. sync: jumlah naik -> kasbon + jurnal ikut berubah ----------
    if ($kasId1 > 0 && !empty($r1['id'])) {
        $s = $svc->syncKasbonDariKasKeluar($kasId1, ['tanggal' => date('Y-m-d'), 'jumlah' => 800000, 'idbank' => null]);
        $hp1b = $svc->getById((int) $r1['id']);
        ok('sync ubah total kasbon', !empty($s['ok']) && (int) $hp1b->total === 800000, ($s['message'] ?? '') . ' total=' . ($hp1b->total ?? 'null'));
        $jmlHp2 = $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->where('id_referensi', (int) $r1['id'])->countAllResults();
        ok('  jurnal tetap 2 baris setelah sync', $jmlHp2 === 2, 'rows=' . $jmlHp2);
        $debit = $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->where('id_referensi', (int) $r1['id'])->where('no_akun', '1020201000')->get()->getRow();
        ok('  jurnal nominal ikut berubah', $debit && (int) $debit->debet === 800000, 'debet=' . ($debit->debet ?? 'null'));
    }

    // ---------- 8. hapus kasbon bersih -> deleted=1 + jurnal hilang ----------
    $bersih = $r2 ?? null;
    if ($bersih === null) {
        $bersih = $svc->createPosition([
            'jenis' => 'piutang',
            'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
            'pihak_tipe' => 'pegawai',
            'pihak_id' => (int) $pegawai->ID_AKUN,
            'tanggal' => date('Y-m-d'),
            'total' => 300000,
            'uraian' => 'SMOKE-KAS kasbon hapus',
        ], (int) $pegawai->ID_AKUN);
        if (!empty($bersih['id'])) { $createdKasbon[] = (int) $bersih['id']; }
        $hpB = !empty($bersih['id']) ? $svc->getById((int) $bersih['id']) : null;
        if ($hpB && (int) $hpB->kas_keluar_id > 0) { $createdKas[] = (int) $hpB->kas_keluar_id; }
    }
    if (!empty($bersih['id'])) {
        $hpB = $svc->getById((int) $bersih['id']);
        $h = $svc->hapusKasbonDariKasKeluar((int) $hpB->kas_keluar_id);
        $hpBb = $db->table('hutang_piutang')->where('id', (int) $bersih['id'])->get()->getRow();
        ok('hapus kasbon bersih -> deleted=1', !empty($h['ok']) && $hpBb && (int) $hpBb->deleted === 1, $h['message'] ?? '');
        ok('  jurnal kasbon terhapus', $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->where('id_referensi', (int) $bersih['id'])->countAllResults() === 0);
    }

    // ---------- 9. hapus ditolak bila kasbon sudah ada pembayaran ----------
    if ($kasId1 > 0) {
        $h2 = $svc->hapusKasbonDariKasKeluar($kasId1);
        ok('hapus ditolak bila kasbon sudah ada pembayaran', !empty($h2) && $h2['ok'] === false, $h2['message'] ?? '');
    }

    // ---------- 9b. nominal 0 tidak boleh mengosongkan kasbon ----------
    $kosong = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
        'pihak_tipe' => 'pegawai',
        'pihak_id' => (int) $pegawai->ID_AKUN,
        'tanggal' => date('Y-m-d'),
        'total' => 150000,
        'uraian' => 'SMOKE-KAS kasbon nol',
    ], (int) $pegawai->ID_AKUN);
    if (!empty($kosong['id'])) {
        $createdKasbon[] = (int) $kosong['id'];
        $hpK = $svc->getById((int) $kosong['id']);
        $kasIdK = (int) $hpK->kas_keluar_id;
        if ($kasIdK > 0) { $createdKas[] = $kasIdK; }
        $g3 = $svc->guardKasbonKasKeluar($kasIdK, 0);
        ok('guard tolak jumlah 0', !empty($g3) && $g3['ok'] === false, $g3['message'] ?? '');
        $s3 = $svc->syncKasbonDariKasKeluar($kasIdK, ['jumlah' => 0]);
        $hpKb = $svc->getById((int) $kosong['id']);
        ok('sync tolak jumlah 0 (kasbon utuh)', !empty($s3) && $s3['ok'] === false && (int) $hpKb->total === 150000, ($s3['message'] ?? '') . ' total=' . ($hpKb->total ?? 'null'));
    }

    // ---------- 9c. total negatif ditolak sebelum baris apa pun dibuat ----------
    $neg = $svc->createPosition([
        'jenis' => 'piutang',
        'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
        'pihak_tipe' => 'pegawai',
        'pihak_id' => (int) $pegawai->ID_AKUN,
        'tanggal' => date('Y-m-d'),
        'total' => '-5000',
        'uraian' => 'SMOKE-KAS negatif',
    ], (int) $pegawai->ID_AKUN);
    ok('kasbon total negatif ditolak', empty($neg['success']), $neg['message'] ?? '');
    ok('  kasbon negatif tak tersimpan', $db->table('hutang_piutang')->where('uraian', 'SMOKE-KAS negatif')->countAllResults() === 0);

    // ---------- 10. tunai tanpa akun KAS aktif ditolak (spec 6) ----------
    $kasAkun = $db->table('akun_kas_bank')
        ->where('unit_id', (int) $unit->idunit)->where('tipe', 'KAS')->where('status', 'aktif')
        ->orderBy('idakun_kas_bank', 'ASC')->get()->getRow();
    if ($kasAkun) {
        $db->table('akun_kas_bank')->where('idakun_kas_bank', (int) $kasAkun->idakun_kas_bank)->update(['status' => 'nonaktif']);
        try {
            $tanpaKas = $svc->createPosition([
                'jenis' => 'piutang',
                'sumber_tipe' => HutangPiutangService::SUMBER_KASBON,
                'pihak_tipe' => 'pegawai',
                'pihak_id' => (int) $pegawai->ID_AKUN,
                'tanggal' => date('Y-m-d'),
                'total' => 250000,
                'uraian' => 'SMOKE-KAS tanpa akun kas',
            ], (int) $pegawai->ID_AKUN);
            ok('tunai tanpa akun KAS aktif ditolak', empty($tanpaKas['success']), $tanpaKas['message'] ?? '');
            if (!empty($tanpaKas['id'])) { $createdKasbon[] = (int) $tanpaKas['id']; }
        } finally {
            $db->table('akun_kas_bank')->where('idakun_kas_bank', (int) $kasAkun->idakun_kas_bank)->update(['status' => 'aktif']);
        }
    } else {
        echo "  SKIP  tunai tanpa akun KAS: unit uji tidak punya akun KAS aktif\n";
    }
} catch (\Throwable $e) {
    ok('exception', false, $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
} finally {
    if (!empty($createdKasbon)) {
        $payIds = array_column($db->table('pembayaran_hutang_piutang')->select('id')->whereIn('hutang_piutang_id', $createdKasbon)->get()->getResultArray(), 'id');
        if ($payIds) {
            $db->table('jurnal')->where('tabel_referensi', 'pembayaran_hutang_piutang')->whereIn('id_referensi', $payIds)->delete();
        }
        $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->whereIn('id_referensi', $createdKasbon)->delete();
        $db->table('pembayaran_hutang_piutang')->whereIn('hutang_piutang_id', $createdKasbon)->delete();
        $db->table('hutang_piutang')->whereIn('id', $createdKasbon)->delete();
    }
    if (!empty($createdKas)) {
        $db->table('kas_keluar')->whereIn('idkas_keluar', $createdKas)->delete();
    }
}

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
