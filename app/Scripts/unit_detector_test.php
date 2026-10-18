<?php
/**
 * Uji penebak unit dari nama campaign.
 *
 * Usage: php74 app/Scripts/unit_detector_test.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/Config/Paths.php';
use Config\Paths;
$paths = new Paths();
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
require_once SYSTEMPATH . 'bootstrap.php';

use App\Services\Marketing\UnitDetector;

$fail = 0;
$pass = 0;

function ok($label, $cond, $detail = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}  " . ($detail !== '' ? "→ {$detail}" : '') . "\n";
    }
}

echo "Uji penebak unit\n";
echo "------------------------------------------------------------\n";

ok('Prob → 1', UnitDetector::detect('iClear Prob 76 - CTWA') === 1);
ok('Probolinggo → 1', UnitDetector::detect('ICLEAR PROBOLINGGO 01') === 1);
ok('Jember → 2', UnitDetector::detect('iClear Jember 64 - CTWA') === 2);
ok('Banyuwangi → 3', UnitDetector::detect('iclear banyuwangi 60') === 3);
ok('Bwi → 3', UnitDetector::detect('iClear Bwi 60') === 3);
ok('Pandaan → 4', UnitDetector::detect('iClear Pandaan 09') === 4);
ok('Genteng → 5', UnitDetector::detect('iClear Genteng 04') === 5);
ok('tanpa cabang → Probolinggo', UnitDetector::detect('Loker Talent iClear') === 1);
ok('Raja Casing → Probolinggo', UnitDetector::detect('Raja Casing') === 1);
ok('nama kosong → Probolinggo', UnitDetector::detect('') === 1);
ok('Banyuwangi menang atas Prob', UnitDetector::detect('Loker Talent iClear Banyuwangi') === 3);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
