<?php
/**
 * Seed missing kpi_targets rows required by NEW production flow.
 * Idempotent: hanya insert jika row belum ada.
 *
 * 1. OMSET_TOKO (cid=1) context slip_gaji → nilai sama dengan penilaian_kinerja
 *    (legacy: non-gaji context memakai target array yang identik).
 * 2. CUSTOMER_COUNT (cid=3) threshold rows (unit_of_measure='threshold'):
 *    atas_customer per unit per context (legacy non-gaji scheme).
 */
require_once __DIR__ . '/../../vendor/autoload.php';
use Config\Paths;
require_once __DIR__ . '/../../app/Config/Paths.php';
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
if (!defined('APPPATH')) define('APPPATH', realpath(rtrim($paths->appDirectory, '\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('ROOTPATH')) define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
if (!defined('SYSTEMPATH')) define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\/ ')) . DIRECTORY_SEPARATOR);
if (!defined('WRITEPATH')) define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \Config\Database::connect();

// 1. slip_gaji omset targets (mirror penilaian_kinerja)
$rows = $db->table('kpi_targets')->where('kpi_component_id', 1)->where('context', 'penilaian_kinerja')->get()->getResult();
foreach ($rows as $src) {
    $exists = $db->table('kpi_targets')
        ->where('kpi_component_id', 1)
        ->where('unit_id', $src->unit_id)
        ->where('context', 'slip_gaji')
        ->countAllResults();
    if (!$exists) {
        $db->table('kpi_targets')->insert([
            'kpi_component_id' => 1,
            'unit_id'          => $src->unit_id,
            'context'          => 'slip_gaji',
            'target_value'     => $src->target_value,
            'batas_awal'       => $src->batas_awal,
            'batas_kedua'      => $src->batas_kedua,
            'batas_ketiga'     => $src->batas_ketiga,
            'batas_keempat'    => $src->batas_keempat,
            'effective_from'   => '2024-01-01',
        ]);
        echo "seed slip_gaji omset target unit {$src->unit_id}\n";
    }
}

// 2. CUSTOMER_COUNT threshold rows (atas_customer) — non-gaji context only.
//    Context 'gaji' memakai target_value default row (130/118/210/118).
$thresholds = [
    'penilaian_kinerja' => [1 => 220, 2 => 180, 3 => 350, 4 => 250],
    'slip_gaji'         => [1 => 220, 2 => 180, 3 => 350, 4 => 250],
];
foreach ($thresholds as $ctx => $perUnit) {
    foreach ($perUnit as $unit => $val) {
        $exists = $db->table('kpi_targets')
            ->where('kpi_component_id', 3)
            ->where('unit_id', $unit)
            ->where('context', $ctx)
            ->where('period_type', 'annual')
            ->countAllResults();
        if (!$exists) {
            $db->table('kpi_targets')->insert([
                'kpi_component_id' => 3,
                'unit_id'          => $unit,
                'context'          => $ctx,
                'target_value'     => $val,
                'period_type'      => 'annual',
                'effective_from'   => '2024-01-01',
            ]);
            echo "seed customer threshold ctx=$ctx unit=$unit val=$val\n";
        }
    }
}

echo "DONE\n";
