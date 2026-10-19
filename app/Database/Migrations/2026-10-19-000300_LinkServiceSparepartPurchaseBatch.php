<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkServiceSparepartPurchaseBatch extends Migration
{
    public function up()
    {
        foreach (['service_sparepart', 'detail_penjualan'] as $table) {
            if (! $this->db->fieldExists('source_purchase_detail_id', $table)) {
                $this->forge->addColumn($table, [
                    'source_purchase_detail_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                ]);
            }
        }
    }

    public function down()
    {
        foreach (['service_sparepart', 'detail_penjualan'] as $table) {
            if ($this->db->fieldExists('source_purchase_detail_id', $table)) {
                $used = $this->db->table($table)->where('source_purchase_detail_id IS NOT NULL', null, false)->countAllResults();
                if ($used > 0) {
                    throw new \RuntimeException('Referensi batch pembelian sudah digunakan; rollback ditolak.');
                }
                $this->forge->dropColumn($table, 'source_purchase_detail_id');
            }
        }
    }
}
