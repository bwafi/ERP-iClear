<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Sidebar DP Service; grants exactly the same jabatan roles as Proses Service (menu 72). */
class AddDpServiceMenu extends Migration
{
    private const MENU_ID = 10150;

    private array $menuDef = [
        'idmenu'     => self::MENU_ID,
        'urutan'     => 78,
        'nama_menu'  => 'DP Service',
        'roles'      => 'dp_service',
        'url'        => 'dp_service',
        'show_menu'  => 1,
        'sub'        => 0,
        'parent'     => 0,
        'utama'      => 1,
        'categories' => 1,
        'icon'       => '<iconify-icon icon="solar:hand-money-bold" width="24" height="24"></iconify-icon>',
        'manualbook' => 'Service Handphone.pdf',
    ];

    /** Matches the current Proses Service sidebar grants for menu 72. */
    private array $targetJabatans = [0, 1, 2, 34, 35, 38, 40, 41, 47];

    public function up()
    {
        $exists = $this->db->table('menu')
            ->where('idmenu', self::MENU_ID)
            ->countAllResults();

        if ($exists === 0) {
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
