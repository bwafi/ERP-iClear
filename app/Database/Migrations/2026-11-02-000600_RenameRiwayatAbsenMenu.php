<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti label menu riwayat absensi agar konsisten dengan grup "Absensi".
 * Hanya nama tampilan yang berubah; URL dan hak akses tetap.
 */
class RenameRiwayatAbsenMenu extends Migration
{
    private const NAMA = [
        115 => 'Riwayat Absen',
        117 => 'Riwayat Absen (admin)',
    ];

    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        foreach (self::NAMA as $id => $nama) {
            $this->db->table('menu')->where('idmenu', $id)->update(['nama_menu' => $nama]);
        }
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->db->table('menu')->where('idmenu', 115)->update(['nama_menu' => 'Riwayat Presensi']);
        $this->db->table('menu')->where('idmenu', 117)->update(['nama_menu' => 'Riwayat Presensi(admin)']);
    }
}
