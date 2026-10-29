<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti IMPROVEMENT (5) menjadi BUDGETING (5) untuk Kepala Divisi (43).
 *
 * Aturan: batas belanja iklan Rp15.000.000/bulan. Aktual di atas batas → skor 0.
 * Bobot IMPROVEMENT posisi 43 ditutup per 2026-09-30 agar skor periode lama
 * tidak berubah; BUDGETING berlaku mulai 2026-10-01. Jabatan 44 (Multimedia)
 * tetap memakai IMPROVEMENT.
 */
class GantiImprovementJabatan43DenganBudgeting extends Migration
{
    private const POSITION = 43;
    private const BUDGET = 15000000;
    private const EFFECTIVE_FROM = '2026-10-01';
    private const EFFECTIVE_TO = '2026-09-30';

    public function up()
    {
        $budgetingId = $this->ensureComponent('BUDGETING', 'Budgeting');
        $improvementId = $this->componentId('IMPROVEMENT');

        // Tutup bobot IMPROVEMENT posisi 43 yang masih terbuka (idempoten).
        if ($improvementId) {
            $this->db->table('kpi_weights')
                ->where('position_id', self::POSITION)
                ->where('kpi_component_id', $improvementId)
                ->where('weight_group', 'kpi')
                ->where('effective_to', null)
                ->update(['effective_to' => self::EFFECTIVE_TO]);
        }

        $exists = $this->db->table('kpi_weights')
            ->where('position_id', self::POSITION)
            ->where('kpi_component_id', $budgetingId)
            ->where('weight_group', 'kpi')
            ->where('effective_from', self::EFFECTIVE_FROM)
            ->countAllResults();
        if (!$exists) {
            $this->db->table('kpi_weights')->insert([
                'kpi_component_id' => $budgetingId,
                'position_id'      => self::POSITION,
                'weight'           => 5,
                'weight_group'     => 'kpi',
                'effective_from'   => self::EFFECTIVE_FROM,
                'effective_to'     => null,
                'created_by'       => null,
            ]);
        }

        foreach ([50, null] as $unitId) {
            $builder = $this->db->table('kpi_targets')
                ->where('kpi_component_id', $budgetingId)
                ->where('context', 'default')
                ->where('effective_from', self::EFFECTIVE_FROM);
            if ($unitId === null) {
                $builder->where('unit_id', null);
            } else {
                $builder->where('unit_id', $unitId);
            }
            if ($builder->countAllResults()) {
                continue;
            }
            $this->db->table('kpi_targets')->insert([
                'kpi_component_id' => $budgetingId,
                'unit_id'          => $unitId,
                'position_id'      => self::POSITION,
                'target_value'     => self::BUDGET,
                'context'          => 'default',
                'period_type'      => 'monthly',
                'period_month'     => null,
                'effective_from'   => self::EFFECTIVE_FROM,
                'effective_to'     => null,
                'created_by'       => null,
            ]);
        }
    }

    public function down()
    {
        $budgetingId = $this->componentId('BUDGETING');
        if ($budgetingId) {
            $this->db->table('kpi_weights')
                ->where('position_id', self::POSITION)
                ->where('kpi_component_id', $budgetingId)
                ->where('weight_group', 'kpi')
                ->where('effective_from', self::EFFECTIVE_FROM)
                ->delete();
            $this->db->table('kpi_targets')
                ->where('kpi_component_id', $budgetingId)
                ->where('period_type', 'monthly')
                ->where('context', 'default')
                ->where('effective_from', self::EFFECTIVE_FROM)
                ->delete();
        }

        $improvementId = $this->componentId('IMPROVEMENT');
        if ($improvementId) {
            $this->db->table('kpi_weights')
                ->where('position_id', self::POSITION)
                ->where('kpi_component_id', $improvementId)
                ->where('weight_group', 'kpi')
                ->where('effective_to', self::EFFECTIVE_TO)
                ->update(['effective_to' => null]);
        }
    }

    private function componentId(string $code): ?int
    {
        $row = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();

        return $row ? (int)$row->id : null;
    }

    private function ensureComponent(string $code, string $name): int
    {
        $id = $this->componentId($code);
        if ($id !== null) {
            $this->db->table('kpi_components')->where('id', $id)->update([
                'name'      => $name,
                'is_active' => 1,
            ]);

            return $id;
        }

        $this->db->table('kpi_components')->insert([
            'code'                 => $code,
            'name'                 => $name,
            'type'                 => 'automatic',
            'calculation_strategy' => '',
            'is_active'            => 1,
        ]);

        return (int)$this->db->insertID();
    }
}
