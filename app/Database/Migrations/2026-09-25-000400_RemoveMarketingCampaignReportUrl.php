<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hapus kolom report_url dari marketing_campaigns.
 *
 * KPI Reporting didefinisikan ulang = % campaign berstatus Selesai (done)
 * terhadap total campaign dalam periode (tanpa tautan laporan). Kolom
 * report_url tidak lagi dipakai.
 */
class RemoveMarketingCampaignReportUrl extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('report_url', 'marketing_campaigns')) {
            $this->forge->dropColumn('marketing_campaigns', 'report_url');
        }
    }

    public function down()
    {
        if (!$this->db->fieldExists('report_url', 'marketing_campaigns')) {
            $this->forge->addColumn('marketing_campaigns', [
                'report_url' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 500,
                    'null'       => true,
                    'after'      => 'status',
                ],
            ]);
        }
    }
}