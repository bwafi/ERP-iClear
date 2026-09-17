<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu sidebar modul "Social Media" (KPI Social Media).
 *
 * Mengikuti pola EnsureContentSidebarMenus: insert ke tabel menu bila belum
 * ada + tambahkan idmenu ke jabatan.ROLES_JABATAN.
 *
 * Role yang berhak:
 *   0  Admin Center, 1 Admin root, 2 Direktur, 34 Manager,
 *   43 Kepala Divisi Digital Marketing (kelola KPI & target, read dashboard).
 */
class EnsureSocialMediaSidebarMenus extends Migration
{
    private array $menuDefs = [
        [
            'idmenu'     => 10100,
            'urutan'     => 120,
            'nama_menu'  => 'Social Media',
            'roles'      => 'social_media',
            'url'        => '',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => 0,
            'utama'      => 1,
            'categories' => 1,
            'icon'       => '<iconify-icon icon="solar:share-circle-bold" width="24" height="24"></iconify-icon>',
        ],
        [
            'idmenu'     => 10101,
            'urutan'     => 121,
            'nama_menu'  => 'KPI Social Media',
            'roles'      => 'social_media_kpi',
            'url'        => 'sosial/kpi',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => 10100,
            'utama'      => 1,
            'categories' => 0,
            'icon'       => null,
        ],
        [
            'idmenu'     => 10102,
            'urutan'     => 122,
            'nama_menu'  => 'Akun Social Media',
            'roles'      => 'social_media_account',
            'url'        => 'sosial/account',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => 10100,
            'utama'      => 1,
            'categories' => 0,
            'icon'       => null,
        ],
        [
            'idmenu'     => 10103,
            'urutan'     => 123,
            'nama_menu'  => 'Target Social Media',
            'roles'      => 'social_media_target',
            'url'        => 'sosial/target',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => 10100,
            'utama'      => 1,
            'categories' => 0,
            'icon'       => null,
        ],
    ];

    private array $targetJabatans = [0, 1, 2, 34, 43];

    public function up()
    {
        foreach ($this->menuDefs as $menu) {
            $exists = $this->db->table('menu')->where('idmenu', $menu['idmenu'])->get()->getRow();
            if ($exists) {
                continue;
            }
            $this->db->table('menu')->insert($menu);
        }

        $menuIds = array_column($this->menuDefs, 'idmenu');

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

            $merged = array_values(array_unique(array_merge($roles, $menuIds)));
            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        $ids = array_column($this->menuDefs, 'idmenu');

        foreach ($ids as $id) {
            $this->db->table('menu')->where('idmenu', $id)->delete();
        }

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

            $filtered = array_values(array_filter($roles, fn($r) => !in_array((int)$r, $ids, true)));

            $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($filtered)]);
        }
    }
}