<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixKepalaTokoOmsetWeight extends Migration
{
    public function up()
    {
        $db = &$this->db;

        $omsetCabangId = $db->table('kpi_components')
            ->select('id')
            ->where('code', 'OMSET_CABANG')
            ->get()
            ->getRow()->id ?? null;

        $omsetTokoId = $db->table('kpi_components')
            ->select('id')
            ->where('code', 'OMSET_TOKO')
            ->get()
            ->getRow()->id ?? null;

        if (!$omsetCabangId || !$omsetTokoId) {
            return;
        }

        // Hapus OMSET_CABANG untuk Kepala Toko (41)
        $db->table('kpi_weights')
            ->where('kpi_component_id', $omsetCabangId)
            ->where('position_id', 41)
            ->delete();

        // Tambah OMSET_TOKO untuk Kepala Toko (41) dengan bobot 45
        $db->table('kpi_weights')->insert([
            'kpi_component_id' => $omsetTokoId,
            'position_id' => 41,
            'weight' => 45,
            'weight_group' => 'kpi',
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'created_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        $db = &$this->db;

        $omsetCabangId = $db->table('kpi_components')
            ->select('id')
            ->where('code', 'OMSET_CABANG')
            ->get()
            ->getRow()->id ?? null;

        $omsetTokoId = $db->table('kpi_components')
            ->select('id')
            ->where('code', 'OMSET_TOKO')
            ->get()
            ->getRow()->id ?? null;

        if (!$omsetCabangId || !$omsetTokoId) {
            return;
        }

        // Hapus OMSET_TOKO untuk Kepala Toko
        $db->table('kpi_weights')
            ->where('kpi_component_id', $omsetTokoId)
            ->where('position_id', 41)
            ->delete();

        // Kembalikan OMSET_CABANG
        $db->table('kpi_weights')->insert([
            'kpi_component_id' => $omsetCabangId,
            'position_id' => 41,
            'weight' => 45,
            'weight_group' => 'kpi',
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'created_by' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
