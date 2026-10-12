<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHoTargets extends Migration
{
    public function up()
    {
        $db = &$this->db;

        $omsetCabangId = $db->table('kpi_components')
            ->select('id')
            ->where('code', 'OMSET_CABANG')
            ->get()
            ->getRow()->id ?? null;

        if (!$omsetCabangId) {
            throw new \RuntimeException('OMSET_CABANG component not found');
        }

        $hoTargets = [
            1 => 57000000,
            2 => 42000000,
            3 => 67000000,
            4 => 62000000,
            5 => 42000000,
        ];

        $hoPositions = [40];

        $data = [];
        foreach ($hoTargets as $unitId => $targetValue) {
            foreach ($hoPositions as $positionId) {
                $data[] = [
                    'kpi_component_id' => $omsetCabangId,
                    'unit_id' => $unitId,
                    'position_id' => $positionId,
                    'context' => 'default',
                    'target_value' => $targetValue,
                    'period_type' => 'monthly',
                    'period_month' => null,
                    'effective_from' => '2026-10-01',
                    'effective_to' => null,
                    'created_by' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
        }

        if (!empty($data)) {
            $db->table('kpi_targets')->insertBatch($data);
        }
    }

    public function down()
    {
        $db = &$this->db;

        $omsetCabangId = $db->table('kpi_components')
            ->select('id')
            ->where('code', 'OMSET_CABANG')
            ->get()
            ->getRow()->id ?? null;

        if (!$omsetCabangId) {
            return;
        }

        $db->table('kpi_targets')
            ->where('kpi_component_id', $omsetCabangId)
            ->whereIn('position_id', [40])
            ->where('effective_from >=', '2026-10-01')
            ->delete();
    }
}
