<?php
/**
 * Backfill: sinkronisasi SEMUA lead Kommo → marketing_lead ERP.
 *
 * Memakai alur IDENTIK dengan webhook (KommoApiService → mapLead → upsert),
 * sehingga idempotent: jalankan ulang tidak membuat duplikat (unique kommo_lead_id).
 *
 * Usage (curl PHP >= 8 karena dibutuhkan ekstensi curl di CLI; php74 TIDAK punya):
 *   php app/Scripts/kommo_backfill.php                 # semua lead
 *   php app/Scripts/kommo_backfill.php --lead=23164862 # satu lead saja
 *   php app/Scripts/kommo_backfill.php --dry-run --max=20
 *   php app/Scripts/kommo_backfill.php --page-size=100 --delay=200000 --max=500
 *
 * Catatan:
 *  - TIDAK membuat Customer/Conversion/Omzet fiktif. Lead status WON tetap
 *    menunggu pencatatan customer & tanggal_won lewat alur ERP existing.
 *  - Lead deleted di Kommo (is_deleted) dilewati; restore via event webhook.
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

use App\Services\Kommo\KommoApiService;
use App\Services\Kommo\KommoWebhookService;

$args = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}
$leadId    = isset($args['lead']) ? (int)$args['lead'] : 0;
$dryRun    = isset($args['dry-run']);
$max       = isset($args['max']) ? (int)$args['max'] : 0;           // 0 = unlimited
$pageSize  = isset($args['page-size']) ? (int)$args['page-size'] : 100;
$delay     = isset($args['delay']) ? (int)$args['delay'] : 100000;  // mikro-detik antar lead
$pageSize  = max(20, min(250, $pageSize));

$api     = new KommoApiService();
$service = new KommoWebhookService($api);

if (!$api->isConfigured()) {
    fwrite(STDERR, "ERROR: Kommo belum dikonfigurasi (KOM_ENABLED / subdomain / access token).\n");
    exit(1);
}

if ($leadId > 0) {
    echo "== Sync lead #{$leadId}" . ($dryRun ? ' (dry-run)' : '') . " ==\n";
    if ($dryRun) {
        $lead = $api->getLead($leadId);
        echo json_encode($service->mapLead($lead), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }
    try {
        $result = $service->syncLead($leadId);
        echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        exit(0);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'Sync lead #' . $leadId . ' GAGAL: ' . get_class($e) . ' ' . $e->getMessage() . "\n");
        exit(2);
    }
}

echo "== BACKFILL LEAD KOMMO → ERP ==" . ($dryRun ? ' (DRY-RUN, tanpa tulis DB)' : '') . " ==\n";
echo "config: enabled=true subdomain=" . json_encode('***') . "\n";

$stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
$failList   = [];
$dryPreview = [];

$page     = 1;
$processed = 0;
$hasMore  = true;

while ($hasMore) {
    $paged = $api->getLeadsPage($page, $pageSize);
    if (empty($paged['items']) && $page === 1) {
        echo "Tidak ada lead di akun.\n";
        exit(0);
    }
    if (empty($paged['items'])) {
        break; // halaman kosong → selesai
    }

    foreach ($paged['items'] as $item) {
        if ($max > 0 && $processed >= $max) {
            break 2;
        }
        $id = (int)($item['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $processed++;
        if (!empty($item['is_unsorted'])) {
            $stats['skipped']++;
            continue;
        }

        if ($dryRun) {
            try {
                $mapped = $service->mapLead($item);
                $dryPreview[] = [
                    'lead'   => $id,
                    'nama'   => $mapped['nama'] ?? null,
                    'status' => $mapped['status'] ?? null,
                    'tanggal' => $mapped['tanggal'] ?? null,
                ];
            } catch (\Throwable $e) {
                $stats['failed']++;
                $failList[] = ['lead' => $id, 'error' => $e->getMessage()];
            }
        } else {
            try {
                $res = $service->syncLead($id, 'update');
                $act = $res['action'] ?? '?';
                if (isset($stats[$act])) {
                    $stats[$act]++;
                } else {
                    $stats[$act] = 1;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $failList[] = ['lead' => $id, 'error' => $e->getMessage()];
                echo "  FAIL  #{$id}: " . $e->getMessage() . "\n";
            }
        }

        if (($processed % 50) === 0) {
            echo "  progress {$processed} — created={$stats['created']} updated={$stats['updated']} failed={$stats['failed']}\n";
        }
        if ($delay > 0) {
            usleep($delay);
        }
    }

    $hasMore = $paged['hasMore'] && !($max > 0 && $processed >= $max);
    $page++;
    if ($page > 500) {
        echo "   WARNING: batas halaman 500 tercapai, hentikan.\n";
        break;
    }
}

echo "----------------------------------------\n";
echo "DONE:\n";
echo "  lead diproses       : {$processed}\n";
foreach ($stats as $k => $v) {
    if ($k === 'total') {
        continue;
    }
    echo sprintf("  %-14s: %d\n", $k, $v);
}
if ($dryRun && count($dryPreview) > 0) {
    echo "\nPreview (dry-run):\n";
    foreach ($dryPreview as $p) {
        echo "  #{$p['lead']} | {$p['nama']} | {$p['status']} | {$p['tanggal']}\n";
    }
}
if ($failList) {
    echo "\nGagal (" . count($failList) . "):\n";
    foreach ($failList as $f) {
        echo "  #" . $f['lead'] . ' — ' . $f['error'] . "\n";
    }
}

exit((int)count($failList) > 0 ? 2 : 0);