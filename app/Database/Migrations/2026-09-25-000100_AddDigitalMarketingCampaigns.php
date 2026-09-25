<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Campaign Digital Marketing + relasi ke Performa Ads.
 *
 * - Buat master marketing_campaigns (id, nama, periode, status, reporting).
 *   Status mengikuti pola content_campaigns: draft / active / done.
 * - marketing_ads_performance: ganti kolom free-text `campaign` menjadi
 *   `campaign_id` (FK ke marketing_campaigns, SELECT wajib) + tambah
 *   `qualified` (Kualitas Leads) hasil Performa Ads.
 *
 * Tabel ads masih kosong saat migration ini dibuat (0 baris), sehingga
 * penghapusan kolom `campaign` aman.
 */
class AddDigitalMarketingCampaigns extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'deskripsi' => [
                'type'    => 'TEXT',
                'null'    => true,
            ],
            'tanggal_mulai' => [
                'type'       => 'DATE',
                'null'       => true,
            ],
            'tanggal_selesai' => [
                'type'    => 'DATE',
                'null'    => true,
            ],
            'period_month' => [
                'type'       => 'TINYINT',
                'constraint' => 4,
                'null'       => true,
            ],
            'period_year' => [
                'type'       => 'SMALLINT',
                'constraint' => 6,
                'null'       => true,
            ],
            'status' => [
                'type'       => "ENUM('draft','active','done')",
                'default'    => 'draft',
                'null'       => false,
            ],
            'report_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'pic' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_at' => [
                'type'       => 'DATETIME',
                'null'       => true,
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('period_year');
        $this->forge->addKey('status');
        $this->forge->createTable('marketing_campaigns', true);

        $this->forge->addColumn('marketing_ads_performance', [
            'campaign_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'unit_id',
            ],
            'qualified' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'hasil',
            ],
        ]);

        $this->forge->addForeignKey('campaign_id', 'marketing_campaigns', 'id', 'SET NULL', 'SET NULL', 'fk_ads_campaign');
        $this->forge->processIndexes('marketing_ads_performance');

        if ($this->db->fieldExists('campaign', 'marketing_ads_performance')) {
            $this->forge->dropColumn('marketing_ads_performance', 'campaign');
        }
        $this->db->query('ALTER TABLE marketing_ads_performance MODIFY COLUMN campaign_id INT(11) UNSIGNED NULL');
    }

    public function down()
    {
        if (!$this->db->fieldExists('campaign', 'marketing_ads_performance')) {
            $this->forge->addColumn('marketing_ads_performance', [
                'campaign' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => false,
                    'default'    => '',
                ],
            ]);
        }
        $this->forge->dropForeignKey('marketing_ads_performance', 'fk_ads_campaign');
        if ($this->db->fieldExists('qualified', 'marketing_ads_performance')) {
            $this->forge->dropColumn('marketing_ads_performance', 'qualified');
        }
        if ($this->db->fieldExists('campaign_id', 'marketing_ads_performance')) {
            $this->forge->dropColumn('marketing_ads_performance', 'campaign_id');
        }
        $this->forge->dropTable('marketing_campaigns', true);
    }
}