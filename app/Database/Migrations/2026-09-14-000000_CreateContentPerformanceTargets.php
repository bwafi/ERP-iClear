<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Target performa konten multi-metric (views, clicks, reach, dll).
 *
 * Menambah tabel pivot content_performance_targets (1 konten -> banyak
 * metric + target masing-masing) menggantikan kolom tunggal
 * contents.performance_metric_id / performance_target. Data existing
 * di-backfill otomatis.
 */
class CreateContentPerformanceTargets extends Migration
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
            'content_id' => [
                'type'       => 'INT',
            ],
            'metric_id' => [
                'type'       => 'INT',
            ],
            'target' => [
                'type'       => 'DECIMAL',
                'constraint' => '14,2',
                'null'       => true,
                'default'    => null,
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
        $this->forge->addUniqueKey(['content_id', 'metric_id']);
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('metric_id', 'performance_metrics', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('content_performance_targets', true);

        // Backfill: ubah data tunggal lama menjadi baris pivot.
        $this->db->query(
            "INSERT INTO content_performance_targets (content_id, metric_id, target, created_at, updated_at)
             SELECT c.id, c.performance_metric_id, c.performance_target, NOW(), NOW()
             FROM contents c
             WHERE c.performance_metric_id IS NOT NULL
               AND c.performance_target IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM content_performance_targets t
                   WHERE t.content_id = c.id AND t.metric_id = c.performance_metric_id
               )"
        );
    }

    public function down()
    {
        $this->forge->dropTable('content_performance_targets', true);
    }
}