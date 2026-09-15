<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Beri akses menu Customer Satisfaction (10049) kembali ke Admin root (1),
 * Manager (34), dan Direktur (2) — selain Kepala Toko (41).
 *
 * Admin root/Direktur: input semua unit.
 * Manager: melihat seluruh unit (read-only).
 * Kepala Toko: input unit sendiri.
 */
class GrantCsAccessAdminManagerDirektur extends Migration
{
    private const MENU_CS = 10049;
    private const PEMBERI_AKSES = [1, 2, 34];

    public function up()
    {
        foreach (self::PEMBERI_AKSES as $idJabatan) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $idJabatan)
                ->get()
                ->getRow();

            if (!$row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            if (!in_array(self::MENU_CS, array_map('intval', $roles), true)) {
                $roles[] = self::MENU_CS;
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $idJabatan)
                    ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
            }
        }
    }

    public function down()
    {
        foreach (self::PEMBERI_AKSES as $idJabatan) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $idJabatan)
                ->get()
                ->getRow();

            if (!$row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
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
}