<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Channel ganda dlm satu baris performa Ads: kolom `channel_ids` (JSON).
 * channel_id lama tetap ada sbg fallback tampilan; data baru memakai
 * channel_ids (bisa lebih dari satu saluran).
 */
class AddChannelIdsToMarketingAdsPerf extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('marketing_ads_performance')) {
            return;
        }

        if (!$db->fieldExists('channel_ids', 'marketing_ads_performance')) {
            $this->forge->addColumn('marketing_ads_performance', [
                'channel_ids' => [
                    'type'       => 'TEXT',
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'channel_id',
                ],
            ]);
        }

        // Migrasi data lama: channel_id tunggal → channel_ids JSON.
        $rows = $db->table('marketing_ads_performance')
            ->where('channel_ids IS NOT NULL AND channel_ids <> ""', null, false)
            ->get()->getResultArray();
        if (count($rows) > 0) {
            // sudah terisi, abaikan
        } else {
            $missing = $db->query(
                "SELECT id, channel_id FROM marketing_ads_performance
                 WHERE (channel_ids IS NULL OR channel_ids = '') AND channel_id IS NOT NULL"
            )->getResultArray();
            foreach ($missing as $row) {
                $ids = json_encode([(int)$row['channel_id']]);
                $db->table('marketing_ads_performance')->where('id', $row['id'])->update(['channel_ids' => $ids]);
            }
        }
    }

    public function down()
    {
        // Non-destruktif.
    }
}