<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Skema Content Management untuk KPI Multimedia/Creative.
 *
 * Tidak ada tabel content/platform yang sudah tersedia di database existing,
 * sehingga seluruh tabel dibuat baru. Seluruh relasi memakai FK + relational
 * table (tanpa JSON/comma-separated ID).
 *
 * Tables:
 *   contents                 — 1 baris = 1 content (pekerjaan/karya)
 *   content_types            — master jenis konten
 *   platforms                — master platform publikasi
 *   performance_metrics      — master metric performa
 *   brand_checklist_items    — master item checklist brand (LOGO/WARNA/FONT/...)
 *   content_units            — target unit (target_scope = SELECTED)
 *   content_people           — relasi content ↔ orang (role TALENT/CREATIVE)
 *   publications             — publikasi content (banyak per content, per unit/platform)
 *   publication_performance  — performa per publikasi per periode
 *   content_checklists       — checklist brand per content (relational, bukan JSON)
 *   content_qc               — histori QC (PASS/REJECT + note + checker)
 */
class CreateContentManagementSchema extends Migration
{
    public function up()
    {
        // ── Master: platform publikasi ─────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code', false, true);
        $this->forge->createTable('platforms', true);

        // ── Master: jenis konten ────────────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code', false, true);
        $this->forge->createTable('content_types', true);

        // ── Master: metric performa ─────────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code', false, true);
        $this->forge->createTable('performance_metrics', true);

        // ── Master: item checklist brand ────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code', false, true);
        $this->forge->createTable('brand_checklist_items', true);

        // ── contents ────────────────────────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'judul' => ['type' => 'VARCHAR', 'constraint' => 191],
            'deskripsi' => ['type' => 'TEXT', 'null' => true],
            'content_type_id' => ['type' => 'INT', 'null' => true],
            'target_scope' => ['type' => 'ENUM', 'constraint' => ['ALL', 'SELECTED'], 'default' => 'ALL'],
            'deadline' => ['type' => 'DATE'],
            'status' => ['type' => 'ENUM', 'constraint' => ['DRAFT', 'PRODUCTION', 'QC', 'APPROVED', 'PUBLISHED', 'COMPLETED', 'REVISION'], 'default' => 'DRAFT'],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'performance_metric_id' => ['type' => 'INT', 'null' => true],
            'performance_target' => ['type' => 'DECIMAL(14,2)', 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('deadline');
        $this->forge->addKey('created_by');
        $this->forge->addForeignKey('content_type_id', 'content_types', 'id', 'RESTRICT', 'SET NULL');
        $this->forge->addForeignKey('performance_metric_id', 'performance_metrics', 'id', 'RESTRICT', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('contents', true);

        // ── content_units (target unit terpilih) ────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'content_id' => ['type' => 'INT'],
            'unit_id' => ['type' => 'INT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['content_id', 'unit_id'], false, true);
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'CASCADE', 'CASCADE');
        $this->forge->createTable('content_units', true);

        // ── content_people (TALENT / CREATIVE) ──────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'content_id' => ['type' => 'INT'],
            'akun_id' => ['type' => 'INT'],
            'role' => ['type' => 'ENUM', 'constraint' => ['TALENT', 'CREATIVE']],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['content_id', 'akun_id', 'role'], false, true);
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('akun_id', 'akun', 'ID_AKUN', 'CASCADE', 'CASCADE');
        $this->forge->createTable('content_people', true);

        // ── publications ────────────────────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'content_id' => ['type' => 'INT'],
            'unit_id' => ['type' => 'INT'],
            'platform_id' => ['type' => 'INT'],
            'link' => ['type' => 'VARCHAR', 'constraint' => 1024, 'null' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['PLANNED', 'PUBLISHED'], 'default' => 'PLANNED'],
            'published_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('content_id');
        $this->forge->addKey('unit_id');
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('platform_id', 'platforms', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('publications', true);

        // ── publication_performance ─────────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'publication_id' => ['type' => 'INT'],
            'metric_id' => ['type' => 'INT'],
            'period_month' => ['type' => 'TINYINT'],
            'period_year' => ['type' => 'SMALLINT'],
            'target' => ['type' => 'DECIMAL(14,2)', 'default' => 0],
            'actual' => ['type' => 'DECIMAL(14,2)', 'default' => 0],
            'achievement' => ['type' => 'DECIMAL(14,2)', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['publication_id', 'metric_id', 'period_month', 'period_year'], false, true);
        $this->forge->addForeignKey('publication_id', 'publications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('metric_id', 'performance_metrics', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('publication_performance', true);

        // ── content_checklists (brand checklist per content) ────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'content_id' => ['type' => 'INT'],
            'item_id' => ['type' => 'INT'],
            'is_checked' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'checked_by' => ['type' => 'INT', 'null' => true],
            'checked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['content_id', 'item_id'], false, true);
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'brand_checklist_items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('checked_by', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('content_checklists', true);

        // ── content_qc (histori QC) ─────────────────────────────────
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'content_id' => ['type' => 'INT'],
            'status' => ['type' => 'ENUM', 'constraint' => ['PASS', 'REJECT']],
            'note' => ['type' => 'TEXT', 'null' => true],
            'checker_id' => ['type' => 'INT', 'null' => true],
            'checked_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('content_id');
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('checker_id', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('content_qc', true);
    }

    public function down()
    {
        $tables = [
            'content_qc',
            'content_checklists',
            'publication_performance',
            'publications',
            'content_people',
            'content_units',
            'contents',
            'brand_checklist_items',
            'performance_metrics',
            'content_types',
            'platforms',
        ];
        foreach ($tables as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}