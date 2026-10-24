<?php
/**
 * Uji klien Meta Ads dengan HTTP palsu (MockHandler).
 *
 * Memastikan parsing insight, agregasi actions, pembacaan header rate limit,
 * dan penanganan token kosong / 401 — tanpa memanggil Meta.
 *
 * Usage: php74 app/Scripts/meta_ads_client_test.php
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

function clientWith(array $responses): \App\Services\Meta\MetaAdsClient
{
    $mock  = new MockHandler($responses);
    $stack = HandlerStack::create($mock);

    return new \App\Services\Meta\MetaAdsClient(new Client(['handler' => $stack]));
}

echo "Uji klien Meta Ads\n";
echo "------------------------------------------------------------\n";

// ── Kelas ada ──────────────────────────────────────────────────────
$hasClient = class_exists(\App\Services\Meta\MetaAdsClient::class);
ok('kelas MetaAdsClient ada', $hasClient);
ok('kelas MetaAdsException ada', class_exists(\App\Services\Meta\MetaAdsException::class));

if (!$hasClient) {
    echo "\n============================================================\n";
    echo "PASS: {$pass}   FAIL: {$fail}\n";
    echo "============================================================\n";
    exit($fail > 0 ? 1 : 0);
}

// ── 1. Response sukses ─────────────────────────────────────────────
$body = json_encode(['data' => [
    [
        'campaign_id'   => '120251146211920189',
        'campaign_name' => 'iClear Prob 76 - CTWA',
        'date_start'    => '2026-10-01',
        'date_stop'     => '2026-10-01',
        'spend'         => '70836',
        'reach'         => '8850',
        'frequency'     => '1.35',
        'impressions'   => '11909',
        'actions'       => [
            ['action_type' => 'link_click', 'value' => '67'],
            ['action_type' => 'onsite_conversion.messaging_conversation_started_7d', 'value' => '12'],
            ['action_type' => 'post_engagement', 'value' => '999'],
        ],
    ],
    [
        'campaign_id'   => '999',
        'campaign_name' => 'Lain',
        'date_start'    => '2026-10-01',
        'date_stop'     => '2026-10-01',
        'spend'         => '100',
        'reach'         => '10',
        'frequency'     => '1.0',
        'impressions'   => '10',
        'actions'       => [],
    ],
]]);

$headers = [
    'Content-Type'                => 'application/json',
    'x-business-use-case-usage'   => '{"1134662688368218":[{"type":"ads_insights","call_count":1,"total_cputime":1,"total_time":1,"estimated_time_to_regain_access":0,"ads_api_access_tier":"development_access"}]}',
    'x-fb-ads-insights-throttle'  => '{"app_id_util_pct":0.01,"acc_id_util_pct":0,"ads_api_access_tier":"development_access"}',
];

$res  = clientWith([new Response(200, $headers, $body)])->fetchCampaignInsights('2026-10-01', '2026-10-01');
$rows = $res['rows'] ?? [];

ok('mengembalikan 2 baris', count($rows) === 2, (string)count($rows));
ok('campaign_id terparsing', ($rows[0]['campaign_id'] ?? null) === '120251146211920189');
ok('campaign_name terparsing', ($rows[0]['campaign_name'] ?? null) === 'iClear Prob 76 - CTWA');
ok('tanggal terparsing', ($rows[0]['date'] ?? null) === '2026-10-01');
ok('spend terparsing', ($rows[0]['spend'] ?? null) === 70836.0);
ok('reach terparsing', ($rows[0]['reach'] ?? null) === 8850);
ok('frequency terparsing', ($rows[0]['frequency'] ?? null) === 1.35);
ok('impressions terparsing', ($rows[0]['impressions'] ?? null) === 11909);
ok('link_clicks diambil dari actions link_click', ($rows[0]['link_clicks'] ?? null) === 67);
ok('conversations diambil dari actions messaging_conversation_started_7d', ($rows[0]['conversations'] ?? null) === 12);
ok('baris tanpa actions → klik & percakapan 0', ($rows[1]['link_clicks'] ?? null) === 0 && ($rows[1]['conversations'] ?? null) === 0);

ok('rate limit terbaca (acc_id_util_pct)', ($res['rate']['acc_id_util_pct'] ?? null) === 0);
ok('rate limit terbaca (app_id_util_pct)', ($res['rate']['app_id_util_pct'] ?? null) === 0.01);
ok('rate limit terbaca (call_count)', ($res['rate']['call_count'] ?? null) === 1);
ok('rate limit terbaca (estimated_time_to_regain_access)', ($res['rate']['estimated_time_to_regain_access'] ?? null) === 0);
ok('tier terbaca', ($res['rate']['tier'] ?? null) === 'development_access');

// ── 2. Token kosong → MetaAdsException, token tidak bocor ──────────
$savedEnv = $_ENV['META_ADS_TOKEN'] ?? null;
$savedSrv = $_SERVER['META_ADS_TOKEN'] ?? null;
$secret   = (string)env('META_ADS_TOKEN');
unset($_ENV['META_ADS_TOKEN'], $_SERVER['META_ADS_TOKEN']);
putenv('META_ADS_TOKEN=');

$errMsg = '';
try {
    clientWith([new Response(200, $headers, $body)])->fetchCampaignInsights('2026-10-01', '2026-10-01');
    $errMsg = null;
} catch (\App\Services\Meta\MetaAdsException $e) {
    $errMsg = $e->getMessage();
} catch (\Throwable $e) {
    $errMsg = 'SALAH-KELAS: ' . get_class($e) . ': ' . $e->getMessage();
}
ok('token kosong → MetaAdsException', is_string($errMsg) && strpos($errMsg, 'SALAH-KELAS') === false, (string)$errMsg);
ok('pesan token kosong menyebut META_ADS_TOKEN', is_string($errMsg) && strpos($errMsg, 'META_ADS_TOKEN') !== false, (string)$errMsg);
ok(
    'pesan token kosong TIDAK memuat nilai token',
    $secret === '' || is_string($errMsg) === false || strpos((string)$errMsg, $secret) === false
);

if ($savedEnv !== null) {
    $_ENV['META_ADS_TOKEN'] = $savedEnv;
}
if ($savedSrv !== null) {
    $_SERVER['META_ADS_TOKEN'] = $savedSrv;
}
putenv('META_ADS_TOKEN=' . $secret);

// ── 3. HTTP 401 → MetaAdsException memuat kode & pesan Meta ────────
$errBody = json_encode(['error' => [
    'message'   => 'Invalid OAuth access token.',
    'type'      => 'OAuthException',
    'code'      => 190,
    'fbtrace_id' => 'TRACE123',
]]);

$err401 = '';
try {
    clientWith([new Response(401, ['Content-Type' => 'application/json'], $errBody)])
        ->fetchCampaignInsights('2026-10-01', '2026-10-01');
    $err401 = null;
} catch (\App\Services\Meta\MetaAdsException $e) {
    $err401 = $e->getMessage();
} catch (\Throwable $e) {
    $err401 = 'SALAH-KELAS: ' . get_class($e) . ': ' . $e->getMessage();
}
ok('HTTP 401 → MetaAdsException', is_string($err401) && strpos($err401, 'SALAH-KELAS') === false, (string)$err401);
ok('pesan 401 memuat kode Meta (190)', is_string($err401) && strpos($err401, '190') !== false, (string)$err401);
ok('pesan 401 memuat pesan Meta', is_string($err401) && strpos($err401, 'Invalid OAuth access token') !== false, (string)$err401);
ok('pesan 401 TIDAK memuat token', $secret === '' || is_string($err401) === false || strpos((string)$err401, $secret) === false);

/*
 * ── 4. Peringatan TLS OpenSSL tidak boleh menggagalkan tarikan ───────
 *
 * Saat OpenSSL menutup koneksi tanpa close_notify, PHP memunculkan
 * "fread(): SSL operation failed with code 1 ... unexpected eof while reading"
 * SETELAH seluruh body diterima. Di CLI, CodeIgniter mengubah peringatan PHP
 * menjadi ErrorException, sehingga Guzzle menganggap pembacaan gagal padahal
 * datanya utuh — dan seluruh tarikan batal.
 *
 * Handler di bawah meniru perilaku CodeIgniter di CLI.
 */
