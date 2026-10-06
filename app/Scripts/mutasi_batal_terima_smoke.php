<?php
/**
 * Smoke test MutasiStok::batalTerima().
 *
 * Membatalkan penerimaan bukan membalik satu kolom. terima() melakukan dua
 * hal sekaligus — status mutasi jadi '1' DAN ModeKasBank membuat sepasang
 * Hutang/Piutang antar unit — jadi test ini memeriksa KEDUA efek ikut
 * kembali, plus penjaga yang harus menahan pembatalan:
 *
 *   1. hanya Admin Root (jabatan 1) yang boleh
 *   2. alasan wajib diisi
 *   3. mutasi yang belum diterima tidak bisa dibatalkan
 *   4. dokumen H/P yang sudah punya pembayaran DITOLAK dibatalkan
 *   5. soft delete, bukan hapus fisik (jejak tetap bisa dibaca)
 *   6. alasan pembatalan benar-benar tersimpan
 *
 * Jalankan: php74 app/Scripts/mutasi_batal_terima_smoke.php
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

// Dipakai untuk menyamar jadi berbagai akun
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

$mutasiModel = new App\Models\ModelMutasiStok();
$hpModel     = new App\Models\ModelHutangPiutang();
$controller  = new App\Controllers\MutasiStok();

$reqProp = new ReflectionProperty(CodeIgniter\Controller::class, 'request');
$reqProp->setAccessible(true);

/**
 * Panggil batalTerima() sebagai seorang akun, dengan POST tertentu.
 */
function batalkan(int $jabatan, int $akunId, int $idmutasi, array $post)
{
    global $controller, $reqProp;

    // Flash dibersihkan dulu: kalau tidak, pesan dari panggilan sebelumnya
    // bocor ke assertion berikutnya dan happy path terlihat gagal.
    unset($_SESSION['gagal'], $_SESSION['sukses']);

    $_SESSION['ID_JABATAN'] = $jabatan;
    $_SESSION['ID_AKUN']    = $akunId;
    $_SESSION['ID_UNIT']    = 0;
    $_POST = $post;
    $_GET  = [];
    $reqProp->setValue($controller, Config\Services::request(null, false));

    $m = new ReflectionMethod($controller, 'batalTerima');
    $m->setAccessible(true);
    $m->invoke($controller, $idmutasi);

    return (string) session('gagal');
}

// ---------------------------------------------------------------- fixtures
$unitKirim  = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getRow();
$unitTerima = $db->table('unit')->orderBy('idunit', 'DESC')->get()->getRow();
$akunAdmin  = $db->table('akun')->where('ID_JABATAN', 1)
    ->select('ID_AKUN, ID_JABATAN')->orderBy('ID_AKUN', 'ASC')->get()->getRow();
$akunBiasa = $db->table('akun')->where('ID_JABATAN !=', 1)
    ->select('ID_AKUN, ID_JABATAN')->orderBy('ID_AKUN', 'ASC')->get()->getRow();

if (! $unitKirim || ! $unitTerima || ! $akunAdmin) {
    echo "SKIP: butuh minimal 2 unit dan 1 akun jabatan 1\n";
    exit(0);
}
$idAdmin = (int) $akunAdmin->ID_AKUN;
echo "UnitKirim={$unitKirim->idunit} UnitTerima={$unitTerima->idunit} Admin={$idAdmin} Biasa=" . (int) ($akunBiasa->ID_AKUN ?? 0) . "\n\n";

/**
 * Buat mutasi + sepasang dokumen H/P, lalu kembalikan id-nya.
 * Mirip efek terima() supaya test tidak bergantung pada data lama.
 */
