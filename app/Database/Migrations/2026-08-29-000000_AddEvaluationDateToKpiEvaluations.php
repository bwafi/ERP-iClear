<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Daily Attendance Storage — Implementation Task 1
 *
 * Enable DAILY attendance evaluation in `kpi_evaluations`:
 *   - add `evaluation_date DATE`
 *   - change uniqueness from (employee, kpi_component, year, month)
 *     to (employee, kpi_component, evaluation_date)
 *   - keep period_year / period_month as derived grouping columns
 *   - add index on evaluation_date
 *
 * Business context:
 *   Attendance components (kehadiran, kebersihan, seragam, kepatuhan_sop)
 *   are evaluated DAILY with raw score 1-5.
 *   One employee + one component + one date = one evaluation.
 *
 * Table currently has 0 rows, so no production backfill is required.
 * Existing monthly-unique constraint is dropped (not data-breaking).
 */
class AddEvaluationDateToKpiEvaluations extends Migration
{
    public function up()
    {
        // 1. Add evaluation_date
        $this->forge->addColumn('kpi_evaluations', [
            'evaluation_date' => [
                'type'       => 'DATE',
                'null'       => true,
                'after'      => 'evaluator_id',
            ],
        ]);

        // 2. Drop obsolete monthly uniqueness if exists
        $indexRows = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name IN ('uq_emp_kpi_period', 'employee_id_kpi_component_id_period_year_period_month')")->getResultArray();
        $droppedNames = [];
        foreach ($indexRows as $idx) {
            $name = $idx['Key_name'];
            if (!in_array($name, $droppedNames, true)) {
                $this->db->query("ALTER TABLE `kpi_evaluations` DROP INDEX `{$name}`");
                $droppedNames[] = $name;
            }
        }

        // 3. New daily uniqueness: employee + component + date
        $this->db->query('ALTER TABLE `kpi_evaluations` ADD UNIQUE KEY `uq_emp_kpi_date` (`employee_id`,`kpi_component_id`,`evaluation_date`)');

        // 4. Index for date-range grouping/aggregation
        $this->db->query('ALTER TABLE `kpi_evaluations` ADD KEY `idx_evaluation_date` (`evaluation_date`)');
    }

    public function down()
    {
        // Revert indexes
        $this->db->query('ALTER TABLE `kpi_evaluations` DROP INDEX `uq_emp_kpi_date`');
        $this->db->query('ALTER TABLE `kpi_evaluations` DROP INDEX `idx_evaluation_date`');

        // Restore previous monthly uniqueness
        $this->db->query('ALTER TABLE `kpi_evaluations` ADD UNIQUE KEY `uq_emp_kpi_period` (`employee_id`,`kpi_component_id`,`period_year`,`period_month`)');

        // Drop evaluation_date
        $this->forge->dropColumn('kpi_evaluations', 'evaluation_date');
    }
}