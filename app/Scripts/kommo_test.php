<?php
/**
 * Functional test integrasi Kommo CRM → ERP (webhook + API v4).
 *
 * Menggunakan FakeKommoApi (tanpa HTTP nyata) untuk menguji:
 *   - lead baru (format JSON & payload lama)
 *   - lead update
 *   - status change → WON
 *   - duplicate webhook (idempotent)
 *   - payload invalid
 *   - Kommo API error
 *   - Lead tidak ditemukan
 *   - database error
 *   - leads.delete (soft-delete) & restore
 *   - isi kolom kommo_* di marketing_lead
 *
 * Semua baris uji ditandai kommo_lead_id >= 9000000 dan di-rollback di akhir.
 *
 * Usage: php74 app/Scripts/kommo_test.php
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

use App\Models\ModelMarketingLead;
use App\Services\Kommo\KommoApiException;
use App\Services\Kommo\KommoApiInterface;
use App\Services\Kommo\KommoWebhookService;
use App\Services\Marketing\MarketingKpiService;

// ── Test helpers ─────────────────────────────────────────────────
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
        echo "  FAIL  {$label}" . ($detail !== '' ? "  [{$detail}]" : '') . "\n";
    }
}

// ── Fake Kommo API ───────────────────────────────────────────────
class FakeKommoApi implements KommoApiInterface
{
    public array $leads = [];
    public array $contacts = [];
    public array $users = [];
    public array $statuses = [];
    public ?\Throwable $failError = null;

    public function isConfigured(): bool
    {
        return true;
    }

    public function getLead(int $leadId): array
    {
        if ($this->failError) {
            throw $this->failError;
        }
        if (!isset($this->leads[$leadId])) {
            throw KommoApiException::notFound('lead #' . $leadId);
        }
        return $this->leads[$leadId];
    }

    public function getLeads(int $limit = 10): array
    {
        return array_slice(array_values($this->leads), 0, $limit);
    }

    public function getLeadsPage(int $page = 1, int $limit = 100): array
    {
        $all   = array_values($this->leads);
        $start = ($page - 1) * $limit;
        return [
            'items'   => array_slice($all, $start, $limit),
            'total'   => count($all),
            'hasMore' => ($start + $limit) < count($all),
        ];
    }

    /** Map leadId → origin talk (simulasi kanal percakapan Kommo). */
    public array $talkOrigins = [];

    public function talkOriginForLead(array $contactIds, int $leadId): ?string
    {
        return $this->talkOrigins[$leadId] ?? null;
    }

    public function getContact(int $contactId): array
    {
        return $this->contacts[$contactId] ?? [];
    }

    public function getUserName(int $userId): ?string
    {
        return $this->users[$userId] ?? null;
    }

    public function statusInfo(int $pipelineId): array
    {
        return $this->statuses[$pipelineId] ?? [];
    }
}

// Model lead yang sengaja rusak (simulasi database error pada insert).
class BrokenLeadModel extends ModelMarketingLead
{
    public function insert($data = null, bool $returnID = true)
    {
        throw new \RuntimeException('simulasi gagal insert (DB error)');
    }
}

// ── Setup environment ────────────────────────────────────────────
$db = \Config\Database::connect();

// Bersihkan sisa uji terdahulu.
$db->query('DELETE FROM marketing_lead WHERE kommo_lead_id >= 9000000');
$db->query('DELETE FROM marketing_source WHERE name = "[KOMMO TEST]"');

echo "\n== STRUKTUR DB (migrasi) ==\n";
$leadCols = array_column($db->query('SHOW COLUMNS FROM marketing_lead')->getResultArray(), 'Field');
$needCols = ['kommo_lead_id', 'kommo_account_id', 'kommo_pipeline_id', 'kommo_status_id', 'kommo_updated_at', 'kommo_deleted_at'];
ok('Kolom kommo_* ada di marketing_lead', count(array_diff($needCols, $leadCols)) === 0, implode(',', array_diff($needCols, $leadCols)));
$idx = array_column($db->query('SHOW INDEX FROM marketing_lead')->getResultArray(), 'Key_name');
ok('Unique index uniq_kommo_lead ada', in_array('uniq_kommo_lead', $idx, true));

