<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sembunyikan menu Presensi lama (`/absensi`) dari sidebar karena alur
 * absensi mobile-first sudah memakai `/absen`. Route tetap ada.
 */
class HidePresensiLamaMenu extends Migration
{
    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->db->table('menu')->where('idmenu', 112)->update(['show_menu' => 0]);
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->db->table('menu')->where('idmenu', 112)->update(['show_menu' => 1]);
    }
}
