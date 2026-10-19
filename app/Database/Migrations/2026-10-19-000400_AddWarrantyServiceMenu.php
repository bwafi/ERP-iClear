<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Makes the existing warranty-claim workflow discoverable in the sidebar. */
class AddWarrantyServiceMenu extends Migration
{
    private const MENU_ID = 10151;

    private array $menuDef = [
        'idmenu'     => self::MENU_ID,
        'urutan'     => 79,
        'nama_menu'  => 'Garansi Service',
        'roles'      => 'garansi_service',
        'url'        => 'garansi_service',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => 0,
        'utama'      => 1,
        'categories' => 1,
        'icon'       => '<iconify-icon icon="solar:shield-check-bold" width="24" height="24"></iconify-icon>',
    ];

    private array $targetJabatans = [0, 1, 2, 34, 35, 38, 40, 41, 47];

    public function up()
    {
        if ($this->db->table('menu')->where('idmenu', self::MENU_ID)->countAllResults() === 0) {
            $this->db->table('menu')->insert($this->menuDef);
        }

        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->get()
                ->getRow();

            if (! $row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (! is_array($roles)) {
                $roles = [];
            }

            $merged = array_values(array_unique(array_merge($roles, [self::MENU_ID])));
            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        $this->db->table('menu')->where('idmenu', self::MENU_ID)->delete();

        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->get()
                ->getRow();

            if (! $row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (! is_array($roles)) {
                continue;
            }

            $filtered = array_values(array_filter(
                $roles,
                static fn ($id) => (int) $id !== self::MENU_ID
            ));

            if ($filtered !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($filtered)]);
            }
        }
    }
}
