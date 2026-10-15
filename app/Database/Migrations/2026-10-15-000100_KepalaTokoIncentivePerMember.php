<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Insentif KEPALA_TOKO: 1% per anggota (tanpa dibagi jumlah anggota).
 *
 * BUSINESS RULE (CONFIRMED):
 *   Insentif Kepala Toko = SELALU tepat 1% × omset unit,
 *   terlepas dari berapa jumlah anggota group. Anggota lain di unit
 *   (Teknisi, Admin) juga masing-masing menerima 1% × omset unit.
 *   Syarat minimum_achievement (harus capai target omset) TETAP berlaku.
 *
 * Perubahan data:
 *   incentive_rules (group KEPALA_TOKO):
 *     base_value      3.00          -> 1.00
 *     division_method 'member_count'-> 'per_member'   (artinya: rate × omset / anggota)
 *     incentive_name  '...3% Pool'  -> 'Kepala Toko 1% per Anggota'
 *   incentive_groups (KEPALA_TOKO): deskripsi disesuaikan.
 *
 * DIGITAL_DIVISION TIDAK disentuh (tetap 1% pool dibagi jumlah anggota).
 *
 * NON-DESTRUKTIF & IDEMPOTEN: hanya UPDATE baris group KEPALA_TOKO.
 * Source-of-truth instalasi baru: IncentiveGroupSeeder.
 */
class KepalaTokoIncentivePerMember extends Migration
{
    public function up()
    {
        $group = $this->db->table('incentive_groups')
            ->where('code', 'KEPALA_TOKO')
            ->get()
            ->getRow();

        if (!$group) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('incentive_rules')
            ->where('incentive_group_id', $group->id)
            ->update([
                'base_value'      => 1.00,
                'division_method' => 'per_member',
                'incentive_name'  => 'Kepala Toko 1% per Anggota',
                'updated_at'      => $now,
            ]);

        $this->db->table('incentive_groups')
            ->where('id', $group->id)
            ->update([
                'description' => '1% omset toko per anggota toko (Kepala Toko, Teknisi, Admin)',
                'updated_at'  => $now,
            ]);
    }

    public function down()
    {
        $group = $this->db->table('incentive_groups')
            ->where('code', 'KEPALA_TOKO')
            ->get()
            ->getRow();

        if (!$group) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('incentive_rules')
            ->where('incentive_group_id', $group->id)
            ->update([
                'base_value'      => 3.00,
                'division_method' => 'member_count',
                'incentive_name'  => 'Kepala Toko 3% Pool',
                'updated_at'      => $now,
            ]);

        $this->db->table('incentive_groups')
            ->where('id', $group->id)
            ->update([
                'description' => '3% omset toko dibagi rata ke anggota toko (Kepala Toko, Teknisi, Admin)',
                'updated_at'  => $now,
            ]);
    }
}