function buatMutasiTerima($db, $kirim, $terima, int $akun): int
{
    $db->table('mutasi')->insert([
        'no_nota_mutasi' => 'TESBATAL' . random_int(100000, 999999),
        'tanggal_kirim' => date('Y-m-d H:i:s'),
        'tanggal_terima'=> date('Y-m-d H:i:s'),
        'status'        => '1',
        'kirim_idunit'  => (int) $kirim,
        'terima_idunit' => (int) $terima,
        'input_by'      => $akun,
        'created_on'    => date('Y-m-d H:i:s'),
        'updated_on'    => date('Y-m-d H:i:s'),
    ]);
    $id = (int) $db->insertID();

    foreach ([['piutang', (int) $kirim], ['hutang', (int) $terima]] as [$jenis, $unit]) {
        $db->table('hutang_piutang')->insert([
            'kode'          => 'TES-BATAL-' . $id . '-' . strtoupper(substr($jenis, 0, 1)),
            'jenis'         => $jenis,
            'sumber_tipe'   => 'mutasi_unit',
            'sumber_id'     => $id,
            'is_projection' => 0,
            'pihak_tipe'    => 'unit',
            'pihak_id'      => $unit,
            'lawan_unit_id' => (int) ($jenis === 'piutang' ? $terima : $kirim),
            'nama_pihak'    => 'Unit ' . $unit,
            'tanggal'       => date('Y-m-d'),
            'jatuh_tempo'   => date('Y-m-d', strtotime('+3 days')),
            'uraian'        => 'Mutasi antar unit #' . $id,
            'total'         => 750000,
            'total_dibayar' => 0,
            'sisa'          => 750000,
            'status'        => 'belum_lunas',
            'unit_id'       => $unit,
            'input_by'      => $akun,
            'deleted'       => 0,
        ]);
    }

    return $id;
}

$hpAktif = fn (int $id): array => $db->table('hutang_piutang')
    ->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', $id)
    ->where('deleted', 0)->get()->getResult();

$db->transBegin();
$ids = [];

// --- 1. hanya Admin Root -------------------------------------------------
$ids[] = $id = buatMutasiTerima($db, $unitKirim->idunit, $unitTerima->idunit, $idAdmin);
$err = batalkan(34, (int) ($akunBiasa->ID_AKUN ?? $idAdmin), $id, ['alasan_batal' => 'saya manager']);
check('jabatan 34 DITOLAK membatalkan', strpos($err, 'Admin Root') !== false, $err);
check('mutasi tetap diterima setelah ditolak', (string) $db->table('mutasi')->where('idmutasi', $id)->get()->getRow()->status === '1');
check('dokumen H/P utuh setelah ditolak', count($hpAktif($id)) === 2);

if ($akunBiasa) {
    $err = batalkan((int) $akunBiasa->ID_JABATAN, (int) $akunBiasa->ID_AKUN, $id, ['alasan_batal' => 'bukan admin']);
    check('akun non-admin DITOLAK', (string) $db->table('mutasi')->where('idmutasi', $id)->get()->getRow()->status === '1');
}

// --- 2. alasan wajib -----------------------------------------------------
$err = batalkan(1, $idAdmin, $id, ['alasan_batal' => '']);
check('alasan kosong DITOLAK', strpos($err, 'Alasan pembatalan wajib diisi') !== false, $err);
$err = batalkan(1, $idAdmin, $id, ['alasan_batal' => '   ']);
check('alasan spasi saja DITOLAK', strpos($err, 'wajib diisi') !== false, $err);
check('mutasi tetap diterima tanpa alasan', (string) $db->table('mutasi')->where('idmutasi', $id)->get()->getRow()->status === '1');

// --- 3. mutasi belum diterima -------------------------------------------
$db->table('mutasi')->where('idmutasi', $id)->update(['status' => '0']);
$err = batalkan(1, $idAdmin, $id, ['alasan_batal' => 'cuma cek']);
check('mutasi belum diterima tidak bisa dibatalkan', strpos($err, 'belum diterima') !== false, $err);
$db->table('mutasi')->where('idmutasi', $id)->update(['status' => '1']);

// --- 4. HAPPY PATH: dua efek ikut kembali -------------------------------
$err = batalkan(1, $idAdmin, $id, ['alasan_batal' => 'Salah klik tombol Terima']);
$row = $db->table('mutasi')->where('idmutasi', $id)->get()->getRow();
check('tidak ada pesan gagal', $err === '', $err);
check('status kembali ke belum diterima', (string) $row->status === '0', (string) $row->status);
check('tanggal_terima dikosongkan', $row->tanggal_terima === null, (string) $row->tanggal_terima);
check('batal_oleh tersimpan', (int) $row->batal_oleh === $idAdmin, (string) $row->batal_oleh);
check('batal_at terisi', $row->batal_at !== null);
check('batal_alasan tersimpan', (string) $row->batal_alasan === 'Salah klik tombol Terima', (string) $row->batal_alasan);
check('dokumen H/P tidak aktif lagi', count($hpAktif($id)) === 0, 'sisa=' . count($hpAktif($id)));

