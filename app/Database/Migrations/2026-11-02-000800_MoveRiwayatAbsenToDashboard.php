<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pindahkan menu Riwayat Absen (115) dari grup Absensi ke parent DASHBOARD.
 * Riwayat Absen (admin) tetap di grup Absensi.
 */
class MoveRiwayatAbsenToDashboard extends Migration
{
    public function up()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->db->table('menu')->where('idmenu', 115)->update([
            'parent' => 1,
            'urutan' => 1,
        ]);
    }

    public function down()
    {
        $this->db->dataCache['table_names'] = [];
        if (! $this->db->tableExists('menu')) {
            return;
        }

        $this->db->table('menu')->where('idmenu', 115)->update([
            'parent' => 10161,
            'urutan' => 103,
        ]);
    }
}
