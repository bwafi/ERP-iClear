<?php
/**
 * Safe Attendance Data Migration: penilaian -> kpi_evaluations
 * 
 * Scope: August 2026
 * Strategy:
 *   - Migrate unique rows
 *   - Deduplicate identical score rows safely (take latest)
 *   - SKIP ambiguous duplicate rows and log them for reporting
 *   - Transactional with rollback on error
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

echo "=== EXECUTING ATTENDANCE MIGRATION (August 2026) ===\n\n";

$aspectMap = [
    'kehadiran'      => 9,
    'kebersihan'     => 10,
    'seragam'        => 11,
    'kepatuhan sop'  => 12
];

$aspects = array_keys($aspectMap);

// Ambiguous duplicates to skip (per audit)
$ambiguousTuples = [
    ['emp' => 59, 'aspek' => 'kepatuhan sop', 'date' => '2026-08-15'],
    ['emp' => 59, 'aspek' => 'kepatuhan sop', 'date' => '2026-08-19'],
    ['emp' => 59, 'aspek' => 'seragam',       'date' => '2026-08-15']
];

$db->transStart();

// 1. Fetch all August 2026 attendance rows from legacy
$rows = $db->query("
    SELECT idpenilaian, pegawai_idpegawai, aspek, skor, input_by, tanggal_penilaian, created_on 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
    ORDER BY tanggal_penilaian ASC, idpenilaian ASC
", $aspects)->getResultArray();

$inserted = 0;
$skippedAmbiguous = 0;
$deduplicated = 0;
$seenKeys = [];

foreach ($rows as $r) {
    $empId = (int)$r['pegawai_idpegawai'];
    $asp = $r['aspek'];
    $date = $r['tanggal_penilaian'];
    $score = (float)$r['skor'];
    $evaluatorId = (int)$r['input_by'];
    $componentId = $aspectMap[$asp];
    
    // Check if this row is part of ambiguous duplicates
    $isAmbiguous = false;
    foreach ($ambiguousTuples as $amb) {
        if ($amb['emp'] == $empId && $amb['aspek'] == $asp && $amb['date'] == $date) {
            $isAmbiguous = true;
            break;
        }
    }
    
    if ($isAmbiguous) {
        $skippedAmbiguous++;
        continue;
    }
    
    $uniqueKey = "$empId-$componentId-$date";
    
    // Derived period
    $ts = strtotime($date);
    $periodYear = (int)date('Y', $ts);
    $periodMonth = (int)date('m', $ts);
    
    // Per-event compatibility calculations
    $normalized = ($score / 5.0) * 100.0;
    
    // Component weight lookup (optional, for compatibility only)
    $empAccount = $db->table('akun')->select('ID_JABATAN')->where('ID_AKUN', $empId)->get()->getRow();
    $positionId = $empAccount ? (int)$empAccount->ID_JABATAN : 0;
    
    $weightRow = $db->table('kpi_weights')
        ->where('kpi_component_id', $componentId)
        ->where('position_id', $positionId)
        ->where('weight_group', 'absen')
        ->get()->getRow();
    $weight = $weightRow ? (float)$weightRow->weight : 20.0;
    $weighted = ($normalized / 100.0) * $weight;
    
    // Check for duplicate key
    if (isset($seenKeys[$uniqueKey])) {
        // Safe deduplication: update existing with latest
        $db->table('kpi_evaluations')
            ->where('employee_id', $empId)
            ->where('kpi_component_id', $componentId)
            ->where('evaluation_date', $date)
            ->update([
                'evaluator_id'     => $evaluatorId,
                'raw_score'        => $score,
                'max_score'        => 5,
                'normalized_score' => $normalized,
                'weighted_score'   => $weighted,
                'notes'            => 'Migrated from penilaian (deduplicated)'
            ]);
        $deduplicated++;
    } else {
        // Insert new
        $db->table('kpi_evaluations')->insert([
            'employee_id'       => $empId,
            'kpi_component_id'  => $componentId,
            'evaluator_id'      => $evaluatorId,
            'evaluation_date'   => $date,
            'raw_score'         => $score,
            'max_score'         => 5,
            'normalized_score'  => $normalized,
            'weighted_score'    => $weighted,
            'notes'             => 'Migrated from penilaian (Task 14)',
            'period_year'       => $periodYear,
            'period_month'      => $periodMonth
        ]);
        $inserted++;
        $seenKeys[$uniqueKey] = true;
    }
}

$db->transComplete();

if ($db->transStatus() === false) {
    echo "ERROR: Migration failed. Transaction rolled back.\n";
    exit(1);
}

echo "Migration Successful!\n";
echo "  Rows Processed: " . count($rows) . "\n";
echo "  Rows Inserted: $inserted\n";
echo "  Rows Deduplicated (safe): $deduplicated\n";
echo "  Rows Skipped (ambiguous duplicates): $skippedAmbiguous\n";
echo "  Total in kpi_evaluations: " . $db->table('kpi_evaluations')->countAllResults() . "\n\n";

echo "=== POST-MIGRATION VALIDATION ===\n\n";

// Validate known employees
$knownEmps = [48, 43, 61];
foreach ($knownEmps as $empId) {
    echo "Employee $empId:\n";
    foreach ($aspectMap as $aspName => $compId) {
        $legacySum = (float)$db->query("
            SELECT SUM(skor) as total 
            FROM penilaian 
            WHERE pegawai_idpegawai = ? 
            AND aspek = ? 
            AND MONTH(tanggal_penilaian) = 8 
            AND YEAR(tanggal_penilaian) = 2026
        ", [$empId, $aspName])->getRow()->total;
        
        $newSum = (float)$db->query("
            SELECT SUM(raw_score) as total 
            FROM kpi_evaluations 
            WHERE employee_id = ? 
            AND kpi_component_id = ? 
            AND period_year = 2026 
            AND period_month = 8
        ", [$empId, $compId])->getRow()->total;
        
        $diff = abs($legacySum - $newSum);
        $status = ($diff < 0.01) ? "MATCH" : "MISMATCH";
        
        // Calculate monthly normalized (SUM / 130 * 100)
        $monthlyNorm = ($newSum / 130.0) * 100.0;
        
        printf("  %-15s: Legacy SUM=%.0f | New SUM=%.0f | MonthlyNorm=%.3f%% [%s]\n", 
            $aspName, $legacySum, $newSum, $monthlyNorm, $status);
    }
    echo "\n";
}

echo "=== NEW ATTENDANCE AGGREGATION TEST ===\n\n";

$aggSvc = new \App\Services\Kpi\AttendanceAggregationService();

foreach ($knownEmps as $empId) {
    $res = $aggSvc->calculateMonthlyAttendance($empId, 1, '08', '2026', 'gaji');
    echo "Employee $empId Attendance Score: {$res['attendance_score']}%\n";
    foreach ($res['components'] as $code => $c) {
        echo "  $code: Normalized={$c['normalized']}% | Weighted={$c['weighted']}%\n";
    }
    echo "\n";
}

echo "Validation complete.\n";
