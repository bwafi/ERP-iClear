<?php
/**
 * Uji skema laporan mingguan + adopsi campaign Oktober.
 *
 * Usage: php74 app/Scripts/marketing_weekly_schema_test.php
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
(new \CodeIgniter\Config\DotEnv(ROOTPATH))->load();

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

echo "Uji skema laporan mingguan\n";
echo "------------------------------------------------------------\n";

$db = \Config\Database::connect();

ok('kolom unit_sumber ada', $db->fieldExists('unit_sumber', 'marketing_campaigns'));
ok('tabel laporan mingguan ada', $db->tableExists('marketing_laporan_mingguan'));

$idx = $db->query(
    "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_laporan_mingguan' AND Non_unique = 0
     GROUP BY INDEX_NAME"
)->getResultArray();
$uniq = array_filter($idx, fn($r) => $r['cols'] === 'period_year,period_month,minggu');
ok('unik per (tahun, bulan, minggu) ada', count($uniq) === 1, json_encode($idx));

$adopsi = [
    '120251146211920189' => 'Prob 76',
    '120251147591300189' => 'Jember 64',
    '120249387385360189' => 'Banyuwangi 60',
    '120251197138500189' => 'Pandaan 09',
    '120251224385770189' => 'Genteng 04',
];
foreach ($adopsi as $metaId => $label) {
    $n = (int)$db->query(
        "SELECT COUNT(*) c FROM marketing_campaigns
         WHERE meta_campaign_id = ? AND period_month = 10 AND period_year = 2026",
        [$metaId]
    )->getRow()->c;
    ok("adopsi Okt: {$label} → {$metaId}", $n === 1, "count={$n}");
}

ok('September tidak tersentuh', (int)$db->query(
    "SELECT COUNT(*) c FROM marketing_campaigns
     WHERE period_year = 2026 AND period_month = 9 AND meta_campaign_id IS NOT NULL"
)->getRow()->c === 0);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
