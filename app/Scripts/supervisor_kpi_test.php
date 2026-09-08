<?php
/**
 * Supervisor KPI Test — 7 komponen KPI Area Supervisor / SPV.
 *
 * Memverifikasi:
 *   1. Konfigurasi (komponen + bobot jabatan 40 = 100%).
 *   2. OMZET_WILAYAH       (sum actual / sum target × 100)
 *   3. TARGET_CABANG       (avg achievement per cabang)
 *   4. PRODUKTIVITAS_CABANG (unique customer bulan berjalan / sebelumnya)
 *   5. SOP                  (avg Kepatuhan SOP Kepala Toko)
 *   6. KINERJA_KEPALA_TOKO  (avg total KPI KT, tanpa absensi)
 *   7. KEDISIPLINAN_TEAM    (avg attendance seluruh team cabang)
 *   8. CUSTOMER_SATISFACTION (manual 0-100, Manager (34) menilai SPV)
 *   9. Session scope berbeda (SPV 49 vs SPV 56) & prev month = 0 & data kosong.
 *
 * Data uji dibuat DALAM SATU TRANSACTION dan di-rollback di akhir —
 * tidak mencemari DB nyata.
 *
 * Usage: php app/Scripts/supervisor_kpi_test.php
 */
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
if (!defined('APPPATH')) define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
if (!defined('SYSTEMPATH')) define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('WRITEPATH')) define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();
$db = \Config\Database::connect();

$YEAR  = 2028;
$MONTH = 5;      // bulan berjalan
$PREV  = 4;      // bulan sebelumnya
$SPV49 = 49;     // Mario R — scope spv_units [2,3]
$SPV56 = 56;     // Bima    — scope spv_units [1,4]
$MGR   = 41;     // Huda (ID_JABATAN 34 Manager) — evaluator CS Supervisor

$pass = 0;
$fail = 0;

function ok($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}" . ($detail !== '' ? "  -> {$detail}" : '') . "\n";
    }
}

function near($a, $b, $tol = 1e-3)
{
    if ($a === null || $b === null) {
        return $a === $b;
    }
    return abs($a - $b) <= $tol;
}

echo "============================================================\n";
echo "SUPERVISOR KPI TEST — periode {$YEAR}-{$MONTH} (synthetic, rollback)\n";
echo "============================================================\n";

// �- 1. Struktur konfigurasi ──────────────────────────────────────
$codes = \App\Services\Kpi\SupervisorKpiService::CODES;
ok('SupervisorKpiService::CODES = 7', count($codes) === 7);

$row = $db->query(
    "SELECT w.kpi_component_id, c.code, w.weight, w.weight_group
     FROM kpi_weights w JOIN kpi_components c ON c.id = w.kpi_component_id
     WHERE w.position_id = 40 AND w.weight_group = 'kpi'"
)->getResultArray();
$weightMap = array_column($row, 'weight', 'code');
$sum = array_sum($weightMap);
ok('Bobot jabatan 40 (kpi group) total = 100', near($sum, 100), "sum={$sum}");
foreach ($codes as $c) {
    ok("Bobot komponen {$c} terpasang di jabatan 40", isset($weightMap[$c]), var_export($weightMap[$c] ?? null, true));
}

// Grup absen SPV = 40/20/20/20 (Detail Absensi tampil seperti team)
$absenRows = $db->query(
    "SELECT c.code, w.weight FROM kpi_weights w
     JOIN kpi_components c ON c.id = w.kpi_component_id
     WHERE w.position_id = 40 AND w.weight_group = 'absen'"
)->getResultArray();
$absenMap = array_column($absenRows, 'weight', 'code');
$absenSum = array_sum($absenMap);
ok('Bobot jabatan 40 (absen group) total = 100', near($absenSum, 100), "sum={$absenSum}");
foreach (['KEHADIRAN' => 40, 'KEBERSIHAN' => 20, 'SERAGAM' => 20, 'KEPATUHAN_SOP' => 20] as $c => $w) {
    ok("Bobot absen {$c} = {$w}", isset($absenMap[$c]) && near($absenMap[$c], $w), var_export($absenMap[$c] ?? null, true));
}

