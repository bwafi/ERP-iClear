<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penilaian Kesesuaian Brief — diisi MANUAL oleh Kepala Divisi (jabatan 43).
 *
 * Setiap penilaian content disimpan sebagai baris baru (riwayat); KPI membaca
 * verdict TERBARU (MAX id) per content.
 */
class CreateContentBriefVerdicts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'content_id'  => ['type' => 'INT'],
            'sesuai'      => ['type' => 'TINYINT', 'constraint' => 1], // 1=sesuai brief, 0=tidak sesuai
            'catatan'     => ['type' => 'TEXT', 'null' => true],
            'penilai_id'  => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('content_id');
        $this->forge->addKey('penilai_id');
        $this->forge->addForeignKey('content_id', 'contents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('penilai_id', 'akun', 'ID_AKUN', 'RESTRICT', 'SET NULL');
        $this->forge->createTable('content_brief_verdicts', true);
    }

    public function down()
    {
        $this->forge->dropTable('content_brief_verdicts', true);
    }
}