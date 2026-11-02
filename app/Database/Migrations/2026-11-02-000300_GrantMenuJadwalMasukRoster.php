<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Beri akses menu Jadwal Masuk (id 118) ke jabatan pengelola roster & absen.
 *
 * Idempoten: nilai 118 hanya ditambahkan jika belum ada, urutan role lain
 * tidak diubah.
 */
class GrantMenuJadwalMasukRoster extends Migration
{
    private const MENU_ID = 118;
    private const JABATAN = [0, 1, 34, 35, 47];

    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        foreach (self::JABATAN as $id) {
            $row = $this->db->table('jabatan')->where('ID_JABATAN', $id)->get()->getRow();
            if (! $row) {
                continue;
            }

            $roles = json_decode((string) $row->ROLES_JABATAN, true);
            $roles = is_array($roles) ? $roles : [];

            if (in_array(self::MENU_ID, array_map('intval', $roles), true)) {
                continue;
            }

            $roles[] = self::MENU_ID;
            $this->db->table('jabatan')->where('ID_JABATAN', $id)->update([
                'ROLES_JABATAN' => json_encode($roles),
            ]);
        }
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        foreach (self::JABATAN as $id) {
            $row = $this->db->table('jabatan')->where('ID_JABATAN', $id)->get()->getRow();
            if (! $row) {
                continue;
            }

            $roles = json_decode((string) $row->ROLES_JABATAN, true);
            if (! is_array($roles)) {
                continue;
            }

            $roles = array_values(array_filter($roles, static fn ($role) => (int) $role !== self::MENU_ID));
            $this->db->table('jabatan')->where('ID_JABATAN', $id)->update([
                'ROLES_JABATAN' => json_encode($roles),
            ]);
        }
    }
}
