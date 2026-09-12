<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Marker hari OFF/Libur absensi KPI (KEHADIRAN).
 *
 * Hari OFF disimpan sebagai TANDA (bukan baris kpi_evaluations raw_score=0),
 * supaya hari OFF benar-benar "null" dan tidak tertukar dengan skor 0
 * (mis. terlambat ≥15 menit). Non-destruktif.
 */
class CreateKpiAttendanceOff extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('kpi_attendance_off')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'employee_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'evaluation_date' => [
                    'type' => 'DATE',
                ],
                'evaluator_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'period_month' => [
                    'type'       => 'TINYINT',
                    'constraint' => 2,
                ],
                'period_year' => [
                    'type'       => 'SMALLINT',
                    'constraint' => 4,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['employee_id', 'evaluation_date', 'evaluator_id']);
            $this->forge->createTable('kpi_attendance_off', true);
        }
    }

    public function down()
    {
        // Non-destruktif: tabel ciri OFF TIDAK dihapus (data lama tidak hilang).
    }
}