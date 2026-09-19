<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu sidebar "Hutang Piutang" + grant ke jabatan Finance.
 *
 * Struktur:
 *  10110 Hutang Piutang (parent, collapsible)
 *    ├─ 10111 Dashboard Hutang Piutang   -> hutangpiutang/dashboard
 *    ├─ 10112 Piutang                    -> hutangpiutang/piutang
 *    ├─ 10113 Hutang                     -> hutangpiutang/hutang
 *    └─ 10114 Riwayat Pembayaran         -> hutangpiutang/riwayat
 *
 * Grant ke jabatan: 0 ADMIN CENTER, 1 Admin root, 2 Direktur, 34 Manager.
 * Idempotent: cek keberadaan sebelum insert, merge de-duplikasi untuk ROLES.
 */
class AddHutangPiutangMenu extends Migration
{
    private const PARENT_ID = 10110;

    private array $menus = [
        10110 => [
            'urutan' => 125, 'nama_menu' => 'Hutang Piutang', 'roles' => 'hutangpiutang',
            'url' => null, 'parent' => 0, 'categories' => 1, 'utama' => 1,
            'icon' => '<iconify-icon icon="solar:hand-money-bold" width="22" height="22"></iconify-icon>',
        ],
        10111 => [
            'urutan' => 126, 'nama_menu' => 'Dashboard Hutang Piutang', 'roles' => 'hutangpiutang',
            'url' => 'hutangpiutang/dashboard', 'parent' => self::PARENT_ID, 'categories' => 0, 'utama' => 1,
            'icon' => null,
        ],
        10112 => [
            'urutan' => 127, 'nama_menu' => 'Piutang', 'roles' => 'hutangpiutang',
            'url' => 'hutangpiutang/piutang', 'parent' => self::PARENT_ID, 'categories' => 0, 'utama' => 1,
            'icon' => null,
        ],
        10113 => [
            'urutan' => 128, 'nama_menu' => 'Hutang', 'roles' => 'hutangpiutang',
            'url' => 'hutangpiutang/hutang', 'parent' => self::PARENT_ID, 'categories' => 0, 'utama' => 1,
            'icon' => null,
        ],
        10114 => [
            'urutan' => 129, 'nama_menu' => 'Riwayat Pembayaran', 'roles' => 'hutangpiutang',
            'url' => 'hutangpiutang/riwayat', 'parent' => self::PARENT_ID, 'categories' => 0, 'utama' => 1,
            'icon' => null,
        ],
    ];

    private array $targetJabatans = [0, 1, 2, 34];

    public function up()
    {
        foreach ($this->menus as $id => $def) {
            $exists = $this->db->table('menu')->where('idmenu', $id)->countAllResults();
            if ($exists === 0) {
                $this->db->table('menu')->insert([
                    'idmenu'     => $id,
                    'urutan'     => $def['urutan'],
                    'nama_menu'  => $def['nama_menu'],
                    'roles'      => $def['roles'],
                    'url'        => $def['url'],
                    'show_menu'  => 1,
                    'sub'        => 0,
                    'parent'     => $def['parent'],
                    'utama'      => $def['utama'],
                    'categories' => $def['categories'],
                    'icon'       => $def['icon'],
                ]);
            }
        }

        $this->mergeRoles(array_keys($this->menus));
    }

    public function down()
    {
        $this->db->table('menu')->whereIn('idmenu', array_keys($this->menus))->delete();
        $this->removeRoles(array_keys($this->menus));
    }

    private function mergeRoles(array $menuIds): void
    {
        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
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

    private function removeRoles(array $menuIds): void
    {
        foreach ($this->targetJabatans as $jabatanId) {
            $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (!$row) {
                continue;
            }
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
            }
            $roles = array_values(array_filter(
                $roles,
                static fn($id) => !in_array((int) $id, $menuIds, true)
            ));
            $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }
}
