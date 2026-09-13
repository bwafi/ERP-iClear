<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Input harian jumlah review Google Maps per unit (Kepala Toko).
 *
 * total_customer TIDAK disimpan di sini — dihitung on-the-fly dari
 * transaksi penjualan (kode_invoice LIKE 'SLL%') + service per tanggal & unit.
 */
class CreateCustomerSatisfaction extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('customer_satisfaction')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'tanggal' => [
                    'type' => 'DATE',
                ],
                'id_unit' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'jumlah_review' => [
                    'type'       => 'INT',
                    'constraint' => 6,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
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
            $this->forge->addUniqueKey(['tanggal', 'id_unit'], 'uq_customer_sat_tanggal_unit');
            $this->forge->addKey('id_unit');
            $this->forge->createTable('customer_satisfaction', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('customer_satisfaction', true);
    }
}