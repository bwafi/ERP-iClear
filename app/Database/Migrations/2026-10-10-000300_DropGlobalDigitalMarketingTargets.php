<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hapus baris target global (unit_id IS NULL) komponen Digital Marketing
 * jabatan 43 — target hanya memakai Head Office (unit_id = 50).
 *
 * Keputusan (2026-10-10): komponen LEADS_QUALITY (dan saudaranya yang
 * se-pola) nilainya sudah sama antara baris global dan HO; baris global
 * tidak perlu dipertahankan karena skoring selalu jatuh ke unit 50.
 */
class DropGlobalDigitalMarketingTargets extends Migration
{
    public function up()
    {
        $codes = ['LEADS_QUALITY', 'CONVERSION', 'CPL'];

        foreach ($codes as $code) {
            $compId = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();
            if (!$compId) {
                continue;
            }
            $this->db->table('kpi_targets')
                ->where('kpi_component_id', $compId->id)
                ->where('unit_id IS NULL')
                ->delete();
        }
    }

    public function down()
    {
        // Data global tidak dibuat ulang — datanya identik dgn baris unit 50.
    }
}