<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rename hub menu modul konten menjadi "Digital Marketing" (umbrella
 * untuk submenu Konten, manajemen CS, dll di kemudian hari).
 */
class RenameContentMenuToDigitalMarketing extends Migration
{
    public function up()
    {
        $this->db->table('menu')->where('idmenu', 10040)->update(['nama_menu' => 'Digital Marketing']);
        $this->db->table('menu')->where('idmenu', 10041)->update(['nama_menu' => 'Dashboard Digital Marketing']);
    }

    public function down()
    {
        $this->db->table('menu')->where('idmenu', 10040)->update(['nama_menu' => 'KPI Konten']);
        $this->db->table('menu')->where('idmenu', 10041)->update(['nama_menu' => 'Dashboard Konten']);
    }
}