// soft delete, bukan hapus fisik: jejaknya harus masih bisa dibaca
$semua = $db->table('hutang_piutang')->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', $id)->get()->getResult();
check('dokumen H/P tidak dihapus fisik', count($semua) === 2, 'jml=' . count($semua));
check('semua dokumen ditandai deleted', count(array_filter($semua, fn ($d) => (int) $d->deleted === 1)) === 2);
check('alasan tersimpan di keterangan dokumen', strpos((string) $semua[0]->keterangan, 'Salah klik tombol Terima') !== false);

// setelah dibatalkan, mutasi bisa DITERIMA lagi (tidak ada dokumen kembar)
$db->table('mutasi')->where('idmutasi', $id)->update(['status' => '1', 'batal_oleh' => null, 'batal_at' => null, 'batal_alasan' => null]);
$ids[] = $id2 = buatMutasiTerima($db, $unitKirim->idunit, $unitTerima->idunit, $idAdmin);
check('mutasi bisa dibatalkan lagi setelah di-terima ulang', $id2 > 0);

// --- 5. BARRIER: H/P sudah ada pembayarannya ----------------------------
$ids[] = $id3 = buatMutasiTerima($db, $unitKirim->idunit, $unitTerima->idunit, $idAdmin);
$db->table('hutang_piutang')->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', $id3)
    ->update(['total_dibayar' => 250000, 'sisa' => 500000, 'status' => 'sebagian']);
$err = batalkan(1, $idAdmin, $id3, ['alasan_batal' => 'salah klik']);
check('pembatalan DITOLAK kalau H/P sudah dibayar sebagian', strpos($err, 'sudah punya pembayaran') !== false, $err);
$row3 = $db->table('mutasi')->where('idmutasi', $id3)->get()->getRow();
check('mutasi tetap diterima saat barrier menolak', (string) $row3->status === '1');
check('dokumen H/P tetap aktif saat barrier menolak', count($hpAktif($id3)) === 2);
// satu mutasi = dua dokumen H/P, keduanya punya pembayaran
check('pembayaran pada kedua dokumen utuh', (int) $db->table('hutang_piutang')
    ->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', $id3)
    ->where('deleted', 0)->where('total_dibayar', 250000)->countAllResults() === 2);

$ids[] = $id4 = buatMutasiTerima($db, $unitKirim->idunit, $unitTerima->idunit, $idAdmin);
$db->table('hutang_piutang')->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', $id4)
    ->update(['total_dibayar' => 750000, 'sisa' => 0, 'status' => 'lunas']);
$err = batalkan(1, $idAdmin, $id4, ['alasan_batal' => 'sudah lunas']);
check('pembatalan DITOLAK kalau H/P sudah lunas', strpos($err, 'sudah punya pembayaran') !== false, $err);

// --- 6. mutasi tidak ada -------------------------------------------------
$err = batalkan(1, $idAdmin, 999999999, ['alasan_batal' => 'x']);
check('mutasi tidak ditemukan ditolak', strpos($err, 'tidak ditemukan') !== false, $err);

// --- 7. model mengizinkan kolom baru (CI4 membuang yang tak terdaftar) ---
$src = file_get_contents(APPPATH . 'Models/ModelMutasiStok.php');
check('ModelMutasiStok mengizinkan batal_oleh', strpos($src, "'batal_oleh'") !== false);
check('ModelMutasiStok mengizinkan batal_at', strpos($src, "'batal_at'") !== false);
check('ModelMutasiStok mengizinkan batal_alasan', strpos($src, "'batal_alasan'") !== false);

$routes = file_get_contents(APPPATH . 'Config/Routes.php');
check('route batal-terima terdaftar', strpos($routes, 'mutasi_stok/batal-terima/') !== false);

// --- 8. UI: tombol & alasan hanya untuk Admin Root ----------------------
$view = file_get_contents(APPPATH . 'Views/stok/mutasi_masuk.php');
check('view punya tombol Batal Terima', strpos($view, 'btn-batal-terima') !== false);
check('view punya textarea alasan', strpos($view, 'name="alasan_batal"') !== false);
check('view membungkus tombol dalam syarat Admin Root', strpos($view, "\$bisaBatal = in_array((int) session('ID_JABATAN'), [1], true);") !== false);
check('view memvalidasi alasan di sisi klien', strpos($view, 'Alasan pembatalan wajib diisi.') !== false);

// ------------------------------------------------------------------------
$db->transRollback();
$_SESSION = [];

echo "\nFAIL: {$fail}\n";
exit($fail > 0 ? 1 : 0);
