<?php

namespace App\Commands;

use App\Services\Meta\MetaCampaignSyncService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Tarik daftar campaign Meta ke master Campaign Digital Marketing.
 *
 *   php spark meta:sync-campaigns            # bulan & tahun berjalan
 *   php spark meta:sync-campaigns 10 2026
 *
 * Biasanya tidak perlu dijalankan manual: `meta:pull` menjalankannya lebih
 * dulu untuk bulan yang sama.
 */
class MetaSyncCampaigns extends BaseCommand
{
    protected $group       = 'Meta';
    protected $name        = 'meta:sync-campaigns';
    protected $description = 'Tarik daftar campaign Meta ke master Campaign.';
    protected $usage       = 'meta:sync-campaigns [bulan] [tahun]';

    public function run(array $params)
    {
        $bulan = (int)($params[0] ?? date('n'));
        $tahun = (int)($params[1] ?? date('Y'));

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            CLI::error('Bulan/tahun tidak valid.');
            return EXIT_ERROR;
        }

        try {
            $hasil = (new MetaCampaignSyncService())->sync($bulan, $tahun);
        } catch (\Throwable $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        }

        CLI::write("Campaign baru      : {$hasil['dibuat']}");
        CLI::write("Campaign diperbarui: {$hasil['diperbarui']}");
        CLI::write("Diturunkan ke draft: {$hasil['diturunkan']}");

        return EXIT_SUCCESS;
    }
}
