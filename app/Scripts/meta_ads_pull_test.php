<?php
/**
 * Uji layanan tarik Meta Ads → Performa Ads (Task 3).
 *
 * Memakai klien palsu (tanpa panggilan jaringan). Periode uji dedikasi
 * 12/2099 dan nama campaign '[TEST]' supaya tidak mengganggu data produksi;
 * semua data uji dihapus di akhir.
 *
 * Usage: php74 app/Scripts/meta_ads_pull_test.php
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
use App\Services\Meta\MetaAdsPullService;

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

/** Klien palsu: mengembalikan data tetap, tanpa jaringan. */
class FakeMetaClient extends MetaAdsClient
{
    private array $rows;
    private array $camps;

    public function __construct(array $rows, array $camps)
    {
        $this->rows  = $rows;
        $this->camps = $camps;
    }

    public function fetchCampaignInsights(string $since, string $until): array
    {
        return [
            'rows' => $this->rows,
            'rate' => [
                'call_count' => 3, 'total_time' => 1, 'total_cputime' => 1,
                'app_id_util_pct' => 0.01, 'acc_id_util_pct' => 0,
                'estimated_time_to_regain_access' => 0, 'tier' => 'development_access',
            ],
        ];
    }

    public function fetchCampaigns(): array
    {
        return $this->camps;
    }
}

function insightRow(string $metaId, string $tanggal, float $spend): array
{
    return [
        'campaign_id' => $metaId, 'campaign_name' => "[TEST] {$metaId}", 'date' => $tanggal,
        'spend' => $spend, 'reach' => 10, 'frequency' => 1.0, 'impressions' => 100,
        'link_clicks' => 5, 'conversations' => 1,
    ];
}

$db    = \Config\Database::connect();
$bulan = 12;
$tahun = 2099;
$tgl   = '2099-12-01';

echo "Uji layanan tarik Meta Ads\n";
echo "------------------------------------------------------------\n";

ok('kelas MetaAdsPullService ada', class_exists(MetaAdsPullService::class));

if (!class_exists(MetaAdsPullService::class)) {
    echo "\n============================================================\n";
    echo "PASS: {$pass}   FAIL: {$fail}\n";
    echo "============================================================\n";
    exit($fail > 0 ? 1 : 0);
}

// ── Bersihkan sisa uji sebelumnya ───────────────────────────────────
$db->query("DELETE FROM marketing_ads_performance WHERE period_year = 2099");
$db->query("DELETE FROM marketing_campaigns WHERE nama LIKE '[TEST]%'");
$db->query("DELETE FROM meta_label_objektif WHERE objective = 'OUTCOME_SALES'");

$MC = new \App\Models\ModelMarketingCampaign();
$id1 = (int)$MC->insert(['nama' => '[TEST] M1', 'status' => 'draft', 'period_month' => $bulan, 'period_year' => $tahun, 'meta_campaign_id' => 'META_TEST_1', 'unit_id' => null]);
$id2 = (int)$MC->insert(['nama' => '[TEST] M2', 'status' => 'draft', 'period_month' => $bulan, 'period_year' => $tahun, 'meta_campaign_id' => 'META_TEST_2', 'unit_id' => null]);
$id3 = (int)$MC->insert(['nama' => '[TEST] M3', 'status' => 'draft', 'period_month' => $bulan, 'period_year' => $tahun, 'meta_campaign_id' => 'META_TEST_3', 'unit_id' => null]);

// M2: baris MANUAL yang sudah ada → harus ditimpa angka Meta.
$db->table('marketing_ads_performance')->insert([
    'period_month' => $bulan, 'period_year' => $tahun, 'tanggal' => $tgl,
    'channel_id' => null, 'unit_id' => null, 'campaign_id' => $id2,
    'amount' => 777, 'sumber' => 'manual', 'note' => '[TEST]',
]);
// M3: baris hasil tarikan sebelumnya; kampanye lalu pause → tidak boleh hilang.
$db->table('marketing_ads_performance')->insert([
    'period_month' => $bulan, 'period_year' => $tahun, 'tanggal' => '2099-12-03',
    'channel_id' => null, 'unit_id' => null, 'campaign_id' => $id3,
    'amount' => 500, 'sumber' => 'meta', 'note' => '[TEST]',
]);

$camps = [
    'META_TEST_1' => ['daily_budget' => 100000.0, 'objective' => 'OUTCOME_SALES', 'optimization_goal' => 'CONVERSATIONS', 'destination_type' => 'WHATSAPP'],
    'META_TEST_2' => ['daily_budget' => 100000.0, 'objective' => 'OUTCOME_SALES', 'optimization_goal' => 'CONVERSATIONS', 'destination_type' => 'WHATSAPP'],
];

// ── Tarikan 1 ───────────────────────────────────────────────────────
$rows1 = [insightRow('META_TEST_1', $tgl, 100.0), insightRow('META_TEST_2', $tgl, 150.0), insightRow('999_UNMAPPED', $tgl, 50.0)];
$r1    = (new MetaAdsPullService(new FakeMetaClient($rows1, $camps)))->pull($tgl, $tgl);

