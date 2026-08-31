<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

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

        // 2. OPERASIONAL Targets (Component ID 13)
        $opComponentId = 13; 
        $contexts = [
            'gaji' => [
                1 => 45000000, 2 => 30000000, 3 => 55000000, 4 => 45000000
            ],
            'penilaian_kinerja' => [
                1 => 50000000, 2 => 30000000, 3 => 55000000, 4 => 50000000
            ],
            'slip_gaji' => [
                1 => 50000000, 2 => 30000000, 3 => 55000000, 4 => 50000000
            ]
        ];

        foreach ($contexts as $ctx => $units) {
            foreach ($units as $unitId => $val) {
                $this->db->table('kpi_targets')->upsert([
                    'kpi_component_id' => $opComponentId,
                    'unit_id' => $unitId,
                    'context' => $ctx,
                    'target_value' => $val,
                    'batas_keempat' => $val,
                    'period_type' => 'monthly',
                    'effective_from' => '2024-01-01',
                ]);
            }
        }
    }
}
