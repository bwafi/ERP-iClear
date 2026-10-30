<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Grant menu sidebar "Asset & Pembagian" (idmenu 10023, url /asset_berjalan)
 * untuk jabatan 2 (Direktur).
 *
 * Menu-nya sudah ada di tabel `menu` dan tampil untuk Finance/Admin/Manager/
 * SPV, tetapi idmenu 10023 belum pernah masuk ROLES_JABATAN jabatan 2 sehingga
 * direktur tidak melihat menunya. Migration ini hanya memperbarui
 * ROLES_JABATAN, tidak mengubah baris menu.
 *
 * Batas unit direktur di halaman itu ditangani kode
 * (`TutupKasir::resolveUnitScope` + `DirekturUnitScopeService`), bukan menu.
 *
 * Idempotent: merge de-duplikasi — aman dijalankan ulang.
 */
class GrantAssetBerjalanDirektur extends Migration
{
    private const MENU_ID = 10023;
    private const JABATAN_ID = 2; // Direktur

    public function up()
    {
        $row = $this->db->table('jabatan')
            ->where('ID_JABATAN', self::JABATAN_ID)
            ->get()
            ->getRow();

        if (! $row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (! is_array($roles)) {
            $roles = [];
        }

        $sudahAda = array_filter($roles, static fn ($id) => (int) $id === self::MENU_ID);
        if ($sudahAda !== []) {
            return;
        }

        $roles[] = self::MENU_ID;

        $this->db->table('jabatan')
            ->where('ID_JABATAN', self::JABATAN_ID)
            ->update(['ROLES_JABATAN' => json_encode(array_values($roles))]);
    }

    public function down()
    {
        $row = $this->db->table('jabatan')
            ->where('ID_JABATAN', self::JABATAN_ID)
            ->get()
            ->getRow();

        if (! $row) {
            return;
        }

        $roles = json_decode($row->ROLES_JABATAN ?? '', true);
        if (! is_array($roles)) {
            return;
        }

        $roles = array_values(array_filter(
            $roles,
            static fn ($id) => (int) $id !== self::MENU_ID
        ));

        $this->db->table('jabatan')
            ->where('ID_JABATAN', self::JABATAN_ID)
            ->update(['ROLES_JABATAN' => json_encode($roles)]);
    }
}
