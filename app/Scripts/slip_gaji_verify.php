<?php
/**
 * slip_gaji bonus/lembur verification
 *
 * OLD = PenilaianKPI::hitungKPIGaji('slip_gaji') + query bon/lembur
 * NEW = LegacyKpiCalculationService('slip_gaji') + SalaryCalculationService
 *
 * Usage: php app/Scripts/slip_gaji_verify.php
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
if (!defined('TESTPATH')) define('TESTPATH', realpath(rtrim($paths->testsDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \Config\Database::connect();
$bulan = '08';
$tahun = '2026';

// Employee yang punya bon/lembur di kas_keluar (penerima = ID_AKUN)
$withBonLembur = $db->query("
    SELECT penerima,
      SUM(CASE WHEN deskripsi LIKE '%bon%' THEN jumlah ELSE 0 END) AS total_bon,
      SUM(CASE WHEN deskripsi LIKE '%lembur%' THEN jumlah ELSE 0 END) AS total_lembur
    FROM kas_keluar
    WHERE kategori_idkategori = 10
      AND penerima REGEXP '^[0-9]+$'
    GROUP BY penerima
    HAVING total_bon > 0 OR total_lembur > 0
")->getResultArray();

$ctrl = new \App\Controllers\PenilaianKPI();



$legacyService = new \App\Services\Kpi\LegacyKpiCalculationService();
$salaryService = new \App\Services\Payroll\SalaryCalculationService();

$pass = 0; $fail = 0;
echo "SLIP GAJI BON/LEMBUR VERIFICATION | {$bulan}/{$tahun}\n";
echo "============================================================\n";

foreach ($withBonLembur as $row) {
    $idakun = (int)$row['penerima'];
    $oldBon = (float)$row['total_bon'];
    $oldLembur = (float)$row['total_lembur'];

    $emp = $db->table('akun')->select('NAMA_AKUN, ID_JABATAN, ID_UNIT')->where('ID_AKUN', $idakun)->get()->getFirstRow();
    if (!$emp) {
        echo "[SKIP] id $idakun bukan employee aktif\n";
        continue;
    }

    // ── OLD ──
    $old = $ref->invoke($ctrl, $idakun, $bulan, $tahun, 'slip_gaji');
    // OLD slip total (view): gaji + lembur
    // OLD bersih (view): gaji + lembur - bon

    // ── NEW ──
    $legacy = $legacyService->calculate($idakun, $bulan, $tahun, 'slip_gaji');
    $periodDate = "$tahun-$bulan-15";
    $result = $salaryService->calculateSalary(
        $idakun,
        $legacy['jabatan'],
        $legacy['unit'],
        'slip_gaji',
        ['TUNJANGAN_KINERJA' => $legacy['skor_total'], 'TUNJANGAN_ABSEN' => $legacy['skor_total2']],
        $legacy['akun']->tunjangan_penempatan,
        $legacy['insentif'],
        $oldLembur,
        $oldBon,
        $periodDate
    );
    $comp = [];
    foreach ($result['components'] as $c) { $comp[$c['component_code']] = (float)$c['amount']; }

    // NEW view-equivalent (seperti controller baru):
    $newGajiPokok   = $comp['GAJI_POKOK'] ?? 0;
    $newGajiKinerja  = $comp['TUNJANGAN_KINERJA'] ?? 0;
    $newGajiAbsen    = $comp['TUNJANGAN_ABSEN'] ?? 0;
    $newInsentif     = (float)$result['incentive'];
    $newPenempatan   = (float)$result['placement_allowance'];
    $newLembur       = (float)$result['lembur'];
    $newBon          = (float)$result['bon'];
    $newGajiBase     = (float)$result['total_gaji'] - $newLembur + $newBon;
    $newGajiTotal    = (float)$result['total_gaji'];      // gaji + lembur - bon
    $newGajiWithLem  = $newGajiBase + $newLembur;        // view TOTAL(A)
    $newBersih       = $newGajiBase + $newLembur - $newBon; // view netto

    $fields = [
        'gaji_pokok'        => [(float)$old['gaji_pokok'], $newGajiPokok],
        'tunjangan_kinerja' => [(float)$old['tunjangan_kinerja'], $newGajiKinerja],
        'tunjangan_absen'   => [(float)$old['tunjangan_absen'], $newGajiAbsen],
        'insentif'          => [(float)$old['insentif'], $newInsentif],
        'tunjangan_penempatan' => [(float)$old['akun']->tunjangan_penempatan, $newPenempatan],
        'lembur'            => [$oldLembur, $newLembur],
        'bon'               => [$oldBon, $newBon],
        'gaji (base)'       => [(float)$old['gaji'], $newGajiBase],
        'TOTAL A (gaji+lembur)' => [(float)$old['gaji'] + $oldLembur, $newGajiWithLem],
        'BERSIH (A-bon)'    => [(float)$old['gaji'] + $oldLembur - $oldBon, $newBersih],
    ];

    echo "Employee: {$emp->NAMA_AKUN} (id={$idakun}, jab={$emp->ID_JABATAN}, unit={$emp->ID_UNIT})\n";
    $itemFail = false;
    foreach ($fields as $label => [$oldVal, $newVal]) {
        $diff = abs($oldVal - $newVal);
        $ok = $diff < 100;
        if (!$ok) $itemFail = true;
        printf("  %-22s OLD=%12.2f NEW=%12.2f diff=%9.2f %s\n", $label, $oldVal, $newVal, $diff, $ok ? 'PASS' : 'FAIL');
    }
    $itemFail ? $fail++ : $pass++;
    echo "  ---\n";
}

echo "============================================================\n";
echo "RESULT: PASS=$pass FAIL=$fail\n";
echo ($fail === 0 ? "STATUS: ALL_IDENTICAL" : "STATUS: DISCREPANCY_FOUND") . "\n";
exit($fail === 0 ? 0 : 1);