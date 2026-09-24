<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Target Total Pelanggan (CUSTOMER_COUNT) memakai nilai penuh per unit dan
 * TANPA threshold (batas_awal / batas_keempat):
 *   unit 1 (BWI)      = 220
 *   unit 2 (JBR)      = 180
 *   unit 3 (BWI)      = 350
 *   unit 4 (Pandaan)  = 250
 *
 * KPI engine membagi target tersebut ÷ 2 DI KODE utk teknisi & kepala toko
 * (target_value DB tetap nilai penuh). Nilai KPI = 0 bila aktual < target;
 * bila tercapai → min(aktual ÷ target × 100, 100).
 *
 * Sebelumnya kpi_targets menyimpan target_value 130/118/210/118 beserta
 * batas_awal/batas_keempat (skema threshold). Baris unit_id 1-4 diperbarui;
 * baris HO (unit 50) tidak disentuh.
 *
 * IDEMPOTEN: UPDATE tidak di-scope nilai lama, aman dijalankan kapan pun.
 */
class UpdateCustomerCountTargets extends Migration
{
    /** unit_id => target value (full). */
    private const TARGETS = [1 => 220, 2 => 180, 3 => 350, 4 => 250];

    /** unit_id => [batas_awal, batas_keempat] legacy utk down(). */
    private const LEGACY_BATAS = [
        1 => [150, 220],
        2 => [150, 180],
        3 => [250, 350],
        4 => [200, 250],
    ];

    /** unit_id => target_value legacy utk down(). */
    private const LEGACY_TARGET = [1 => 130, 2 => 118, 3 => 210, 4 => 118];

    public function up()
    {
        $id = $this->customerComponentId();
        if ($id === null) {
            return;
        }

        foreach (self::TARGETS as $unit => $target) {
            $this->db->table('kpi_targets')
                ->where('kpi_component_id', $id)
                ->where('unit_id', $unit)
                ->update([
                    'target_value'  => $target,
                    'batas_awal'    => null,
                    'batas_keempat' => null,
                ]);
        }
    }

    public function down()
    {
        $id = $this->customerComponentId();
        if ($id === null) {
            return;
        }

        foreach (self::LEGACY_TARGET as $unit => $target) {
            [$batasAwal, $batasKeempat] = self::LEGACY_BATAS[$unit];
            $this->db->table('kpi_targets')
                ->where('kpi_component_id', $id)
                ->where('unit_id', $unit)
                ->update([
                    'target_value'  => $target,
                    'batas_awal'    => $batasAwal,
                    'batas_keempat' => $batasKeempat,
                ]);
        }
    }

    private function customerComponentId(): ?int
    {
        $row = $this->db->table('kpi_components')
            ->select('id')
            ->where('code', 'CUSTOMER_COUNT')
            ->get()
            ->getRow();

        return $row ? (int) $row->id : null;
    }
}