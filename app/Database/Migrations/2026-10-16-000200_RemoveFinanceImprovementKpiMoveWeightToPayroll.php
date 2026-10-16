<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hapus KPI "Improvement Finance" (jabatan 0) dan pindahkan bobotnya ke Payroll.
 *
 * Keputusan bisnis 2026-10-16: KPI Improvement Finance dihapus. Bobotnya (5)
 * dipindahkan ke Payroll sehingga menjadi 10, agar total tetap 100:
 *   kesehatan_uang 30, akurasi 20, cash_flow 15, hutang_piutang 10,
 *   rekonsiliasi 10, payroll 10, compliance 5  → total 100.
 *
 * Yang dilakukan:
 *  1. Hapus baris kpi_weights untuk komponen FIN_IMPROVEMENT (posisi 0).
 *  2. Hapus baris referensi lain (kpi_targets, kpi_evaluations, incentive_rules)
 *     bila ada — FK-nya RESTRICT sehingga harus dibersihkan sebelum komponen.
 *  3. Hapus komponen kpi_components code = FIN_IMPROVEMENT.
 *  4. Ubah bobot kpi_weights payroll (posisi 0) dari 5 menjadi 10.
 *  5. Hapus riwayat penilaian manual finance_kpi_records kpi_code = 'improvement'
 *     (KPI-nya sudah tidak ada; kalau dibiarkan tetap muncul di "Riwayat Manual").
 *
 * CATATAN: langkah 5 bersifat destruktif dan TIDAK dipulihkan di down().
 * Komponen + bobot dipulihkan, tetapi riwayat manual yang sudah dihapus tidak.
 */
class RemoveFinanceImprovementKpiMoveWeightToPayroll extends Migration
{
    /** ID_JABATAN Finance. */
    private const FINANCE_POSITION = 0;

    private const REMOVE_CODE = 'FIN_IMPROVEMENT';
    private const PAYROLL_CODE = 'FIN_PAYROLL';
    private const PAYROLL_WEIGHT = 10;   // sebelumnya 5
    private const IMPROVEMENT_WEIGHT = 5;

    /** Tabel yang punya FK RESTRICT ke kpi_components.id. */
    private const REFERENCING_TABLES = ['kpi_weights', 'kpi_targets', 'kpi_evaluations', 'incentive_rules'];

    public function up()
    {
        $ids = $this->componentIds([self::REMOVE_CODE]);

        if (! empty($ids)) {
            foreach (self::REFERENCING_TABLES as $table) {
                if ($this->db->tableExists($table)) {
                    $this->db->table($table)->whereIn('kpi_component_id', $ids)->delete();
                }
            }

            $this->db->table('kpi_components')->whereIn('id', $ids)->delete();
        }

        // Payroll 5 -> 10 (menyerap bobot Improvement).
        foreach ($this->componentIds([self::PAYROLL_CODE]) as $payrollId) {
            $this->db->table('kpi_weights')
                ->where('position_id', self::FINANCE_POSITION)
                ->where('kpi_component_id', $payrollId)
                ->where('weight_group', 'kpi')
                ->update(['weight' => (float) self::PAYROLL_WEIGHT]);
        }

        // Riwayat penilaian manual KPI yang sudah dihapus.
        if ($this->db->tableExists('finance_kpi_records')) {
            $this->db->table('finance_kpi_records')
                ->where('kpi_code', strtolower(preg_replace('/^FIN_/', '', self::REMOVE_CODE)))
                ->delete();
        }
    }

    public function down()
    {
        // Pulihkan komponen Improvement Finance.
        $id = $this->ensureComponent(self::REMOVE_CODE, 'Improvement Finance');

        // Pulihkan bobotnya (5) di posisi 0.
        $exists = $this->db->table('kpi_weights')
            ->where('position_id', self::FINANCE_POSITION)
            ->where('kpi_component_id', $id)
            ->where('weight_group', 'kpi')
            ->countAllResults();

        if ($exists === 0) {
            $this->db->table('kpi_weights')->insert([
                'kpi_component_id' => $id,
                'position_id'      => self::FINANCE_POSITION,
                'weight'           => (float) self::IMPROVEMENT_WEIGHT,
                'weight_group'     => 'kpi',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ]);
        }

        // Payroll 10 -> 5.
        foreach ($this->componentIds([self::PAYROLL_CODE]) as $payrollId) {
            $this->db->table('kpi_weights')
                ->where('position_id', self::FINANCE_POSITION)
                ->where('kpi_component_id', $payrollId)
                ->where('weight_group', 'kpi')
                ->update(['weight' => (float) self::IMPROVEMENT_WEIGHT]);
        }
    }

    /** @return int[] */
    private function componentIds(array $codes): array
    {
        $rows = $this->db->table('kpi_components')
            ->whereIn('code', $codes)
            ->get()->getResult();

        return array_map(static fn ($r) => (int) $r->id, $rows);
    }

    private function ensureComponent(string $code, string $name): int
    {
        $existing = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();

        if ($existing) {
            return (int) $existing->id;
        }

        $this->db->table('kpi_components')->insert([
            'code'                 => $code,
            'name'                 => $name,
            'type'                 => 'automatic',
            'calculation_strategy' => '',
            'category'             => 'finance',
            'is_active'            => 1,
        ]);

        return (int) $this->db->insertID();
    }
}
