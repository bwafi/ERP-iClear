<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu sidebar modul "Digital Marketing" (Content Management untuk KPI Multimedia).
 *
 * Mengikuti pola EnsurePenilaianSidebarMenus: insert ke tabel menu (bila belum
 * ada) + tambahkan idmenu ke jabatan.ROLES_JABATAN untuk role yang berhak.
 *
 * Role yang berhak mengakses modul konten:
 *   0  Admin Center, 1 Admin root, 2 Direktur, 34 Manager,
 *   43 Kepala Divisi (monitoring), 44 Multimedia/Creative (operasional),
 *   48 Talent (view-only).
 */
class EnsureContentSidebarMenus extends Migration
{
    private array $menuDefs = [
        [
            'idmenu'     => 10040,
            'urutan'     => 109,
            'nama_menu'  => 'Digital Marketing',
            'roles'      => 'konten',
            'url'        => '',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => 0,
            'utama'      => 1,
            'categories' => 1,
            'icon'       => '<iconify-icon icon="solar:gallery-bold" width="24" height="24"></iconify-icon>',
        ],
        [
            'idmenu'     => 10041,
            'urutan'     => 110,
            'nama_menu'  => 'Dashboard Digital Marketing',
            'roles'      => 'konten_dashboard',
            'url'        => 'konten/dashboard',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => 10040,
            'utama'      => 1,
            'categories' => 0,
            'icon'       => null,
        ],
        [
            'idmenu'     => 10042,
            'urutan'     => 111,
            'nama_menu'  => 'Manajemen Konten',
            'roles'      => 'konten',
            'url'        => 'konten',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => 10040,
            'utama'      => 1,
            'categories' => 0,
            'icon'       => null,
        ],
    ];

    private array $targetJabatans = [0, 1, 2, 34, 43, 44, 48];

    public function up()
    {
        foreach ($this->menuDefs as $def) {
            $exists = $this->db->table('menu')
                ->where('idmenu', $def['idmenu'])
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('menu')->insert($def);
            }
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
        foreach (array_column($this->menuDefs, 'idmenu') as $id) {
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

            $ids = array_column($this->menuDefs, 'idmenu');
            $merged = array_values(array_filter($roles, fn($r) => !in_array((int)$r, $ids, true)));
            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode(array_values($merged))]);
            }
        }
    }
}