function denganHandlerCI4(callable $fn)
{
    set_error_handler(function ($severity, $message, $file = '', $line = 0) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        return $fn();
    } finally {
        restore_error_handler();
    }
}

function clientDenganPeringatan(string $peringatan, array $headers, string $body): \App\Services\Meta\MetaAdsClient
{
    $mock = new MockHandler([
        function ($request, $options) use ($peringatan, $headers, $body) {
            trigger_error($peringatan, E_USER_WARNING);

            return new Response(200, $headers, $body);
        },
    ]);

    return new \App\Services\Meta\MetaAdsClient(new Client(['handler' => HandlerStack::create($mock)]));
}

$peringatanTls = 'fread(): SSL operation failed with code 1. OpenSSL Error messages: '
    . 'error:0A000126:SSL routines::unexpected eof while reading';

$errTls = null;
$resTls = null;
try {
    $resTls = denganHandlerCI4(fn() => clientDenganPeringatan($peringatanTls, $headers, $body)
        ->fetchCampaignInsights('2026-10-01', '2026-10-01'));
} catch (\Throwable $e) {
    $errTls = get_class($e) . ': ' . $e->getMessage();
}
ok(
    'peringatan TLS OpenSSL tidak menggagalkan tarikan',
    $errTls === null && count($resTls['rows'] ?? []) === 2,
    (string)$errTls
);

