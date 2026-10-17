<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pemetaan campaign ERP → campaign Meta Ads.
 *
 *  - meta_campaign_id: ID kampanye di Meta (UNIQUE, null = belum dipetakan);
 *  - unit_id         : cabang default untuk baris hasil tarikan Meta.
 *
 * Pemetaan diisi manual lewat form Campaign — tidak ditebak dari nama
 * kampanye, karena ini menyangkut pembagian biaya.
 */
class AddMetaMappingToMarketingCampaigns extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->fieldExists('meta_campaign_id', 'marketing_campaigns')) {
            $this->forge->addColumn('marketing_campaigns', [
                'meta_campaign_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => true,
                    'after'      => 'pic',
                ],
            ]);
        }

        if (!$db->fieldExists('unit_id', 'marketing_campaigns')) {
            $this->forge->addColumn('marketing_campaigns', [
                'unit_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'after'      => 'meta_campaign_id',
                ],
            ]);
        }

        $indexes = array_map(fn($i) => (string)$i->name, $db->getIndexData('marketing_campaigns'));
        if (!in_array('meta_campaign_id', $indexes, true)) {
            $this->forge->addKey('meta_campaign_id', false, true);
            $this->forge->processIndexes('marketing_campaigns');
        }
    }

    public function down()
    {
        // Non-destruktif: pemetaan dipertahankan agar data tidak hilang.
    }
}
