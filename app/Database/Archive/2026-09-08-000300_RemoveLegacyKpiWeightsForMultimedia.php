<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jabatan Multimedia (44) hanya memakai KPI Konten (5 komponen, bobot 100).
 * Hapus bobot KPI lama (weight_group 'kpi') yang bukan komponen KONTEN_*,
 * agar di penilaian_kinerja hanya 5 KPI baru yang tampil.
 * Group absen/behavior/operational/other TIDAK disentuh.
 */
class RemoveLegacyKpiWeightsForMultimedia extends Migration
{
    protected $backup = [];

    public function up()
    {
        $kontenIds = $this->db->table('kpi_components')
            ->whereIn('code', \App\Services\Konten\ContentKpiService::COMPONENT_CODES)
            ->get()
            ->getResult();

        $keepIds = array_map('intval', array_column($kontenIds, 'id'));

        $legacy = $this->db->table('kpi_weights')
            ->where('position_id', 44)
            ->where('weight_group', 'kpi')
            ->whereNotIn('kpi_component_id', $keepIds)
            ->get()
            ->getResultArray();

        if (!empty($legacy)) {
            $this->backup = $legacy;

            $removeIds = array_map('intval', array_column($legacy, 'id'));
            $this->db->table('kpi_weights')
                ->whereIn('id', $removeIds)
                ->delete();
        }
    }

    public function down()
    {
        if (!empty($this->backup)) {
            $this->db->table('kpi_weights')->insertBatch($this->backup);
        }
    }
}