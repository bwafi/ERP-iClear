<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * KPI Manager (jabatan 34) — 7 komponen perusahaan, bobot total 100.
 *
 *   OMZET_GLOBAL           50  (rumus sama Kepala Divisi 43)
 *   FIN_KESEHATAN_UANG     15  (nilai Finance apa adanya)
 *   TARGET_CABANG          10  (versi semua cabang)
 *   EFEKTIVITAS_ORGANISASI 10  (rata-rata KPI SPV jabatan 40)
 *   SDM_LEADERSHIP          5  (rata-rata KPI bawahan 40/43/45/0)
 *   IMPROVEMENT_PERUSAHAAN  5  (input manual bulanan)
 *   REPORTING_GOVERNANCE    5  (input manual bulanan)
 *
 * Posisi 34 sebelumnya belum punya bobot sama sekali → skor_total = 0 →
 * tunjangan kinerja Rp 0. Migrasi idempoten: bobot lama posisi 34 (group
 * kpi) dihapus dulu supaya tidak dobel skor.
 */
class AddKpiJabatan34Manager extends Migration
{
    private const NEW_COMPONENTS = [
        ['code' => 'EFEKTIVITAS_ORGANISASI', 'name' => 'Efektivitas Organisasi', 'type' => 'automatic'],
        ['code' => 'SDM_LEADERSHIP',         'name' => 'SDM & Leadership',       'type' => 'automatic'],
        ['code' => 'IMPROVEMENT_PERUSAHAAN', 'name' => 'Improvement Perusahaan', 'type' => 'manual'],
        ['code' => 'REPORTING_GOVERNANCE',   'name' => 'Reporting & Governance', 'type' => 'manual'],
    ];

    private const WEIGHTS_34 = [
        'OMZET_GLOBAL'           => 50,
        'FIN_KESEHATAN_UANG'     => 15,
        'TARGET_CABANG'          => 10,
        'EFEKTIVITAS_ORGANISASI' => 10,
        'SDM_LEADERSHIP'         => 5,
        'IMPROVEMENT_PERUSAHAAN' => 5,
        'REPORTING_GOVERNANCE'   => 5,
    ];

    public function up()
    {
        foreach (self::NEW_COMPONENTS as $c) {
            $existing = $this->db->table('kpi_components')->where('code', $c['code'])->get()->getRow();
            if ($existing) {
                $this->db->table('kpi_components')
                    ->where('id', $existing->id)
                    ->update([
                        'name'                 => $c['name'],
                        'type'                 => $c['type'],
                        'calculation_strategy' => '',
                        'is_active'            => 1,
                    ]);
                continue;
            }
            $this->db->table('kpi_components')->insert([
                'code'                 => $c['code'],
                'name'                 => $c['name'],
                'type'                 => $c['type'],
                'calculation_strategy' => '',
                'is_active'            => 1,
            ]);
        }

        // Kumpulkan id 7 kode (baru + pakai ulang).
        $codeToId = [];
        foreach (array_keys(self::WEIGHTS_34) as $code) {
            $row = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();
            if ($row) {
                $codeToId[$code] = (int)$row->id;
            }
        }

        // Bersihkan bobot lama posisi 34 (group kpi) — cegah dobel skor.
        $this->db->table('kpi_weights')
            ->where('position_id', 34)
            ->where('weight_group', 'kpi')
            ->delete();

        $rows = [];
        foreach (self::WEIGHTS_34 as $code => $weight) {
            if (!isset($codeToId[$code])) {
                continue;
            }
            $rows[] = [
                'kpi_component_id' => $codeToId[$code],
                'position_id'      => 34,
                'weight'           => (float)$weight,
                'weight_group'     => 'kpi',
                'effective_from'   => '2024-01-01',
                'effective_to'     => null,
                'created_by'       => null,
            ];
        }
        if (!empty($rows)) {
            $this->db->table('kpi_weights')->insertBatch($rows);
        }
    }

    public function down()
    {
        $ids = [];
        foreach (array_keys(self::WEIGHTS_34) as $code) {
            $row = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();
            if ($row) {
                $ids[] = (int)$row->id;
            }
        }
        if (!empty($ids)) {
            $this->db->table('kpi_weights')
                ->where('position_id', 34)
                ->where('weight_group', 'kpi')
                ->whereIn('kpi_component_id', $ids)
                ->delete();
        }
    }
}
