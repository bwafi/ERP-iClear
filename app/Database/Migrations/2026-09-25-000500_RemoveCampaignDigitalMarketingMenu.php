<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hapus menu "Campaign Digital Marketing" dari sidebar.
 *
 * Mengikuti pola kampanye Konten (content_campaigns): halaman campaign TIDAK
 * tampil di menu sidebar, diakses lewat tombol (Dashboard Digital Marketing &
 * modal Performa Ads). Baris menu idmenu 10050 dihapus + role dicabut dari
 * jabatan Admin root (1), Manager (34), Kepala Divisi (43).
 */
class RemoveCampaignDigitalMarketingMenu extends Migration
{
    protected const MENU_DEF = [
        'idmenu'    => 10050,
        'urutan'    => 117,
        'nama_menu' => 'Campaign Digital Marketing',
        'roles'     => 'marketing_campaign',
        'url'       => 'marketing/campaign',
    ];

    protected const TARGET_JABATANS = [43, 1, 34];

    public function up()
    {
        $db = $this->db;

        $db->table('menu')->where('idmenu', self::MENU_DEF['idmenu'])->delete();

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

    public function down()
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
}