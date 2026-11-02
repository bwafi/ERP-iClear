<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Beri akses menu grup Absensi yang belum di-grant ke jabatan mana pun:
 * Presensi (112) dan Riwayat Absen (115) untuk semua jabatan; Riwayat Absen
 * (admin) (117) untuk admin/manager/direktur/finance.
 *
 * Idempoten: hanya menambah id yang belum ada, role lain tidak diubah.
 */
class GrantMenuAbsensiGroup extends Migration
{
    private const SEMUA = [112, 115];
    private const ADMIN = [117];
    private const JABATAN_ADMIN = [0, 1, 2, 34, 40];

    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        $jabatan = $this->db->table('jabatan')->select('ID_JABATAN')->get()->getResult();
        foreach ($jabatan as $row) {
            $tambah = self::SEMUA;
            if (in_array((int) $row->ID_JABATAN, self::JABATAN_ADMIN, true)) {
                $tambah = array_merge($tambah, self::ADMIN);
            }
            $this->tambah((int) $row->ID_JABATAN, $tambah);
        }
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('jabatan')) {
            return;
        }

        $jabatan = $this->db->table('jabatan')->select('ID_JABATAN')->get()->getResult();
        foreach ($jabatan as $row) {
            $this->hapus((int) $row->ID_JABATAN, array_merge(self::SEMUA, self::ADMIN));
        }
    }

    private function tambah(int $jabatan, array $menu): void
    {
        $roles  = $this->roles($jabatan);
        $kurang = array_values(array_diff($menu, array_map('intval', $roles)));

        if ($kurang === []) {
            return;
        }

        $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->update([
            'ROLES_JABATAN' => json_encode(array_merge($roles, $kurang)),
        ]);
    }

    private function hapus(int $jabatan, array $menu): void
    {
        $roles = array_map('intval', $this->roles($jabatan));
        $sisa  = array_values(array_filter($roles, static fn ($id) => ! in_array($id, $menu, true)));

        if ($sisa !== $roles) {
            $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->update([
                'ROLES_JABATAN' => json_encode($sisa),
            ]);
        }
    }

    private function roles(int $jabatan): array
    {
        $row   = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();
        $roles = json_decode((string) ($row->ROLES_JABATAN ?? ''), true);

        return is_array($roles) ? $roles : [];
    }
}
