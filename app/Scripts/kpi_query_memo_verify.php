<?php
/**
 * Self-check optimasi query jalur KPI/gaji & finance (masalah N+1 di
 * /asset_berjalan).
 *
 * Dua hal yang dijaga:
 *  1. ANGKA TIDAK BERUBAH — hasil dibandingkan dengan patokan (fixture).
 *  2. PEKERJAAN MAHAL TIDAK DIULANG — panggilan kedua dengan argumen sama
 *     tidak menambah query, termasuk lewat instance service yang baru
 *     (halaman membuat service baru per pegawai/kalkulator).
 *
 * Pakai:
 *   php app/Scripts/kpi_query_memo_verify.php           # verifikasi
 *   php app/Scripts/kpi_query_memo_verify.php record    # tulis ulang patokan
 *
 * Jangan pakai mode record saat sedang merapikan performa: patokan HARUS
 * berasal dari kode sebelum optimasi. Tulis ulang hanya kalau memang data
 * contohnya berubah.
 *
 * Exit 0 = semua PASS. Bootstrap mengikuti assetberjalan_debug.php.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
foreach (['APPPATH'=>'appDirectory','ROOTPATH'=>'','SYSTEMPATH'=>'systemDirectory','WRITEPATH'=>'writableDirectory','TESTPATH'=>'testsDirectory'] as $c=>$p) {
    if (!defined($c)) { $v = $c==='ROOTPATH' ? realpath(APPPATH.'../') : realpath(rtrim($paths->$p,'\\/ ')); define($c, $v.DIRECTORY_SEPARATOR); }
}
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$gagal = 0;
function check(string $nama, bool $kondisi): void
{
    global $gagal;
    echo ($kondisi ? 'PASS' : 'FAIL') . ' ' . $nama . "\n";
    if (! $kondisi) {
        $gagal++;
    }
}

// Penghitung query: event DBQuery dipicu CI4 tiap query dijalankan.
$GLOBALS['kpi_q_total'] = 0;
$GLOBALS['kpi_q_sql']   = [];
\CodeIgniter\Events\Events::on('DBQuery', static function ($event) {
    $GLOBALS['kpi_q_total']++;
    if ($event instanceof \CodeIgniter\Database\Query) {
        $GLOBALS['kpi_q_sql'][] = $event->getQuery();
    }
});
$qcount = function (): int {
    return $GLOBALS['kpi_q_total'];
};
// Berapa kali sebuah tabel/klausa dibaca — untuk membuktikan pembacaan berat
// tidak diulang, tanpa bergantung pada angka total yang bisa bergeser.
$hitungSql = function (string $needle): int {
    $n = 0;
    foreach ($GLOBALS['kpi_q_sql'] as $q) {
        if (stripos($q, $needle) !== false) {
            $n++;
        }
    }
    return $n;
};
$mulaiHitung = function (): void {
    $GLOBALS['kpi_q_total'] = 0;
    $GLOBALS['kpi_q_sql']   = [];
};

$db = \Config\Database::connect();

// Sanity: pastikan penghitung memang jalan (kalau tidak, check cache jadi palsu).
$db->table('akun')->limit(1)->get();
check('penghitung query berfungsi', $qcount() > 0);

// Periode uji: bulan yang sudah tutup supaya patokan tidak bergeser oleh input baru.
const BULAN   = '09';
const TAHUN   = '2026';
const TGL     = '2026-09-15';
const PERIODE = '2026-09';
const AWAL    = '2026-09-01';
const AKHIR   = '2026-09-30';

$hoEmployees = $db->table('akun')
    ->select('ID_AKUN, ID_JABATAN')
    ->where('ID_UNIT', 50)
    ->where('STATUS_PEGAWAI', 1)
    ->where('TERIMA_GAJI', 1)
    ->orderBy('ID_AKUN', 'ASC')
    ->limit(3)
    ->get()->getResultArray();

// Pegawai Finance (jabatan 0) — jalur kas/rekon harian yang paling berat.
$financeRow = $db->table('akun')
    ->select('ID_AKUN')
    ->where('ID_UNIT', 50)
    ->where('STATUS_PEGAWAI', 1)
    ->where('TERIMA_GAJI', 1)
    ->where('ID_JABATAN', 0)
    ->orderBy('ID_AKUN', 'ASC')
    ->get()->getRowArray();

if (count($hoEmployees) < 3 || $financeRow === null) {
    echo "SKIP: pegawai HO/FInance untuk diuji tidak lengkap\n";
    exit(1);
}

$empFinance  = (int) $financeRow['ID_AKUN'];
$fixturePath  = __DIR__ . '/fixtures/kpi_query_memo_golden.json';
$fixturePath2 = __DIR__ . '/fixtures/kpi_finance_memo_golden.json';
$mode         = $argv[1] ?? 'verify';

// ---------------------------------------------------------------------------
// 1. Anggaran query: satu perhitungan KPI pegawai Finance (paling berat).
//    Dijalankan paling awal supaya cache benar-benar dingin.
// ---------------------------------------------------------------------------
$mulaiHitung();
$kpiFinance   = (new \App\Services\Kpi\KpiCalculationService())
    ->calculateForSalary($empFinance, BULAN, TAHUN, 'gaji', TGL, TGL);
$biayaFinance = $qcount();

check("finance: satu pegawai <= 240 query (nyatanya $biayaFinance)", $biayaFinance <= 240);

// ---------------------------------------------------------------------------
// 2. Sumber data finance: agregat rentang & peta acuan tidak diulang.
//    Semua check berbasis selisih, jadi tidak bergantung urutan pemanggilan.
// ---------------------------------------------------------------------------
// a) Agregat rentang TutupKasir — beberapa kalkulator berbeda meminta unit &
//    rentang yang sama, dan tiap method = 1 query.
$sumber = new \App\Services\Finance\TutupKasirSourceDefinition();
$rang1  = $sumber->cashPenjualanRange(1, AWAL, AKHIR);
$nR1    = $qcount();
$rang2  = (new \App\Services\Finance\TutupKasirSourceDefinition())->cashPenjualanRange(1, AWAL, AKHIR);

check('finance: agregat rentang tidak diulang (instance baru)', $qcount() === $nR1);
check('finance: nilai agregat rentang identik', $rang1 === $rang2);

// b) Agregat Kesehatan Uang (hpp & kawan-kawan).
$kesehatan = new \App\Services\Finance\KesehatanUangCalculator();
$hpp1      = $kesehatan->hpp(1, AWAL, AKHIR);
$nK1       = $qcount();
$hpp2      = (new \App\Services\Finance\KesehatanUangCalculator())->hpp(1, AWAL, AKHIR);

check('finance: KesehatanUang hpp tidak diulang (instance baru)', $qcount() === $nK1);
check('finance: nilai KesehatanUang hpp identik', $hpp1 === $hpp2);

// c) Hitungan rekon harian.
$rekon  = new \App\Models\ModelFinanceRekonDaily();
$rekon1 = $rekon->countLengkapVerifiedInRange(1, AWAL, AKHIR);
$nM1    = $qcount();
$rekon2 = (new \App\Models\ModelFinanceRekonDaily())->countLengkapVerifiedInRange(1, AWAL, AKHIR);

check('finance: rekon harian tidak diulang (instance baru)', $qcount() === $nM1);
check('finance: nilai rekon harian identik', $rekon1 === $rekon2);

// d) Peta pembayaran hutang (tanpa argumen): isinya sama untuk semua unit.
$mulaiHitung();
(new \App\Services\Finance\HutangTimelinessCalculator())->calculate(1, (int) BULAN, (int) TAHUN);
$hutang1 = $hitungSql('pembayaran_hutang');
(new \App\Services\Finance\HutangTimelinessCalculator())->calculate(1, (int) BULAN, (int) TAHUN);
$hutang2 = $hitungSql('pembayaran_hutang');

check("finance: pembayaran_hutang tidak dibaca ulang (2x hitung = $hutang1 lalu $hutang2)", $hutang2 === $hutang1);

// e) Sidebar: menu tidak boleh jadi N+1 per baris menu.
$mulaiHitung();
$menu1      = (new \App\Models\Core())->get_menu_show();
$menuSekali = $hitungSql('FROM `menu`');
$menu2      = (new \App\Models\Core())->get_menu_show();

check("menu: sekali bangun hemat (<= 5 query, nyatanya $menuSekali)", $menuSekali <= 5);
check('menu: bangun kedua tidak menambah query menu', $hitungSql('FROM `menu`') === $menuSekali);
check('menu: hasil identik', $menu1 === $menu2);

// f) Daftar service aktif dibangun tiap request (BaseController): hitungan
//    kerusakan/sparepart per baris harus sekali (GROUP BY), bukan per service.
$mulaiHitung();
$services      = (new \App\Models\ModelService())->ProsesServiceAktif();
$bacaKerusakan = $hitungSql('FROM `service_kerusakan`');
$bacaSparepart = $hitungSql('FROM `service_sparepart`');
$jmlService    = count($services);

check(
    "service aktif: hitungan per baris di-batch ($bacaKerusakan + $bacaSparepart query untuk $jmlService baris)",
    $bacaKerusakan <= 2 && $bacaSparepart <= 2
);

if ($jmlService > 0) {
    $pertama      = $services[0];
    $cekKerusakan = (int) $db->table('service_kerusakan')
        ->where('service_idservice', $pertama->idservice)
        ->countAllResults();
    check('service aktif: jumlah kerusakan baris pertama tetap benar', (int) $pertama->jumlah_kerusakan === $cekKerusakan);
}

// g) Cache baca wajib batal begitu ada query tulis di request yang sama.
//    Inilah yang menjaga pola "simpan lalu hitung ulang" tetap benar
//    (dipakai rekon_daily_test.php dan alur simpan/verifikasi).
$rekonUji = new \App\Models\ModelFinanceRekonDaily();
$db->table('finance_rekon_daily')->where('unit_id', 1)->where('tanggal', '2019-01-15')->delete();
$hitungAwal = $rekonUji->countLengkapVerifiedInRange(1, '2019-01-01', '2019-01-31');
$db->table('finance_rekon_daily')->insert([
    'unit_id'             => 1,
    'tanggal'             => '2019-01-15',
    'actual_cash_masuk'   => 1000000,
    'actual_transfer_masuk' => 500000,
    'actual_kas_keluar'   => 250000,
    'selisih_cash_masuk'  => 0,
    'selisih_transfer_masuk' => 0,
    'selisih_kas_keluar'  => 0,
    'status_proses'       => 'verified',
]);
$hitungSetelahTulis = $rekonUji->countLengkapVerifiedInRange(1, '2019-01-01', '2019-01-31');
$db->table('finance_rekon_daily')->where('unit_id', 1)->where('tanggal', '2019-01-15')->delete();
$hitungSetelahHapus = $rekonUji->countLengkapVerifiedInRange(1, '2019-01-01', '2019-01-31');

check(
    "cache baca batal setelah tulis ($hitungAwal -> $hitungSetelahTulis -> $hitungSetelahHapus)",
    $hitungSetelahTulis === $hitungAwal + 1 && $hitungSetelahHapus === $hitungAwal
);

// ---------------------------------------------------------------------------
// 3. Absen & distribusi gaji HO: hasil dibaca sekali per request.
// ---------------------------------------------------------------------------
$empId = (int) $hoEmployees[0]['ID_AKUN'];

$att1 = (new \App\Services\Kpi\AttendanceAggregationService())
    ->calculateMonthlyAttendance($empId, 50, BULAN, TAHUN, 'gaji');
$nA2  = $qcount();
$att2 = (new \App\Services\Kpi\AttendanceAggregationService())
    ->calculateMonthlyAttendance($empId, 50, BULAN, TAHUN, 'gaji');

check('absen: panggilan ulang tidak menambah query (instance baru)', $qcount() === $nA2);
check('absen: hasil identik', $att1 === $att2);

// Distribusi HO: sekali hitung untuk seluruh periode, lalu bagian per unit
// diambil dari hasil itu — bukan menghitung ulang seluruh KPI pegawai.
$dist      = (new \App\Services\Payroll\HoSalaryDistributionService())->calculateDistribution(PERIODE);
$nD1       = $qcount();
$biayaDist = $nD1 - $nA2;
check("distribusi: satu periode <= 3000 query (nyatanya $biayaDist)", $biayaDist <= 3000);

$shares = (new \App\Services\Payroll\HoSalaryDistributionService())->getUnitShares(PERIODE);
check('distribusi: getUnitShares memakai hasil yang sama (0 query)', $qcount() === $nD1);
$sharesLain = (new \App\Services\Payroll\HoSalaryDistributionService())->getUnitShares(PERIODE);
check('distribusi: hasil getUnitShares identik', $shares === $sharesLain);

$distLain = (new \App\Services\Payroll\HoSalaryDistributionService())->calculateDistribution(PERIODE);
check('distribusi: panggilan kedua 0 query', $qcount() === $nD1);
check('distribusi: hasil calculateDistribution identik', $dist == $distLain);

// ---------------------------------------------------------------------------
// 4. Susun angka untuk dibandingkan dengan patokan.
// ---------------------------------------------------------------------------
$snapshot = ['attendance' => [], 'kpi' => [], 'dist' => [], 'shares' => $shares];

foreach ($hoEmployees as $emp) {
    $id = (int) $emp['ID_AKUN'];

    $snapshot['attendance'][$id] = (new \App\Services\Kpi\AttendanceAggregationService())
        ->calculateMonthlyAttendance($id, 50, BULAN, TAHUN, 'gaji');

    $kpi = (new \App\Services\Kpi\KpiCalculationService())
        ->calculateForSalary($id, BULAN, TAHUN, 'gaji', TGL, TGL);
    $snapshot['kpi'][$id] = [
        'skor_total'  => $kpi['skor_total'] ?? null,
        'skor_total2' => $kpi['skor_total2'] ?? null,
        'insentif'    => $kpi['insentif'] ?? null,
    ];
}

$snapshot['dist'] = [
    'divisor'            => $dist['divisor'],
    'total_perkiraan'    => $dist['total_perkiraan'],
    'total_dipotong'     => $dist['total_dipotong'],
    'per_employee_share' => $dist['per_employee_share'],
    'employees'          => array_map(static function ($e) {
        return [
            'perkiraan_gaji' => $e['perkiraan_gaji'] ?? null,
            'insentif'       => $e['insentif'] ?? null,
        ];
    }, $dist['employees']),
];

// Angka jalur finance/kasir (patokan fase 2).
$snapshotFinance = [
    'items' => array_map(static function ($it) {
        return [
            'code'           => $it['code'] ?? null,
            'weighted_score' => $it['weighted_score'] ?? null,
            'achievement'    => $it['achievement'] ?? null,
        ];
    }, $kpiFinance['items'] ?? []),
    'agregat' => [
        'cashPenjualanRange' => $rang1,
        'hppUnit1'           => $hpp1,
        'rekonVerifiedUnit1' => $rekon1,
        'menuJumlah'         => count($menu1),
    ],
];

if ($mode === 'record') {
    @mkdir(dirname($fixturePath), 0775, true);
    file_put_contents($fixturePath, json_encode($snapshot, JSON_PRETTY_PRINT));
    file_put_contents($fixturePath2, json_encode($snapshotFinance, JSON_PRETTY_PRINT));
    echo "Patokan ditulis ke $fixturePath dan $fixturePath2\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// 5. Angka tidak bergeser dari patokan.
// ---------------------------------------------------------------------------
if (! is_file($fixturePath) || ! is_file($fixturePath2)) {
    check('patokan tersedia', false);
    echo "Jalankan mode record lebih dulu.\n";
    exit(1);
}

$golden = json_decode((string) file_get_contents($fixturePath), true);
$beda   = [];
foreach (['attendance', 'kpi', 'dist', 'shares'] as $bagian) {
    if (($snapshot[$bagian] ?? null) != ($golden[$bagian] ?? null)) {
        $beda[] = $bagian;
    }
}
check('angka identik dengan patokan' . ($beda ? ' (beda: ' . implode(', ', $beda) . ')' : ''), $beda === []);

$golden2 = json_decode((string) file_get_contents($fixturePath2), true);
check(
    'angka finance identik dengan patokan' . (($snapshotFinance != $golden2) ? ' (beda)' : ''),
    $snapshotFinance == $golden2
);

echo $gagal === 0 ? "\nSEMUA PASS\n" : "\n$gagal CHECK GAGAL\n";
exit($gagal === 0 ? 0 : 1);
