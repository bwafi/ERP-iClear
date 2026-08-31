<?php
/**
 * KPI Regression Harness
 *
 * Bandingkan OLD (PenilaianKPI::hitungKPIGaji via reflection)
 * vs NEW (LegacyKpiCalculationService) untuk employee nyata.
 *
 * Usage: php app/Scripts/kpi_regression.php [bulan] [tahun]
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

$bulan = $argv[1] ?? date('m');
$tahun = $argv[2] ?? date('Y');
$rendah = floatval($argv[3] ?? 0.01); // toleransi floating point

$db = \Config\Database::connect();

$contexts = ['gaji', 'penilaian_kinerja', 'slip_gaji'];

$controllers = $db->table('akun')
    ->where('STATUS_PEGAWAI', 1)
    ->where('ID_JABATAN !=', 1)
    ->orderBy('ID_JABATAN', 'ASC')
    ->get()
    ->getResultArray();

echo "============================================================\n";
echo "KPI REGRESSION: OLD hitungKPIGaji vs NEW LegacyKpiCalculationService\n";
echo "Periode: $bulan/$tahun   Toleransi: $rendah\n";
echo "Jumlah employee: " . count($controllers) . "\n";
echo "============================================================\n";

$ctrl = new \App\Controllers\PenilaianKPI();


$service = new \App\Services\Kpi\LegacyKpiCalculationService();

$totalPass = 0;
$totalFail = 0;

foreach ($controllers as $emp) {
    $idAkun = $emp['ID_AKUN'];
    $nama   = $emp['NAMA_AKUN'];
    $jabatan = $emp['ID_JABATAN'];
    $unit    = $emp['ID_UNIT'];

    foreach ($contexts as $context) {
        // OLD
        try {
            // $old = null; // Method hitungKPIGaji removed

        } catch (\Throwable $e) {
            echo "[FAIL][$context] $nama ($idAkun) OLD crash: {$e->getMessage()}\n";
            $totalFail++;
            continue;
        }

        // NEW
        try {
            $new = $service->calculate($idAkun, $bulan, $tahun, $context);
        } catch (\Throwable $e) {
            echo "[FAIL][$context] $nama ($idAkun) NEW crash: {$e->getMessage()}\n";
            $totalFail++;
            continue;
        }

        $fields = [
            'skor_total'        => 'total KPI',
            'skor_total2'       => 'total absen',
            'tunjangan_kinerja' => 'tunj. kinerja',
            'tunjangan_absen'   => 'tunj. absen',
            'insentif'          => 'insentif',
            'gaji_pokok'        => 'gaji pokok',
            'gaji'              => 'gaji total',
        ];

        $failDetail = [];
        $kpiItemDiffs = 0;

        // Bandingkan detail_kpi
        foreach ($old['detail_kpi'] as $i => $oldItem) {
            $newItem = $new['detail_kpi'][$i] ?? null;
            if ($newItem === null) {
                $kpiItemDiffs++;
                continue;
            }
            foreach (['nama', 'bobot', 'nilai'] as $key) {
                if (abs((float)$oldItem[$key] - (float)$newItem[$key]) > $rendah) {
                    $kpiItemDiffs++;
                }
            }
        }

        // Bandingkan detail_absen
        foreach ($old['detail_absen'] as $i => $oldItem) {
            $newItem = $new['detail_absen'][$i] ?? null;
            if ($newItem === null) {
                $kpiItemDiffs++;
                continue;
            }
            foreach (['nama', 'bobot', 'nilai'] as $key) {
                if (abs((float)$oldItem[$key] - (float)$newItem[$key]) > $rendah) {
                    $kpiItemDiffs++;
                }
            }
        }

        $allDiff = [];
        foreach ($fields as $field => $label) {
            $diff = abs((float)$old[$field] - (float)$new[$field]);
            if ($diff > $rendah) {
                $allDiff[$label] = sprintf('OLD=%.2f NEW=%.2f diff=%.4f', (float)$old[$field], (float)$new[$field], $diff);
            }
        }

        if (empty($allDiff) && $kpiItemDiffs === 0) {
            $totalPass++;
            echo "[PASS][$context] $nama (id=$idAkun, jab=$jabatan, unit=$unit) | KPI={$new['skor_total']} Gaji={$new['gaji']}\n";
        } else {
            $totalFail++;
            echo "[FAIL][$context] $nama (id=$idAkun, jab=$jabatan, unit=$unit)\n";
            if ($kpiItemDiffs > 0) {
                echo "    detail_kpi/absen item diff count: $kpiItemDiffs\n";
            }
            foreach ($allDiff as $label => $detail) {
                echo "    $label: $detail\n";
            }
        }
    }
}

echo "============================================================\n";
echo "RESULT: PASS=$totalPass FAIL=$totalFail\n";
if ($totalFail === 0) {
    echo "STATUS: ALL_IDENTICAL\n";
} else {
    echo "STATUS: DISCREPANCY_FOUND\n";
    exit(1);
}