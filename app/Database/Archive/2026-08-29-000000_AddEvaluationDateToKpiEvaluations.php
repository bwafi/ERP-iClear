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
 */
class AddEvaluationDateToKpiEvaluations extends Migration
{
    public function up()
    {
        // 1. Add evaluation_date if not exists
        if (!$this->db->fieldExists('evaluation_date', 'kpi_evaluations')) {
            $this->forge->addColumn('kpi_evaluations', [
                'evaluation_date' => [
                    'type'       => 'DATE',
                    'null'       => true,
                    'after'      => 'evaluator_id',
                ],
            ]);
        }

        // 2. Drop Foreign Keys first (Required to drop the index used by them)
        $this->db->query('ALTER TABLE `kpi_evaluations` DROP FOREIGN KEY IF EXISTS `kpi_evaluations_employee_id_foreign`');
        $this->db->query('ALTER TABLE `kpi_evaluations` DROP FOREIGN KEY IF EXISTS `kpi_evaluations_kpi_component_id_foreign`');

        // 3. Drop obsolete monthly uniqueness if exists
        $indexRows = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name IN ('uq_emp_kpi_period', 'employee_id_kpi_component_id_period_year_period_month')")->getResultArray();
        $droppedNames = [];
        foreach ($indexRows as $idx) {
            $name = $idx['Key_name'];
            if (!in_array($name, $droppedNames, true)) {
                $this->db->query("ALTER TABLE `kpi_evaluations` DROP INDEX `{$name}`");
                $droppedNames[] = $name;
            }
        }

        // 4. Re-create Foreign Keys (MySQL will automatically create necessary indexes)
        $this->db->query('ALTER TABLE `kpi_evaluations` ADD CONSTRAINT `kpi_evaluations_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `akun` (`ID_AKUN`) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->db->query('ALTER TABLE `kpi_evaluations` ADD CONSTRAINT `kpi_evaluations_kpi_component_id_foreign` FOREIGN KEY (`kpi_component_id`) REFERENCES `kpi_components` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT');

        // 5. New daily uniqueness: employee + component + date
        $dailyIdx = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name = 'uq_emp_kpi_date'")->getResultArray();
        if (empty($dailyIdx)) {
            $this->db->query('ALTER TABLE `kpi_evaluations` ADD UNIQUE KEY `uq_emp_kpi_date` (`employee_id`,`kpi_component_id`,`evaluation_date`)');
        }

        // 6. Index for date-range grouping/aggregation
        $evalDateIdx = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name = 'idx_evaluation_date'")->getResultArray();
        if (empty($evalDateIdx)) {
            $this->db->query('ALTER TABLE `kpi_evaluations` ADD KEY `idx_evaluation_date` (`evaluation_date`)');
        }
    }

    public function down()
    {
        // Rollback logic if needed, usually dropping the added column and index
        // For this refactor, since it's a structural change, we primarily ensure 'up' works.
    }
}
