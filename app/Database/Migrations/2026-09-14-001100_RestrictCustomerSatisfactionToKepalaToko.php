<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Restriksi menu Customer Satisfaction (10049) hanya untuk Kepala Toko (41).
 *
 * CS bekerja di cabang bersama teknisi; input review Google Maps harian adalah
 * tugas Kepala Toko. Jabatan lain (Admin Center, Manager, SPV, CS, Kadiv, IT,
 * Admin root, Direktur) tidak lagi melihat/input terkecuali Kepala Toko.
 */
class RestrictCustomerSatisfactionToKepalaToko extends Migration
{
    private const MENU_CS = 10049;
    private const ONLY_JABATAN = [41]; // Kepala Toko

    public function up()
    {
        $jabatans = $this->db->table('jabatan')->get()->getResult();

        foreach ($jabatans as $jab) {
            $idJabatan = (int)$jab->ID_JABATAN;

            if (in_array($idJabatan, self::ONLY_JABATAN, true)) {
                continue;
            }

            $roles = json_decode($jab->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            $roles = array_values(array_filter(
                $roles,
                static fn($id) => (int)$id !== self::MENU_CS
            ));

            $this->db->table('jabatan')
                ->where('ID_JABATAN', $idJabatan)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }

    public function down()
    {
        // Restore: tambahkan kembali menu CS ke ROLES_JABATAN (best effort:
        // jabatan yg tadinya pemilik menu CS dari migrasi sebelumnya).
        $pemilikLama = [0, 1, 2, 34, 35, 40, 41, 42, 43, 45];

        $jabatans = $this->db->table('jabatan')->get()->getResult();
        foreach ($jabatans as $jab) {
            if (!in_array((int)$jab->ID_JABATAN, $pemilikLama, true)) {
                continue;
            }

            $roles = json_decode($jab->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            if (!in_array(self::MENU_CS, array_map('intval', $roles), true)) {
                $roles[] = self::MENU_CS;
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jab->ID_JABATAN)
                    ->update(['ROLES_JABATAN' => json_encode($roles)]);
            }
        }
    }
}