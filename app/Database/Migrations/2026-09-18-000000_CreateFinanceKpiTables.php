<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dashboard Finance + KPI Finance — Fase 1.
 *
 * Tabel:
 *  - finance_kpi_records : rekaman skor per (unit, KPI, periode)
 *  - finance_omzet_daily : input omzet Sheet harian + snapshot omzet ERP
 */
class CreateFinanceKpiTables extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // 1. finance_kpi_records
        // ---------------------------------------------------------------
        if (!$this->db->tableExists('finance_kpi_records')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'unit_id' => [
                    'type'   => 'INT',
                    'constraint' => 11,
                    'null'   => false,
                ],
                'period_year' => [
                    'type'     => 'SMALLINT',
                    'null'     => false,
                ],
                'period_month' => [
                    'type'     => 'TINYINT',
                    'null'     => false,
                ],
                'kpi_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => false,
                ],
                'mode' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => false,
                    'default'    => 'auto',
                ],
                'score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'contribution' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'weight' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'detail_json' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'evaluator_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'evaluated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
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
            $this->forge->addUniqueKey(
                ['unit_id', 'kpi_code', 'period_year', 'period_month'],
                'uq_finance_kpi_period'
            );
            $this->forge->createTable('finance_kpi_records', true);
        }

        // ---------------------------------------------------------------
        // 2. finance_omzet_daily
        // ---------------------------------------------------------------
        if (!$this->db->tableExists('finance_omzet_daily')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'unit_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => false,
                ],
                'tanggal' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'omzet_erp' => [
                    'type' => 'BIGINT',
                    'null' => true,
                ],
                'omzet_sheet' => [
                    'type' => 'BIGINT',
                    'null' => true,
                ],
                'selisih' => [
                    'type' => 'BIGINT',
                    'null' => true,
                ],
                'is_match' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => true,
                ],
                'input_by' => [
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
            $this->forge->addUniqueKey(['unit_id', 'tanggal'], 'uq_finance_omzet_daily');
            $this->forge->createTable('finance_omzet_daily', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('finance_omzet_daily', true);
        $this->forge->dropTable('finance_kpi_records', true);
    }
}