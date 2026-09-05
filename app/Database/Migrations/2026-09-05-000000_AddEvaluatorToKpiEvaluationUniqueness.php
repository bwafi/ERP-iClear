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
 *
 * CATATAN: index uq_emp_kpi_date dipakai FK employee_id / kpi_component_id.
 * MySQL tidak mengizinkan DROP INDEX selama index masih dibutuhkan FK,
 * jadi FK pada tabel dilepas dulu, index diganti, kemudian FK dibuat ulang.
 */
class AddEvaluatorToKpiEvaluationUniqueness extends Migration
{
    private function tableForeignKeys(): array
    {
        $rows = $this->db->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kpi_evaluations'
               AND REFERENCED_TABLE_NAME IS NOT NULL"
        )->getResultArray();

        return array_column($rows, 'CONSTRAINT_NAME');
    }

    private function indexExists(string $keyName): bool
    {
        return !empty($this->db->query(
            "SHOW INDEX FROM `kpi_evaluations` WHERE Key_name = '{$keyName}'"
        )->getResultArray());
    }

    private function dropAllForeignKeys(): void
    {
        foreach ($this->tableForeignKeys() as $constraint) {
            $this->db->query("ALTER TABLE `kpi_evaluations` DROP FOREIGN KEY `{$constraint}`");
        }
    }

    private function recreateForeignKeys(): void
    {
        $existing = $this->tableForeignKeys();

        $definitions = [
            'kpi_evaluations_employee_id_foreign' =>
                'FOREIGN KEY (`employee_id`) REFERENCES `akun` (`ID_AKUN`) ON DELETE RESTRICT ON UPDATE RESTRICT',
            'kpi_evaluations_kpi_component_id_foreign' =>
                'FOREIGN KEY (`kpi_component_id`) REFERENCES `kpi_components` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT',
            'kpi_evaluations_evaluator_id_foreign' =>
                'FOREIGN KEY (`evaluator_id`) REFERENCES `akun` (`ID_AKUN`) ON DELETE RESTRICT ON UPDATE RESTRICT',
        ];

        foreach ($definitions as $constraint => $definition) {
            if (!in_array($constraint, $existing, true)) {
                $this->db->query("ALTER TABLE `kpi_evaluations` ADD CONSTRAINT `{$constraint}` {$definition}");
            }
        }
    }

    public function up()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $this->dropAllForeignKeys();

        if ($this->indexExists('uq_emp_kpi_date')) {
            $this->db->query('ALTER TABLE `kpi_evaluations` DROP INDEX `uq_emp_kpi_date`');
        }

        if (!$this->indexExists('uq_emp_kpi_eval_date')) {
            $this->db->query('ALTER TABLE `kpi_evaluations` ADD UNIQUE KEY `uq_emp_kpi_eval_date` (`employee_id`,`kpi_component_id`,`evaluator_id`,`evaluation_date`)');
        }

        $this->recreateForeignKeys();

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $this->dropAllForeignKeys();

        if ($this->indexExists('uq_emp_kpi_eval_date')) {
            $this->db->query('ALTER TABLE `kpi_evaluations` DROP INDEX `uq_emp_kpi_eval_date`');
        }

        if (!$this->indexExists('uq_emp_kpi_date')) {
            $this->db->query('ALTER TABLE `kpi_evaluations` ADD UNIQUE KEY `uq_emp_kpi_date` (`employee_id`,`kpi_component_id`,`evaluation_date`)');
        }

        $this->recreateForeignKeys();

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}