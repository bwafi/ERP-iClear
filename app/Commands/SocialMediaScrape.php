<?php

namespace App\Commands;

use App\Services\SocialMedia\BrightDataClient;
use App\Services\SocialMedia\BrightDataSocialMediaProvider;
use App\Services\SocialMedia\SocialMediaScraperService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Command TRIGGER ASYNC social media via Bright Data.
 *
 *   php spark social:pull            # semua platform bright_data
 *   php spark social:pull --platform facebook
 *   php spark social:pull --platform tiktok
 *
 * HANYA trigger → simpan snapshot_id ke social_media_scrape_jobs → SELESAI.
 * Tidak ada polling /progress, tidak ada GET /snapshot, tidak ada sleep.
 * Pengambilan hasil dilakukan processor terpisah (social:process, belum dibuat).
 *
 * Cron example (setiap hari 02:00):
 *   0 2 * * * cd /path/erp && php74 spark social:pull >> writable/logs/social_pull.log 2>&1
 *
 * Tidak ada hardcode jumlah akun / URL / cabang / target / bulan / tanggal,
 * dan tidak memakai snapshot_id yang di-hardcode.
 */
class SocialMediaScrape extends BaseCommand
{
    protected $group       = 'SocialMedia';
    protected $name        = 'social:pull';
    protected $description = 'Trigger async scraping Bright Data & simpan snapshot_id (tanpa menunggu ready).';
    protected $usage       = 'social:pull [--platform=facebook|tiktok]';

    protected $options = [
        '-platform' => 'Filter platform (facebook | tiktok). Kosong = semua.',
    ];

    public function run(array $params)
    {
        $platform = (string)CLI::getOption('platform');

        if ($platform !== '' && !in_array($platform, ['facebook', 'tiktok'], true)) {
            CLI::error("Platform tidak didukung: {$platform}");
            return EXIT_ERROR;
        }

        try {
            $service = new SocialMediaScraperService();
            $service->registerProvider(new BrightDataSocialMediaProvider(new BrightDataClient()));

            $platforms = $platform === '' ? null : [$platform];

            CLI::write('Memulai scraping social media…', 'yellow');

            $result = $service->triggerAll('bright_data', $platforms);

            foreach ($result['results'] as $row) {
                CLI::newLine();
                CLI::write(sprintf('[%s] %s', ucfirst((string)$row['platform']), $row['account_name']));
                if ($row['snapshot_id'] !== null) {
                    CLI::write('Trigger berhasil', 'green');
                    CLI::write('Snapshot: ' . $row['snapshot_id']);
                } else {
                    CLI::write('Trigger gagal: ' . $row['error_message'], 'red');
                }
            }

            CLI::newLine();
            CLI::write(sprintf(
                'Selesai trigger %d akun (%d berhasil, %d gagal).',
                count($result['results']),
                $result['ok'],
                $result['failed']
            ), $result['failed'] > 0 ? 'red' : 'green');

            return $result['failed'] > 0 ? EXIT_ERROR : EXIT_SUCCESS;
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        }
    }
}