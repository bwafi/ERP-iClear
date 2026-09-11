<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu Digital Marketing (10040):
 *
 * - 10044 "Marketing KPI" → "Dashboard Digital Marketing" (dashboard utama divisi),
 *   dinaikkan urutan ke paling atas (110).
 * - 10041 "Dashboard Digital Marketing" → "Dashboard Multimedia & Creative"
 *   (halaman KPI Multimedia/Creative), urutan 111.
 * - Sisanya digeser agar urutan unik & berurutan.
 */
class RenameReorderDigitalMarketingMenus extends Migration
{
    public function up()
    {
        $menu = $this->db->table('menu');

        $menu->where('idmenu', 10044)->update([
            'nama_menu' => 'Dashboard Digital Marketing',
            'urutan'    => 110,
        ]);
        $menu->where('idmenu', 10041)->update([
            'nama_menu' => 'Dashboard Multimedia',
            'urutan'    => 111,
        ]);
        $menu->where('idmenu', 10042)->update(['urutan' => 112]);
        $menu->where('idmenu', 10043)->update(['urutan' => 113]);
        // 10045 (Lead Marketing) & 10046 (Biaya Iklan) tetap 114/115.
    }

    public function down()
    {
        $menu = $this->db->table('menu');

        $menu->where('idmenu', 10044)->update([
            'nama_menu' => 'Marketing KPI',
            'urutan'    => 113,
        ]);
        $menu->where('idmenu', 10041)->update([
            'nama_menu' => 'Dashboard Digital Marketing',
            'urutan'    => 110,
        ]);
        $menu->where('idmenu', 10042)->update(['urutan' => 111]);
        $menu->where('idmenu', 10043)->update(['urutan' => 112]);
    }
}