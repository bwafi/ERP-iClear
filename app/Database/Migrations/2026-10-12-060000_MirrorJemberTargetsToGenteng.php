<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Salin seluruh target dasar (position_id NULL) dari Jember (unit 2) ke
 * Genteng (unit 5) — sesuai arahan user "target dll mirip Jember".
 *
 * KEADAAN SEBELUM
 * ---------------
 * Unit 5 ICLEAR Genteng hanya punya 1 baris target: target HO OMSET_CABANG
 * position_id=40. Tidak ada target dasar, sehingga KpiCalculationService
 * melewati tiap komponen (target kosong) dan KPI pegawai unit 5 (mis. Teknisi
 * jabatan 36: OMSET_TOKO 70 / OMSET_TEKNISI 15 / CUSTOMER_COUNT 15) tampil
 * kosong di penilaian_kinerja.
 *
 * PERUBAHAN
 * ---------
 * Menyalin semua baris kpi_targets unit 2 (position_id NULL) ke unit 5 dengan
 * target_value/context/period/effective sama persis. Baris position_id=40
 * (target HO) tidak disentuh.
 *
 * IDEMPOTENT: baris yang sudah ada (komponen + unit 5 + position NULL)
 * dilewati, jadi dijalankan berulang tidak menggandakan data.
 */
class MirrorJemberTargetsToGenteng extends Migration
{
    private const SOURCE_UNIT = 2;
    private const DEST_UNIT   = 5;

    public function up()
    {
        if (! $this->db->tableExists('kpi_targets')) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = $this->db->table('kpi_targets')
            ->where('unit_id', self::SOURCE_UNIT)
            ->where('position_id', null)
            ->get()
            ->getResultArray();

        // OMSET_CABANG position NULL tidak ikut disalin: komponen itu sudah
        // tidak punya bobot di jabatan mana pun (yatim) dan hanya muncul sebagai
        // info "target dasar" sehingga menyalinnya justru memunculkan baris
        // ganda yang membingungkan.
        $skipComponent = $this->db->table('kpi_components')
            ->select('id')
            ->where('code', 'OMSET_CABANG')
            ->get()
            ->getRow();
        $skipId = $skipComponent ? (int) $skipComponent->id : null;

        foreach ($rows as $row) {
            if ($skipId !== null && (int) $row['kpi_component_id'] === $skipId) {
                continue;
            }

            $exists = $this->db->table('kpi_targets')
                ->where('kpi_component_id', $row['kpi_component_id'])
                ->where('unit_id', self::DEST_UNIT)
                ->where('position_id', null)
                ->countAllResults() > 0;

            if ($exists) {
                continue;
            }

            $this->db->table('kpi_targets')->insert([
                'kpi_component_id' => $row['kpi_component_id'],
                'unit_id'          => self::DEST_UNIT,
                'position_id'      => null,
                'target_value'     => $row['target_value'],
                'context'          => $row['context'],
                'period_type'      => $row['period_type'],
                'period_month'     => $row['period_month'],
                'effective_from'   => $row['effective_from'],
                'effective_to'     => $row['effective_to'],
                'created_by'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('kpi_targets')) {
            return;
        }

        $this->db->table('kpi_targets')
            ->where('unit_id', self::DEST_UNIT)
            ->where('position_id', null)
            ->delete();
    }
}
