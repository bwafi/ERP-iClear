<?php
/**
 * Uji skema & model integrasi Meta Ads (Task 1).
 *
 * Memeriksa kolom/tabel hasil migrasi Meta, fallback label Objektif, dan
 * pemetaan campaign Meta → campaign ERP. Tidak memanggil Meta.
 *
 * Usage: php74 app/Scripts/meta_ads_schema_test.php
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

$db = \Config\Database::connect();

echo "Uji skema & model Meta Ads\n";
echo "------------------------------------------------------------\n";

ok('kolom marketing_campaigns.meta_campaign_id ada', $db->fieldExists('meta_campaign_id', 'marketing_campaigns'));
ok('kolom marketing_campaigns.unit_id ada', $db->fieldExists('unit_id', 'marketing_campaigns'));
ok('kolom marketing_ads_performance.sumber ada', $db->fieldExists('sumber', 'marketing_ads_performance'));
ok('kolom marketing_ads_performance.ditarik_pada ada', $db->fieldExists('ditarik_pada', 'marketing_ads_performance'));
ok('tabel meta_label_objektif ada', $db->tableExists('meta_label_objektif'));
ok('tabel meta_pull_log ada', $db->tableExists('meta_pull_log'));

// ── Model label Objektif: fallback kode mentah kalau belum dipetakan ──
$hasLabelModel = class_exists(\App\Models\ModelMetaLabelObjektif::class);
ok('model ModelMetaLabelObjektif ada', $hasLabelModel);

if ($hasLabelModel) {
    $L = new \App\Models\ModelMetaLabelObjektif();
    ok(
        'labelFor jatuh ke kode mentah (tidak kosong)',
        $L->labelFor('OUTCOME_SALES', 'CONVERSATIONS', 'WHATSAPP') === 'OUTCOME_SALES / CONVERSATIONS / WHATSAPP',
        (string)$L->labelFor('OUTCOME_SALES', 'CONVERSATIONS', 'WHATSAPP')
    );
    ok(
        'labelFor memakai UNDEFINED untuk bagian kosong',
        $L->labelFor('OUTCOME_AWARENESS', 'REACH', null) === 'OUTCOME_AWARENESS / REACH / UNDEFINED',
        (string)$L->labelFor('OUTCOME_AWARENESS', 'REACH', null)
    );
}

// ── Model log tarikan ──
ok('model ModelMetaPullLog ada', class_exists(\App\Models\ModelMetaPullLog::class));

// ── metaMap(): campaign Meta → campaign ERP ──
$hasCampModel = class_exists(\App\Models\ModelMarketingCampaign::class);
ok('model ModelMarketingCampaign ada', $hasCampModel);

if ($hasCampModel) {
    ok('metaMap() bisa dipanggil', method_exists(\App\Models\ModelMarketingCampaign::class, 'metaMap'));

    $M = new \App\Models\ModelMarketingCampaign();
    $metaId = '[TEST]' . substr((string)time(), -6);
    $newId  = $M->insert([
        'nama'         => '[TEST] Campaign Meta',
        'status'       => \App\Models\ModelMarketingCampaign::STATUS_DRAFT,
        'period_month' => 12,
        'period_year'  => 2099,
        'meta_campaign_id' => $metaId,
        'unit_id'      => null,
    ]);

    $map = method_exists($M, 'metaMap') ? $M->metaMap() : [];
    ok(
        'metaMap() memetakan meta_campaign_id → campaign_id',
        isset($map[$metaId]) && (int)$map[$metaId]['campaign_id'] === (int)$newId,
        $metaId
    );
    ok(
        'metaMap() menyertakan unit_id (null bila belum diisi)',
        isset($map[$metaId]) && array_key_exists('unit_id', $map[$metaId]) && $map[$metaId]['unit_id'] === null
    );

    if ($newId) {
        $M->delete($newId);
    }
    ok(
        'data uji [TEST] dibersihkan',
        (int)$db->query("SELECT COUNT(*) c FROM marketing_campaigns WHERE nama LIKE '[TEST]%'")->getRow()->c === 0
    );
}

echo "\n============================================================\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
echo "============================================================\n";
exit($fail > 0 ? 1 : 0);
