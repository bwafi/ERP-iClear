<?php
/**
 * READ-ONLY audit: cari semua kasus skor_total / skor_total2 > 100
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
$svc = new \App\Services\Kpi\LegacyKpiCalculationService();

$employees = $db->table('akun')->where('STATUS_PEGAWAI', 1)->get()->getResultArray();

$full = [];
foreach (['04','05','06','07','08'] as $bln) {
    foreach ($employees as $emp) {
        foreach (['gaji','penilaian_kinerja','slip_gaji'] as $ctx) {
            try {
                $r = $svc->calculate((int)$emp['ID_AKUN'], $bln, '2026', $ctx);
            } catch (\Throwable $e) { continue; }
            $s1 = (float)$r['skor_total'];
            $s2 = (float)$r['skor_total2'];
            if ($s1 > 100.01 || $s2 > 100.01) {
                $full[] = [
                    'emp' => $emp['ID_AKUN'], 'nama' => $emp['NAMA_AKUN'],
                    'jab' => $emp['ID_JABATAN'], 'unit' => $emp['ID_UNIT'],
                    'bln' => $bln, 'ctx' => $ctx,
                    's1' => round($s1, 2), 's2' => round($s2, 2),
                    'detail_kpi' => $r['detail_kpi'],
                    'detail_absen' => $r['detail_absen'],
                ];
            }
        }
    }
}

echo "KASUS skor_total > 100 ATAU skor_total2 > 100 (periode 04-08/2026):\n";
echo str_repeat('=', 90) . "\n";
foreach ($full as $f) {
    printf("EMP %-3d %-12s jab=%-2d unit=%-2d %s/2026 [%s]  skor_total=%-8.2f skor_total2=%.2f\n",
        $f['emp'], $f['nama'], $f['jab'], $f['unit'], $f['bln'], $f['ctx'], $f['s1'], $f['s2']);
}

echo "\n\nDETAIL penyebab (per kasus unik):\n";
echo str_repeat('-', 90) . "\n";
$seen = [];
foreach ($full as $f) {
    $key = $f['emp'].'|'.$f['jab'].'|'.$f['bln'].'|'.$f['ctx'];
    if (isset($seen[$key]) || ($f['s1'] <= 100.01)) continue; // fokus s1>100 dulu
    $seen[$key] = true;
    echo "EMP {$f['emp']} {$f['nama']} jab={$f['jab']} unit={$f['unit']} {$f['bln']}/2026 [{$f['ctx']}] skor_total={$f['s1']}\n";
    foreach ($f['detail_kpi'] as $k) {
        $contrib = round($k['nilai'] * $k['bobot'] / 100, 2);
        if ($k['nilai'] > 100 || $contrib > 10) {
            printf("   [>100 atau kontrib besar] %-14s bobot=%3d nilai=%.4f contrib=%8.2f\n", $k['nama'], $k['bobot'], $k['nilai'], $contrib);
        }
    }
    echo "\n";
}

echo "\nTOTAL kasus (baris s1>100 atau s2>100): " . count($full) . "\n";
$s1c = 0; $s2c = 0;
foreach ($full as $f) { if ($f['s1'] > 100.01) $s1c++; if ($f['s2'] > 100.01) $s2c++; }
echo "s1>100: $s1c kasus, s2>100: $s2c kasus\n";
