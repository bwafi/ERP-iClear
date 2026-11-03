<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Halaman /penilaian/absen kini hanya untuk koreksi kehadiran/OFF dan skor
 * Kebersihan & Kepatuhan SOP; kehadiran dan seragam otomatis. Nama menu
 * "Penilaian Absensi" diganti "Penilaian Harian". Route tidak berubah.
 */
class RenamePenilaianAbsensiMenu extends Migration
{
    private const MENU_ID = 10027;

    public function up()
    {
        $this->db->table('menu')
            ->where('idmenu', self::MENU_ID)
            ->update(['nama_menu' => 'Penilaian Harian']);
    }

    public function down()
    {
        $this->db->table('menu')
            ->where('idmenu', self::MENU_ID)
            ->update(['nama_menu' => 'Penilaian Absensi']);
    }
}