// ── Fixture data Kommo ───────────────────────────────────────────
$ts = strtotime('2026-09-05');
$api = new FakeKommoApi();
$api->users[777] = 'CS Fathoni';
$api->contacts[8000001] = [
    'id'       => 8000001,
    'name'     => 'Budi Customer',
    'custom_fields_values' => [
        ['field_code' => 'PHONE', 'values' => [['value' => '0812345678']]],
    ],
];
$api->statuses[1] = [
    901 => ['name' => 'New', 'is_editable' => true],
    902 => ['name' => 'Won', 'is_editable' => false],
    903 => ['name' => 'Lost', 'is_editable' => false],
];
$api->leads[9000001] = [
    'id'                  => 9000001,
    'name'                => 'Lead Google Ads',
    'price'               => 0,
    'responsible_user_id' => 777,
    'status_id'           => 901,
    'pipeline_id'         => 1,
    'created_at'          => $ts,
    'updated_at'          => $ts,
    'created_by'          => 1,
    'account_id'          => 99,
    'custom_fields_values' => [
        ['field_id' => 1, 'field_code' => 'UTM_SOURCE', 'name' => 'Source', 'values' => [['value' => 'Google ads']]],
    ],
    '_embedded' => ['contacts' => [['id' => 8000001, 'is_main' => true]]],
];

$model     = new ModelMarketingLead();
$service   = new KommoWebhookService($api, $model);
$kpi       = new MarketingKpiService();

function jsonWebhook(string $event, int $leadId, array $extra = []): array
{
    return array_merge([
        'account' => ['id' => 99, 'subdomain' => 'iclear-test'],
        'event'   => $event,
        'data'    => ['object_id' => $leadId, 'object_type' => 'lead'],
    ], $extra);
}

// ── 1. LEAD BARU (JSON format) ───────────────────────────────────
echo "\n== LEAD BARU ==\n";
$res = $service->processWebhook(jsonWebhook('leads.add', 9000001));
ok('Event add diproses (created)', $res['ok'] && ($res['processed'][0]['action'] ?? '') === 'created', json_encode($res['errors']));
$row = $model->where('kommo_lead_id', 9000001)->first();
ok('1 baris lead dibuat', $row !== null);
ok('nama lead dari Kommo', $row && $row->nama === 'Lead Google Ads', $row->nama ?? '-');
ok('nama contact "Budi Customer" tersimpan (fallback nama)', !$row || $row->nama !== 'Budi Customer'); // nama lead lebih diprioritaskan
$phoneRow = $model->where('kommo_lead_id', 9000001)->first();
ok('no_hp dari custom field contact (PHONE)', $phoneRow && $phoneRow->no_hp === '0812345678', $phoneRow->no_hp ?? '-');
ok('cs = responsible user (CS Fathoni)', $phoneRow && $phoneRow->cs === 'CS Fathoni', $phoneRow->cs ?? '-');
ok('source_id ter-resolusi (Google ads → 5)', $phoneRow && (int)$phoneRow->source_id === 5, (string)($phoneRow->source_id ?? 'null'));
ok('status NEW untuk stage open', $phoneRow && $phoneRow->status === 'NEW', $phoneRow->status ?? '-');
ok('ads_organic default ORGANIC', $phoneRow && $phoneRow->ads_organic === 'ORGANIC', $phoneRow->ads_organic ?? '-');
ok('tanggal dari created_at Kommo', $phoneRow && $phoneRow->tanggal === '2026-09-05', $phoneRow->tanggal ?? '-');
ok('kommo_updated_at/status/pipeline/accont tersimpan', $phoneRow && (int)$phoneRow->kommo_updated_at === $ts && (int)$phoneRow->kommo_status_id === 901 && (int)$phoneRow->kommo_pipeline_id === 1 && (int)$phoneRow->kommo_account_id === 99);

