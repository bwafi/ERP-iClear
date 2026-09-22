<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Koreksi target omzet NON-HO cabang 1 (Probolinggo) context penilaian_kinerja.
 *
 * Sumber kebenaran: pedoman-resmi §VI "Target Team Cabang" → Probolinggo Rp50.000.000.
 * KPI engine membaca kpi_targets.target_value sebagai target dasar (non-HO), lalu
 * supervisor/marketing menambahkan Rp7.000.000 utk target HO SPV.
 *
 * Sebelum koreksi: unit 1 context penilaian_kinerja = Rp55.000.000 (tidak konsisten
 * dgn context 'gaji' yg sudah Rp50.000.000) sehingga "Target HO" cabang 1 di
 * /penilaian_kinerja tampil Rp62.000.000 (55+7). Sesudah koreksi Rp50.000.000 →
 * Target HO Rp57.000.000, konsisten dgn pedoman.
 *
 * Berlaku utk OMSET_CABANG (SPV) & OMSET_TOKO (KT) — keduanya bersumber dari
 * threshold omzet yang sama di KPIConfigurationSeeder.
 *
 * NON-DESTRUKTIF & IDEMPOTEN: UPDATE scoped (component tsb, unit_id=1,
 * context=penilaian_kinerja, target_value=55.000.000) → 50.000.000.
 */
class UpdateTargetOmsetUnit1 extends Migration
{
    private const FROM_VALUE = 55000000.00;
    private const TO_VALUE   = 50000000.00;

    public function up()
    {
        $this->applyForCodes(['OMSET_CABANG', 'OMSET_TOKO'], self::TO_VALUE);
    }

    public function down()
    {
        $this->applyForCodes(['OMSET_CABANG', 'OMSET_TOKO'], self::FROM_VALUE);
    }

    private function applyForCodes(array $codes, float $value)
    {
        $rows = $this->db->table('kpi_components')
            ->select('id')
            ->whereIn('code', $codes)
            ->get()
            ->getResultArray();

        $ids = array_map('intval', array_column($rows, 'id'));
        if (empty($ids)) {
            return;
        }

        $this->db->table('kpi_targets')
            ->whereIn('kpi_component_id', $ids)
            ->where('unit_id', 1)
            ->where('context', 'penilaian_kinerja')
            ->where('target_value', self::FROM_VALUE)
            ->update(['target_value' => $value]);
    }
}