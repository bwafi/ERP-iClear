<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Konsolidasi konteks target KPI menjadi SATU konteks.
 *
 * Keputusan (2026-10-10):
 *   - kpi_targets.context: nilai 'penilaian_kinerja' yang MENANG saat konflik
 *     dengan 'gaji'/'slip_gaji'. Komponen yang hanya punya 'default' tetap
 *     memakai nilainya.
 *   - Semua baris diseragamkan ke context='default' (satu konteks).
 *   - Kolom batas_awal/kedua/ketiga/keempat DROP — seluruh skoring sekarang
 *     memakai target_value saja (tidak ada tier/batas lagi).
 *   - salary_structures.context: baris konteks non-default (hanya jabatan 46
 *     yang sudah tidak aktif) dihapus; kolom diseragamkan ke ENUM('default').
 */
class CollapseKpiTargetContextSingle extends Migration
{
    public function up()
    {
        $db       = $this->db;
        $priority = ['penilaian_kinerja' => 0, 'gaji' => 1, 'slip_gaji' => 2, 'default' => 3];

        // 1) Pilih satu baris tersisa per kelompok (komponen + unit + jabatan).
        $rows = $db->table('kpi_targets')
            ->select('id, kpi_component_id, unit_id, position_id, context')
            ->orderBy('kpi_component_id', 'ASC')
            ->orderBy('unit_id', 'ASC')
            ->orderBy('position_id', 'ASC')
            ->orderBy('effective_from', 'DESC')
            ->get()
            ->getResult();

        $groupKey = function ($r) {
            return $r->kpi_component_id . '|' . var_export($r->unit_id, true) . '|' . var_export($r->position_id, true);
        };

        $keep    = [];
        $bestPrio = [];
        foreach ($rows as $r) {
            $k = $groupKey($r);
            $p = $priority[$r->context] ?? 3;
            if (!isset($keep[$k]) || $p < $bestPrio[$k]) {
                $keep[$k]    = (int) $r->id;
                $bestPrio[$k] = $p;
            }
        }

        $keepIds   = array_values($keep);
        $deleteIds = [];
        foreach ($rows as $r) {
            if (!in_array((int) $r->id, $keepIds, true)) {
                $deleteIds[] = (int) $r->id;
            }
        }

        if (!empty($deleteIds)) {
            $db->table('kpi_targets')->whereIn('id', $deleteIds)->delete();
        }

        // 2) Seragamkan konteks baris yang tersisa → 'default'.
        $db->query('UPDATE kpi_targets SET context = \'default\'');

        // 3) Hapus kolom batas_* dan perkecil ENUM context jadi tunggal.
        $db->query('ALTER TABLE kpi_targets
            DROP COLUMN batas_awal,
            DROP COLUMN batas_kedua,
            DROP COLUMN batas_ketiga,
            DROP COLUMN batas_keempat');
        $db->query("ALTER TABLE kpi_targets MODIFY context ENUM('default') NOT NULL DEFAULT 'default'");

        // 4) salary_structures: hapus baris konteks non-default (jabatan 46 yg nonaktif).
        $db->table('salary_structures')
            ->whereIn('context', ['gaji', 'penilaian_kinerja', 'slip_gaji'])
            ->delete();
        $db->query("ALTER TABLE salary_structures MODIFY context ENUM('default') NOT NULL DEFAULT 'default'");
    }

    public function down()
    {
        // Data duplikat yang sudah dihapus tidak bisa dipulihkan.
        // Yang dibalik hanya skema agar rollback tidak error.
        $db = $this->db;
        $db->query("ALTER TABLE kpi_targets MODIFY context ENUM('gaji','penilaian_kinerja','slip_gaji','default') NOT NULL DEFAULT 'default'");
        $db->query('ALTER TABLE kpi_targets
            ADD batas_awal DECIMAL(15,2) NULL,
            ADD batas_kedua DECIMAL(15,2) NULL,
            ADD batas_ketiga DECIMAL(15,2) NULL,
            ADD batas_keempat DECIMAL(15,2) NULL');
        $db->query("ALTER TABLE salary_structures MODIFY context ENUM('gaji','penilaian_kinerja','slip_gaji','default') NOT NULL DEFAULT 'default'");
    }
}