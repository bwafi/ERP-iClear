<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * SupervisorKpiSeeder — KPI Area Supervisor / SPV (jabatan 40).
 *
 * Incremental & aman (TIDAK truncate / TIDAK menyentuh jabatan lain):
 *   1. Insert 7 komponen KPI baru (skip bila sudah ada).
 *   2. Ganti mapping bobot jabatan 40 (weight_group='kpi') menjadi
 *      7 komponen area dengan total 100%.
 *
 * Usage: php spark db:seed SupervisorKpiSeeder
 */
class SupervisorKpiSeeder extends Seeder
{
    private const COMPONENTS = [
        [
            'code' => 'OMZET_WILAYAH',
            'name' => 'Omzet Wilayah',
            'description' => 'SUM(actual omzet cabang) / SUM(target omzet cabang) x 100 pada area Supervisor',
            'type' => 'automatic',
            'category' => 'supervisor',
            'unit_of_measure' => 'percent',
            'calculation_strategy' => 'omzet_wilayah',
            'is_active' => 1,
        ],
        [
            'code' => 'TARGET_CABANG',
            'name' => 'Target Cabang',
            'description' => 'Rata-rata pencapaian target tiap cabang pada area Supervisor',
            'type' => 'automatic',
            'category' => 'supervisor',
            'unit_of_measure' => 'percent',
            'calculation_strategy' => 'target_cabang',
            'is_active' => 1,
        ],
        [
            'code' => 'PRODUKTIVITAS_CABANG',
            'name' => 'Produktivitas Cabang',
            'description' => 'Unique customer bulan berjalan vs bulan sebelumnya pada area Supervisor',
            'type' => 'automatic',
            'category' => 'supervisor',
            'unit_of_measure' => 'percent',
            'calculation_strategy' => 'produktivitas_cabang',
            'is_active' => 1,
        ],
        [
            'code' => 'SOP',
            'name' => 'SOP',
            'description' => 'Rata-rata nilai KPI SOP Kepala Toko (Kepatuhan SOP) pada area Supervisor',
            'type' => 'automatic',
            'category' => 'supervisor',
            'unit_of_measure' => 'percent',
            'calculation_strategy' => 'sop_supervisor',
            'is_active' => 1,
        ],
        [
            'code' => 'KINERJA_KEPALA_TOKO',
            'name' => 'Kinerja Kepala Toko',
            'description' => 'Rata-rata total KPI Kepala Toko (tanpa absensi) pada area Supervisor',
            'type' => 'automatic',
            'category' => 'supervisor',
            'unit_of_measure' => 'percent',
            'calculation_strategy' => 'kinerja_kepala_toko',
            'is_active' => 1,
        ],
        [
            'code' => 'KEDISIPLINAN_TEAM',
            'name' => 'Kedisiplinan Team',
            'description' => 'Rata-rata nilai kedisiplinan seluruh team cabang pada area Supervisor',
            'type' => 'automatic',
            'category' => 'supervisor',
            'unit_of_measure' => 'percent',
            'calculation_strategy' => 'kedisiplinan_team',
            'is_active' => 1,
        ],
        [
            'code' => 'CUSTOMER_SATISFACTION',
            'name' => 'Customer Satisfaction',
            'description' => 'Manual input 0-100 (sumber MANUAL; siap dikembangkan ke GOOGLE_BUSINESS_PROFILE)',
            'type' => 'manual',
            'category' => 'service',
            'unit_of_measure' => 'percent',
            'calculation_strategy' => null,
            'is_active' => 1,
        ],
    ];

    private const WEIGHTS = [
        'OMZET_WILAYAH'         => 20,
        'TARGET_CABANG'         => 15,
        'PRODUKTIVITAS_CABANG'  => 15,
        'SOP'                   => 15,
        'KINERJA_KEPALA_TOKO'   => 15,
        'KEDISIPLINAN_TEAM'     => 10,
        'CUSTOMER_SATISFACTION' => 10,
    ];

    public function run()
    {
        $components = $this->db->table('kpi_components');
        $weights    = $this->db->table('kpi_weights');

        $map = [];
        foreach (self::COMPONENTS as $row) {
            $existing = $components->where('code', $row['code'])->get()->getRow();
            if ($existing) {
                $map[$row['code']] = (int)$existing->id;
                continue;
            }
            $components->insert($row);
            $map[$row['code']] = (int)$this->db->insertID();
        }

        // Ganti mapping bobot KPI jabatan 40 (kpi group) dengan 7 komponen area.
        $weights->where('position_id', 40)->where('weight_group', 'kpi')->delete();

        foreach (self::WEIGHTS as $code => $weight) {
            $weights->upsert([
                'kpi_component_id' => $map[$code],
                'position_id'      => 40,
                'weight'           => $weight,
                'weight_group'     => 'kpi',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ]);
        }

        // Ganti mapping grup absen jabatan 40 — bobot SAMA dengan team (40/20/20/20)
        // agar Detail Absensi SPV ikut tampil (skor_absen tetap dari calculateSPVAttendance).
        $weights->where('position_id', 40)->where('weight_group', 'absen')->delete();

        $absen = [
            'KEHADIRAN'     => 40,
            'KEBERSIHAN'    => 20,
            'SERAGAM'       => 20,
            'KEPATUHAN_SOP' => 20,
        ];
        foreach ($absen as $code => $weight) {
            $row = $components->where('code', $code)->get()->getRow();
            if (!$row) {
                continue;
            }
            $weights->upsert([
                'kpi_component_id' => (int)$row->id,
                'position_id'      => 40,
                'weight'           => $weight,
                'weight_group'     => 'absen',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ]);
        }
    }
}