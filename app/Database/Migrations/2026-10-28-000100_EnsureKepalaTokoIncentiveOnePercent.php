<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pastikan insentif KEPALA_TOKO = 1% per anggota (bukan 3% pool / jumlah anggota).
 *
 * Migrasi 2026-10-15-000100 sudah melakukan perubahan ini, tetapi environment
 * yang melewatkan migrasi tersebut (atau mengembalikan datanya) masih memakai
 * base_value 3.00 + division_method member_count. Akibatnya unit dengan 2
 * anggota mendapat 1,5% per orang.
 *
 * NON-DESTRUKTIF & IDEMPOTEN: hanya UPDATE baris rule group KEPALA_TOKO.
 */
class EnsureKepalaTokoIncentiveOnePercent extends Migration
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
