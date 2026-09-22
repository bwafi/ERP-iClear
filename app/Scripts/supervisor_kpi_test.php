<?php
/**
 * Supervisor KPI Test — 7 komponen KPI Area Supervisor / SPV.
 *
 * Memverifikasi:
 *   1. Konfigurasi (komponen + bobot jabatan 40 = 100%).
 *   2. OMZET_WILAYAH       (threshold: actual total < target total → 0; actual ≥ target → ratio, cap 100)
 *   3. TARGET_CABANG       (jumlah cabang tercapai / total cabang scope × 100)
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
// Target HO untuk SPV = target dasar + Rp7.000.000 PER CABANG (scope).
// Unit 2: 35jt + 7jt = 42jt; Unit 3: 60jt + 7jt = 67jt → total target 109jt.
// Actual 100jt (40jt + 60jt) < 109jt → OMZET_WILAYAH = 0 (threshold belum tercapai).
$ow = $svc->achievement('OMZET_WILAYAH', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('OMZET_WILAYAH = 0 (100jt < target HO 109jt, threshold)', near($ow, 0.0), var_export($ow, true));

// TARGET_CABANG = jumlah cabang tercapai / total cabang scope × 100.
// Unit 2: 40jt < 42jt → tidak tercapai. Unit 3: 60jt < 67jt → tidak tercapai.
// 0 / 2 × 100 = 0.
$tc = $svc->achievement('TARGET_CABANG', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 0 (0/2 cabang tercapai)', near($tc, 0.0), var_export($tc, true));

// PRODUKTIVITAS = 36/30 = 120 → di-cap 100 (pedoman §VII).
$pc = $svc->achievement('PRODUKTIVITAS_CABANG', $SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('PRODUKTIVITAS_CABANG = 100 (36/30 = 120, cap 100)', near($pc, 100.0), var_export($pc, true));

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
$teamUnits = $svc->scopeUnits($SPV49, 1);
foreach (\App\Services\Kpi\SupervisorKpiService::TEAM_JABATANS as $jd) {
    foreach ($teamUnits as $u) { // mirror scopeEmployees(akun) — rekap team area SPV
        foreach ($db->query("SELECT ID_AKUN, ID_UNIT FROM akun
            WHERE ID_UNIT = ? AND ID_JABATAN = ? AND STATUS_PEGAWAI = 1 AND (deleted IS NULL OR deleted = 0)", [$u, $jd])->getResultArray() as $m) {
            $teamScores[] = (float)$att->calculateMonthlyAttendance((int)$m['ID_AKUN'], (int)$m['ID_UNIT'], (string)$MONTH, (string)$YEAR, 'penilaian_kinerja')['attendance_score'];
        }
    }
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

// ── 5b. Regression: target HO +7jt/cabang, cap ≤100, salary 1.5jt, insentif 0 ──
$compIdOmset = (int)$db->query("SELECT id FROM kpi_components WHERE code = 'OMSET_CABANG'")->getRow()->id;

// Scope masing-masing SPV (tidak boleh hardcode semua unit).
ok('Scope SPV 49 = [2,3]', $svc->scopeUnits($SPV49, 50) === [2, 3], json_encode($svc->scopeUnits($SPV49, 50)));
ok('Scope SPV 56 = [1,4]', $svc->scopeUnits($SPV56, 50) === [1, 4], json_encode($svc->scopeUnits($SPV56, 50)));

// Adjust per cabang: 2 cabang dalam scope = +Rp14.000.000 total.
$t2 = (float)$db->query("SELECT target_value FROM kpi_targets WHERE kpi_component_id = ? AND unit_id = 2 AND context = 'penilaian_kinerja' AND effective_from <= ? ORDER BY effective_from DESC LIMIT 1", [$compIdOmset, $dateAnchor])->getRow()->target_value;
$t3 = (float)$db->query("SELECT target_value FROM kpi_targets WHERE kpi_component_id = ? AND unit_id = 3 AND context = 'penilaian_kinerja' AND effective_from <= ? ORDER BY effective_from DESC LIMIT 1", [$compIdOmset, $dateAnchor])->getRow()->target_value;
$adj = \App\Services\Kpi\SupervisorKpiService::HO_TARGET_ADJUSTMENT;
ok('Adjustment HO per cabang = Rp7.000.000', near($adj, 7000000), "adj={$adj}");
$adjTotal = (($t2 + $adj) + ($t3 + $adj)) - ($t2 + $t3);
ok('2 cabang dalam scope → total adjustment = +Rp14.000.000', near($adjTotal, 14000000), "adjTotal={$adjTotal}");
ok('OMZET threshold: actual (100jt) < target stlh adjustment HO (109jt) → 0', near($ow, 0.0), "ow={$ow}");

// Target dasar cabang TIDAK berubah (tidak ditulis kembali ke kpi_targets).
ok('Target dasar cabang 2 tetap 35jt (tidak diubah)', near($t2, 35000000), "t2={$t2}");
$t4Out = (float)$db->query("SELECT target_value FROM kpi_targets WHERE kpi_component_id = ? AND unit_id = 4 AND context = 'penilaian_kinerja' AND effective_from <= ? ORDER BY effective_from DESC LIMIT 1", [$compIdOmset, $dateAnchor])->getRow()->target_value;
ok('Cabang luar scope (unit 4) tetap 55jt (dasar, tanpa adjustment utk SPV49)', near($t4Out, 55000000), "t4={$t4Out}");

// Actual omzet TIDAK di-adjust +7jt.
$omsetCalc = new \App\Services\Kpi\OmsetCabangCalculator();
ok('Actual omzet unit 2 tetap 40jt (tanpa +7jt)', near($omsetCalc->calculate(0, 2, $MONTH, $YEAR), 40000000), var_export($omsetCalc->calculate(0, 2, $MONTH, $YEAR), true));
ok('Actual omzet unit 3 tetap 60jt (tanpa +7jt)', near($omsetCalc->calculate(0, 3, $MONTH, $YEAR), 60000000), var_export($omsetCalc->calculate(0, 3, $MONTH, $YEAR), true));

// Cap ≤100 per komponen & total.
$allKpiLe100 = true;
foreach ($kpi['detail_kpi'] as $d) {
    if ($d['nilai'] !== null && $d['nilai'] > 100) {
        $allKpiLe100 = false;
    }
}
ok('Semua detail KPI komponen SPV ≤ 100', $allKpiLe100, json_encode(array_column($kpi['detail_kpi'], 'nilai')));
ok('skor_total (total KPI) SPV ≤ 100', $kpi['skor_total'] <= 100.0, var_export($kpi['skor_total'], true));
ok('Insentif SPV = Rp0 (tidak ada group/member SPV)', near((float)$kpi['insentif'], 0.0), var_export($kpi['insentif'], true));

// Tidak ada hardcode daftar semua unit di jalur kalkulasi SPV.
$kpiSrc = file_get_contents(APPPATH . 'Services/Kpi/KpiCalculationService.php');
ok('Tidak ada hardcode [1, 2, 3, 4] di KpiCalculationService.php', strpos($kpiSrc, '[1, 2, 3, 4]') === false, 'literal ditemukan');
ok('Blok insentif SPV memakai scopeUnits()', strpos($kpiSrc, 'scopeUnits($employeeId, $unit)') !== false, 'scopeUnits tidak dipakai');

// Salary: maksimum tunjangan kinerja SPV = Rp1.500.000 (pedoman §XVIII).
$ssSpv = $db->query("SELECT ss.base_value, ss.calculation_type FROM salary_structures ss
    JOIN salary_components sc ON sc.id = ss.salary_component_id
    WHERE ss.position_id = 40 AND sc.code = 'TUNJANGAN_KINERJA'
    ORDER BY ss.effective_from ASC, ss.unit_id ASC LIMIT 1")->getRow();
ok('Maksimum tunjangan kinerja SPV (40) = Rp1.500.000', near((float)$ssSpv->base_value, 1500000), var_export($ssSpv->base_value ?? null, true));
$ss41 = $db->query("SELECT ss.base_value FROM salary_structures ss
    JOIN salary_components sc ON sc.id = ss.salary_component_id
    WHERE ss.position_id = 41 AND sc.code = 'TUNJANGAN_KINERJA'
    ORDER BY ss.effective_from ASC, ss.unit_id ASC LIMIT 1")->getRow();
ok('Tunjangan kinerja KT 41 tetap Rp850.000', near((float)$ss41->base_value, 850000), var_export($ss41->base_value ?? null, true));
$ss43 = $db->query("SELECT ss.base_value FROM salary_structures ss
    JOIN salary_components sc ON sc.id = ss.salary_component_id
    WHERE ss.position_id = 43 AND sc.code = 'TUNJANGAN_KINERJA'
    ORDER BY ss.effective_from ASC, ss.unit_id ASC LIMIT 1")->getRow();
ok('Tunjangan kinerja Kepala Divisi/Digital Marketing (43) = Rp1.500.000', near((float)$ss43->base_value, 1500000), var_export($ss43->base_value ?? null, true));
$ss0 = $db->query("SELECT ss.base_value FROM salary_structures ss
    JOIN salary_components sc ON sc.id = ss.salary_component_id
    WHERE ss.position_id = 0 AND sc.code = 'TUNJANGAN_KINERJA'
    ORDER BY ss.effective_from ASC, ss.unit_id ASC LIMIT 1")->getRow();
ok('Tunjangan kinerja Admin Center (0) = Rp1.250.000', near((float)($ss0->base_value ?? 0), 1250000), var_export($ss0->base_value ?? null, true));
$ss34 = $db->query("SELECT ss.base_value FROM salary_structures ss
    JOIN salary_components sc ON sc.id = ss.salary_component_id
    WHERE ss.position_id = 34 AND sc.code = 'TUNJANGAN_KINERJA'
    ORDER BY ss.effective_from ASC, ss.unit_id ASC LIMIT 1")->getRow();
ok('Tunjangan kinerja Manager (34) = Rp2.250.000', near((float)($ss34->base_value ?? 0), 2250000), var_export($ss34->base_value ?? null, true));
$ss44 = $db->query("SELECT ss.base_value FROM salary_structures ss
    JOIN salary_components sc ON sc.id = ss.salary_component_id
    WHERE ss.position_id = 44 AND sc.code = 'TUNJANGAN_KINERJA'
    ORDER BY ss.effective_from ASC, ss.unit_id ASC LIMIT 1")->getRow();
ok('Tunjangan kinerja Multimedia (44) = Rp750.000', near((float)$ss44->base_value, 750000), var_export($ss44->base_value ?? null, true));
$ss45 = $db->query("SELECT ss.base_value FROM salary_structures ss
    JOIN salary_components sc ON sc.id = ss.salary_component_id
    WHERE ss.position_id = 45 AND sc.code = 'TUNJANGAN_KINERJA'
    ORDER BY ss.effective_from ASC, ss.unit_id ASC LIMIT 1")->getRow();
ok('Tunjangan kinerja IT & System (45) = Rp750.000', near((float)$ss45->base_value, 750000), var_export($ss45->base_value ?? null, true));

// Engine salary (SalaryCalculationService) memakai 1.5jt utk SPV @ KPI 100%.
$salarySvc = new \App\Services\Payroll\SalaryCalculationService();
$salaryRes = $salarySvc->calculateSalary(
    $SPV49, 40, 50, 'slip_gaji',
    ['TUNJANGAN_KINERJA' => 100, 'TUNJANGAN_ABSEN' => 100],
    0.0, 0.0, 0.0, 0.0, $dateAnchor
);
$amtKinerja = 0.0;
foreach ($salaryRes['components'] as $c) {
    if ($c['component_code'] === 'TUNJANGAN_KINERJA') {
        $amtKinerja = (float)$c['amount'];
    }
}
ok('KPI 100% → tunjangan kinerja SPV = Rp1.500.000', near($amtKinerja, 1500000), "amt={$amtKinerja}");

// ── 6. Session scope berbeda (SPV 56, scope [1,4]) ───────────────
$ow56 = $svc->achievement('OMZET_WILAYAH', $SPV56, 4, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('SPV 56 (scope [1,4]) TIDAK melihat data unit [2,3] → actual 0 → omzet 0', near($ow56, 0.0), var_export($ow56, true));

$pc56 = $svc->achievement('PRODUKTIVITAS_CABANG', $SPV56, 4, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('SPV 56: prev unique customer = 0 → PRODUKTIVITAS = null (tanpa ÷0)', $pc56 === null, var_export($pc56, true));

$cs56 = $svc->achievement('CUSTOMER_SATISFACTION', $SPV56, 4, $MONTH, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('SPV 56: CS belum diinput = null', $cs56 === null, var_export($cs56, true));

// ── 6b. Threshold OMZET & count TARGET_CABANG (bulan 6-13) ──────
// Helper: insert penjualan gross per unit pada bulan tertentu (dalam transaction).
$addSales = function (int $mf, array $perUnit) use ($salesTable, $detTable, $db, $YEAR) {
    foreach ($perUnit as $unitC => $subC) {
        $cust = 999000 + $mf * 100 + $unitC;
        $salesTable->insert([
            'kode_invoice'    => 'SV-2028-' . $mf . '-' . $unitC . '-' . $cust,
            'tanggal'         => sprintf('%04d-%02d-15 10:10:00', $YEAR, $mf),
            'total_penjualan' => $subC,
            'diskon'          => 0,
            'total_ppn'       => 0,
            'harus_dibayar'   => (string)$subC,
            'bayar'           => $subC,
            'bayar_tunai'     => $subC,
            'bank_idbank'     => '0',
            'bayar_bank'      => 0,
            'id_pelanggan'    => $cust,
            'input_by'        => 58,
            'sales_by'        => 58,
            'unit_idunit'     => $unitC,
        ]);
        $pid = $db->insertID();
        $detTable->insert([
            'jumlah'                => 1,
            'harga_penjualan'       => $subC,
            'sub_total'             => $subC,
            'hpp_penjualan'         => 0,
            'satuan_jual'           => 'pcs',
            'diskon_penjualan'      => '0',
            'penjualan_idpenjualan' => $pid,
            'barang_idbarang'       => 158,
            'unit_idunit'           => $unitC,
            'bundle'                => 0,
        ]);
    }
};

// Bulan 6: unit2 & unit3 GROSS 100jt masing-masing → total 200jt > 109jt.
$addSales(6, [2 => 100000000, 3 => 100000000]);
$owCap = $svc->achievement('OMZET_WILAYAH', $SPV49, 1, 6, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('OMZET_WILAYAH = 100 saat actual (200jt) > target HO (109jt) [cap]', near($owCap, 100.0), var_export($owCap, true));
$tcCap = $svc->achievement('TARGET_CABANG', $SPV49, 1, 6, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 100 (2/2 cabang tercapai)', near($tcCap, 100.0), var_export($tcCap, true));
ok('Actual omzet bulan 6 tetap 100jt/cabang (tanpa +7jt)', near($omsetCalc->calculate(0, 2, 6, $YEAR), 100000000), var_export($omsetCalc->calculate(0, 2, 6, $YEAR), true));

// Bulan 7: actual PERSIS sama target HO (u2=42jt, u3=67jt → 109 = 109).
$addSales(7, [2 => 42000000, 3 => 67000000]);
$owEq = $svc->achievement('OMZET_WILAYAH', $SPV49, 1, 7, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('OMZET_WILAYAH = 100 saat actual == target (109 = 109)', near($owEq, 100.0), var_export($owEq, true));
$tcEq = $svc->achievement('TARGET_CABANG', $SPV49, 1, 7, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 100 saat actual == target (2/2)', near($tcEq, 100.0), var_export($tcEq, true));

// Bulan 8: hanya 1 cabang tercapai (u2=42jt tercapai, u3=66jt tidak).
$addSales(8, [2 => 42000000, 3 => 66000000]);
$tcHalf = $svc->achievement('TARGET_CABANG', $SPV49, 1, 8, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 50 (1/2 cabang tercapai)', near($tcHalf, 50.0), var_export($tcHalf, true));
$owHalf = $svc->achievement('OMZET_WILAYAH', $SPV49, 1, 8, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('OMZET tetap 0 walau 1 cabang tercapai (108jt < 109jt)', near($owHalf, 0.0), var_export($owHalf, true));

// Bulan 9: actual jauh di bawah target (2jt total).
$addSales(9, [2 => 1000000, 3 => 1000000]);
$owLow = $svc->achievement('OMZET_WILAYAH', $SPV49, 1, 9, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('OMZET_WILAYAH = 0 saat actual jauh di bawah target', near($owLow, 0.0), var_export($owLow, true));
$tcLow = $svc->achievement('TARGET_CABANG', $SPV49, 1, 9, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 0 saat 0/2 cabang tercapai', near($tcLow, 0.0), var_export($tcLow, true));

// Bulan 10-13: TARGET_CABANG dengan 4 cabang.
// Scope SPV 56 diperluas SEMENTARA → [1,2,3,4] (dalam transaction, di-rollback).
// Target HO (penilaian_kinerja): u1=50+7=57jt, u2=35+7=42jt, u3=60+7=67jt, u4=55+7=62jt.
$unitTable = $db->table('spv_units');
foreach ([2, 3] as $extra) {
    $unitTable->insert([
        'spv_id'     => $SPV56,
        'unit_id'    => $extra,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}
ok('Scope SPV56 diperluas sementara = [1,2,3,4]', $svc->scopeUnits($SPV56, 4) === [1, 2, 3, 4], json_encode($svc->scopeUnits($SPV56, 4)));

$addSales(10, [2 => 42000000]);
$tc14t = $svc->achievement('TARGET_CABANG', $SPV56, 4, 10, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 25 (1/4 cabang tercapai)', near($tc14t, 25.0), var_export($tc14t, true));

$addSales(11, [2 => 42000000, 3 => 67000000]);
$tc24t = $svc->achievement('TARGET_CABANG', $SPV56, 4, 11, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 50 (2/4 cabang tercapai)', near($tc24t, 50.0), var_export($tc24t, true));

$addSales(12, [2 => 42000000, 3 => 67000000, 4 => 62000000]);
$tc34t = $svc->achievement('TARGET_CABANG', $SPV56, 4, 12, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 75 (3/4 cabang tercapai)', near($tc34t, 75.0), var_export($tc34t, true));

$addSales(13, [1 => 57000000, 2 => 42000000, 3 => 67000000, 4 => 62000000]);
$tc44t = $svc->achievement('TARGET_CABANG', $SPV56, 4, 13, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('TARGET_CABANG = 100 (4/4 cabang tercapai)', near($tc44t, 100.0), var_export($tc44t, true));
$ow44t = $svc->achievement('OMZET_WILAYAH', $SPV56, 4, 13, $YEAR, 'penilaian_kinerja', 'penilaian_kinerja', $dateAnchor);
ok('OMZET_WILAYAH = 100 saat actual == target (228 = 228, 4 cabang)', near($ow44t, 100.0), var_export($ow44t, true));

// ── 6c. Info target/realisasi utk tampilan (omzetDetail, read-only) ──
$od = $svc->omzetDetail($SPV49, 1, $MONTH, $YEAR, 'penilaian_kinerja', $dateAnchor);
ok('omzetDetail SPV49 bulan 5 ada', is_array($od), json_encode($od));
ok('omzetDetail target_ho_total = 109jt', near($od['target_ho_total'] ?? null, 109000000), json_encode($od['target_ho_total'] ?? null));
ok('omzetDetail target_non_ho_total = 95jt', near($od['target_non_ho_total'] ?? null, 95000000), json_encode($od['target_non_ho_total'] ?? null));
ok('omzetDetail actual_total = 100jt', near($od['actual_total'] ?? null, 100000000), json_encode($od['actual_total'] ?? null));
ok('omzetDetail shortfall_ho = 9jt', near($od['shortfall_ho'] ?? null, 9000000), json_encode($od['shortfall_ho'] ?? null));
$cb2 = $od['cabang'][0] ?? null;
ok('omzetDetail cabang unit2 target_ho 42jt actual 40jt belum tercapai', $cb2 && $cb2['unit'] === 2 && near($cb2['target_ho'], 42000000) && near($cb2['actual'], 40000000) && $cb2['reached'] === false, json_encode($cb2));

$kpiInfo = $kpiSvc->calculateForSalary($SPV49, (string)$MONTH, (string)$YEAR, 'penilaian_kinerja')['detail_kpi'];
$rowByName = array_column($kpiInfo, null, 'nama');
$owRow = $rowByName['Omzet Wilayah'] ?? [];
ok('Detail "Omzet Wilayah" membawa target HO 109jt', near($owRow['target'] ?? null, 109000000), json_encode($owRow));
ok('Detail "Omzet Wilayah" shortfall 9jt', near($owRow['shortfall'] ?? null, 9000000), json_encode($owRow));
$tcRow = $rowByName['Target Cabang'] ?? [];
ok('Detail "Target Cabang" unit_count 2, reached 0, shortfall 2', ($tcRow['unit_count'] ?? null) === 2 && ($tcRow['reached'] ?? null) === 0 && ($tcRow['shortfall'] ?? null) === 2, json_encode($tcRow));

$od6 = $svc->omzetDetail($SPV49, 1, 6, $YEAR, 'penilaian_kinerja', $dateAnchor);
ok('omzetDetail bulan 6: 200jt realisasi, semua cabang tercapai', near($od6['actual_total'] ?? null, 200000000) && !empty($od6['cabang']) && $od6['cabang'][0]['reached'] && $od6['cabang'][1]['reached'], json_encode($od6));

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