// Peringatan lain TIDAK boleh ikut ditelan — kalau bukan penyebab yang dikenal,
// harus tetap terlihat.
$errLain = null;
try {
    denganHandlerCI4(fn() => clientDenganPeringatan('ada yang rusak lain', $headers, $body)
        ->fetchCampaignInsights('2026-10-01', '2026-10-01'));
} catch (\Throwable $e) {
    $errLain = get_class($e);
}
ok('peringatan lain tetap dilaporkan (tidak ditelan)', $errLain === \ErrorException::class, (string)$errLain);

/*
 * ── 5. Kegagalan jaringan diulang, lalu menyerah dengan pesan jelas ──
 *
 * Transport di sini memakai stream PHP (ext-curl tidak aktif) dan sesekali
 * gagal menyambung. GET bersifat idempoten, jadi aman diulang — tapi harus
 * ada batasnya.
 */
function connectGagal(): \GuzzleHttp\Exception\ConnectException
{
    return new \GuzzleHttp\Exception\ConnectException(
        'koneksi putus',
        new \GuzzleHttp\Psr7\Request('GET', 'https://graph.facebook.com/x')
    );
}

$mockUlang = new MockHandler([connectGagal(), connectGagal(), new Response(200, $headers, $body)]);
$errUlang  = null;
$resUlang  = [];
try {
    $resUlang = (new \App\Services\Meta\MetaAdsClient(new Client(['handler' => HandlerStack::create($mockUlang)])))
        ->fetchCampaignInsights('2026-10-01', '2026-10-01');
} catch (\Throwable $e) {
    $errUlang = get_class($e) . ': ' . $e->getMessage();
}
ok('gagal jaringan diulang sampai berhasil', $errUlang === null && count($resUlang['rows'] ?? []) === 2, (string)$errUlang);

$mockHabis = new MockHandler([connectGagal(), connectGagal(), connectGagal(), new Response(200, $headers, $body)]);
$errHabis  = null;
try {
    (new \App\Services\Meta\MetaAdsClient(new Client(['handler' => HandlerStack::create($mockHabis)])))
        ->fetchCampaignInsights('2026-10-01', '2026-10-01');
} catch (\Throwable $e) {
    $errHabis = $e;
}
ok('menyerah setelah batas percobaan', $errHabis instanceof \App\Services\Meta\MetaAdsException, get_class($errHabis ?? new \stdClass()));
ok('batas percobaan = 3 (tidak mengulang tanpa henti)', $mockHabis->count() === 1, 'sisa=' . $mockHabis->count());

/*
 * ── 6. Token lewat header, bukan URL (spec §10) ──────────────────────
 *
 * Token di query string ikut tercatat di URL permintaan; dari sana ia bocor
 * ke pesan exception dan jejak (trace) PHP. URL paging Meta juga membawa
 * access_token sendiri, jadi URL itu harus dibersihkan sebelum dipakai.
 */
