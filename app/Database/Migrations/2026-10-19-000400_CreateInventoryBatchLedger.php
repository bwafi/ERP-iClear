<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryBatchLedger extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('source_purchase_detail_id', 'garansi_sparepart_keluar')) {
            $this->forge->addColumn('garansi_sparepart_keluar', [
                'source_purchase_detail_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            ]);
        }
        if (! $this->db->fieldExists('source_purchase_detail_id', 'detail_mutasi')) {
            $this->forge->addColumn('detail_mutasi', [
                'source_purchase_detail_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            ]);
        }
        $this->forge->addField([
            'item_id' => ['type' => 'INT', 'constraint' => 11],
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'batch_id' => ['type' => 'INT', 'constraint' => 11],
            'quantity' => ['type' => 'BIGINT', 'constraint' => 20, 'default' => 0],
        ]);
        $this->forge->addKey(['item_id', 'unit_id', 'batch_id'], true);
        $this->forge->createTable('inventory_batch_balances', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'item_id' => ['type' => 'INT', 'constraint' => 11],
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'batch_id' => ['type' => 'INT', 'constraint' => 11],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'source_id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'source_event' => ['type' => 'VARCHAR', 'constraint' => 64],
            'quantity' => ['type' => 'BIGINT', 'constraint' => 20],
            'reverses_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_type', 'source_id', 'source_event', 'batch_id'], false, true, 'uniq_batch_source');
        $this->forge->addKey(['item_id', 'unit_id', 'batch_id'], false, false, 'idx_batch_balance');
        $this->forge->addKey('reverses_id', false, true, 'uniq_batch_reversal');
        $this->forge->createTable('inventory_batch_events', true);
    }

    public function down()
    {
        if ($this->db->table('inventory_batch_events')->countAllResults() > 0) {
            throw new \RuntimeException('Ledger batch berisi transaksi; rollback ditolak.');
        }
        $this->forge->dropTable('inventory_batch_events', true);
        $this->forge->dropTable('inventory_batch_balances', true);
        if ($this->db->fieldExists('source_purchase_detail_id', 'garansi_sparepart_keluar')) {
            $used = $this->db->table('garansi_sparepart_keluar')
                ->where('source_purchase_detail_id IS NOT NULL', null, false)->countAllResults();
            if ($used > 0) {
                throw new \RuntimeException('Referensi batch pengganti sudah digunakan; rollback ditolak.');
            }
            $this->forge->dropColumn('garansi_sparepart_keluar', 'source_purchase_detail_id');
        }
        if ($this->db->fieldExists('source_purchase_detail_id', 'detail_mutasi')) {
            $used = $this->db->table('detail_mutasi')
                ->where('source_purchase_detail_id IS NOT NULL', null, false)->countAllResults();
            if ($used > 0) {
                throw new \RuntimeException('Referensi batch transfer sudah digunakan; rollback ditolak.');
            }
            $this->forge->dropColumn('detail_mutasi', 'source_purchase_detail_id');
        }
    }
}
