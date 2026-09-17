<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Skema Social Media KPI.
 *
 * Relasi:
 *   units → social_media_accounts → social_media_posts → social_media_metric_snapshots
 *
 * Aturan:
 *   - Snapshot tidak pernah di-overwrite; setiap run menyimpan snapshot baru.
 *   - Post identity = (social_media_account_id + external_post_id), bukan URL.
 *   - Unit milik account; post/snapshot mengikuti unit account-nya.
 *
 * Sumber metric untuk KPI = social_media_posts + social_media_metric_snapshots,
 * bukan input metric manual.
 */
class CreateSocialMediaSchema extends Migration
{
    public function up()
    {
        // ── social_media_accounts ─────────────────────────────────────
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'unit_id'             => ['type' => 'INT', 'constraint' => 11],
            'platform'            => ['type' => 'VARCHAR', 'constraint' => 30],
            'account_name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'username'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'external_account_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'profile_url'         => ['type' => 'VARCHAR', 'constraint' => 512],
            'provider'            => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'is_active'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['unit_id', 'platform', 'profile_url'], false, true);
        $this->forge->addKey('platform');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'CASCADE', 'CASCADE');
        $this->forge->createTable('social_media_accounts', true);

        // ── social_media_posts ────────────────────────────────────────
        $this->forge->addField([
            'id'                     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'social_media_account_id'=> ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'platform'               => ['type' => 'VARCHAR', 'constraint' => 30],
            'external_post_id'       => ['type' => 'VARCHAR', 'constraint' => 200],
            'post_url'               => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
            'post_type'              => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'caption'                => ['type' => 'TEXT', 'null' => true],
            'published_at'           => ['type' => 'DATETIME', 'null' => true],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['social_media_account_id', 'external_post_id'], false, true);
        $this->forge->addKey('platform');
        $this->forge->addKey('published_at');
        $this->forge->addKey('social_media_account_id');
        $this->forge->addForeignKey('social_media_account_id', 'social_media_accounts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('social_media_posts', true);

        // ── social_media_metric_snapshots ─────────────────────────────
        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'social_media_post_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'captured_at'          => ['type' => 'DATETIME'],
            'views'                => ['type' => 'BIGINT', 'null' => true],
            'plays'                => ['type' => 'BIGINT', 'null' => true],
            'likes'                => ['type' => 'BIGINT', 'null' => true],
            'comments'             => ['type' => 'BIGINT', 'null' => true],
            'shares'               => ['type' => 'BIGINT', 'null' => true],
            'saves'                => ['type' => 'BIGINT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['social_media_post_id', 'captured_at'], false, true);
        $this->forge->addKey('captured_at');
        $this->forge->addForeignKey('social_media_post_id', 'social_media_posts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('social_media_metric_snapshots', true);

        // ── social_media_targets ──────────────────────────────────────
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'period_month'  => ['type' => 'CHAR', 'constraint' => 7], // YYYY-MM
            'unit_id'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0], // 0 = global
            'platform'      => ['type' => 'VARCHAR', 'constraint' => 30],
            'metric'        => ['type' => 'VARCHAR', 'constraint' => 40],
            'target_value'  => ['type' => 'DECIMAL', 'constraint' => '18,2', 'null' => false, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['period_month', 'unit_id', 'platform', 'metric'], false, true);
        $this->forge->addKey('platform');
        $this->forge->createTable('social_media_targets', true);
    }

    public function down()
    {
        $this->forge->dropTable('social_media_metric_snapshots', true);
        $this->forge->dropTable('social_media_posts', true);
        $this->forge->dropTable('social_media_accounts', true);
        $this->forge->dropTable('social_media_targets', true);
    }
}