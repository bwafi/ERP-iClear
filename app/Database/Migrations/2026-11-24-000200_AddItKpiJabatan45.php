<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * KPI Team IT (jabatan 45): ganti komponen lama dengan 5 komponen objektif.
 *
 *   IT_DEVELOPMENT  25  commit `feat`  (target 15/bulan)
 *   IT_BUG_FIX      25  commit `fix`   (target 30/bulan)
 *   IT_BACKUP       25  hari backup sukses / hari dimonitor
 *   IT_UPTIME       15  hari uptime OK / hari dimonitor
 *   IT_DOCS         10  commit `docs`  (target 5/bulan)
 *
 * Berlaku mulai 2026-11-01. Baris lama (OMSET_TOKO, BUG_MINOR, BUG_OPERASIONAL,
 * ECOMMERCE, FITUR) ditutup 2026-10-31 agar skor periode lalu tidak berubah.
 *
 * Target disimpan di kpi_targets (unit_id NULL, position_id 45) supaya bisa
 * diubah tanpa deploy, sesuai aturan target KPI yang sudah ada.
 */
class AddItKpiJabatan45 extends Migration
{
    private const POSITION = 45;

    private const EFFECTIVE_FROM = '2026-11-01';

    /** code => [name, unit_of_measure, target] */
    private const COMPONENTS = [
        'IT_DEVELOPMENT' => ['name' => 'Development (Commit feat)', 'unit' => 'commit', 'target' => 15, 'weight' => 25],
        'IT_BUG_FIX'     => ['name' => 'Penyelesaian Bug (Commit fix)', 'unit' => 'commit', 'target' => 30, 'weight' => 25],
        'IT_BACKUP'      => ['name' => 'Backup Database', 'unit' => 'percent', 'target' => 100, 'weight' => 25],
        'IT_UPTIME'      => ['name' => 'System Uptime', 'unit' => 'percent', 'target' => 100, 'weight' => 15],
        'IT_DOCS'        => ['name' => 'Dokumentasi (Commit docs)', 'unit' => 'commit', 'target' => 5, 'weight' => 10],
    ];

    public function up()
    {
        $codeToId = [];

        foreach (self::COMPONENTS as $code => $meta) {
            $existing = $this->db->table('kpi_components')
                ->where('code', $code)
                ->get()->getRow();

            if ($existing) {
                $this->db->table('kpi_components')
                    ->where('id', $existing->id)
                    ->update([
                        'name'                 => $meta['name'],
                        'type'                 => 'automatic',
                        'category'             => 'technical',
                        'unit_of_measure'      => $meta['unit'],
                        'calculation_strategy' => null,
                        'is_active'            => 1,
                    ]);
                $codeToId[$code] = (int) $existing->id;
                continue;
            }

            $this->db->table('kpi_components')->insert([
                'code'                 => $code,
                'name'                 => $meta['name'],
                'type'                 => 'automatic',
                'category'             => 'technical',
                'unit_of_measure'      => $meta['unit'],
                'calculation_strategy' => null,
                'is_active'            => 1,
            ]);
            $codeToId[$code] = (int) $this->db->insertID();
        }

        $newIds = array_values($codeToId);

        // Bobot baru (hapus dulu kalau migrasi pernah dijalankan manual).
        $this->db->table('kpi_weights')
            ->where('position_id', self::POSITION)
            ->where('weight_group', 'kpi')
            ->whereIn('kpi_component_id', $newIds)
            ->delete();

        // Tutup bobot lama tepat sebelum periode baru.
        $this->db->table('kpi_weights')
            ->where('position_id', self::POSITION)
            ->where('weight_group', 'kpi')
            ->whereNotIn('kpi_component_id', $newIds)
            ->where('effective_to IS NULL')
            ->update(['effective_to' => '2026-10-31']);

        $weights = [];
        foreach (self::COMPONENTS as $code => $meta) {
            $weights[] = [
                'kpi_component_id' => $codeToId[$code],
                'position_id'      => self::POSITION,
                'weight'           => (float) $meta['weight'],
                'weight_group'     => 'kpi',
                'effective_from'   => self::EFFECTIVE_FROM,
                'effective_to'     => null,
                'created_by'       => null,
            ];
        }
        $this->db->table('kpi_weights')->insertBatch($weights);

        // Target: unit NULL (berlaku seluruh unit), khusus jabatan 45.
        $this->db->table('kpi_targets')
            ->where('position_id', self::POSITION)
            ->whereIn('kpi_component_id', $newIds)
            ->delete();

        $targets = [];
        foreach (self::COMPONENTS as $code => $meta) {
            $targets[] = [
                'kpi_component_id' => $codeToId[$code],
                'unit_id'          => null,
                'position_id'      => self::POSITION,
                'context'          => 'default',
                'target_value'     => (float) $meta['target'],
                'period_type'      => 'monthly',
                'period_month'     => null,
                'effective_from'   => self::EFFECTIVE_FROM,
                'effective_to'     => null,
                'created_by'       => null,
            ];
        }
        $this->db->table('kpi_targets')->insertBatch($targets);
    }

    public function down()
    {
        $ids = array_map(
            static fn ($row) => (int) $row->id,
            $this->db->table('kpi_components')
                ->whereIn('code', array_keys(self::COMPONENTS))
                ->get()->getResult()
        );

        if ($ids === []) {
            return;
        }

        $this->db->table('kpi_weights')
            ->where('position_id', self::POSITION)
            ->whereIn('kpi_component_id', $ids)
            ->delete();

        $this->db->table('kpi_targets')
            ->where('position_id', self::POSITION)
            ->whereIn('kpi_component_id', $ids)
            ->delete();

        // Buka kembali bobot lama yang ditutup migrasi ini.
        $this->db->table('kpi_weights')
            ->where('position_id', self::POSITION)
            ->where('weight_group', 'kpi')
            ->where('effective_to', '2026-10-31')
            ->update(['effective_to' => null]);

        $this->db->table('kpi_components')->whereIn('id', $ids)->delete();
    }
}