// ── 1b. CUSTOM FIELD KOMMO (price, cabang, nama user) ───────────
$api->contacts[8000002] = ['id' => 8000002, 'name' => '', 'custom_fields_values' => null];
$api->leads[9000002] = [
    'id'                  => 9000002,
    'name'                => 'Lead Template',
    'price'               => 838000,
    'responsible_user_id' => 777,
    'status_id'           => 901,
    'pipeline_id'         => 1,
    'created_at'          => $ts,
    'updated_at'          => $ts,
    'created_by'          => 1,
    'account_id'          => 99,
    'custom_fields_values' => [
        ['field_id' => 10, 'field_code' => null, 'field_name' => 'Nama User',     'values' => [['value' => 'Yolanda']]],
        ['field_id' => 11, 'field_code' => null, 'field_name' => 'Cabang Tujuan', 'values' => [['value' => 'Probolinggo/Jember']]],
    ],
    '_embedded' => ['contacts' => [['id' => 8000002, 'is_main' => true]]],
];
$resCf = $service->processWebhook(jsonWebhook('leads.add', 9000002));
$cfRow = $model->where('kommo_lead_id', 9000002)->first();
ok('lead custom-field dibuat', $resCf['ok'] && ($resCf['processed'][0]['action'] ?? '') === 'created', json_encode($resCf['errors']));
ok('nama memakai "Nama User" + lead id', $cfRow && $cfRow->nama === 'Yolanda (#9000002)', $cfRow->nama ?? '-');
ok('price dari lead.price', $cfRow && (float)$cfRow->price === 838000.0, (string)($cfRow->price ?? 'null'));
ok('cabang dari custom field "Cabang Tujuan"', $cfRow && $cfRow->cabang === 'Probolinggo/Jember', $cfRow->cabang ?? '-');
$model->where('kommo_lead_id', 9000002)->delete();

// ── 1c. SOURCE DARI TALK (kanal percakapan, tanpa UTM) ──────────
$api->contacts[8000003] = ['id' => 8000003, 'name' => 'Bpk dari IG', 'custom_fields_values' => null];
$api->leads[9000003] = [
    'id'                  => 9000003,
    'name'                => 'Lead #9000003',
    'price'               => 0,
    'responsible_user_id' => 777,
    'status_id'           => 901,
    'pipeline_id'         => 1,
    'created_at'          => $ts,
    'updated_at'          => $ts,
    'created_by'          => 1,
    'account_id'          => 99,
    'custom_fields_values' => null,
    '_embedded' => ['contacts' => [['id' => 8000003, 'is_main' => true]]],
];
$api->talkOrigins[9000003] = 'instagram_business'; // bukti percakapan dari IG
$resTalk = $service->processWebhook(jsonWebhook('leads.add', 9000003));
$talkRow = $model->where('kommo_lead_id', 9000003)->first();
ok('source dari talk origin instagram_business → Instagram', $talkRow && (int)$talkRow->source_id === 1, (string)($talkRow->source_id ?? 'null'));
ok('nama lead tetap diprioritaskan', $talkRow && $talkRow->nama === 'Lead #9000003', $talkRow->nama ?? '-');
$model->where('kommo_lead_id', 9000003)->delete();

