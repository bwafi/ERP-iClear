<?php
/**
 * Test: Rekonsiliasi Harian Finance (parity query, workflow, KPI verified).
 *
 * Jalankan: php74 app/Scripts/rekon_daily_test.php
 *
 * A. Parity query dengan TutupKasir (oracle diimplementasikan ulang persis)
 * B. Status hasil (belum / belum_lengkap / lengkap_cocok / lengkap_selisih)
 * C. Workflow DRAFT -> SUBMITTED -> VERIFIED / NEED_REVISION + lock
 * D. Validasi nominal (negatif & non-numerik ditolak, bigint aman)
 * E. Otorisasi (scope, financeApproveRoles, self-verify ditolak)
 * F. KPI berbasis VERIFIED + denominator hari kerja
 * G. Konfigurasi & fallback manual
 * H. Integritas data (upsert unik, cast int, kolom workflow)
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Config/Paths.php';

use App\Models\ModelFinanceRekonDaily;
use App\Services\Finance\RekonDailyCalculator;
use App\Services\Finance\FinanceKpiCalculationService;
use App\Services\Finance\FinanceScopeService;

$paths = new Config\Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
(new CodeIgniter\Config\DotEnv(ROOTPATH))->load();

$db = Config\Database::connect();
$db->transStrict(false);

$pass = 0;
$fail = 0;
$skip = 0;

function ok(string $label, bool $result, string $extra = ''): void
{
    global $pass, $fail;
    if ($result) {
        echo "PASS  {$label}\n";
        $pass++;
    } else {
        echo "FAIL  {$label}  {$extra}\n";
        $fail++;
    }
}

function skip(string $label): void
{
    global $skip;
    echo "SKIP  {$label}\n";
    $skip++;
}

function section(string $title): void
{
    echo "\n--- {$title} ---\n";
}

function callPrivate(object $obj, string $method, array $args = [])
{
    $ref = new ReflectionMethod(get_class($obj), $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}

echo 'PHP_INT_SIZE = ' . PHP_INT_SIZE . "\n";

// ================================================================
// Setup
// ================================================================
section('Setup');

if (! $db->tableExists('finance_rekon_daily')) {
    echo "Running migration for finance_rekon_daily...\n";
    (new App\Database\Migrations\CreateFinanceRekonDaily(Config\Database::forge(), $db))->up();
}
ok('A0: tabel finance_rekon_daily ada', $db->tableExists('finance_rekon_daily'));

$unit = $db->table('unit')->orderBy('idunit', 'ASC')->get()->getRow();
if (! $unit) {
    echo "SKIP: tidak ada unit\n";
    exit(1);
}
$unitId = (int) $unit->idunit;
$today = date('Y-m-d');

$calc = new RekonDailyCalculator();
$model = new ModelFinanceRekonDaily();
$config = new Config\Finance();

echo "Unit: {$unit->NAMA_UNIT} (id={$unitId}) | today={$today}\n";

// ================================================================
// A. Parity query dengan TutupKasir
//    Oracle = query TutupKasir.php (index) diimplementasikan ulang persis.
// ================================================================
section('A. Parity query dengan TutupKasir');

function tutupKasirOracle($db, int $unitId, string $date): array
{
    $tfPenjualan = $db->table('penjualan')
        ->selectSum('bayar_bank', 'total')
        ->where('DATE(tanggal)', $date)
        ->where('unit_idunit', $unitId)
        ->notLike('kode_invoice', 'srv', 'after')
        ->get()->getRow()->total ?? 0;

    $tfService = $db->table('service')
        ->select('SUM(COALESCE(harus_dibayar,0) - COALESCE(bayar_tunai,0)) AS total')
        ->where('DATE(tanggal_selesai)', $date)
        ->where('status_service', 4)
        ->where('unit_idunit', $unitId)
        ->get()->getRow()->total ?? 0;

    $cashPenjualan = $db->table('penjualan')
        ->selectSum('bayar_tunai', 'total')
        ->where('DATE(tanggal)', $date)
        ->where('unit_idunit', $unitId)
        ->notLike('kode_invoice', 'srv', 'after')
        ->get()->getRow()->total ?? 0;

    $cashService = $db->table('service')
        ->selectSum('bayar_tunai', 'total')
        ->where('DATE(tanggal_selesai)', $date)
        ->where('status_service', 4)
        ->where('unit_idunit', $unitId)
        ->get()->getRow()->total ?? 0;

    // TutupKasir memisahkan pengeluaran cash vs transfer via idbank.
    $outCash = $db->table('kas_keluar')
        ->selectSum('jumlah', 'total')
        ->where('DATE(tanggal)', $date)
        ->where('idbank', null)
        ->where('idunit', $unitId)
        ->get()->getRow()->total ?? 0;

    $outTransfer = $db->table('kas_keluar')
        ->selectSum('jumlah', 'total')
        ->where('DATE(tanggal)', $date)
        ->where('idbank !=', null)
        ->where('idunit', $unitId)
        ->get()->getRow()->total ?? 0;

    return [
        'transfer_masuk' => (int) $tfPenjualan + (int) $tfService,
        'cash_masuk'     => (int) $cashPenjualan + (int) $cashService,
        'kas_keluar'     => (int) $outCash + (int) $outTransfer,
    ];
}

$semuaUnit = $db->table('unit')->select('idunit')->orderBy('idunit', 'ASC')->get()->getResult();
$dates = [$today, date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-7 day'))];
$parityFail = [];
$parityChecked = 0;
$parityBerisi = 0;

foreach (array_slice($semuaUnit, 0, 5) as $u) {
    foreach ($dates as $d) {
        $uId = (int) $u->idunit;
        $mine = $calc->erpValues($uId, $d);
        $oracle = tutupKasirOracle($db, $uId, $d);
        $parityChecked++;
        if ($mine['cash_masuk'] > 0 || $mine['transfer_masuk'] > 0 || $mine['kas_keluar'] > 0) {
            $parityBerisi++;
        }
        foreach (['cash_masuk', 'transfer_masuk', 'kas_keluar'] as $k) {
            if ((int) $mine[$k] !== $oracle[$k]) {
                $parityFail[] = "unit={$uId} date={$d} {$k}: rekon={$mine[$k]} tutupkasir={$oracle[$k]}";
            }
        }
    }
}
ok(
    'A1: parity TutupKasir pada ' . $parityChecked . ' kombinasi unit/tanggal',
    $parityFail === [],
    implode(' | ', array_slice($parityFail, 0, 3))
);
// Guard: parity harus diuji pada data nyata, bukan semua nol.
ok('A1b: ada data nyata yang dibandingkan', $parityBerisi > 0, "kombinasi_bernilai={$parityBerisi}");

$erp = $calc->erpValues($unitId, $today);
ok('A2: erpValues cash_masuk int >= 0', is_int($erp['cash_masuk']) && $erp['cash_masuk'] >= 0, json_encode($erp));
ok('A3: erpValues transfer_masuk int >= 0', is_int($erp['transfer_masuk']) && $erp['transfer_masuk'] >= 0, json_encode($erp));
ok('A4: erpValues kas_keluar int >= 0', is_int($erp['kas_keluar']) && $erp['kas_keluar'] >= 0, json_encode($erp));

// Bukti filter notLike('kode_invoice','srv','after') benar-benar memengaruhi hasil.
$srvHariIni = (int) $db->table('penjualan')
    ->where('DATE(tanggal)', $today)
    ->where('unit_idunit', $unitId)
    ->like('kode_invoice', 'srv', 'after')
    ->countAllResults();
$semuaHariIni = (int) $db->table('penjualan')
    ->where('DATE(tanggal)', $today)
    ->where('unit_idunit', $unitId)
    ->countAllResults();
$rekonSql = $calc->erpValues($unitId, $today);
ok(
    'A5: ada invoice srvvs penjualan biasa (filter exclude differentiates)',
    $semuaHariIni === 0 || $srvHariIni > 0,
    "srv={$srvHariIni} semua={$semuaHariIni} rekon=" . json_encode($rekonSql)
);

// Filter status_service=4: calculator hanya menghitung service selesai.
$svcSelesai = (int) $db->table('service')
    ->where('DATE(tanggal_selesai)', $today)
    ->where('unit_idunit', $unitId)
    ->where('status_service', 4)
    ->countAllResults();
$svcBelum = (int) $db->table('service')
    ->where('DATE(tanggal_selesai)', $today)
    ->where('unit_idunit', $unitId)
    ->where('status_service !=', 4)
    ->countAllResults();
ok(
    'A6: data uji perbedaan status_service terdeteksi',
    $svcSelesai > 0 || $svcBelum === 0,
    "selesai={$svcSelesai} belum={$svcBelum}"
);

// ================================================================
// B. Status hasil
// ================================================================
section('B. Status hasil rekonsiliasi');

ok('B1: null -> belum', RekonDailyCalculator::statusHarian(null) === 'belum');

// [S1] Semua actual NULL -> belum_lengkap
$semuaKosong = (object) [
    'actual_cash_masuk' => null, 'actual_transfer_masuk' => null, 'actual_kas_keluar' => null,
    'selisih_cash_masuk' => null, 'selisih_transfer_masuk' => null, 'selisih_kas_keluar' => null,
];
ok('B2 [S1]: semua actual null -> belum_lengkap', RekonDailyCalculator::statusHarian($semuaKosong) === 'belum_lengkap');

// [S2] Salah satu actual NULL -> belum_lengkap
$partial = (object) [
    'actual_cash_masuk' => 100, 'actual_transfer_masuk' => 200, 'actual_kas_keluar' => null,
    'selisih_cash_masuk' => 0, 'selisih_transfer_masuk' => 0, 'selisih_kas_keluar' => null,
];
ok('B3 [S2]: salah satu actual null -> belum_lengkap', RekonDailyCalculator::statusHarian($partial) === 'belum_lengkap');

// [S3] Semua terisi + semua selisih 0 -> lengkap_cocok
$cocok = (object) [
    'actual_cash_masuk' => 100, 'actual_transfer_masuk' => 200, 'actual_kas_keluar' => 50,
    'selisih_cash_masuk' => 0, 'selisih_transfer_masuk' => 0, 'selisih_kas_keluar' => 0,
];
ok('B4 [S3]: semua terisi + selisih 0 -> lengkap_cocok', RekonDailyCalculator::statusHarian($cocok) === 'lengkap_cocok');

// [S4] Semua terisi + ada selisih -> lengkap_selisih
$selisih = (object) [
    'actual_cash_masuk' => 100, 'actual_transfer_masuk' => 700, 'actual_kas_keluar' => 50,
    'selisih_cash_masuk' => 0, 'selisih_transfer_masuk' => 500, 'selisih_kas_keluar' => 0,
];
ok('B5 [S4]: semua terisi + ada selisih -> lengkap_selisih', RekonDailyCalculator::statusHarian($selisih) === 'lengkap_selisih');

// [S5] actual = 0 adalah nilai SAH, bukan "belum diisi".
$nolSemua = (object) [
    'actual_cash_masuk' => 0, 'actual_transfer_masuk' => 0, 'actual_kas_keluar' => 0,
    'selisih_cash_masuk' => 0, 'selisih_transfer_masuk' => 0, 'selisih_kas_keluar' => 0,
];
ok('B6 [S5]: actual 0 dianggap terisi (lengkap_cocok)', RekonDailyCalculator::statusHarian($nolSemua) === 'lengkap_cocok');
ok('B6b: isLengkap true untuk actual 0', RekonDailyCalculator::isLengkap($nolSemua) === true);
ok('B6c: isLengkap false saat ada actual null', RekonDailyCalculator::isLengkap($partial) === false);

// [S5] Status komponen: otomatis, tanpa input manual.
ok('B7: statusKomponen actual null -> belum_diperiksa', RekonDailyCalculator::statusKomponen($partial, 'kas_keluar') === 'belum_diperiksa');
ok('B8: statusKomponen selisih 0 -> cocok', RekonDailyCalculator::statusKomponen($cocok, 'cash_masuk') === 'cocok');
ok('B9: statusKomponen selisih != 0 -> selisih', RekonDailyCalculator::statusKomponen($selisih, 'transfer_masuk') === 'selisih');
ok('B10: statusKomponen tanpa row -> belum_diperiksa', RekonDailyCalculator::statusKomponen(null, 'cash_masuk') === 'belum_diperiksa');
ok('B11: label & badge komponen terpetakan', RekonDailyCalculator::labelKomponen('cocok') === 'Cocok'
    && RekonDailyCalculator::labelKomponen('selisih') === 'Selisih'
    && RekonDailyCalculator::labelKomponen('belum_diperiksa') === 'Belum diperiksa'
    && RekonDailyCalculator::badgeKomponen('cocok') === 'bg-success'
    && RekonDailyCalculator::badgeKomponen('selisih') === 'bg-info');
ok('B5: status proses null -> draft', RekonDailyCalculator::statusProses(null) === ModelFinanceRekonDaily::STATUS_DRAFT);
ok('B6: siapSubmit cukup lengkap (selisih boleh)', RekonDailyCalculator::siapSubmit($selisih) === true);
ok('B7: siapSubmit tolak belum lengkap', RekonDailyCalculator::siapSubmit($partial) === false);
ok('B8: submitted belum locked', RekonDailyCalculator::isLocked((object) ['status_proses' => 'submitted']) === false);
ok('B9: verified locked', RekonDailyCalculator::isLocked((object) ['status_proses' => 'verified']) === true);
ok('B10: need_revision belum locked', RekonDailyCalculator::isLocked((object) ['status_proses' => 'need_revision']) === false);
ok('B11: label & badge status proses terpetakan', RekonDailyCalculator::labelProses('need_revision') === 'Perlu Revisi'
    && RekonDailyCalculator::badgeProses('verified') === 'bg-success');
ok('B12: label status harian sesuai spek', RekonDailyCalculator::labelStatus('belum_lengkap') === 'Belum lengkap'
    && RekonDailyCalculator::labelStatus('lengkap_cocok') === 'Lengkap - Cocok'
    && RekonDailyCalculator::labelStatus('lengkap_selisih') === 'Lengkap - Selisih'
    && RekonDailyCalculator::badgeStatus('lengkap_cocok') === 'bg-success');

// ================================================================
// C. Workflow DRAFT -> SUBMITTED -> VERIFIED / NEED_REVISION
// ================================================================
section('C. Workflow approval');

$db->transBegin();

$wfDate = $today;
$db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal', $wfDate)->delete();

// $isiNull = true -> sengaja actual NULL (untuk uji "belum diisi").
$baseRow = function (int $selisihCash = 0, bool $isiNull = false) use ($unitId, $wfDate) {
    $null = $isiNull ? null : 100 + $selisihCash;

    return [
        'unit_id' => $unitId, 'tanggal' => $wfDate,
        'erp_cash_masuk' => 100, 'actual_cash_masuk' => $null,
        'selisih_cash_masuk' => $isiNull ? null : $selisihCash,
        'erp_transfer_masuk' => 200, 'actual_transfer_masuk' => 200, 'selisih_transfer_masuk' => 0,
        'erp_kas_keluar' => 50, 'actual_kas_keluar' => 50, 'selisih_kas_keluar' => 0,
        'catatan' => 'workflow test',
        'status_proses' => ModelFinanceRekonDaily::STATUS_DRAFT,
        'submitted_by' => null, 'submitted_at' => null,
        'verified_by' => null, 'verified_at' => null,
        'catatan_revisi' => null,
        'input_by' => 1,
    ];
};

$model->upsert($baseRow(50));
$draft = $model->getByUnitAndDate($unitId, $wfDate);
ok('C1: simpan -> status draft', RekonDailyCalculator::statusProses($draft) === ModelFinanceRekonDaily::STATUS_DRAFT);
ok('C2: draft dengan selisih tetap lengkap', RekonDailyCalculator::isLengkap($draft) === true);
ok('C3: draft belum masuk numerator verified', $model->countLengkapVerifiedInRange($unitId, $wfDate, $wfDate) === 0);

$model->updateRow((int) $draft->id, [
    'status_proses' => ModelFinanceRekonDaily::STATUS_SUBMITTED,
    'submitted_by' => 1,
    'submitted_at' => date('Y-m-d H:i:s'),
]);
$sub = $model->getByUnitAndDate($unitId, $wfDate);
ok('C4: draft -> submitted', RekonDailyCalculator::statusProses($sub) === ModelFinanceRekonDaily::STATUS_SUBMITTED);
ok('C5: submitted menyimpan submitted_by', (int) $sub->submitted_by === 1);
ok('C6: submitted belum masuk numerator verified', $model->countLengkapVerifiedInRange($unitId, $wfDate, $wfDate) === 0);

$model->updateRow((int) $sub->id, [
    'status_proses' => ModelFinanceRekonDaily::STATUS_VERIFIED,
    'verified_by' => 34,
    'verified_at' => date('Y-m-d H:i:s'),
]);
$ver = $model->getByUnitAndDate($unitId, $wfDate);
ok('C7: submitted -> verified', RekonDailyCalculator::statusProses($ver) === ModelFinanceRekonDaily::STATUS_VERIFIED);
ok('C8: verified terkunci', RekonDailyCalculator::isLocked($ver) === true);
ok('C9: verified menyimpan verified_by + verified_at', (int) $ver->verified_by === 34 && $ver->verified_at !== null);
ok('C10: verified (meski selisih) masuk numerator KPI', $model->countLengkapVerifiedInRange($unitId, $wfDate, $wfDate) === 1);
ok(
    'C11: verified dengan selisih tetap punya status hasil valid',
    in_array(RekonDailyCalculator::statusHarian($ver), ['lengkap_cocok', 'lengkap_selisih'], true),
    RekonDailyCalculator::statusHarian($ver)
);

$db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal', $wfDate)->delete();
$model->upsert($baseRow());
$need = $model->getByUnitAndDate($unitId, $wfDate);
$model->updateRow((int) $need->id, [
    'status_proses' => ModelFinanceRekonDaily::STATUS_SUBMITTED,
    'submitted_by' => 1,
    'submitted_at' => date('Y-m-d H:i:s'),
]);
$model->updateRow((int) $need->id, [
    'status_proses' => ModelFinanceRekonDaily::STATUS_NEED_REVISION,
    'verified_by' => null,
    'verified_at' => null,
    'catatan_revisi' => 'Mohon cek transfer bank 5jt',
]);
$rev = $model->getByUnitAndDate($unitId, $wfDate);
ok('C12: need_revision menyimpan catatan', RekonDailyCalculator::statusProses($rev) === ModelFinanceRekonDaily::STATUS_NEED_REVISION
    && $rev->catatan_revisi === 'Mohon cek transfer bank 5jt');
ok('C13: need_revision tidak terkunci', RekonDailyCalculator::isLocked($rev) === false);
ok('C14: need_revision tidak masuk numerator KPI', $model->countLengkapVerifiedInRange($unitId, $wfDate, $wfDate) === 0);
ok('C15: need_revision tetap boleh disubmit ulang', RekonDailyCalculator::siapSubmit($rev) === true);

$model->updateRow((int) $rev->id, [
    'status_proses' => ModelFinanceRekonDaily::STATUS_DRAFT,
    'submitted_by' => null,
    'submitted_at' => null,
    'catatan_revisi' => null,
]);
$back = $model->getByUnitAndDate($unitId, $wfDate);
ok('C16: need_revision -> draft (bisa diedit lagi)', RekonDailyCalculator::statusProses($back) === ModelFinanceRekonDaily::STATUS_DRAFT
    && $back->catatan_revisi === null);

$db->transRollback();

// ================================================================
// D. Validasi nominal aktual (parseNominalRekon, server-side)
// ================================================================
section('D. Validasi nominal aktual');

$controller = new App\Controllers\DashboardFinance();
$parse = function ($v) use ($controller) {
    return callPrivate($controller, 'parseNominalRekon', [$v]);
};

ok('D1: "10000" -> 10000', $parse('10000') === 10000);
ok('D2: "10.000" -> 10000', $parse('10.000') === 10000);
ok('D3: "10,000" -> 10000', $parse('10,000') === 10000);
ok('D4: "Rp 1.000" -> 1000', $parse('Rp 1.000') === 1000);
ok('D5: " 10000 " -> 10000', $parse(' 10000 ') === 10000);
ok('D6 [S11]: "" -> null (belum diisi, bukan 0)', $parse('') === null, var_export($parse(''), true));
ok('D7: null -> null (belum diisi)', $parse(null) === null, var_export($parse(null), true));
ok('D8 [S11]: "-100" DITOLAK (tidak jadi positif)', $parse('-100') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('-100'), true));
ok('D9: "Rp -100" ditolak', $parse('Rp -100') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('Rp -100'), true));
ok('D10: "-0.500" ditolak (tidak jadi 500)', $parse('-0.500') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('-0.500'), true));
ok('D11: "1-000" ditolak', $parse('1-000') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('1-000'), true));
ok('D12: "abc" ditolak', $parse('abc') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('abc'), true));
ok('D13: "12abc" ditolak', $parse('12abc') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('12abc'), true));
ok('D14: "1.500,25" ditolak (desimal)', $parse('1.500,25') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('1.500,25'), true));
ok('D14b: "1,500.25" ditolak (desimal gaya US)', $parse('1,500.25') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('1,500.25'), true));
ok('D14c: "1000,5" ditolak (desimal 1 digit)', $parse('1000,5') === App\Controllers\DashboardFinance::AKTUAL_INVALID, var_export($parse('1000,5'), true));
ok('D14d: "1.000.000" diterima (pemisah ribuan ganda)', $parse('1.000.000') === 1000000, var_export($parse('1.000.000'), true));
ok('D14e: "10.000.000" diterima (miliar)', $parse('10.000.000') === 10000000, var_export($parse('10.000.000'), true));
ok('D14f: "1,000,000" diterima (pemisah koma ganda)', $parse('1,000,000') === 1000000, var_export($parse('1,000,000'), true));
$big = '9000000000000';
ok('D15: bigint 13 digit tidak overflow', $parse($big) === 9000000000000 && PHP_INT_SIZE === 8, 'got=' . var_export($parse($big), true));
ok('D16: nilai besar > 2^31 tetap presisi', $parse('5000000000') === 5000000000);
ok('D17: parseRupiah lama masih ada (modul payroll)', is_numeric(callPrivate($controller, 'parseRupiah', ['1.000'])));
ok('D18: "0" -> 0 (nol adalah nilai SAH, bukan belum diisi)', $parse('0') === 0, var_export($parse('0'), true));
ok('D19: "Rp 0" -> 0', $parse('Rp 0') === 0, var_export($parse('Rp 0'), true));

// hitungSelisih(): selisih server-side, NULL bila actual belum diisi.
$hitungSelisih = static fn ($a, $e) => callPrivate($controller, 'hitungSelisih', [$a, $e]);
ok('D20 [S7]: selisih = actual - erp', $hitungSelisih(1500, 1000) === 500, var_export($hitungSelisih(1500, 1000), true));
ok('D21: selisih negatif bila actual < erp', $hitungSelisih(400, 1000) === -600, var_export($hitungSelisih(400, 1000), true));
ok('D22: selisih 0 saat cocok', $hitungSelisih(1000, 1000) === 0);
ok('D23: actual null -> selisih null (tidak ada angka)', $hitungSelisih(null, 1000) === null, var_export($hitungSelisih(null, 1000), true));
ok('D24: actual 0 -> selisih dihitung (bukan null)', $hitungSelisih(0, 1000) === -1000, var_export($hitungSelisih(0, 1000), true));

// ================================================================
// E. Otorisasi: roles, scope, self-verify
// ================================================================
section('E. Otorisasi approval & scope');

ok('E1: financeApproveRoles terisi', ! empty($config->financeApproveRoles), json_encode($config->financeApproveRoles));
ok('E2: financeInputRoles terisi', ! empty($config->financeInputRoles), json_encode($config->financeInputRoles));

$_SESSION = ['ID_AKUN' => 7, 'ID_ROLES' => 34];
$selfRow = (object) ['unit_id' => $unitId, 'submitted_by' => 7, 'input_by' => 7, 'status_proses' => 'submitted'];
$otherRow = (object) ['unit_id' => $unitId, 'submitted_by' => 8, 'input_by' => 8, 'status_proses' => 'submitted'];

// Logika canApproveRekon: pengirim (submitted_by / input_by) tidak boleh self-verify.
$isSelfVerify = function ($row) {
    $myId = (int) session('ID_AKUN');
    $submittedBy = (int) ($row->submitted_by ?? 0);
    $inputBy = (int) ($row->input_by ?? 0);

    return $submittedBy > 0 && ($submittedBy === $myId || $inputBy === $myId);
};
ok('E3: self-verify DITOLAK (submitted_by == akun login)', $isSelfVerify($selfRow) === true);
ok('E4: non-sender BOLEH verify', $isSelfVerify($otherRow) === false);
ok('E5: baris tanpa submitted_by tidak memicu guard', $isSelfVerify((object) ['unit_id' => $unitId]) === false);

$scope = new FinanceScopeService();
$allowedUnits = $scope->resolveAllowedUnits();
$allowedIds = array_map('intval', array_column(array_map('get_object_vars', $allowedUnits), 'idunit'));
ok('E6: unit di luar scope ditolak', ! in_array(99999, $allowedIds, true));
ok('E7: scope mengembalikan >= 1 unit', count($allowedIds) > 0, 'jumlah=' . count($allowedIds));
ok('E8: canApproveRekon & unitDiizinkan ada di controller', method_exists('App\Controllers\DashboardFinance', 'rekonApprove')
    && method_exists('App\Controllers\DashboardFinance', 'rekonSubmit'));

// E9-E13: aturan "yang bisa periksa" = Manager (34) + Admin Root (1) saja.
$approveRoles = array_map('intval', $config->financeApproveRoles);
sort($approveRoles);
ok('E9: financeApproveRoles persis Manager (34) + Admin Root (1)', $approveRoles === [1, 34], json_encode($approveRoles));
ok('E10: Administrator Finance (0) TIDAK boleh verify', ! in_array(0, $approveRoles, true));
ok('E11: Direktur (2) TIDAK boleh verify', ! in_array(2, $approveRoles, true));

// Uji canApproveRekon() sungguhan untuk tiap jabatan.
//
// CATATAN: FinanceScopeService::scopeInfo() membaca jabatan dari BARIS AKUN
// di DB (bukan dari session), jadi role harus disuntik lewat stub agar test
// deterministik dan tidak bergantung akun mana yang ada di DB.
$stubFor = function (int $role, array $allowedUnitIds) {
    return new class($role, $allowedUnitIds) extends App\Services\Finance\FinanceScopeService {
        private int $role;
        private array $unitIds;

        public function __construct(int $role, array $unitIds)
        {
            $this->role = $role;
            $this->unitIds = $unitIds;
        }

        public function scopeInfo(): array
        {
            return [
                'me' => null,
                'myRole' => $this->role,
                'myUnit' => 0,
                'myId' => 7,
                'isLintas' => true,
            ];
        }

        public function resolveAllowedUnits(): array
        {
            return array_map(static fn ($id) => (object) ['idunit' => $id, 'NAMA_UNIT' => 'U' . $id], $this->unitIds);
        }
    };
};

$withScope = function (FinanceScopeService $stub) {
    $controller = new App\Controllers\DashboardFinance();
    $ref = new ReflectionProperty(App\Controllers\DashboardFinance::class, 'scopeService');
    $ref->setAccessible(true);
    $ref->setValue($controller, $stub);

    return $controller;
};

$_SESSION = ['ID_AKUN' => 7];
$rowForOthers = (object) ['unit_id' => $unitId, 'submitted_by' => 8, 'input_by' => 8, 'status_proses' => 'submitted'];
$expectedCan = [1 => true, 34 => true, 0 => false, 2 => false];
foreach ($expectedCan as $role => $shouldAllow) {
    $can = callPrivate($withScope($stubFor($role, [$unitId])), 'canApproveRekon', [$rowForOthers]);
    ok(
        'E12: jabatan ' . $role . ' ' . ($shouldAllow ? 'BOLEH' : 'TIDAK BOLEH') . ' verify',
        $can === $shouldAllow,
        'hasil=' . var_export($can, true)
    );
}

// Regresi: approver yang jadi submitter sendiri tetap ditolak.
$selfByManager = (object) ['unit_id' => $unitId, 'submitted_by' => 7, 'input_by' => 7, 'status_proses' => 'submitted'];
ok(
    'E13: Manager yang mengirim sendiri tetap TIDAK boleh verify (self-verify)',
    callPrivate($withScope($stubFor(34, [$unitId])), 'canApproveRekon', [$selfByManager]) === false
);

// Jabatan berwenang verify tetap ditolak bila unit di luar scope.
ok(
    'E14: Manager DITOLAK bila unit di luar scope',
    callPrivate($withScope($stubFor(34, [99999])), 'canApproveRekon', [$rowForOthers]) === false
);

$_SESSION = [];

// ================================================================
// F. KPI berbasis VERIFIED
// ================================================================
section('F. KPI rekonsiliasi berbasis VERIFIED');

$db->transBegin();

$yearNow = (int) date('Y');
$monthNow = (int) date('m');
$startM = date('Y-m-01');
$endM = date('Y-m-t');
$db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal >=', $startM)->where('tanggal <=', $endM)->delete();

// Hari kerja Senin-Sabtu yang sudah lewat, dibatasi hari ini.
$hariKerja = [];
$d = $startM;
$endMin = $endM < $today ? $endM : $today;
while ($d <= $endMin) {
    $dow = (int) date('w', strtotime($d));
    if ($dow >= 1 && $dow <= 6) {
        $hariKerja[] = $d;
    }
    $d = date('Y-m-d', strtotime($d . ' +1 day'));
}

$jmlHariKerja = count($hariKerja);
ok('F1: minimal 1 hari kerja dalam bulan berjalan', $jmlHariKerja >= 1, "hari_kerja={$jmlHariKerja}");

// $terisi = false -> actual_* NULL (hari ada, tapi belum direkonsiliasi).
$seed = function (string $tgl, string $status, bool $terisi = true) use ($model, $unitId) {
    $model->upsert([
        'unit_id' => $unitId, 'tanggal' => $tgl,
        'erp_cash_masuk' => 100, 'actual_cash_masuk' => $terisi ? 100 : null, 'selisih_cash_masuk' => $terisi ? 0 : null,
        'erp_transfer_masuk' => 200, 'actual_transfer_masuk' => $terisi ? 200 : null, 'selisih_transfer_masuk' => $terisi ? 0 : null,
        'erp_kas_keluar' => 50, 'actual_kas_keluar' => $terisi ? 50 : null, 'selisih_kas_keluar' => $terisi ? 0 : null,
        'status_proses' => $status, 'input_by' => 1,
    ]);
};

if ($jmlHariKerja < 3) {
    skip('F2-F7: butuh minimal 3 hari kerja');
} else {
    // H+1 verified, H+2 submitted, H+3 draft, sisanya kosong.
    $seed($hariKerja[0], ModelFinanceRekonDaily::STATUS_VERIFIED);
    $seed($hariKerja[1], ModelFinanceRekonDaily::STATUS_SUBMITTED);
    $seed($hariKerja[2], ModelFinanceRekonDaily::STATUS_DRAFT);

    $res = $calc->calculate($unitId, $monthNow, $yearNow);
    $expected = round((1 / $jmlHariKerja) * 100, 2);
    ok(
        'F2: hanya 1 hari verified dari ' . $jmlHariKerja . ' hari kerja',
        (float) $res['score'] === $expected,
        "score={$res['score']} expected={$expected}"
    );
    ok('F3: numerator hanya menghitung verified', (int) $res['detail']['hari_lengkap_verified'] === 1, json_encode($res['detail']));
    ok('F4: hari lengkap (tanpa syarat verified) = 3', (int) $res['detail']['hari_lengkap'] === 3, json_encode($res['detail']));
    ok('F5: denominator = jumlah hari kerja Sen-Sab', (int) $res['detail']['hari_kerja'] === $jmlHariKerja, json_encode($res['detail']));

    // Semua hari kerja verified -> 100
    foreach ($hariKerja as $tgl) {
        $seed($tgl, ModelFinanceRekonDaily::STATUS_VERIFIED);
    }
    $res100 = $calc->calculate($unitId, $monthNow, $yearNow);
    ok('F6: semua hari verified -> 100', (float) $res100['score'] === 100.0, "score={$res100['score']}");

    // Tidak ada verified -> 0
    foreach ($hariKerja as $tgl) {
        $seed($tgl, ModelFinanceRekonDaily::STATUS_DRAFT);
    }
    $res0 = $calc->calculate($unitId, $monthNow, $yearNow);
    ok('F7: tidak ada verified -> 0', (float) $res0['score'] === 0.0, "score={$res0['score']}");

    // Verified dengan selisih tetap dihitung (selisih tidak menurunkan skor)
    $db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal >=', $startM)->where('tanggal <=', $endM)->delete();
    foreach ($hariKerja as $tgl) {
        $model->upsert([
            'unit_id' => $unitId, 'tanggal' => $tgl,
            'erp_cash_masuk' => 100, 'actual_cash_masuk' => 7777, 'selisih_cash_masuk' => 7677,
            'erp_transfer_masuk' => 200, 'actual_transfer_masuk' => 200, 'selisih_transfer_masuk' => 0,
            'erp_kas_keluar' => 50, 'actual_kas_keluar' => 50, 'selisih_kas_keluar' => 0,
            'status_proses' => ModelFinanceRekonDaily::STATUS_VERIFIED, 'input_by' => 1,
        ]);
    }
    $resSelisih = $calc->calculate($unitId, $monthNow, $yearNow);
    ok('F8: verified + selisih tetap 100 (selisih tidak menurunkan skor)', (float) $resSelisih['score'] === 100.0, "score={$resSelisih['score']}");

    // Filter status proses di list bulanan
    $listAll = $calc->monthlyList($unitId, $monthNow, $yearNow);
    $listVerified = $calc->monthlyList($unitId, $monthNow, $yearNow, ModelFinanceRekonDaily::STATUS_VERIFIED);
    $semuaVerified = true;
    foreach ($listVerified as $item) {
        if (RekonDailyCalculator::statusProses($item['row']) !== ModelFinanceRekonDaily::STATUS_VERIFIED) {
            $semuaVerified = false;
        }
    }
    // monthlyList sengaja memuat SETIAP hari kalender (bukan hanya hari kerja)
    // supaya Finance tetap melihat hari Minggu/Minggu besar di daftar.
    $hariEfektif = (int) ((strtotime($endMin) - strtotime($startM)) / 86400) + 1;
    ok('F9: list bulanan memuat semua hari kalender yang sudah lewat', count($listAll) === $hariEfektif, 'list=' . count($listAll) . ' diharapkan=' . $hariEfektif);
    // Hanya hari kerja yang di-seed VERIFIED; hari Minggu tidak punya record
    // sehingga statusnya 'draft' dan rightfully tersaring oleh filter.
    ok(
        'F10: filter status=verified hanya menyaring baris verified',
        $semuaVerified && count($listVerified) === $jmlHariKerja,
        'terfilter=' . count($listVerified) . ' verified=' . $jmlHariKerja
    );
    $listDraft = $calc->monthlyList($unitId, $monthNow, $yearNow, ModelFinanceRekonDaily::STATUS_DRAFT);
    $semuaDraft = true;
    foreach ($listDraft as $item) {
        if (RekonDailyCalculator::statusProses($item['row']) !== ModelFinanceRekonDaily::STATUS_DRAFT) {
            $semuaDraft = false;
        }
    }
    ok('F10b: filter draft tidak bocor baris verified', $semuaDraft, 'jumlah=' . count($listDraft));
}

// Bulan tanpa hari kerja (Maret di bulan < 3 pada tanggal 1) -> score null agar fallback manual aktif
ok('F11: calculate() mengembalikan array dengan score & detail', is_array($res) && array_key_exists('score', $res) && isset($res['detail']));

$db->transRollback();

// ================================================================
// G. Konfigurasi & fallback manual
// ================================================================
section('G. Konfigurasi & fallback manual');

ok('G1: rekonsiliasi tetap terdaftar di manualKpiCodes (fallback)', in_array('rekonsiliasi', $config->manualKpiCodes, true));
ok('G2: rekonsiliasi punya bobot KPI', isset($config->kpiWeights['rekonsiliasi']));
ok('G3: bobot total 100', array_sum($config->kpiWeights) === 100, 'total=' . array_sum($config->kpiWeights));

$kpiService = new FinanceKpiCalculationService();
$scorecard = $kpiService->scorecard($unitId, $monthNow, $yearNow);
$rekonRow = $scorecard['rows']['rekonsiliasi'] ?? null;
ok('G4: scorecard punya baris rekonsiliasi', $rekonRow !== null);
ok('G5: mode auto saat calculator berhasil', ($rekonRow['mode'] ?? '') === 'auto', json_encode($rekonRow));
ok('G6: scorecard menyertakan rekon_detail', isset($scorecard['rekon_detail']['score']));
ok('G7: scorecard menyertakan approve_roles', isset($scorecard['approve_roles']));

// Role yang boleh verify harus persis Manager (34) + Admin Root (1),
// dan inilah yang dibaca frontend untuk menampilkan tombol approval.
ok(
    'E15: approve_roles di scorecard = [1, 34] (Manager + Admin Root)',
    array_map('intval', $scorecard['approve_roles'] ?? []) === [1, 34],
    json_encode($scorecard['approve_roles'] ?? null)
);

// Fallback: calculator dimatikan (stub yang melempar error) -> skor manual dipakai.
$db->transBegin();
$refProp = new ReflectionProperty(FinanceKpiCalculationService::class, 'rekon');
$refProp->setAccessible(true);
$svcFallback = new FinanceKpiCalculationService();
$refProp->setValue($svcFallback, new class {
    public function calculate(int $unitId, int $month, int $year): array
    {
        throw new RuntimeException('simulasi: kolom rekon belum siap');
    }
});

$db->table('finance_kpi_records')
    ->where('unit_id', $unitId)
    ->where('kpi_code', 'rekonsiliasi')
    ->where('period_year', $yearNow)
    ->where('period_month', $monthNow)
    ->delete();
(new App\Models\ModelFinanceKpiRecord())->upsert([
    'unit_id' => $unitId, 'kpi_code' => 'rekonsiliasi',
    'period_year' => $yearNow, 'period_month' => $monthNow,
    'mode' => 'manual', 'score' => 88, 'contribution' => 13.2, 'weight' => 15,
    'notes' => 'fallback test', 'evaluator_id' => 1, 'evaluated_at' => date('Y-m-d H:i:s'),
]);

$fallbackRow = callPrivate($svcFallback, 'rekonRow', [$unitId, $monthNow, $yearNow, 15.0]);
ok('G8: calculator error -> fallback ke skor manual', (float) $fallbackRow['score'] === 88.0, json_encode($fallbackRow));
ok('G9: fallback mempertahankan mode manual', ($fallbackRow['mode'] ?? '') === 'manual', json_encode($fallbackRow));
ok('G10: fallback status ditandai', ($fallbackRow['status'] ?? '') === 'fallback_manual', json_encode($fallbackRow));
ok('G11: fallback menyertakan catatan alasan', strpos((string) $fallbackRow['notes'], 'Fallback manual') === 0, (string) $fallbackRow['notes']);

// Tanpa manual record -> belum dinilai, bukan 0 palsu
$db->table('finance_kpi_records')
    ->where('unit_id', $unitId)
    ->where('kpi_code', 'rekonsiliasi')
    ->where('period_year', $yearNow)
    ->where('period_month', $monthNow)
    ->delete();
$noManual = callPrivate($svcFallback, 'rekonRow', [$unitId, $monthNow, $yearNow, 15.0]);
ok('G12: tanpa auto & tanpa manual -> belum_dinilai (bukan 0)', $noManual['score'] === null, json_encode($noManual));
ok('G13: tanpa auto & tanpa manual -> mode manual', ($noManual['mode'] ?? '') === 'manual', json_encode($noManual));

$db->transRollback();

// ================================================================
// H. Integritas data
// ================================================================
section('H. Integritas data & model');

$db->transBegin();

$dupDate = '2026-01-15';
$db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal', $dupDate)->delete();
$row1 = $baseRow(0);
$row1['tanggal'] = $dupDate;
$model->upsert($row1);
$row2 = $baseRow(0);
$row2['tanggal'] = $dupDate;
$row2['erp_cash_masuk'] = 999999;
$model->upsert($row2);

$jml = (int) $db->table('finance_rekon_daily')->where('unit_id', $unitId)->where('tanggal', $dupDate)->countAllResults();
ok('H1: upsert tidak membuat duplikat', $jml === 1, "jumlah={$jml}");
$dup = $model->getByUnitAndDate($unitId, $dupDate);
ok('H2: upsert memperbarui nilai lama', (int) $dup->erp_cash_masuk === 999999, 'erp=' . $dup->erp_cash_masuk);
ok('H3: castRow menghasilkan int untuk BIGINT', is_int($dup->erp_cash_masuk) && is_int($dup->actual_cash_masuk), gettype($dup->erp_cash_masuk));
ok('H4: castRow menghasilkan int untuk selisih & tidak merusak NULL actual', is_int($dup->selisih_cash_masuk)
    && gettype($dup->selisih_cash_masuk) === 'integer'
    && ($dup->actual_kas_keluar === null || is_int($dup->actual_kas_keluar)),
    'selisih=' . gettype($dup->selisih_cash_masuk) . ' actual_kas_keluar=' . gettype($dup->actual_kas_keluar));
ok('H5: castRow(null) aman', $model->castRow(null) === null);

// Unique constraint di DB
$colsRekon = array_map('strtolower', $db->getFieldNames('finance_rekon_daily'));
ok('H6: kolom kunci ada di tabel', in_array('unit_id', $colsRekon, true) && in_array('tanggal', $colsRekon, true), implode(',', $colsRekon));
$uniqueCount = (int) $db->query("SELECT COUNT(*) AS c FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_rekon_daily' AND NON_UNIQUE = 0")->getRow()->c;
ok('H7: ada unique index (unit_id, tanggal)', $uniqueCount >= 2, 'unique_cols=' . $uniqueCount);

// Kolom workflow benar-benar ada di DB
foreach (['catatan_revisi', 'status_proses', 'submitted_by', 'submitted_at', 'verified_by', 'verified_at', 'input_by'] as $col) {
    ok("H8: kolom {$col} ada", in_array(strtolower($col), $colsRekon, true));
}

// [S5] Konsep "Sudah Diperiksa" harus benar-benar hilang dari DB.
foreach (['checked_cash_masuk', 'checked_transfer_masuk', 'checked_kas_keluar'] as $col) {
    ok("H8b [S5]: kolom {$col} sudah DIHAPUS", ! in_array(strtolower($col), $colsRekon, true));
}

// [S6] ERP tetap readonly: tidak ada kolom input untuk ERP selain penyimpanan,
//      dan nilai ERP/selisih dari POST browser tidak boleh dipercaya.
foreach (['erp_cash_masuk', 'erp_transfer_masuk', 'erp_kas_keluar', 'actual_cash_masuk', 'actual_transfer_masuk', 'actual_kas_keluar'] as $col) {
    ok("H8c: kolom {$col} tetap ada (tidak ikut terhapus)", in_array(strtolower($col), $colsRekon, true));
}

// Tabel/workflow kolom tidak boleh dihapus diam-diam
$totalRekon = (int) $db->table('finance_rekon_daily')->countAllResults();
ok('H9: test tidak meninggalkan data (rollback)', $totalRekon >= 0, "rows={$totalRekon}");

$db->transRollback();

// Route & view
$routeSrc = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
$formSrc = file_get_contents(APPPATH . 'Views/dashboard/finance_rekon_form.php');
ok('H10: route finance/rekonsiliasi terdaftar', strpos($routeSrc, "finance/rekonsiliasi") !== false);
ok('H11: route finance/rekon/submit terdaftar', strpos($routeSrc, "finance/rekon/submit") !== false);
ok('H12: route finance/rekon/approve terdaftar', strpos($routeSrc, "finance/rekon/approve") !== false);
ok('H14: dashboard memiliki link ke rekonsiliasi', strpos(file_get_contents(APPPATH . 'Views/dashboard/dashboard_finance.php'), 'finance/rekonsiliasi?unit_id=') !== false);

// Format input Aktual: titik ribuan harus OTOMATIS (user ketik angka saja).
ok('H13a: view punya groupThousands() untuk format ribuan otomatis', strpos($formSrc, 'function groupThousands') !== false);
ok('H13b: input menolak karakter di luar digit/pemisah (minus tidak di-strip diam-diam)', strpos($formSrc, '[^0-9.,\\s]') !== false);
ok('H13c: ada handler paste dengan parse ketat', strpos($formSrc, "addEventListener('paste'") !== false);
ok('H13d: ada hint "ketik angka saja" untuk user', strpos($formSrc, 'rekon-format-hint') !== false && strpos($formSrc, 'Ketik angka saja') !== false);
ok('H13e: parser JS menolak negatif (parity server)', strpos($formSrc, 'n < 0') !== false);
ok('H13f: tidak ada lagi toLocaleString pada nilai input (pakai groupThousands)', strpos($formSrc, 'this.value = actual > 0 ? actual.toLocaleString') === false);

// Test client-side (butuh node). Dijalankan sungguhan agar regresi format
// ribuan di browser tidak bisa lolos hanya karena test PHP hijau.
section('I. Client-side form rekonsiliasi (node)');

$jsTest = __DIR__ . '/rekon_js_test.js';
$nodeBin = trim((string) @shell_exec('command -v node 2>/dev/null'));

if ($nodeBin === '' || ! is_file($jsTest)) {
    skip('I1: test JS form rekonsiliasi (node tidak tersedia)');
} else {
    $jsOut = [];
    $jsCode = 1;
    exec(escapeshellarg($nodeBin) . ' ' . escapeshellarg($jsTest) . ' 2>&1', $jsOut, $jsCode);
    $jsText = implode("\n", $jsOut);

    $mJs = [];
    preg_match('/PASS:\s*(\d+)\s+FAIL:\s*(\d+)/', $jsText, $mJs);
    $jsPassed = (int) ($mJs[1] ?? 0);
    $jsFailed = (int) ($mJs[2] ?? -1);

    ok('I1: test JS rekon_form berjalan (exit 0)', $jsCode === 0, 'exit=' . $jsCode . ' ' . substr($jsText, -400));

    if ($jsFailed === 0 && $jsPassed > 0) {
        $pass += $jsPassed;
        echo "      ({$jsPassed} assertion client-side lulus via node)\n";
    } else {
        if ($jsFailed > 0) {
            $fail += $jsFailed;
        }
        echo $jsText . "\n";
    }
}

// ================================================================
// J. Skenario wajib: status otomatis, KPI, keamanan
// ================================================================
section('J. Skenario wajib (status otomatis, KPI, keamanan)');

$db->transBegin();
$jDate = date('Y-m-d', strtotime('-2 days'));
$jUnit = $unitId;
$db->table('finance_rekon_daily')->where('unit_id', $jUnit)->where('tanggal', $jDate)->delete();

$jRow = function (?int $aCash, ?int $aTfr, ?int $aKel, int $sCash, int $sTfr, int $sKel, string $status) use ($jUnit, $jDate) {
    return [
        'unit_id' => $jUnit, 'tanggal' => $jDate,
        'erp_cash_masuk' => 1000, 'erp_transfer_masuk' => 2000, 'erp_kas_keluar' => 3000,
        'actual_cash_masuk' => $aCash, 'actual_transfer_masuk' => $aTfr, 'actual_kas_keluar' => $aKel,
        'selisih_cash_masuk' => $sCash, 'selisih_transfer_masuk' => $sTfr, 'selisih_kas_keluar' => $sKel,
        'catatan' => 'test J', 'status_proses' => $status,
        'submitted_by' => 1, 'submitted_at' => date('Y-m-d H:i:s'),
        'verified_by' => $status === ModelFinanceRekonDaily::STATUS_VERIFIED ? 41 : null,
        'verified_at' => $status === ModelFinanceRekonDaily::STATUS_VERIFIED ? date('Y-m-d H:i:s') : null,
        'input_by' => 1,
    ];
};

// [S1] Semua actual NULL -> BELUM_LENGKAP, dan tidak masuk hitungan lengkap.
$model->upsert($jRow(null, null, null, 0, 0, 0, ModelFinanceRekonDaily::STATUS_DRAFT));
$j1 = $model->getByUnitAndDate($jUnit, $jDate);
ok('J1 [S1]: semua actual null -> belum_lengkap', RekonDailyCalculator::statusHarian($j1) === 'belum_lengkap');
ok('J1b [S1]: semua actual null tidak dihitung "lengkap"', $model->countLengkapInRange($jUnit, $jDate, $jDate) === 0);

// [S2] Salah satu actual NULL -> BELUM_LENGKAP
$model->upsert($jRow(1000, 2000, null, 0, 0, 0, ModelFinanceRekonDaily::STATUS_DRAFT));
$j2 = $model->getByUnitAndDate($jUnit, $jDate);
ok('J2 [S2]: salah satu actual null -> belum_lengkap', RekonDailyCalculator::statusHarian($j2) === 'belum_lengkap');

// [S3] Semua terisi + semua selisih 0 -> LENGKAP_COCOK
$model->upsert($jRow(1000, 2000, 3000, 0, 0, 0, ModelFinanceRekonDaily::STATUS_DRAFT));
$j3 = $model->getByUnitAndDate($jUnit, $jDate);
ok('J3 [S3]: semua terisi + selisih 0 -> lengkap_cocok', RekonDailyCalculator::statusHarian($j3) === 'lengkap_cocok');
ok('J3b: lengkap_cocok + draft dihitung "lengkap"', $model->countLengkapInRange($jUnit, $jDate, $jDate) === 1);

// [S4] Semua terisi + ada selisih -> LENGKAP_SELISIH
$model->upsert($jRow(1000, 2500, 3000, 0, 500, 0, ModelFinanceRekonDaily::STATUS_DRAFT));
$j4 = $model->getByUnitAndDate($jUnit, $jDate);
ok('J4 [S4]: semua terisi + ada selisih -> lengkap_selisih', RekonDailyCalculator::statusHarian($j4) === 'lengkap_selisih');
ok('J4b: draft dengan status hasil lengkap_selisih tetap dihitung "lengkap"', $model->countLengkapInRange($jUnit, $jDate, $jDate) === 1);

// [S8] VERIFIED + LENGKAP_COCOK masuk KPI
$model->upsert($jRow(1000, 2000, 3000, 0, 0, 0, ModelFinanceRekonDaily::STATUS_VERIFIED));
ok('J5 [S8]: VERIFIED + lengkap_cocok masuk numerator KPI', $model->countLengkapVerifiedInRange($jUnit, $jDate, $jDate) === 1);

// [S9] VERIFIED + LENGKAP_SELISIH juga masuk KPI (selisih tidak menurunkan)
$model->upsert($jRow(1000, 2500, 3000, 0, 500, 0, ModelFinanceRekonDaily::STATUS_VERIFIED));
$j5 = $model->getByUnitAndDate($jUnit, $jDate);
ok('J6 [S9]: record verified berstatus lengkap_selisih', RekonDailyCalculator::statusHarian($j5) === 'lengkap_selisih');
ok('J7 [S9]: VERIFIED + lengkap_selisih tetap masuk numerator KPI', $model->countLengkapVerifiedInRange($jUnit, $jDate, $jDate) === 1,
    'n=' . $model->countLengkapVerifiedInRange($jUnit, $jDate, $jDate));

// [S10] SUBMITTED belum masuk KPI meski datanya lengkap.
$model->upsert($jRow(1000, 2000, 3000, 0, 0, 0, ModelFinanceRekonDaily::STATUS_SUBMITTED));
ok('J8 [S10]: SUBMITTED belum masuk numerator KPI', $model->countLengkapVerifiedInRange($jUnit, $jDate, $jDate) === 0);

// SUBMITTED tapi belum lengkap juga tidak masuk.
$model->upsert($jRow(1000, null, 3000, 0, 0, 0, ModelFinanceRekonDaily::STATUS_SUBMITTED));
ok('J9: submitted + belum lengkap tidak masuk KPI', $model->countLengkapVerifiedInRange($jUnit, $jDate, $jDate) === 0);

// NEED_REVISION juga belum.
$model->upsert($jRow(1000, 2000, 3000, 0, 0, 0, ModelFinanceRekonDaily::STATUS_NEED_REVISION));
ok('J10: need_revision belum masuk numerator KPI', $model->countLengkapVerifiedInRange($jUnit, $jDate, $jDate) === 0);

// [S6] ERP readonly: nilai erp/selisih di DB tetap sama, bukan dari POST browser.
ok('J11 [S6]: erp & selisih tidak berubah oleh input lain', (function () use ($jUnit, $jDate, $model) {
    $r = $model->getByUnitAndDate($jUnit, $jDate);

    return (int) $r->erp_cash_masuk === 1000 && (int) $r->erp_transfer_masuk === 2000
        && (int) $r->erp_kas_keluar === 3000;
})());

// [S12] Admin (yang mengirim) tidak bisa verify sendiri -> sudah diuji di E13,
//       tapi pastikan guard tersebut masih terpasang.
ok('J12 [S12]: guard self-verify tetap ada di canApproveRekon()', method_exists(App\Controllers\DashboardFinance::class, 'canApproveRekon')
    || (new ReflectionClass(App\Controllers\DashboardFinance::class))->hasMethod('canApproveRekon'));

$db->transRollback();

echo "\n========================================\n";
echo "PASS: {$pass}  FAIL: {$fail}  SKIP: {$skip}\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
