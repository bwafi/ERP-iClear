<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Grant menu sidebar "Hutang Supplier" (33) dan "Piutang Pegawai" (210)
 * untuk jabatan Finance: 0 ADMIN CENTER, 1 Admin root, 2 Direktur, 34 Manager.
 *
 * Kedua menu ini SUDAH ada di tabel `menu` (bentuk item flat, sama dengan
 * Pembelian/22) tetapi idmenu-nya tidak pernah masuk ke ROLES_JABATAN jabatan
 * mana pun -> kontennya tersembunyi dari sidebar. Migration ini hanya
 * memperbarui ROLES_JABATAN, tidak mengubah baris menu.
 *
 * Idempotent: merge de-duplikasi — aman dijalankan ulang.
 */
class GrantHutangPiutangMenus extends Migration
{
    private const MENU_IDS = [33, 210];

    // Jabatan yang berhak melihat modul Hutang & Piutang (sama dengan akses finance).
    private array $targetJabatans = [0, 1, 2, 34];

    public function up()
    {
        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->get()
                ->getRow();

            if (!$row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }

            $merged = array_values(array_unique(array_merge($roles, self::MENU_IDS)));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
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
                static fn($id) => !in_array((int) $id, self::MENU_IDS, true)
            ));

            $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }
}