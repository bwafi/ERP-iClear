<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel detail absensi KPI: menyimpan jam masuk aktual + metadata untuk
 * perhitungan otomatis nilai kehadiran. Relasi 1:1 (shift biasa) atau 1:2
 * (PS: pagi+sore) ke kpi_evaluations.
 *
 * Backward-compatible: data lama di kpi_evaluations (tanpa detail ini) tetap
 * valid dengan raw_score manual. Service prioritas baca auto_score dari sini.
 */
class CreateKpiAttendanceDetail extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('kpi_attendance_detail')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'evaluation_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false,
                'comment'    => 'FK ke kpi_evaluations.id (KEHADIRAN component)',
            ],
            'shift' => [
                'type'       => 'ENUM',
                'constraint' => ['PAGI', 'SIANG', 'PS'],
                'null'       => false,
                'comment'    => 'Shift karyawan',
            ],
            'session' => [
                'type'       => 'ENUM',
                'constraint' => ['PAGI', 'SORE', 'FULL'],
                'null'       => false,
                'default'    => 'FULL',
                'comment'    => 'Sesi: PAGI/SORE (khusus PS), FULL (Pagi/Siang)',
            ],
            'attendance_type' => [
                'type'       => 'ENUM',
                'constraint' => ['NORMAL', 'IZIN_TELAT'],
                'null'       => false,
                'default'    => 'NORMAL',
                'comment'    => 'Jenis absensi: NORMAL atau IZIN_TELAT',
            ],
            'scheduled_time' => [
                'type'    => 'TIME',
                'null'    => false,
                'comment' => 'Jam mulai shift (08:45, 12:45, 17:00)',
            ],
            'actual_time' => [
                'type'    => 'TIME',
                'null'    => false,
                'comment' => 'Jam masuk aktual dari absensi WA',
            ],
            'late_minutes' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'default'    => 0,
                'comment'    => 'Keterlambatan dalam menit (0 jika tepat waktu)',
            ],
            'auto_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '3,1',
                'null'       => false,
                'default'    => '5.0',
                'comment'    => 'Nilai otomatis (0-5) berdasarkan aturan scoring',
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
        $this->forge->addKey('evaluation_id');
        $this->forge->addKey(['shift', 'session']);
        $this->forge->createTable('kpi_attendance_detail', true);
    }

    public function down()
    {
        $this->forge->dropTable('kpi_attendance_detail', true);
    }
}