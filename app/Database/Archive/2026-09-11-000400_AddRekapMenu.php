<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu "Rekap Marketing Harian" di grup Digital Marketing (10040).
 * Non-destruktif: insert jika belum ada + tambah role untuk jabatan target.
 */
class AddRekapMenu extends Migration
{
    protected const MENU_DEF = [
        'idmenu'    => 10047,
        'urutan'    => 113,
        'nama_menu' => 'Rekap Harian',
        'roles'     => 'marketing_rekap',
        'url'       => 'marketing/rekap',
    ];

    protected const TARGET_JABATANS = [0, 1, 2, 34, 43, 44, 48];

    public function up()
    {
        $db = $this->db;

        $exists = (int)$db->table('menu')->where('idmenu', self::MENU_DEF['idmenu'])->countAllResults();
        if ($exists === 0) {
            $db->table('menu')->insert(array_merge(self::MENU_DEF, [
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 10040,
                'utama'      => 1,
                'categories' => 0,
                'icon'       => null,
            ]));
        }

        foreach (self::TARGET_JABATANS as $jabatanId) {
            $row = $db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (!$row) {
                continue;
            }
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }
            $merged = array_values(array_unique(array_merge($roles, [self::MENU_DEF['idmenu']])));
            if ($merged !== $roles) {
                $db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        // Roles dihapus dari jabatan; baris menu dibiarkan (non-destruktif).
        $db = $this->db;
        foreach (self::TARGET_JABATANS as $jabatanId) {
            $row = $db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (!$row) {
                continue;
            }
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
            }
            $roles = array_values(array_diff($roles, [self::MENU_DEF['idmenu']]));
            $db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }
}