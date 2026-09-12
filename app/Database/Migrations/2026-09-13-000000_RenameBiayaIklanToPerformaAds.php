<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu "Biaya Iklan" (idmenu 10046) mengarah ke marketing/ads yang sudah
 * tidak ada (route & tabel marketing_ads_cost dihapus sejak Performa Ads
 * menjadi sumber tunggal data iklan). Repurpose idmenu yang sama menjadi
 * menu "Performa Ads" agar akses (ROLES_JABATAN/ROLES berisi idmenu 10046)
 * tetap berlaku tanpa ubah grant.
 */
class RenameBiayaIklanToPerformaAds extends Migration
{
    protected const MENU_DEF = [
        'idmenu'    => 10046,
        'urutan'    => 115,
        'nama_menu' => 'Performa Ads',
        'roles'     => 'marketing_ads',
        'url'       => 'marketing/ads_performa',
    ];

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
            return;
        }

        $db->table('menu')
            ->where('idmenu', self::MENU_DEF['idmenu'])
            ->update([
                'nama_menu' => self::MENU_DEF['nama_menu'],
                'roles'     => self::MENU_DEF['roles'],
                'url'       => self::MENU_DEF['url'],
                'show_menu' => 1,
                'parent'    => 10040,
            ]);
    }

    public function down()
    {
        $this->db->table('menu')
            ->where('idmenu', self::MENU_DEF['idmenu'])
            ->update([
                'nama_menu' => 'Biaya Iklan',
                'url'       => 'marketing/ads',
            ]);
    }
}