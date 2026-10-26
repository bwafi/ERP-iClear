<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jejak audit koreksi DP service.
 *
 * Koreksi DP mengubah baris `service_dp` dan jurnal DP yang sudah ada, jadi
 * sebelum/sesudah nilainya disimpan di sini agar perubahan tetap dapat
 * ditelusuri. Tabel ini hanya untuk audit; tidak dipakai perhitungan.
 */
class AddServiceDpKoreksi extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('service_dp_koreksi')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'service_dp_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'data_sebelum' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'data_sesudah' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'alasan' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'oleh' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('service_dp_id');
        $this->forge->createTable('service_dp_koreksi', true);
    }

    public function down()
    {
        if (! $this->db->tableExists('service_dp_koreksi')) {
            return;
        }

        if ($this->db->table('service_dp_koreksi')->countAllResults() > 0) {
            throw new \RuntimeException('Jejak audit koreksi DP sudah ada; tabel tidak dihapus lewat rollback migrasi.');
        }

        $this->forge->dropTable('service_dp_koreksi', true);
    }
}