// ── 2. LEAD UPDATE ───────────────────────────────────────────────
echo "\n== LEAD UPDATE ==\n";
$api->leads[9000001]['name'] = 'Lead Google Ads (edited)';
$api->leads[9000001]['updated_at'] = $ts + 3600;
$res = $service->processWebhook(jsonWebhook('leads.update', 9000001));
ok('Event update → action updated', $res['ok'] && ($res['processed'][0]['action'] ?? '') === 'updated', json_encode($res['errors']));
$row = $model->where('kommo_lead_id', 9000001)->first();
ok('Nama diperbarui tanpa baris baru', $row && $row->nama === 'Lead Google Ads (edited)');
ok('Jumlah baris tetep 1 (idempotent update)', (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id=9000001')->getRow()->c === 1);

// ── 3. STATUS CHANGE → WON ───────────────────────────────────────
echo "\n== STATUS CHANGE ==\n";
$api->leads[9000001]['status_id'] = 902; // Won (non-editable)
$api->leads[9000001]['updated_at'] = $ts + 7200;
$res = $service->processWebhook(jsonWebhook('leads.status', 9000001));
ok('Event status diproses', $res['ok'], json_encode($res['errors']));
$row = $model->where('kommo_lead_id', 9000001)->first();
ok('Status berubah menjadi WON', $row && $row->status === 'WON', $row->status ?? '-');
ok('kommo_status_id 902 tercatat', $row && (int)$row->kommo_status_id === 902);
ok('WON tetap WAJIB link customer via ERP (customer_id null)', $row && $row->customer_id === null && $row->tanggal_won === null);

// ── 4. DUPLICATE WEBHOOK ─────────────────────────────────────────
echo "\n== DUPLICATE WEBHOOK ==\n";
$dup = [
    'account' => ['id' => 99, 'subdomain' => 'iclear-test'],
    'data'    => ['object_id' => 9000001, 'object_type' => 'lead'],
    'event'   => 'leads.add',
];
$res2 = $service->processWebhook($dup); // event add lagi untuk lead yang sama
ok('Webhook duplikat → action updated, bukan create', $res2['ok'] && ($res2['processed'][0]['action'] ?? '') === 'updated', json_encode($res2));
ok('Total baris kommo_lead_id=9000001 tetap 1', (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id=9000001')->getRow()->c === 1);

// ── 5. PAYLOAD INVALID ───────────────────────────────────────────
echo "\n== PAYLOAD INVALID ==\n";
$r3 = $service->processWebhook([]);
ok('Payload [] ditolak (ok=false)', !$r3['ok'] && !empty($r3['errors']) && $r3['processed'] === []);
$r4 = $service->processWebhook(['foo' => 'bar']);
ok('Payload tanpa event lead ditolak', !$r4['ok'] && !empty($r4['errors']));
$before = (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id >= 9000000')->getRow()->c;
ok('Payload invalid tidak membuat baris', $before === 1);

// ── 6. KOMMO API ERROR ───────────────────────────────────────────
echo "\n== KOMMO API ERROR ==\n";
$api->failError = KommoApiException::transport('curl#28 timeout');
$r5 = $service->processWebhook(jsonWebhook('leads.add', 9000002));
ok('API timeout → ok=false & error kommo_api_error', !$r5['ok'] && ($r5['errors'][0]['error'] ?? '') === 'kommo_api_error', json_encode($r5['errors']));
$api->failError = null;
$before = (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id >= 9000000')->getRow()->c;
ok('API error tidak membuat baris baru', $before === 1);

// ── 7. LEAD TIDAK DITEMUKAN ──────────────────────────────────────
echo "\n== LEAD TIDAK DITEMUKAN ==\n";
$r6 = $service->processWebhook(jsonWebhook('leads.add', 9999991));
ok('Lead 404 → ok=false & error lead_tidak_ditemukan', !$r6['ok'] && ($r6['errors'][0]['error'] ?? '') === 'lead_tidak_ditemukan', json_encode($r6['errors']));
$before = (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id >= 9000000')->getRow()->c;
ok('404 tidak meng-INSERT lead fiktif', $before === 1);

// ── 8. DATABASE ERROR ────────────────────────────────────────────
echo "\n== DATABASE ERROR ==\n";
$api->leads[9000003] = [
    'id'                  => 9000003,
    'name'                => 'DB Error Lead',
    'responsible_user_id' => 777,
    'status_id'           => 901,
    'pipeline_id'         => 1,
    'created_at'          => $ts,
    'updated_at'          => $ts,
    '_embedded'           => ['contacts' => []],
];
$broken = new BrokenLeadModel();
$svcBroken = new KommoWebhookService($api, $broken);
$r7 = $svcBroken->processWebhook(jsonWebhook('leads.add', 9000003));
ok('DB error → ok=false & error database_error', !$r7['ok'] && ($r7['errors'][0]['error'] ?? '') === 'database_error', json_encode($r7['errors']));
$before = (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id >= 9000000')->getRow()->c;
ok('DB error tidak meninggalkan baris rusak', $before === 1);

// ── 9. PAYLOAD LAMA (form-urlencoded) ────────────────────────────
echo "\n== PAYLOAD LAMA ==\n";
$legacyApi = new FakeKommoApi();
$legacyApi->users[777] = 'CS Fathoni';
$legacyApi->statuses[1] = $api->statuses[1];
$legacyApi->leads[9000004] = [
    'id'                  => 9000004,
    'name'                => 'Lead Via Payload Lama',
    'responsible_user_id' => 777,
    'status_id'           => 901,
    'pipeline_id'         => 1,
    'created_at'          => strtotime('2026-09-06'),
    'updated_at'          => strtotime('2026-09-06'),
    '_embedded'           => ['contacts' => []],
];
$svcLegacy = new KommoWebhookService($legacyApi, $model);
$legacyPayload = [
    'account' => ['subdomain' => 'iclear-test', 'id' => 99],
    'leads'   => [
        'add' => [['id' => 9000004]],
    ],
];
$r8 = $svcLegacy->processWebhook($legacyPayload);
ok('Payload lama leads.add diproses', $r8['ok'] && ($r8['processed'][0]['action'] ?? '') === 'created', json_encode($r8['errors']));
$row = $model->where('kommo_lead_id', 9000004)->first();
ok('Lead (payload lama) tersimpan', $row && $row->nama === 'Lead Via Payload Lama');
$legacyStatus = [
    'account' => ['subdomain' => 'iclear-test', 'id' => 99],
    'leads'   => [
        'status' => [['id' => 9000004, 'status_id' => 902, 'pipeline_id' => 1]],
    ],
];
$legacyApi->leads[9000004]['status_id'] = 902;
$r9 = $svcLegacy->processWebhook($legacyStatus);
ok('Payload lama leads.status (WON) diproses', $r9['ok']);
$row = $model->where('kommo_lead_id', 9000004)->first();
ok('Status dari payload lama = WON', $row && $row->status === 'WON', $row->status ?? '-');

// ── 10. LEADS DELETE & RESTORE ───────────────────────────────────
echo "\n== LEADS DELETE / RESTORE ==\n";
$del = [
    'account' => ['id' => 99],
    'event'   => 'leads.delete',
    'data'    => ['object_id' => 9000001, 'object_type' => 'lead'],
];
$r10 = $service->processWebhook($del);
ok('leads.delete → soft-delete', $r10['ok'] && ($r10['processed'][0]['action'] ?? '') === 'deleted');
$row = $model->where('kommo_lead_id', 9000001)->first();
ok('kommo_deleted_at terisi, baris TIDAK dihapus', $row && $row->kommo_deleted_at !== null);
$countLeadsSep = $kpi->countLeads(9, 2026);
$rowCount = (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id=9000001 AND kommo_deleted_at IS NULL')->getRow()->c;
ok('Lead terhapus tidak dihitung KPI (countLeads 9/2026 tanpa lead deleted)', $rowCount === 0);

$rev = [
    'account' => ['id' => 99],
    'event'   => 'leads.restore',
    'data'    => ['object_id' => 9000001, 'object_type' => 'lead'],
];
$api->leads[9000001]['status_id'] = 901;
$r11 = $service->processWebhook($rev);
ok('leads.restore diproses', $r11['ok']);
$row = $model->where('kommo_lead_id', 9000001)->first();
ok('kommo_deleted_at bersih setelah restore', $row && $row->kommo_deleted_at === null);
$rowCount = (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id=9000001 AND kommo_deleted_at IS NULL')->getRow()->c;
ok('Lead restore kembali dihitung KPI', $rowCount === 1);

// ── 11. Controller-endpoint (matikan KOM eksplisit agar deterministik) ──
echo "\n== ENDPOINT (DISABLED) ==\n";
putenv('KOM_ENABLED=false');
$_SERVER['REQUEST_METHOD'] = 'POST';
$config = new \Config\App();
$uri = new \CodeIgniter\HTTP\SiteURI($config, 'api/kommo/webhook', 'localhost', 'http');
$req = new \CodeIgniter\HTTP\IncomingRequest($config, $uri, null, new \CodeIgniter\HTTP\UserAgent());
\CodeIgniter\Config\Services::injectMock('request', $req);
$ctrl = new \App\Controllers\KommoWebhook();
$ctrl->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
$resp = $ctrl->handle();
$bodyResp = json_decode((string)$resp->getBody(), true);
ok('Endpoint disabled → HTTP 503', $resp->getStatusCode() === 503, (string)$resp->getStatusCode());
ok('Endpoint disabled → respon JSON ok=false', is_array($bodyResp) && ($bodyResp['ok'] ?? true) === false, var_export($bodyResp, true));
putenv('KOM_ENABLED=true');

// ── ROLLBACK ─────────────────────────────────────────────────────
echo "\n== ROLLBACK ==\n";
$db->query('DELETE FROM marketing_lead WHERE kommo_lead_id >= 9000000');
$db->query('DELETE FROM marketing_source WHERE name = "[KOMMO TEST]"');
$left = (int)$db->query('SELECT COUNT(*) c FROM marketing_lead WHERE kommo_lead_id >= 9000000')->getRow()->c;
ok('Semua data uji dibersihkan', $left === 0, "sisa={$left}");

echo "\n==================================================\n";
echo "SMOKE KOMMO:  PASS={$pass}  FAIL={$fail}\n";
exit($fail > 0 ? 1 : 0);