$m1 = $db->table('marketing_ads_performance')->where(['period_year' => $tahun, 'campaign_id' => $id1])->get()->getResultArray();
$m2 = $db->table('marketing_ads_performance')->where(['period_year' => $tahun, 'campaign_id' => $id2])->get()->getRowArray();

ok('M1 tersimpan satu baris', count($m1) === 1, (string)count($m1));
ok('M1 amount = spend Meta', isset($m1[0]) && (float)$m1[0]['amount'] === 100.0, (string)($m1[0]['amount'] ?? '-'));
ok('M1 klik = link_clicks', isset($m1[0]) && (int)$m1[0]['klik'] === 5);
ok('M1 hasil = percakapan', isset($m1[0]) && (int)$m1[0]['hasil'] === 1);
ok('M1 sumber = meta', isset($m1[0]) && $m1[0]['sumber'] === 'meta');
ok('M1 ditarik_pada terisi', !empty($m1[0]['ditarik_pada'] ?? null));
ok('M1 periode diambil dari tanggal insight', isset($m1[0]) && (int)$m1[0]['period_month'] === 12 && (int)$m1[0]['period_year'] === 2099);

ok('label objective jatuh ke kode mentah, tidak kosong', ($m1[0]['objective'] ?? '') === 'OUTCOME_SALES / CONVERSATIONS / WHATSAPP', (string)($m1[0]['objective'] ?? '-'));

ok('baris manual ditimpa angka Meta', is_array($m2) && $m2['sumber'] === 'meta' && (float)$m2['amount'] === 150.0, (string)($m2['amount'] ?? '-'));

ok('kampanye tanpa peta masuk daftar', in_array('999_UNMAPPED', $r1['tanpa_peta'] ?? [], true));
$unmapped = (int)$db->query("SELECT COUNT(*) c FROM marketing_ads_performance WHERE period_year = 2099 AND note = '[TEST]' AND campaign_id IS NULL")->getRow()->c;
ok('tidak ada baris dibuat untuk kampanye tanpa peta', $unmapped === 0, (string)$unmapped);
ok('baris tanpa peta terhitung dilewati', ($r1['dilewati'] ?? 0) >= 1, (string)($r1['dilewati'] ?? '-'));

$m3 = $db->table('marketing_ads_performance')->where(['period_year' => $tahun, 'campaign_id' => $id3])->get()->getResultArray();
ok('baris lama kampanye pause tidak dihapus', count($m3) === 1 && $m3[0]['tanggal'] === '2099-12-03', json_encode(array_column($m3, 'tanggal')));
$nol = (int)$db->query("SELECT COUNT(*) c FROM marketing_ads_performance WHERE period_year = 2099 AND campaign_id = {$id3} AND amount = 0")->getRow()->c;
ok('tidak menulis baris nol palsu', $nol === 0, (string)$nol);

ok('hasil tarikan melaporkan baru/diperbarui', ($r1['baru'] ?? -1) === 1 && ($r1['diperbarui'] ?? -1) === 1, json_encode($r1));
ok('log tarikan tersimpan', !empty($r1['log_id']));

// ── Tarikan 2: angka Meta berubah → harus UPDATE, bukan baris kedua ──
$rows2 = [insightRow('META_TEST_1', $tgl, 200.0), insightRow('META_TEST_2', $tgl, 150.0)];
$r2    = (new MetaAdsPullService(new FakeMetaClient($rows2, $camps)))->pull($tgl, $tgl);

$cnt = (int)$db->query("SELECT COUNT(*) c FROM marketing_ads_performance WHERE period_year = 2099 AND campaign_id = {$id1}")->getRow()->c;
$amt = (float)$db->query("SELECT amount FROM marketing_ads_performance WHERE period_year = 2099 AND campaign_id = {$id1}")->getRow()->amount;
ok('tarikan kedua tidak menggandakan baris', $cnt === 1, (string)$cnt);
ok('angka terbarui pada tarikan kedua', $amt === 200.0, (string)$amt);
ok('tarikan kedua tidak membuat baris baru', ($r2['baru'] ?? -1) === 0, json_encode($r2));

// ── Dry run: tidak menulis apa pun ──────────────────────────────────
$rows3 = [insightRow('META_TEST_1', $tgl, 999.0)];
$r3    = (new MetaAdsPullService(new FakeMetaClient($rows3, $camps)))->pull($tgl, $tgl, true);
$amtAfterDry = (float)$db->query("SELECT amount FROM marketing_ads_performance WHERE period_year = 2099 AND campaign_id = {$id1}")->getRow()->amount;
ok('dry-run tidak mengubah data', $amtAfterDry === 200.0, (string)$amtAfterDry);
ok('dry-run tidak menulis log', ($r3['log_id'] ?? null) === null);

// ── Bersihkan ───────────────────────────────────────────────────────
$db->query("DELETE FROM marketing_ads_performance WHERE period_year = 2099");
$db->query("DELETE FROM marketing_campaigns WHERE nama LIKE '[TEST]%'");
$db->query("DELETE FROM meta_label_objektif WHERE objective = 'OUTCOME_SALES'");
ok('data uji dibersihkan', (int)$db->query("SELECT COUNT(*) c FROM marketing_campaigns WHERE nama LIKE '[TEST]%'")->getRow()->c === 0);

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
