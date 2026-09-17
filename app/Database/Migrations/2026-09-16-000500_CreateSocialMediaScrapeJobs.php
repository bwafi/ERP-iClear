<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tracking job/snapshot Bright Data (async trigger).
 *
 * social:pull hanya trigger → simpan snapshot_id di sini → selesai.
 * Processor (social:process, nanti) akan polling progress & download
 * snapshot berdasar baris di tabel ini.
 *
 * snapshot_id dari Bright Data identik dengan job → UNIQUE index
 * untuk mencegah duplicate record saat response diproses ulang.
 */
class CreateSocialMediaScrapeJobs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'social_media_account_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'platform'               => ['type' => 'VARCHAR', 'constraint' => 30],
            'dataset_id'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'snapshot_id'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'requested_at'           => ['type' => 'DATETIME', 'null' => true],
            'completed_at'           => ['type' => 'DATETIME', 'null' => true],
            'error_message'          => ['type' => 'TEXT', 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('snapshot_id', false, true); // unique: 1 snapshot = 1 record
        $this->forge->addKey('status');
        $this->forge->addKey('social_media_account_id');
        $this->forge->addForeignKey('social_media_account_id', 'social_media_accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('social_media_scrape_jobs', true);
    }

    public function down()
    {
        $this->forge->dropTable('social_media_scrape_jobs', true);
    }
}