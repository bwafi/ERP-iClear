<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Konfigurasi KPI kecil (SPV, OPERASIONAL, TUTUP_KASIR/STOK_OPNAME/CUSTOMER).
 *
 * Konteks target sudah dikonsolidasi jadi SATU ('default') dan kolom batas_*
 * sudah tidak ada — seluruh target memakai target_value.
 */
class KpiConfigSeeder extends Seeder
{
    public function run()
    {
        // 1. SPV Attendance Weights (Position 40)
        $attendanceWeights = [
            ['code' => 'KEHADIRAN', 'weight' => 40],
            ['code' => 'KEBERSIHAN', 'weight' => 20],
            ['code' => 'SERAGAM', 'weight' => 20],
            ['code' => 'KEPATUHAN_SOP', 'weight' => 20],
        ];

        foreach ($attendanceWeights as $row) {
            $component = $this->db->table('kpi_components')
                ->where('code', $row['code'])
                ->get()->getRow();

            if ($component) {
                $this->db->table('kpi_weights')->upsert([
                    'position_id' => 40,
                    'kpi_component_id' => $component->id,
                    'weight' => $row['weight'],
                    'weight_group' => 'absen',
                    'effective_from' => '2024-01-01',
                ]);
            }
        }

        // 2. OPERASIONAL Targets (Component untuk SPV) — nilai penilaian_kinerja (menang).
        $opComponentId = 13;
        $opTargets = [
            1 => 50000000, 2 => 30000000, 3 => 55000000, 4 => 50000000
        ];

        foreach ($opTargets as $unitId => $val) {
            $this->db->table('kpi_targets')->upsert([
                'kpi_component_id' => $opComponentId,
                'unit_id' => $unitId,
                'context' => 'default',
                'target_value' => $val,
                'period_type' => 'monthly',
                'effective_from' => '2024-01-01',
            ]);
        }

        // 3. TUTUP_KASIR & STOK_OPNAME Targets (satu konteks default)
        $tutupKasirComp = $this->db->table('kpi_components')->where('code', 'TUTUP_KASIR')->get()->getRow();
        $stokOpnameComp = $this->db->table('kpi_components')->where('code', 'STOK_OPNAME')->get()->getRow();
        $customerComp   = $this->db->table('kpi_components')->where('code', 'CUSTOMER_COUNT')->get()->getRow();

        $custConfig = [
            1 => ['target' => 220],
            2 => ['target' => 180],
            3 => ['target' => 350],
            4 => ['target' => 250],
        ];

        foreach ([1, 2, 3, 4] as $uId) {
            if ($tutupKasirComp) {
                $this->db->table('kpi_targets')->upsert([
                    'kpi_component_id' => $tutupKasirComp->id,
                    'unit_id'          => $uId,
                    'context'          => 'default',
                    'target_value'     => 30.00,
                    'period_type'      => 'monthly',
                    'effective_from'   => '2024-01-01',
                ]);
            }

            if ($stokOpnameComp) {
                $this->db->table('kpi_targets')->upsert([
                    'kpi_component_id' => $stokOpnameComp->id,
                    'unit_id'          => $uId,
                    'context'          => 'default',
                    'target_value'     => 4.00,
                    'period_type'      => 'monthly',
                    'effective_from'   => '2024-01-01',
                ]);
            }

            if ($customerComp && isset($custConfig[$uId])) {
                $this->db->table('kpi_targets')->upsert([
                    'kpi_component_id' => $customerComp->id,
                    'unit_id'          => $uId,
                    'context'          => 'default',
                    'target_value'     => $custConfig[$uId]['target'],
                    'period_type'      => 'monthly',
                    'effective_from'   => '2024-01-01',
                ]);
            }
        }
    }
}