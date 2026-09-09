<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambahkan komponen KPI Konten (KPI Multimedia/Creative) ke engine KPI
 * existing. Komponen tipe automatic namun dihitung langsung oleh
 * ContentKpiService (branch KpiCalculationService utk position 44),
 * mirip pola Supervisor (position 40).
 */
class AddKontenKpiComponents extends Migration
{
    protected const COMPONENTS = [
        ['code' => 'KONTEN_JUMLAH',   'name' => 'Jumlah Konten',       'weight' => 20],
        ['code' => 'KONTEN_DEADLINE', 'name' => 'Ketepatan Deadline',  'weight' => 20],
        ['code' => 'KONTEN_KUALITAS', 'name' => 'Kualitas Konten',     'weight' => 25],
        ['code' => 'KONTEN_BRAND',    'name' => 'Konsistensi Brand',   'weight' => 15],
        ['code' => 'KONTEN_PERFORMA', 'name' => 'Performa Konten',     'weight' => 20],
    ];

    public function up()
    {
        $now = date('Y-m-d H:i:s');
        $componentRows = [];
        foreach (self::COMPONENTS as $c) {
            $componentRows[] = [
                'code'                => $c['code'],
                'name'                => $c['name'],
                'description'         => 'KPI Konten (Multimedia/Creative) — dihitung ContentKpiService dari data operasional modul Konten.',
                'type'                => 'automatic',
                'category'            => 'marketing',
                'unit_of_measure'     => 'percent',
                'calculation_strategy' => null,
                'is_active'           => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }
        $this->db->table('kpi_components')->insertBatch($componentRows);

        $componentIds = [];
        foreach (self::COMPONENTS as $c) {
            $componentIds[$c['code']] = (int)$this->db->table('kpi_components')
                ->where('code', $c['code'])
                ->get()->getRow()->id;
        }

        $weightRows = array_map(
            fn(array $c) => [
                'kpi_component_id' => $componentIds[$c['code']],
                'position_id'      => 44, // Multimedia / Creative
                'weight'           => $c['weight'],
                'weight_group'     => 'kpi',
                'effective_from'   => '2026-09-01',
                'effective_to'     => null,
                'created_by'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            self::COMPONENTS
        );
        $this->db->table('kpi_weights')->insertBatch($weightRows);
    }

    public function down()
    {
        $codes = array_column(self::COMPONENTS, 'code');
        $componentIds = $this->db->table('kpi_components')
            ->whereIn('code', $codes)
            ->get()
            ->getResult();

        foreach ($componentIds as $c) {
            $this->db->table('kpi_weights')->where('kpi_component_id', $c->id)->delete();
        }
        $this->db->table('kpi_components')->whereIn('code', $codes)->delete();
    }
}