// ── 2. Siapkan data uji (satu transaction) ───────────────────────
$db->transBegin();
try {
    $salesTable = $db->table('penjualan');
    $detTable   = $db->table('detail_penjualan');
    $evalTable  = $db->table('kpi_evaluations');

    // Bulan berjalan: unit2 15 customer unik (omzet sale #1 gross 40jt), unit3 21 (gross 60jt)
    $currentSales = [];
    for ($i = 1; $i <= 15; $i++) {
        $currentSales[] = [2, 9000000 + $i, ($i === 1) ? 40000000 : 0, 0, '05-12'];
    }
    for ($i = 1; $i <= 21; $i++) {
        $currentSales[] = [3, 9100000 + $i, ($i === 1) ? 60000000 : 0, 0, '05-13'];
    }
    // Bulan sebelumnya: unit2 10 customer, unit3 20 customer
    $prevSales = [];
    for ($i = 1; $i <= 10; $i++) {
        $prevSales[] = [2, 9200000 + $i, 0, 0, '04-11'];
    }
    for ($i = 1; $i <= 20; $i++) {
        $prevSales[] = [3, 9300000 + $i, 0, 0, '04-12'];
    }

    foreach ($currentSales as $s) {
        list($unit, $cust, $sub, $hpp, $dd) = $s;
        $salesTable->insert([
            'kode_invoice'    => 'SV-2028-' . $unit . '-' . $cust,
            'tanggal'         => sprintf('%04d-%s 10:10:00', $YEAR, $dd),
            'total_penjualan' => $sub,
            'diskon'          => 0,
            'total_ppn'       => 0,
            'harus_dibayar'   => (string)$sub,
            'bayar'           => $sub,
            'bayar_tunai'     => $sub,
            'bank_idbank'     => '0',
            'bayar_bank'      => 0,
            'id_pelanggan'    => $cust,
            'input_by'        => 58,
            'sales_by'        => 58,
            'unit_idunit'     => $unit,
        ]);
        $pid = $db->insertID();
        $detTable->insert([
            'jumlah'               => 1,
            'harga_penjualan'      => $sub,
            'sub_total'            => $sub,
            'hpp_penjualan'        => $hpp,
            'satuan_jual'          => 'pcs',
            'diskon_penjualan'     => '0',
            'penjualan_idpenjualan' => $pid,
            'barang_idbarang'      => 158,
            'unit_idunit'          => $unit,
            'bundle'               => 0,
        ]);
    }

    foreach ($prevSales as $s) {
        list($unit, $cust, $sub, $hpp, $dd) = $s;
        $salesTable->insert([
            'kode_invoice'    => 'SV-2028P-' . $unit . '-' . $cust,
            'tanggal'         => sprintf('%04d-%s 09:10:00', $YEAR, $dd),
            'total_penjualan' => 0,
            'diskon'          => 0,
            'total_ppn'       => 0,
            'harus_dibayar'   => '0',
            'bayar'           => 0,
            'bayar_tunai'     => 0,
            'bank_idbank'     => '0',
            'bayar_bank'      => 0,
            'id_pelanggan'    => $cust,
            'input_by'        => 58,
            'sales_by'        => 58,
            'unit_idunit'     => $unit,
        ]);
    }

    // Attendance evals team cabang unit 2 & 3 (bulan berjalan)
    $team = [53, 58, 59, 60, 61, 67, 68];
    $compIds = [];
    foreach ($db->query("SELECT id, code FROM kpi_components WHERE code IN ('KEHADIRAN','KEPATUHAN_SOP')")->getResultArray() as $c) {
        $compIds[$c['code']] = (int)$c['id'];
    }
    // Jumlah hari skor KP berbeda agar kedua KT (58,60) nilainya tidak identik.
    $ktDays = [
        58 => ['hadir' => '05-10', 'sop' => ['05-10', '05-11']],
        60 => ['hadir' => '05-10', 'sop' => ['05-10', '05-11', '05-12', '05-13', '05-14']],
    ];
    foreach ($team as $emp) {
        $days = $ktDays[$emp] ?? ['hadir' => '05-10', 'sop' => ['05-10']];
        $evalTable->insert([
            'employee_id' => $emp, 'kpi_component_id' => $compIds['KEHADIRAN'], 'evaluator_id' => $SPV49,
            'evaluation_date' => sprintf('%04d-%s 08:00:00', $YEAR, $days['hadir']),
            'raw_score' => 5, 'max_score' => 5, 'normalized_score' => 100, 'weighted_score' => 0,
            'notes' => 'test', 'period_year' => $YEAR, 'period_month' => $MONTH,
        ]);
        foreach ($days['sop'] as $day) {
            $evalTable->insert([
                'employee_id' => $emp, 'kpi_component_id' => $compIds['KEPATUHAN_SOP'], 'evaluator_id' => $SPV49,
                'evaluation_date' => sprintf('%04d-%s 08:00:00', $YEAR, $day),
                'raw_score' => 5, 'max_score' => 5, 'normalized_score' => 100, 'weighted_score' => 0,
                'notes' => 'test', 'period_year' => $YEAR, 'period_month' => $MONTH,
            ]);
        }
    }
} catch (\Throwable $e) {
    $db->transRollback();
    ok('Setup data uji', false, $e->getMessage());
    exit(1);
}

