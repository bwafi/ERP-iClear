<?php
/**
 * Uji perencanaan sync campaign Meta + penjagaan periode.
 *
 * Usage: php74 app/Scripts/meta_campaign_sync_test.php
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

use App\Services\Meta\MetaCampaignSyncService;

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

echo "Uji sync campaign Meta\n";
echo "------------------------------------------------------------\n";

$svc = new MetaCampaignSyncService();

$opsi = [
    ['id' => '111', 'nama' => 'iClear Jember 64', 'spend' => 100.0, 'aktif' => true],
    ['id' => '222', 'nama' => 'Loker Talent iClear', 'spend' => 50.0, 'aktif' => false],
];
$existing = [
    '111' => ['id' => 7, 'unit_id' => 4, 'unit_sumber' => 'manual', 'status' => 'active'],
    '999' => ['id' => 8, 'unit_id' => 1, 'unit_sumber' => 'auto', 'status' => 'active'],
];
$p = $svc->plan($opsi, $existing);

ok('campaign lama → update, bukan create', $p['upsert'][0]['id'] === 7);
ok('unit manual tidak ditimpa', $p['upsert'][0]['unit_id'] === 4);
ok('campaign baru → id null', $p['upsert'][1]['id'] === null);
ok('unit baru ditebak dari nama', $p['upsert'][1]['unit_id'] === 1);
ok('status dari Meta (aktif)', $p['upsert'][0]['status'] === 'active');
ok('status dari Meta (tidak aktif)', $p['upsert'][1]['status'] === 'draft');
ok('campaign tak dilaporkan diturunkan', $p['demote'] === [8], json_encode($p['demote']));

$manual = $svc->plan(
    [['id' => '111', 'nama' => 'iClear Jember 64', 'spend' => 1.0, 'aktif' => false]],
    ['111' => ['id' => 7, 'unit_id' => 4, 'unit_sumber' => 'manual', 'status' => 'active']]
);
ok('unit manual tetap walau status turun', $manual['upsert'][0]['unit_id'] === 4);
ok('status jadi draft', $manual['upsert'][0]['status'] === 'draft');

$auto = $svc->plan(
    [['id' => '111', 'nama' => 'iClear Genteng 04', 'spend' => 1.0, 'aktif' => true]],
    ['111' => ['id' => 7, 'unit_id' => 1, 'unit_sumber' => 'auto', 'status' => 'draft']]
);
ok('unit auto ikut nama baru', $auto['upsert'][0]['unit_id'] === 5);

$threw = false;
try {
    $svc->sync(9, 2026);
} catch (\RuntimeException $e) {
    $threw = true;
}
ok('periode sebelum 2026-10 ditolak', $threw);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
