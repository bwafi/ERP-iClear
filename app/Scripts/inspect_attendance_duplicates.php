<?php
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

$aspects = ['kehadiran', 'kebersihan', 'seragam', 'kepatuhan sop'];

echo "=== DETAILED DUPLICATE INSPECTION ===\n\n";

$dups = $db->query("
    SELECT pegawai_idpegawai, aspek, tanggal_penilaian, COUNT(*) as cnt 
    FROM penilaian 
    WHERE aspek IN (?, ?, ?, ?) 
    AND MONTH(tanggal_penilaian) = 8 
    AND YEAR(tanggal_penilaian) = 2026
    GROUP BY pegawai_idpegawai, aspek, tanggal_penilaian 
    HAVING cnt > 1
", $aspects)->getResultArray();

$identicalCount = 0;
$differingCount = 0;

foreach ($dups as $d) {
    $empId = $d['pegawai_idpegawai'];
    $asp = $d['aspek'];
    $date = $d['tanggal_penilaian'];
    
    $rows = $db->query("
        SELECT idpenilaian, skor, input_by, created_on, updated_on 
        FROM penilaian 
        WHERE pegawai_idpegawai = ? 
        AND aspek = ? 
        AND tanggal_penilaian = ?
        ORDER BY idpenilaian ASC
    ", [$empId, $asp, $date])->getResultArray();
    
    echo "Duplicate: Emp $empId | $asp | $date ({$d['cnt']} rows)\n";
    $firstScore = null;
    $isIdentical = true;
    foreach ($rows as $r) {
        echo "  ID: {$r['idpenilaian']} | Score: {$r['skor']} | InputBy: {$r['input_by']} | Created: {$r['created_on']}\n";
        if ($firstScore === null) {
            $firstScore = $r['skor'];
        } elseif ($firstScore != $r['skor']) {
            $isIdentical = false;
        }
    }
    
    if ($isIdentical) {
        echo "  -> Status: IDENTICAL SCORES (safe deduplication)\n";
        $identicalCount++;
    } else {
        echo "  -> Status: AMBIGUOUS (differing scores)\n";
        $differingCount++;
    }
    echo "\n";
}

echo "Summary: $identicalCount identical score duplicates, $differingCount ambiguous duplicates.\n";
