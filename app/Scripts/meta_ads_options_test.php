<?php
/**
 * Uji daftar pilihan campaign Meta untuk pemetaan di form Campaign.
 *
 * Yang diuji: `MetaAdsClient::fetchCampaignOptions()` — gabungan campaign yang
 * ada belanja pada periode + campaign yang sedang ACTIVE (walau belum ada
 * belanja), spend dijumlahkan per campaign, urut belanja terbesar.
 *
 * Memakai HTTP palsu (MockHandler), tanpa jaringan & tanpa database.
 *
 * Usage: php74 app/Scripts/meta_ads_options_test.php
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
$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

use App\Services\Meta\MetaAdsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

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

function clientWith(array $responses): MetaAdsClient
{
    return new MetaAdsClient(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
}

echo "Uji pilihan campaign Meta\n";
echo "------------------------------------------------------------\n";

$ada = method_exists(MetaAdsClient::class, 'fetchCampaignOptions');
ok('MetaAdsClient::fetchCampaignOptions ada', $ada);

if (!$ada) {
    echo "\n============================================================\n";
    echo "PASS: {$pass}   FAIL: {$fail}\n";
    echo "============================================================\n";
    exit($fail > 0 ? 1 : 0);
}

$headers = ['Content-Type' => 'application/json'];

// Insight: campaign 100 belanja 2 hari (100 + 50 = 150); campaign 200 belanja 300.
$insights = json_encode(['data' => [
    ['campaign_id' => '100', 'campaign_name' => 'Alpha', 'date_start' => '2026-10-01', 'spend' => '100',
     'reach' => '1', 'frequency' => '1', 'impressions' => '1', 'actions' => []],
    ['campaign_id' => '100', 'campaign_name' => 'Alpha', 'date_start' => '2026-10-02', 'spend' => '50',
     'reach' => '1', 'frequency' => '1', 'impressions' => '1', 'actions' => []],
    ['campaign_id' => '200', 'campaign_name' => 'Beta', 'date_start' => '2026-10-01', 'spend' => '300',
     'reach' => '1', 'frequency' => '1', 'impressions' => '1', 'actions' => []],
]]);

// Campaign ACTIVE: 100 (sudah ada belanja) dan 300 (belum ada belanja).
$aktifs = json_encode(['data' => [
    ['id' => '100', 'name' => 'Alpha'],
    ['id' => '300', 'name' => 'Gamma'],
]]);

$opsi = clientWith([
    new Response(200, $headers, $insights),
    new Response(200, $headers, $aktifs),
])->fetchCampaignOptions('2026-10-01', '2026-10-31');

ok('mengembalikan 3 campaign (2 belanja + 1 aktif tanpa belanja)', count($opsi) === 3, (string)count($opsi));
ok(
    'urut belanja terbesar lalu aktif tanpa belanja',
    array_column($opsi, 'id') === ['200', '100', '300'],
    implode(',', array_column($opsi, 'id'))
);

$byId = [];
foreach ($opsi as $o) {
    $byId[$o['id']] = $o;
}

ok('spend dijumlahkan per campaign (100: 100+50=150)', ($byId['100']['spend'] ?? null) == 150.0, var_export($byId['100']['spend'] ?? null, true));
ok('nama campaign ikut terbawa', ($byId['200']['nama'] ?? null) === 'Beta', (string)($byId['200']['nama'] ?? '-'));
ok('campaign berbelanja tapi tidak aktif → aktif=false', ($byId['200']['aktif'] ?? null) === false);
ok('campaign aktif ditandai aktif=true', ($byId['100']['aktif'] ?? null) === true && ($byId['300']['aktif'] ?? null) === true);
ok('campaign aktif tanpa belanja → spend 0', ($byId['300']['spend'] ?? null) == 0.0, var_export($byId['300']['spend'] ?? null, true));

// Tidak ada belanja & tidak ada yang aktif → daftar kosong (bukan error).
$kosong = clientWith([
    new Response(200, $headers, json_encode(['data' => []])),
    new Response(200, $headers, json_encode(['data' => []])),
])->fetchCampaignOptions('2026-10-01', '2026-10-31');
ok('tanpa kampanye apa pun → daftar kosong', $kosong === [], var_export($kosong, true));

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
