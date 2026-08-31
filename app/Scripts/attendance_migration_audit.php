<?php
/**
 * Attendance Data Migration Audit Script
 * 
 * Purpose: Audit legacy penilaian table before migrating to kpi_evaluations
 * Scope: August 2026 attendance data
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

echo "=== STEP 1: SOURCE DATA AUDIT (August 2026) ===\n\n";

$aspects = ['kehadiran', 'kebersihan', 'seragam', 'kepatuhan sop'];

// Total attendance rows
$total = $db->query("
    SELECT COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
", $aspects)->getRow()->cnt;
echo "Total attendance rows (Aug 2026): $total\n\n";

// By aspek
echo "--- Rows by Aspek ---\n";
foreach ($aspects as $asp) {
    $cnt = $db->query("
        SELECT COUNT(*) as cnt 
        FROM penilaian 
        WHERE aspek = ? 
        AND MONTH(tanggal_penilaian) = 8 
        AND YEAR(tanggal_penilaian) = 2026
    ", [$asp])->getRow()->cnt;
    echo "$asp: $cnt\n";
}

// By employee
echo "\n--- Rows by Employee (Top 10) ---\n";
$byEmp = $db->query("
    SELECT pegawai_idpegawai, COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
    GROUP BY pegawai_idpegawai 
    ORDER BY cnt DESC 
    LIMIT 10
", $aspects)->getResultArray();
foreach ($byEmp as $e) {
    echo "Emp {$e['pegawai_idpegawai']}: {$e['cnt']} rows\n";
}

// Duplicates
echo "\n--- Duplicate Check ---\n";
$dups = $db->query("
    SELECT pegawai_idpegawai, aspek, tanggal_penilaian, COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
    GROUP BY pegawai_idpegawai, aspek, tanggal_penilaian 
    HAVING cnt > 1
", $aspects)->getResultArray();
echo "Duplicate (emp, aspek, date) tuples: " . count($dups) . "\n";
if (count($dups) > 0) {
    echo "Sample duplicates (first 5):\n";
    foreach (array_slice($dups, 0, 5) as $d) {
        echo "  Emp {$d['pegawai_idpegawai']}, {$d['aspek']}, {$d['tanggal_penilaian']}: {$d['cnt']} occurrences\n";
    }
}

// Invalid scores
echo "\n--- Invalid Scores ---\n";
$invalid = $db->query("
    SELECT COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
    AND (skor < 1 OR skor > 5)
", $aspects)->getRow()->cnt;
echo "Rows with score < 1 or > 5: $invalid\n";

// Missing employee IDs
echo "\n--- Missing Employee IDs ---\n";
$missing = $db->query("
    SELECT COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
    AND (pegawai_idpegawai IS NULL OR pegawai_idpegawai = '' OR pegawai_idpegawai = '0')
", $aspects)->getRow()->cnt;
echo "Rows with missing/zero employee ID: $missing\n";

// Missing dates
echo "\n--- Missing Dates ---\n";
$missingDate = $db->query("
    SELECT COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND tanggal_penilaian IS NULL
", $aspects)->getRow()->cnt;
echo "Rows with missing date: $missingDate\n";

echo "\n=== STEP 2: COMPONENT MAPPING ===\n\n";
$components = $db->table('kpi_components')->whereIn('code', ['KEHADIRAN', 'KEBERSIHAN', 'SERAGAM', 'KEPATUHAN_SOP'])->get()->getResultArray();
$componentMap = [];
foreach ($components as $c) {
    echo "{$c['code']} (ID: {$c['id']})\n";
    $componentMap[$c['code']] = $c['id'];
}

echo "\n=== STEP 3: EVALUATOR MAPPING (Sample) ===\n\n";
$evaluators = $db->query("
    SELECT DISTINCT input_by, COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
    GROUP BY input_by 
    ORDER BY cnt DESC
", $aspects)->getResultArray();
echo "Unique evaluators: " . count($evaluators) . "\n";
foreach (array_slice($evaluators, 0, 5) as $e) {
    echo "  Evaluator {$e['input_by']}: {$e['cnt']} evaluations\n";
}

echo "\n=== PRE-MIGRATION SNAPSHOT ===\n\n";
echo "Known employees validation:\n";
$knownEmps = [48, 43, 61];
foreach ($knownEmps as $empId) {
    echo "\nEmployee $empId:\n";
    foreach ($aspects as $asp) {
        $sum = $db->query("
            SELECT SUM(skor) as total 
            FROM penilaian 
            WHERE pegawai_idpegawai = ? 
            AND aspek = ? 
            AND MONTH(tanggal_penilaian) = 8 
            AND YEAR(tanggal_penilaian) = 2026
        ", [$empId, $asp])->getRow()->total;
        if ($sum > 0) {
            echo "  $asp: SUM = $sum\n";
        }
    }
}

echo "\nAudit complete.\n";