$halaman1 = json_encode([
    'data'   => [['campaign_id' => '1', 'campaign_name' => 'A', 'date_start' => '2026-10-01',
                  'spend' => '10', 'reach' => '1', 'frequency' => '1', 'impressions' => '1', 'actions' => []]],
    'paging' => ['next' => 'https://graph.facebook.com/v25.0/act_1/insights?level=campaign&access_token=RAHASIA&after=CURSOR'],
]);
$halaman2 = json_encode(['data' => []]);

$diminta  = [];
$mockUri  = new MockHandler([new Response(200, $headers, $halaman1), new Response(200, $headers, $halaman2)]);
$stackUri = HandlerStack::create($mockUri);
$stackUri->push(function (callable $handler) use (&$diminta) {
    return function ($request, $options) use ($handler, &$diminta) {
        $diminta[] = $request;

        return $handler($request, $options);
    };
});

$resUri = (new \App\Services\Meta\MetaAdsClient(new Client(['handler' => $stackUri])))
    ->fetchCampaignInsights('2026-10-01', '2026-10-01');

ok('paging mengikuti halaman berikutnya', count($diminta) === 2 && count($resUri['rows'] ?? []) === 1, 'permintaan=' . count($diminta));
ok('URL permintaan tidak memuat access_token', strpos((string)$diminta[0]->getUri(), 'access_token') === false, (string)$diminta[0]->getUri());
ok('URL paging dibersihkan dari access_token', strpos((string)$diminta[1]->getUri(), 'access_token') === false, (string)$diminta[1]->getUri());
ok('token TIDAK muncul di URL permintaan mana pun', $secret === '' || (strpos((string)$diminta[0]->getUri(), $secret) === false && strpos((string)$diminta[1]->getUri(), $secret) === false));
ok('parameter lain di URL paging tetap ada', strpos((string)$diminta[1]->getUri(), 'after=CURSOR') !== false, (string)$diminta[1]->getUri());
ok('token dikirim lewat header Authorization', strpos($diminta[0]->getHeaderLine('Authorization'), 'Bearer ') === 0, $diminta[0]->getHeaderLine('Authorization'));

/*
 * ── 7. Batas laju Meta: tunggu lalu ulangi (spec §9) ─────────────────
 */
function batasLajuBody(int $kode = 80004): string
{
    return json_encode(['error' => [
        'message'    => 'There have been too many calls from this ad-account.',
        'type'       => 'OAuthException',
        'code'       => $kode,
        'error_data' => ['estimated_time_to_regain_access' => 1],
    ]]);
}

$errLaju = null;
$resLaju = [];
$klienLaju = new \App\Services\Meta\MetaAdsClient(new Client(['handler' => HandlerStack::create(new MockHandler([
    new Response(429, ['Content-Type' => 'application/json'], batasLajuBody()),
    new Response(200, $headers, $body),
]))]));
$klienLaju->tungguMaks = 0; // jangan benar-benar menunggu di uji
try {
    $resLaju = $klienLaju->fetchCampaignInsights('2026-10-01', '2026-10-01');
} catch (\Throwable $e) {
    $errLaju = get_class($e) . ': ' . $e->getMessage();
}
ok('batas laju ditunggu lalu diulang sampai berhasil', $errLaju === null && count($resLaju['rows'] ?? []) === 2, (string)$errLaju);

$mockLajuHabis = new MockHandler([
    new Response(429, ['Content-Type' => 'application/json'], batasLajuBody()),
    new Response(429, ['Content-Type' => 'application/json'], batasLajuBody()),
    new Response(429, ['Content-Type' => 'application/json'], batasLajuBody()),
]);
$pesanLaju = '';
try {
    $klienHabis = new \App\Services\Meta\MetaAdsClient(new Client(['handler' => HandlerStack::create($mockLajuHabis)]));
    $klienHabis->tungguMaks = 0;
    $klienHabis->fetchCampaignInsights('2026-10-01', '2026-10-01');
} catch (\Throwable $e) {
    $pesanLaju = $e->getMessage();
}
ok('batas laju berhenti setelah batas percobaan', $mockLajuHabis->count() === 0, 'sisa=' . $mockLajuHabis->count());
ok('pesan batas laju bisa ditindaklanjuti', strpos($pesanLaju, 'Batas laju') !== false, $pesanLaju);
ok('pesan batas laju menyebut kode Meta', strpos($pesanLaju, '80004') !== false, $pesanLaju);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
