<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Snapshot bulanan data mentah KPI Team IT (jabatan 45).
 *
 * Dipisahkan dari kpi_evaluations karena datanya bukan penilaian manual:
 * satu baris per bulan berisi hasil sinkronisasi commit GitHub, hari backup,
 * dan hari uptime. Kolom metric nullable supaya sumber yang gagal/belum
 * tersedia tampil N/A, bukan 0.
 */
class CreateItKpiMetrics extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'period' => ['type' => 'VARCHAR', 'constraint' => 7],
            'feat_commits' => ['type' => 'INT', 'null' => true],
            'fix_commits' => ['type' => 'INT', 'null' => true],
            'docs_commits' => ['type' => 'INT', 'null' => true],
            'backup_ok_days' => ['type' => 'INT', 'null' => true],
            'backup_checked_days' => ['type' => 'INT', 'null' => true],
            'uptime_ok_days' => ['type' => 'INT', 'null' => true],
            'uptime_checked_days' => ['type' => 'INT', 'null' => true],
            'restore_ok' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'detail' => ['type' => 'TEXT', 'null' => true],
            'synced_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('period', false, true);
        $this->forge->createTable('kpi_it_metrics');
    }

    public function down()
    {
        $this->forge->dropTable('kpi_it_metrics', true);
    }
}
