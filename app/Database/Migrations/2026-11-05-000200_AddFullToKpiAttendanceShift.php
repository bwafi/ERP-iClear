<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah shift FULL (08:45–21:00) ke detail absensi KPI.
 */
class AddFullToKpiAttendanceShift extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('kpi_attendance_detail')) {
            return;
        }

        $tabel = $this->db->prefixTable('kpi_attendance_detail');
        $this->db->query(
            "ALTER TABLE {$tabel}
             MODIFY shift ENUM('PAGI','SIANG','FULL','PS') NOT NULL"
        );
    }

    public function down()
    {
        if (! $this->db->tableExists('kpi_attendance_detail')) {
            return;
        }

        $tabel = $this->db->prefixTable('kpi_attendance_detail');
        $this->db->query("UPDATE {$tabel} SET shift = 'PAGI' WHERE shift = 'FULL'");
        $this->db->query(
            "ALTER TABLE {$tabel}
             MODIFY shift ENUM('PAGI','SIANG','PS') NOT NULL"
        );
    }
}