$svc = new \App\Services\Kpi\SupervisorKpiService();
$kpiSvc = new \App\Services\Kpi\KpiCalculationService();
$att = new \App\Services\Kpi\AttendanceAggregationService();
$dateAnchor = sprintf('%04d-%02d-15', $YEAR, $MONTH);

// ── 3. Per-komponen (SPV 49, scope [2,3]) ─────────────────────────
$ow = $svc->achievement('OMZET_WILAYAH', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('OMZET_WILAYAH = 105.2632 (100jt/95jt)', near($ow, 105.2632), var_export($ow, true));

$tc = $svc->achievement('TARGET_CABANG', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 107.1429', near($tc, 107.1429), var_export($tc, true));

$pc = $svc->achievement('PRODUKTIVITAS_CABANG', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('PRODUKTIVITAS_CABANG = 120 (36/30)', near($pc, 120.0), var_export($pc, true));

$a58 = $att->calculateMonthlyAttendance(58, 3, (string)$MONTH, (string)$YEAR, 'penilaian_kinerja');
$a60 = $att->calculateMonthlyAttendance(60, 2, (string)$MONTH, (string)$YEAR, 'penilaian_kinerja');
$sopExp = ((float)$a58['components']['KEPATUHAN_SOP']['normalized'] + (float)$a60['components']['KEPATUHAN_SOP']['normalized']) / 2;
$sop = $svc->achievement('SOP', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok("SOP = avg(Kepatuhan SOP KT 58,60) ({$sopExp})", near($sop, $sopExp), var_export($sop, true));

$t58 = $kpiSvc->calculateForEmployee(58, 3, (string)$MONTH, (string)$YEAR, 'penilaian_kinerja', $dateAnchor)['total_score'];
$t60 = $kpiSvc->calculateForEmployee(60, 2, (string)$MONTH, (string)$YEAR, 'penilaian_kinerja', $dateAnchor)['total_score'];
$kktExp = ($t58 + $t60) / 2;
$kkt = $svc->achievement('KINERJA_KEPALA_TOKO', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok("KINERJA_KEPALA_TOKO = avg(KT) ({$t58},{$t60})", near($kkt, $kktExp), var_export($kkt, true));

$teamScores = [];
foreach ([53, 58, 59, 60, 61, 67, 68] as $emp) {
    $u = in_array($emp, [53, 60, 68], true) ? 2 : 3;
    $teamScores[] = (float)$att->calculateMonthlyAttendance($emp, $u, (string)$MONTH, (string)$YEAR, 'penilaian_kinerja')['attendance_score'];
}
$kdExp = array_sum($teamScores) / count($teamScores);
$kd = $svc->achievement('KEDISIPLINAN_TEAM', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok("KEDISIPLINAN_TEAM = avg (all team, {$kdExp})", near($kd, $kdExp), var_export($kd, true));

$cs = $svc->achievement('CUSTOMER_SATISFACTION', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('CUSTOMER_SATISFACTION belum diinput = null', $cs === null, var_export($cs, true));

// ── 4. Simpan & baca CS (manual 0-100, Manager menilai SPV) ──────
$save = $svc->saveCustomerSatisfaction($SPV49, $MGR, $MONTH, $YEAR, 92);
ok('Simpan CS 92 (Manager, 41) berhasil', !empty($save['success']), json_encode($save));
$cs = $svc->achievement('CUSTOMER_SATISFACTION', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('CS terbaca = 92', near($cs, 92), var_export($cs, true));
$bad = $svc->saveCustomerSatisfaction($SPV49, $MGR, $MONTH, $YEAR, 150);
ok('CS 150 ditolak (harus 0-100)', $bad['success'] === false, json_encode($bad));

// ── 5. Kartu KPI via KpiCalculationService (SPV 49) ──────────────
$kpi = $kpiSvc->calculateForSalary($SPV49, (string)$MONTH, (string)$YEAR, 'penilaian_kinerja');
$expectedNames = [
    'Omzet Wilayah', 'Target Cabang', 'Produktivitas Cabang', 'SOP',
    'Kinerja Kepala Toko', 'Kedisiplinan Team', 'Customer Satisfaction',
];
foreach ($expectedNames as $n) {
    ok("Detail KPI mengandung '{$n}'", in_array($n, array_column($kpi['detail_kpi'] ?? [], 'nama'), true));
}
$detail = [];
$weightedSum = 0.0;
foreach ($kpi['detail_kpi'] as $d) {
    $detail[$d['nama']] = $d['nilai'];
    if ($d['nilai'] !== null) {
        $weightedSum += ($d['nilai'] * $d['bobot']) / 100.0;
    }
}
ok('skor_total = Σ (nilai×bobot/100)', near($kpi['skor_total'], round($weightedSum, 2)), "total={$kpi['skor_total']} vs {$weightedSum}");
ok('Detail nilai Omzet Wilayah cocok', near($detail['Omzet Wilayah'], $ow), var_export($detail['Omzet Wilayah'], true));
ok('Detail nilai Customer Satisfaction = 92', near($detail['Customer Satisfaction'], 92), var_export($detail['Customer Satisfaction'], true));

// Detail Absensi SPV ikut tampil (bobot sama dgn team)
$detailAbsen = array_column($kpi['detail_absen'] ?? [], 'bobot', 'nama');
ok('Detail Absensi SPV = 4 kriteria', count($kpi['detail_absen'] ?? []) === 4, json_encode($kpi['detail_absen'] ?? []));
$absenWeightsOk =
    near($detailAbsen['Kehadiran'] ?? null, 40)
    && near($detailAbsen['Kebersihan'] ?? null, 20)
    && near($detailAbsen['Seragam'] ?? null, 20)
    && near($detailAbsen['Kepatuhan SOP'] ?? null, 20);
ok('Detail Absensi SPV bobot = 40/20/20/20', $absenWeightsOk, json_encode($detailAbsen));

// ── 6. Session scope berbeda (SPV 56, scope [1,4]) ───────────────
$ow56 = $svc->achievement('OMZET_WILAYAH', $SPV56, 4, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('SPV 56 (scope [1,4]) TIDAK melihat data unit [2,3] → omzet 0 bukan 105', near($ow56, 0.0), var_export($ow56, true));

$pc56 = $svc->achievement('PRODUKTIVITAS_CABANG', $SPV56, 4, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('SPV 56: prev unique customer = 0 → PRODUKTIVITAS = null (tanpa ÷0)', $pc56 === null, var_export($pc56, true));

$cs56 = $svc->achievement('CUSTOMER_SATISFACTION', $SPV56, 4, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('SPV 56: CS belum diinput = null', $cs56 === null, var_export($cs56, true));

// ── 7. Edge & fallback ───────────────────────────────────────────
ok('scopeUnits fallback ke unit sendiri', $svc->scopeUnits(999999, 7) === [7], json_encode($svc->scopeUnits(999999, 7)));
$unk = $svc->achievement('UNKNOWN_COMPONENT', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('Komponen tidak dikenal → null', $unk === null, var_export($unk, true));

// ── 8. Rollback (tidak mencemari DB) ─────────────────────────────
$db->transRollback();
$afterSales = (int)$db->query("SELECT COUNT(*) c FROM penjualan WHERE kode_invoice LIKE 'SV-2028-%'")->getRow()->c;
$afterEvals = (int)$db->query(
    "SELECT COUNT(*) c FROM kpi_evaluations WHERE period_year = 2028 AND period_month IN (4,5) AND notes = 'test'"
)->getRow()->c;
ok('Data uji telah di-rollback (sales)', $afterSales === 0, "sisa={$afterSales}");
ok('Data uji telah di-rollback (evaluations)', $afterEvals === 0, "sisa={$afterEvals}");

echo "============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail === 0 ? 0 : 1);