<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateKPIConfigurationSchema extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'type' => [
                'type' => 'ENUM',
                'constraint' => ['automatic', 'manual'],
            ],
            'category' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'unit_of_measure' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'calculation_method'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code', false, true);
        $this->forge->addKey('type');
        $this->forge->addKey('category');
        $this->forge->createTable('kpi_components');

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],
            'kpi_component_id' => [
                'type' => 'INT',
            ],
            'position_id' => [
                'type' => 'INT',
            ],
            'weight' => [
                'type' => 'DECIMAL',
                'constraint' => [5, 2],
            ],
            'weight_group' => [
                'type' => 'ENUM',
                'constraint' => ['kpi', 'absen', 'behavior', 'operational', 'other'],
                'default' => 'kpi',
            ],
            'effective_from' => [
                'type' => 'DATE',
            ],
            'effective_to' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('kpi_component_id', 'kpi_components', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('position_id', 'jabatan', 'ID_JABATAN', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'SET NULL', 'SET NULL');
        $this->forge->addKey(['position_id', 'weight_group', 'effective_from', 'effective_to']);
        $this->forge->addKey('effective_to');
        $this->forge->createTable('kpi_weights');

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],
            'kpi_component_id' => [
                'type' => 'INT',
            ],
            'unit_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'position_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'target_value' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'context' => [
                'type' => 'ENUM',
                'constraint' => ['gaji', 'penilaian_kinerja', 'slip_gaji', 'default'],
                'default' => 'default',
            ],
            'batas_awal' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'batas_kedua' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'batas_ketiga' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'batas_keempat' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'period_type' => [
                'type' => 'ENUM',
                'constraint' => ['monthly', 'quarterly', 'annual'],
                'default' => 'monthly',
            ],
            'period_month' => [
                'type' => 'INT',
                'null' => true,
            ],
            'effective_from' => [
                'type' => 'DATE',
            ],
            'effective_to' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('kpi_component_id', 'kpi_components', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('position_id', 'jabatan', 'ID_JABATAN', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'SET NULL', 'SET NULL');
        $this->forge->addKey(['kpi_component_id', 'unit_id', 'effective_from']);
        $this->forge->addKey('effective_to');
        $this->forge->createTable('kpi_targets');

        $this->forge->addField([
            'id'                   => ['type' => 'INT', 'auto_increment' => true],
            'employee_id'          => ['type' => 'INT'],
            'kpi_component_id'     => ['type' => 'INT'],
            'evaluator_id'         => ['type' => 'INT'],
            'raw_score'            => ['type' => 'DECIMAL', 'constraint' => [5, 2]],
            'max_score'            => ['type' => 'DECIMAL', 'constraint' => [5, 2], 'default' => 5],
            'normalized_score'     => ['type' => 'DECIMAL', 'constraint' => [5, 2]],
            'weighted_score'       => ['type' => 'DECIMAL', 'constraint' => [5, 2]],
            'notes'                => ['type' => 'TEXT', 'null' => true],
            'period_year'          => ['type' => 'INT'],
            'period_month'         => ['type' => 'INT'],
            'created_at'           => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at'           => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('employee_id', 'akun', 'ID_AKUN', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('kpi_component_id', 'kpi_components', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('evaluator_id', 'akun', 'ID_AKUN', 'RESTRICT', 'RESTRICT');
        $this->forge->addUniqueKey(['employee_id', 'kpi_component_id', 'period_year', 'period_month']);
        $this->forge->addKey(['period_year', 'period_month']);
        $this->forge->addKey('evaluator_id');
        $this->forge->createTable('kpi_evaluations');

        // 4b. incentive_groups
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'auto_increment' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'is_active'      => ['type' => 'BOOLEAN', 'default' => 1],
            'effective_from' => ['type' => 'DATE'],
            'effective_to'   => ['type' => 'DATE', 'null' => true],
            'created_at'     => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at'     => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code', false, true);
        $this->forge->createTable('incentive_groups');

        // 4c. incentive_members (per unit + periode)
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'auto_increment' => true],
            'incentive_group_id' => ['type' => 'INT'],
            'employee_id'        => ['type' => 'INT'],
            'unit_id'            => ['type' => 'INT'],
            'effective_from'     => ['type' => 'DATE'],
            'effective_to'       => ['type' => 'DATE', 'null' => true],
            'is_active'          => ['type' => 'BOOLEAN', 'default' => 1],
            'created_at'         => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at'         => ['type' => 'TIMESTAMP', 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('incentive_group_id', 'incentive_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('employee_id', 'akun', 'ID_AKUN', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'RESTRICT', 'RESTRICT');
        $this->forge->addKey(['incentive_group_id', 'unit_id', 'effective_from', 'effective_to']);
        $this->forge->createTable('incentive_members');

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],
            'incentive_group_id' => [
                'type' => 'INT',
            ],
            'kpi_component_id' => [
                'type' => 'INT',
            ],
            'incentive_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'calculation_type' => [
                'type' => 'ENUM',
                'constraint' => ['percentage', 'tier', 'flat'],
                'default' => 'percentage',
            ],
            'base_value' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'minimum_achievement' => [
                'type' => 'DECIMAL',
                'constraint' => [5, 2],
                'default' => 100,
            ],
            'division_method' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'effective_from' => [
                'type' => 'DATE',
            ],
            'effective_to' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('incentive_group_id', 'incentive_groups', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('kpi_component_id', 'kpi_components', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'SET NULL', 'SET NULL');
        $this->forge->addKey(['incentive_group_id', 'kpi_component_id']);
        $this->forge->addKey('effective_to');
        $this->forge->createTable('incentive_rules');

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'type' => [
                'type' => 'ENUM',
                'constraint' => ['base', 'allowance', 'deduction', 'incentive'],
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'default_value' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => 1,
            ],
            'is_configurable' => [
                'type' => 'BOOLEAN',
                'default' => 1,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('type');
        $this->forge->createTable('salary_components');

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],
            'position_id' => [
                'type' => 'INT',
            ],
            'salary_component_id' => [
                'type' => 'INT',
            ],
            'unit_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'context' => [
                'type' => 'ENUM',
                'constraint' => ['gaji', 'penilaian_kinerja', 'slip_gaji', 'default'],
                'default' => 'default',
            ],
            'base_value' => [
                'type' => 'DECIMAL',
                'constraint' => [15, 2],
                'null' => true,
            ],
            'calculation_type' => [
                'type' => 'ENUM',
                'constraint' => ['fixed', 'percent_of_base', 'percent_of_kpi'],
                'default' => 'fixed',
            ],
            'multiplier' => [
                'type' => 'DECIMAL',
                'constraint' => [5, 2],
                'null' => true,
            ],
            'effective_from' => [
                'type' => 'DATE',
            ],
            'effective_to' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'default' => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('position_id', 'jabatan', 'ID_JABATAN', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('salary_component_id', 'salary_components', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'akun', 'ID_AKUN', 'SET NULL', 'SET NULL');
        $this->forge->addUniqueKey(['position_id', 'salary_component_id', 'unit_id', 'context', 'effective_from']);
        $this->forge->addKey(['position_id', 'effective_from']);
        $this->forge->createTable('salary_structures');
    }

    public function down()
    {
        $this->forge->dropTable('salary_structures');
        $this->forge->dropTable('salary_components');
        $this->forge->dropTable('incentive_rules');
        $this->forge->dropTable('incentive_members');
        $this->forge->dropTable('incentive_groups');
        $this->forge->dropTable('kpi_evaluations');
        $this->forge->dropTable('kpi_targets');
        $this->forge->dropTable('kpi_weights');
        $this->forge->dropTable('kpi_components');
    }
}
