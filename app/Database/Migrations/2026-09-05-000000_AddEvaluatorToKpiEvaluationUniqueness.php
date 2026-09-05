<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Allow multiple evaluators per (employee, component, date).
 *
 * Kasus rata-rata (Manager dinilai SPV + Kepala Divisi + IT + Admin Center)
 * membutuhkan penyimpanan beberapa baris evaluasi per komponen/tanggal.
 *  - drop uq_emp_kpi_date (employee, component, date)
 *  - add  uq_emp_kpi_eval_date (employee, component, EVALUATOR, date)
 */
class AddEvaluatorToKpiEvaluationUniqueness extends Migration
{
    public function up()
    {
        $indexRows = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name = 'uq_emp_kpi_date'")->getResultArray();

        if (!empty($indexRows)) {
            $this->db->query('ALTER TABLE `kpi_evaluations` DROP INDEX `uq_emp_kpi_date`');
        }

        $newRows = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name = 'uq_emp_kpi_eval_date'")->getResultArray();

        if (empty($newRows)) {
            $this->db->query('ALTER TABLE `kpi_evaluations` ADD UNIQUE KEY `uq_emp_kpi_eval_date` (`employee_id`,`kpi_component_id`,`evaluator_id`,`evaluation_date`)');
        }
    }

    public function down()
    {
        $newRows = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name = 'uq_emp_kpi_eval_date'")->getResultArray();

        if (!empty($newRows)) {
            $this->db->query('ALTER TABLE `kpi_evaluations` DROP INDEX `uq_emp_kpi_eval_date`');
        }

        $oldRows = $this->db->query("SHOW INDEX FROM `kpi_evaluations` WHERE Key_name = 'uq_emp_kpi_date'")->getResultArray();

        if (empty($oldRows)) {
            $this->db->query('ALTER TABLE `kpi_evaluations` ADD UNIQUE KEY `uq_emp_kpi_date` (`employee_id`,`kpi_component_id`,`evaluation_date`)');
        }
    }
}