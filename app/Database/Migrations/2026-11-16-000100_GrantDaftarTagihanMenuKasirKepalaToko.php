<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Beri akses menu "Hutang Supplier" (`menu.idmenu = 33`, url
 * `daftar_tagihan`) ke jabatan operasional yang mencatat pelunasan tagihan
 * supplier di unit:
 *
 *   35 ADMIN / KASIR
 *   41 Kepala Toko
 *   47 ADMIN / KASIR (entri jabatan duplikat di data existing)
 *
 * Sebelumnya menu ini hanya di-grant ke Finance (0), Admin root (1),
 * Direktur (2), dan Manager (34) lewat GrantHutangPiutangMenus.
 *
 * Hanya menyentuh `jabatan.ROLES_JABATAN`. Idempoten: id yang sudah ada tidak
 * diduplikasi dan role lain tidak diubah.
 */
class GrantDaftarTagihanMenuKasirKepalaToko extends Migration
{
    private const MENU_ID = 33;

    private const TARGET_JABATAN = [35, 41, 47];

    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        foreach (self::TARGET_JABATAN as $jabatanId) {
            $this->tambah($jabatanId, self::MENU_ID);
        }
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        foreach (self::TARGET_JABATAN as $jabatanId) {
            $this->hapus($jabatanId, self::MENU_ID);
        }
    }

    private function tambah(int $jabatan, int $menu): void
    {
        $roles = $this->roles($jabatan);
        if (in_array($menu, array_map('intval', $roles), true)) {
            return;
        }

        $roles[] = $menu;

        $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->update([
            'ROLES_JABATAN' => json_encode($roles),
        ]);
    }

    private function hapus(int $jabatan, int $menu): void
    {
        $roles = array_map('intval', $this->roles($jabatan));
        $sisa  = array_values(array_filter($roles, static fn ($id) => $id !== $menu));

        if ($sisa === $roles) {
            return;
        }

        $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->update([
            'ROLES_JABATAN' => json_encode($sisa),
        ]);
    }

    /**
     * Role jabatan dalam bentuk array. Baris yang tidak ada / JSON rusak
     * dianggap kosong supaya migration tidak menulis ke jabatan yang salah.
     */
    private function roles(int $jabatan): array
    {
        $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();
        if (! $row) {
            return [];
        }

        $roles = json_decode((string) ($row->ROLES_JABATAN ?? ''), true);

        return is_array($roles) ? $roles : [];
    }
}
