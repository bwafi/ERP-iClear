<?php
/**
 * Uji kelengkapan DETAIL TRANSAKSI dokumen cetak Bukti Hutang Piutang untuk
 * 4 sumber: kasbon, piutang_pelanggan, pembelian, piutang_legacy.
 *
 * Jalankan: php74 app/Scripts/hutang_piutang_detail_cetak_check.php
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
define('FCPATH', realpath(ROOTPATH . 'public') . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$config = new \Config\App();
$uri = new \CodeIgniter\HTTP\SiteURI($config, 'hutangpiutang', 'localhost', 'http');
$request = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
\CodeIgniter\Config\Services::injectMock('request', $request);

$db = \Config\Database::connect();
$svc = new \App\Services\Finance\HutangPiutangService();
$unitId = 1;
$inputBy = 1;

$passed = 0;
$failed = 0;
function ok(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "PASS  {$label}\n";
    } else {
        $failed++;
        echo "FAIL  {$label}" . ($detail !== '' ? "  [{$detail}]" : '') . "\n";
    }
}

function renderCetak($db, $row)
{
    $unit = $row->unit_id ? (new \App\Models\ModelUnit())->find($row->unit_id) : null;
    $svc = new \App\Services\Finance\HutangPiutangService();
    return view('cetak/bukti_hutang_piutang', [
        'row' => $row,
        'unit' => $unit,
        'detail' => $svc->getDetailTransaksi($row),
        'pembayaran' => [],
    ]);
}

// ------------------------------------------------------------------ data uji
$pegawai = $db->table('akun')->select('ID_AKUN, NAMA_AKUN')->where('ID_AKUN', 5)->get()->getRow();
$namaPegawai = $pegawai ? $pegawai->NAMA_AKUN : 'Pegawai Uji';

$pelangganNama = 'Pelanggan Uji HPD';
$db->table('pelanggan')->insert(['nama' => $pelangganNama, 'no_hp' => '0899000111', 'alamat' => 'Jl. Uji Pelanggan', 'deleted' => '0']);
$pelangganId = (int) $db->insertID();

$barangNama = 'LCD iPhone 14 Pro Max';
$db->table('barang')->insert(['kode_barang' => 'TEST-HPD-1', 'nama_barang' => $barangNama, 'harga' => 500000, 'harga_beli' => 300000, 'status_ppn' => 0]);
$barangId = (int) $db->insertID();

$suplierNama = 'PT Supplier Uji HPD';
$db->table('suplier')->insert(['nama_suplier' => $suplierNama, 'alamat' => 'Jl. Uji Supplier', 'no_hp' => '0812000111', 'unit_idunit' => $unitId]);
$suplierId = (int) $db->insertID();

$noNota = 'NT-TEST-HPD-001';
$db->table('pembelian')->insert([
    'no_nota_supplier' => $noNota,
    'tanggal_masuk' => date('Y-m-d'),
    'jatuh_tempo' => date('Y-m-d', strtotime('+30 days')),
    'total_transaksi' => 300000,
    'total_bayar' => 300000,
    'bayar' => 0,
    'sisa' => 300000,
    'status' => 'belum_lunas',
    'suplier_id_suplier' => $suplierId,
    'unit_idunit' => $unitId,
]);
$pembelianId = (int) $db->insertID();
$db->table('detail_pembelian')->insert([
    'jumlah' => 1,
    'hrg_beli' => 300000,
    'total_harga' => 300000,
    'satuan_beli' => 'pcs',
    'barang_idbarang' => $barangId,
    'pembelian_idpembelian' => $pembelianId,
    'unit_idunit' => $unitId,
]);
$svc->syncFromPembelian($pembelianId);

$kodePiutang = 'PUT-TEST-LEG-001';
$db->table('piutang')->insert([
    'kode_piutang' => $kodePiutang,
    'tanggal' => date('Y-m-d'),
    'jumlah_hutang' => 150000,
    'sisa_hutang' => 150000,
    'pegawai_idpegawai' => 5,
    'unit_idunit' => $unitId,
]);
$piutangId = (int) $db->insertID();
$svc->syncFromPiutangLegacy($piutangId);

$noService = 'SRV-TEST-9999';
$db->table('service')->insert([
    'no_service' => $noService,
    'keluhan' => 'Service iPhone 14 Pro Max',
    'tipe_hp' => 'iPhone 14 Pro Max',
    'imei' => '123456789012345',
    'total_service' => 750000,
    'harus_dibayar' => 750000,
    'pelanggan_id_pelanggan' => $pelangganId,
    'unit_idunit' => $unitId,
    'service_by' => 5,
    'input_by' => 5,
]);
$serviceId = (int) $db->insertID();
$db->table('service_sparepart')->insert([
    'jumlah' => 1,
    'harga_penjualan' => 250000,
    'sub_total' => 250000,
    'service_idservice' => $serviceId,
    'barang_idbarang' => $barangId,
    'unit_idunit' => $unitId,
]);

$rKasbon = $svc->createPosition([
    'jenis' => 'piutang',
    'sumber_tipe' => 'kasbon',
    'pihak_tipe' => 'pegawai',
    'pihak_id' => 5,
    'total' => 200000,
    'tanggal' => date('Y-m-d'),
    'uraian' => 'Kasbon untuk biaya transport',
    'unit_id' => $unitId,
], $inputBy);

$rPiutang = $svc->createPosition([
    'jenis' => 'piutang',
    'sumber_tipe' => 'piutang_pelanggan',
    'pihak_tipe' => 'pelanggan',
    'pihak_id' => $pelangganId,
    'total' => 750000,
    'tanggal' => date('Y-m-d'),
    'uraian' => 'Service iPhone 14 Pro Max ' . $noService,
    'unit_id' => $unitId,
], $inputBy);

ok('createPosition kasbon', !empty($rKasbon['success']), $rKasbon['message'] ?? '');
ok('createPosition piutang pelanggan', !empty($rPiutang['success']), $rPiutang['message'] ?? '');

$createdHpIds = [];
if (!empty($rKasbon['id'])) {
    $createdHpIds[] = (int) $rKasbon['id'];
}
if (!empty($rPiutang['id'])) {
    $createdHpIds[] = (int) $rPiutang['id'];
}

$hpPembelian = $db->table('hutang_piutang')->where('sumber_tipe', 'pembelian')->where('sumber_id', $pembelianId)->get()->getRow();
$hpLegacy = $db->table('hutang_piutang')->where('sumber_tipe', 'piutang_legacy')->where('sumber_id', $piutangId)->get()->getRow();
$hpKasbon = !empty($rKasbon['id']) ? $db->table('hutang_piutang')->where('id', $rKasbon['id'])->get()->getRow() : null;
$hpPiutang = !empty($rPiutang['id']) ? $db->table('hutang_piutang')->where('id', $rPiutang['id'])->get()->getRow() : null;

foreach ([$hpPembelian, $hpLegacy, $hpKasbon, $hpPiutang] as $hp) {
    if ($hp) {
        $createdHpIds[] = (int) $hp->id;
    }
}

// ------------------------------------------------------------- uji adapter
// 1. KASBON
$d = $hpKasbon ? $svc->getDetailTransaksi($hpKasbon) : [];
ok('kasbon: sumber = Kasbon Pegawai', ($d['sumber_label'] ?? '') === 'Kasbon Pegawai');
ok('kasbon: label pihak = Nama Pegawai', ($d['pihak_label'] ?? '') === 'Nama Pegawai');
ok('kasbon: nama pegawai terisi', strpos((string) ($d['pihak_nama'] ?? ''), trim($namaPegawai)) !== false, $d['pihak_nama'] ?? '');
ok('kasbon: keperluan = uraian', ($d['detail'] ?? '') === 'Kasbon untuk biaya transport', $d['detail'] ?? '');
$html = $hpKasbon ? renderCetak($db, $hpKasbon) : '';
ok('kasbon: HTML memuat Keperluan & nama', strpos($html, 'Kasbon untuk biaya transport') !== false && strpos($html, 'Detail Transaksi / Keperluan') !== false && strpos($html, trim($namaPegawai)) !== false);

// 2. PIUTANG PELANGGAN
$d = $hpPiutang ? $svc->getDetailTransaksi($hpPiutang) : [];
ok('piutang pelanggan: sumber benar', ($d['sumber_label'] ?? '') === 'Piutang Pelanggan');
ok('piutang pelanggan: label pihak', ($d['pihak_label'] ?? '') === 'Nama Pelanggan');
ok('piutang pelanggan: nama pelanggan', ($d['pihak_nama'] ?? '') === $pelangganNama, $d['pihak_nama'] ?? '');
ok('piutang pelanggan: referensi service terdeteksi', ($d['referensi'] ?? '') === $noService, $d['referensi'] ?? '');
ok('piutang pelanggan: label referensi No. Service', ($d['referensi_label'] ?? '') === 'No. Service');
$itemNames = array_column($d['items'] ?? [], 'nama');
ok('piutang pelanggan: detail barang/jasa service', in_array($barangNama, $itemNames, true), implode(',', $itemNames));
$html = $hpPiutang ? renderCetak($db, $hpPiutang) : '';
ok('piutang pelanggan: HTML memuat no service & item', strpos($html, $noService) !== false && strpos($html, $barangNama) !== false && strpos($html, 'Detail Barang / Jasa') !== false);

// 3. PEMBELIAN
$d = $hpPembelian ? $svc->getDetailTransaksi($hpPembelian) : [];
ok('pembelian: sumber = Hutang Supplier', ($d['sumber_label'] ?? '') === 'Hutang Supplier');
ok('pembelian: label pihak = Nama Supplier', ($d['pihak_label'] ?? '') === 'Nama Supplier');
ok('pembelian: nama supplier', ($d['pihak_nama'] ?? '') === $suplierNama, $d['pihak_nama'] ?? '');
ok('pembelian: referensi = no nota supplier', ($d['referensi'] ?? '') === $noNota, $d['referensi'] ?? '');
$itemNames = array_column($d['items'] ?? [], 'nama');
ok('pembelian: detail barang terisi', in_array($barangNama, $itemNames, true), implode(',', $itemNames));
$html = $hpPembelian ? renderCetak($db, $hpPembelian) : '';
ok('pembelian: HTML memuat nota & supplier & barang', strpos($html, $noNota) !== false && strpos($html, $suplierNama) !== false && strpos($html, $barangNama) !== false);

// 4. PIUTANG LEGACY
$d = $hpLegacy ? $svc->getDetailTransaksi($hpLegacy) : [];
ok('legacy: sumber = Piutang Pegawai', ($d['sumber_label'] ?? '') === 'Piutang Pegawai');
ok('legacy: label pihak = Nama Pegawai', ($d['pihak_label'] ?? '') === 'Nama Pegawai');
ok('legacy: nama pegawai', strpos((string) ($d['pihak_nama'] ?? ''), trim($namaPegawai)) !== false, $d['pihak_nama'] ?? '');
ok('legacy: referensi = kode piutang', ($d['referensi'] ?? '') === $kodePiutang, $d['referensi'] ?? '');
ok('legacy: label referensi Kode Piutang', ($d['referensi_label'] ?? '') === 'Kode Piutang');
$html = $hpLegacy ? renderCetak($db, $hpLegacy) : '';
ok('legacy: HTML memuat kode piutang & pegawai', strpos($html, $kodePiutang) !== false && strpos($html, 'Piutang Pegawai') !== false);

// --------------------------------------------------------------- uji PDF
if ($hpPiutang) {
    try {
        $htmlPdf = renderCetak($db, $hpPiutang);
        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12]);
        $mpdf->curlAllowUnsafeSslRequests = true;
        error_reporting(0);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $mpdf->WriteHTML($htmlPdf);
        $out = sys_get_temp_dir() . '/bukti_hp_detail.pdf';
        $mpdf->Output($out, 'F');
        $size = is_file($out) ? filesize($out) : 0;
        ok('PDF dengan detail barang terbentuk', $size > 1000, $size . ' bytes');
        @unlink($out);
    } catch (\Throwable $e) {
        ok('PDF dengan detail barang terbentuk', false, $e->getMessage());
    }
}

// ----------------------------------------------------------------- cleanup
if (!empty($createdHpIds)) {
    $db->table('pembayaran_hutang_piutang')->whereIn('hutang_piutang_id', $createdHpIds)->delete();
    $db->table('jurnal')->where('tabel_referensi', 'hutang_piutang')->whereIn('id_referensi', $createdHpIds)->delete();
    $db->table('hutang_piutang')->whereIn('id', $createdHpIds)->delete();
}
$db->table('service_sparepart')->where('service_idservice', $serviceId)->delete();
$db->table('service')->where('idservice', $serviceId)->delete();
$db->table('detail_pembelian')->where('pembelian_idpembelian', $pembelianId)->delete();
$db->table('pembelian')->where('idpembelian', $pembelianId)->delete();
$db->table('piutang')->where('idpiutang', $piutangId)->delete();
$db->table('suplier')->where('id_suplier', $suplierId)->delete();
$db->table('pelanggan')->where('id_pelanggan', $pelangganId)->delete();
$db->table('barang')->where('idbarang', $barangId)->delete();

echo "\n" . str_repeat('-', 48) . "\n";
echo "HASIL: {$passed} PASS, {$failed} FAIL\n";
exit($failed > 0 ? 1 : 0);
