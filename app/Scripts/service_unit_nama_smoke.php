<?php
/**
 * Verifikasi nama unit pada daftar Service (datadriven, bukan hardcoded).
 *
 * Unit "ICLEAR Genteng" (id 5) ditambahkan lewat migration EnsureUnitGenteng,
 * tapi halaman /proses_service, /riwayat_service, /bisa_diambil, dan
 * /sudah_diambil masih memetakan 1..4 => nama kota di dalam view/query.
 * Akibatnya service Genteng tampil "Tidakdiketahui" dan tidak bisa difilter.
 *
 * Test ini mengunci: nama unit selalu diambil dari tabel `unit`, dan filter
 * unit menerima id maupun nama sehingga unit baru otomatis ikut muncul.
 *
 * Jalankan: php app/Scripts/service_unit_nama_smoke.php
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

$model = new App\Models\ModelService();

$unitList = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getResult();
check('tabel unit punya unit Gentle/Genteng', count(array_filter($unitList, static fn ($u) => stripos((string) $u->NAMA_UNIT, 'genteng') !== false)) > 0);

// Unit tanpa nama hardcoded di view: ambil yang bukan 1..4 supaya regression
// kembali ke 1..4 langsung ketahuan.
$unitBaru = null;
foreach ($unitList as $u) {
    if (! in_array((int) $u->idunit, [1, 2, 3, 4], true)) {
        $unitBaru = $u;
        break;
    }
}
check('ada unit di luar 1..4 untuk diuji', $unitBaru !== null);
if ($unitBaru === null) {
    exit(1);
}
$unitId   = (int) $unitBaru->idunit;
$unitNama = (string) $unitBaru->NAMA_UNIT;

// resolveUnitFilter: id, nama lengkap, dan nilai ngawur.
check('resolveUnitFilter menerima id unit', $model->resolveUnitFilter((string) $unitId) === $unitId);
check('resolveUnitFilter menerima nama unit', $model->resolveUnitFilter($unitNama) === $unitId);
check('resolveUnitFilter menolak unit ngawur', $model->resolveUnitFilter('UnitTidakAda') === null);
check('resolveUnitFilter kosong = semua unit', $model->resolveUnitFilter('') === null);

$db->query("INSERT INTO pelanggan (nama, no_hp, deleted) VALUES ('Smoke Unit Nama', '081234567890', 0)");
$pid = $db->insertID();
$db->query("INSERT INTO service (no_service, no_hp, tipe_hp, pelanggan_id_pelanggan, unit_idunit, status_service, status_proses, created_at)
            VALUES ('SRV-SMOKE-UNIT', '081234567890', 'Smoke', {$pid}, {$unitId}, 1, 1, NOW())");
$sid = $db->insertID();

$ambilSatu = static function (array $rows, int $id) {
    foreach ($rows as $r) {
        if ((int) $r->idservice === $id) {
            return $r;
        }
    }
    return null;
};

// /proses_service (status 1) - tanpa filter, kolom unit harus bernama asli.
$res = $model->ProsesServiceAktifServerSide(0, 500, '', 0, 'asc', '', '', '');
$row = $ambilSatu($res['data'], $sid);
check('proses_service: nama_unit terisi dari DB', $row !== null && $row->nama_unit === $unitNama, var_export($row->nama_unit ?? null, true));

$res = $model->ProsesServiceAktifServerSide(0, 500, '', 0, 'asc', '', '', (string) $unitId);
check('proses_service: filter by id unit', $ambilSatu($res['data'], $sid) !== null);

$res = $model->ProsesServiceAktifServerSide(0, 500, '', 0, 'asc', '', '', $unitNama);
check('proses_service: filter by nama unit', $ambilSatu($res['data'], $sid) !== null);

// /riwayat_service
$res = $model->getRiwayatServiceServerSide(0, 500, '', 0, 'asc', '', '', (string) $unitId);
$row = $ambilSatu($res['data'], $sid);
check('riwayat_service: nama_unit terisi dari DB', $row !== null && $row->nama_unit === $unitNama, var_export($row->nama_unit ?? null, true));

// /sudah_diambil. Di-cari lewat no_service karena halaman ini tidak punya
// filter unit, dan baris smoke (tanggal_selesai NULL) selalu terpotong di
// halaman pertama saat diurutkan tanggal_selesai DESC.
$db->query("UPDATE service SET status_service = 4 WHERE idservice = {$sid}");
$res = $model->ServiceSudahDiambilServerSide(0, 50, 'SRV-SMOKE-UNIT', 0, 'desc', '', '');
$row = $res['data'][0] ?? null;
check('sudah_diambil: nama_unit terisi dari DB', $row !== null && $row->nama_unit === $unitNama, var_export($row->nama_unit ?? null, true));

// /bisa_diambil
$db->query("UPDATE service SET status_service = 3 WHERE idservice = {$sid}");
$rows = $model->ServiceBisaDiambil();
$row  = $ambilSatu($rows, $sid);
check('bisa_diambil: nama_unit terisi dari DB', $row !== null && $row->nama_unit === $unitNama, var_export($row->nama_unit ?? null, true));

$db->query("DELETE FROM service WHERE idservice = {$sid}");
$db->query("DELETE FROM pelanggan WHERE id_pelanggan = {$pid}");

echo $fail === 0 ? "\nSemua PASS.\n" : "\n{$fail} FAIL.\n";
exit($fail === 0 ? 0 : 1);
