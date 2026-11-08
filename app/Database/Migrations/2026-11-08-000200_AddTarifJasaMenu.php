<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu master Tarif Jasa (grup 10170 + item 10171).
 *
 * Jasa keluar dari master barang/stok; halaman /tarif_jasa menjadi pintu
 * kelola harga jasa (tanpa field stok). Idempoten seperti SeedKasBankMenus:
 * cek idmenu sebelum insert, dan merge ROLES_JABATAN yang diberikan.
 */
class AddTarifJasaMenu extends Migration
{
    private const GROUP_ID  = 10170;
    private const JASA_ID   = 10171;

    private array $menuRows = [
        [
            'idmenu'     => self::GROUP_ID,
            'urutan'     => self::GROUP_ID,
            'nama_menu'  => 'Master Jasa',
            'roles'      => 'master_jasa',
            'url'        => '',
            'show_menu'  => 1,
            'sub'        => 1,
            'parent'     => 0,
            'utama'      => 1,
            'categories' => 1,
            'icon'       => '<iconify-icon icon="solar:database-bold" width="24" height="24"></iconify-icon>',
            'manualbook' => null,
        ],
        [
            'idmenu'     => self::JASA_ID,
            'urutan'     => 1,
            'nama_menu'  => 'Tarif Jasa',
            'roles'      => 'tarif_jasa',
            'url'        => 'tarif_jasa',
            'show_menu'  => 1,
            'sub'        => 0,
            'parent'     => self::GROUP_ID,
            'utama'      => 1,
            'categories' => 0,
            'icon'       => null,
            'manualbook' => null,
        ],
    ];

    /** Jabatan yang boleh melihat master (sama dengan audience koreksi HPP/status produk). */
    private array $targetJabatans = [0, 1, 2, 34, 35, 40, 41, 47];

    public function up()
    {
        if (! $this->db->tableExists('menu')) {
            return;
        }

        foreach ($this->menuRows as $row) {
            $exists = $this->db->table('menu')->where('idmenu', $row['idmenu'])->get()->getRow();
            if ($exists) {
                continue;
            }
            $this->db->table('menu')->insert($row);
        }

        $grantIds = array_column($this->menuRows, 'idmenu');

        foreach ($this->targetJabatans as $jabatan) {
            $row = $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatan)
                ->get()
                ->getRow();

            if (! $row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (! is_array($roles)) {
                $roles = [];
            }

            $merged = array_values(array_unique(array_merge($roles, $grantIds)));

            if ($merged !== $roles) {
                $this->db->table('jabatan')
                    ->where('ID_JABATAN', $jabatan)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $grantIds = array_column($this->menuRows, 'idmenu');
        $this->db->table('menu')->whereIn('idmenu', $grantIds)->delete();

        foreach ($this->targetJabatans as $jabatan) {
            $row = $this->db->table('jabatan')->where('ID_JABATAN', $jabatan)->get()->getRow();
            if (! $row) {
                continue;
            }

            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (! is_array($roles)) {
                continue;
            }

            $sisa = array_values(array_filter(
                $roles,
                static fn ($id) => ! in_array((int) $id, $grantIds, true)
            ));

            $this->db->table('jabatan')
                ->where('ID_JABATAN', $jabatan)
                ->update(['ROLES_JABATAN' => json_encode($sisa)]);
        }
    }
}
