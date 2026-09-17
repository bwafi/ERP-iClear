<?php

namespace App\Commands;

use App\Services\SocialMedia\BrightDataClient;
use App\Services\SocialMedia\BrightDataSocialMediaProvider;
use App\Services\SocialMedia\SocialMediaScraperService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Command PROCESS BATCH social media scrape jobs (Bright Data async processor).
 *
 *   php spark social:process               # proses batch default (limit 10)
 *   php spark social:process --limit 20    # proses batch dengan limit 20
 *
 * FLOW:
 *   1. Ambil social_media_scrape_jobs dengan status pending/processing/running.
 *   2. Untuk setiap job: GET /datasets/v3/progress/{snapshot_id}
 *   3. Status handling:
 *      - starting / running : biarkan tetap pending/processing, jangan download
 *      - failed             : tandai job failed dan catat error
 *      - ready              : download GET /datasets/v3/snapshot/{snapshot_id}
 *                            -> normalize -> upsert posts -> insert snapshots -> mark completed
 *   4. Selesai setelah 1 kali pengecekan batch (tanpa sleep/polling loop).
 *
 * Cron example (setiap 5/15 menit):
 *   * /5 * * * cd /path/erp && php74 spark social:process >> writable/logs/social_process.log 2>&1
 */
class SocialMediaProcess extends BaseCommand
{
    protected $group       = 'SocialMedia';
    protected $name        = 'social:process';
    protected $description = 'Proses social_media_scrape_jobs yang sudah di-trigger: cek progress dan download snapshot jika ready.';
    protected $usage       = 'social:process [--limit=10]';

    protected $options = [
        '-limit' => 'Jumlah maksimal job yang diproses dalam 1 run batch (default 10).',
    ];

    public function run(array $params)
    {
        $limit = (int)CLI::getOption('limit');
        if ($limit <= 0) {
            $limit = 10;
        }

        try {
            $service = new SocialMediaScraperService();
            $service->registerProvider(new BrightDataSocialMediaProvider(new BrightDataClient()));

            CLI::write('Memulai pengecekan batch social media scrape jobs…', 'yellow');

            $summary = $service->processPendingBatch($limit, 'bright_data');

            if ($summary['total'] === 0) {
                CLI::write('Tidak ada job pending/processing yang perlu diproses.', 'light_gray');
                return EXIT_SUCCESS;
            }

            foreach ($summary['results'] as $res) {
                CLI::newLine();
                CLI::write(sprintf('[%s] %s (Job #%d | %s)', ucfirst((string)$res['platform']), $res['account_name'], $res['job_id'], $res['snapshot_id']));

                if ($res['status'] === 'completed') {
                    CLI::write("  Status: COMPLETED — {$res['message']}", 'green');
                } elseif ($res['status'] === 'failed') {
                    CLI::write("  Status: FAILED — {$res['message']}", 'red');
                } else {
                    CLI::write("  Status: {$res['status']} (belum ready, ditunda ke run berikutnya)", 'yellow');
                }
            }

            CLI::newLine();
            CLI::write(sprintf(
                'Selesai memproses batch: %d total (%d completed, %d masih menunggu, %d failed).',
                $summary['total'],
                $summary['completed'],
                $summary['waiting'],
                $summary['failed']
            ), $summary['failed'] > 0 ? 'red' : 'green');

            return EXIT_SUCCESS;
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        }
    }
}