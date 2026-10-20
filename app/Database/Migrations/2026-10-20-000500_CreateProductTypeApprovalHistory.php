<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductTypeApprovalHistory extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'barang_id' => ['type' => 'INT'],
            'jenis_sebelum' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'jenis_sesudah' => ['type' => 'VARCHAR', 'constraint' => 20],
            'petugas_id' => ['type' => 'INT'],
            'alasan' => ['type' => 'VARCHAR', 'constraint' => 500],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('barang_id');
        $this->forge->createTable('barang_tipe_history', true);
    }

    public function down()
    {
        if ($this->db->tableExists('barang_tipe_history') && $this->db->table('barang_tipe_history')->countAllResults() > 0) {
            throw new \RuntimeException('Rollback ditolak: histori persetujuan tipe item harus dipertahankan.');
        }
        $this->forge->dropTable('barang_tipe_history', true);
    }
}
