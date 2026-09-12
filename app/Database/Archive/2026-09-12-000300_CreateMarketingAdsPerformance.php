<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Performa iklan Ads (Meta Ads): PPN, Daily Budget, Objective,
 * Reach, Impression, Klik, Hasil per (tanggal, campaign) ± channel.
 *
 * Sumber data dashboard Laporan Digital Marketing. Spending tetap dari
 * marketing_ads_cost (menu Biaya Iklan) — TIDAK dicampur di sini.
 */
class CreateMarketingAdsPerformance extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('marketing_ads_performance')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'period_month' => [
                    'type'       => 'TINYINT',
                    'constraint' => 2,
                ],
                'period_year' => [
                    'type'       => 'SMALLINT',
                    'constraint' => 4,
                ],
                'tanggal' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'channel_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'campaign' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'daily_budget' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'null'       => true,
                ],
                'ppn' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'objective' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'reach' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'impression' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'klik' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'hasil' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'note' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['period_month', 'period_year']);
            $this->forge->addKey(['campaign']);
            $this->forge->createTable('marketing_ads_performance', true);
        }
    }

    public function down()
    {
        // Non-destruktif: tabel dipertahankan agar data tidak hilang.
    }
}