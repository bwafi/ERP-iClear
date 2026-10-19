<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryLedger extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'item_id' => ['type' => 'INT', 'constraint' => 11],
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'quantity' => ['type' => 'BIGINT', 'constraint' => 20, 'default' => 0],
            'value' => ['type' => 'BIGINT', 'constraint' => 20, 'default' => 0],
        ]);
        $this->forge->addKey(['item_id', 'unit_id'], true);
        $this->forge->createTable('inventory_ledger_balances', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'item_id' => ['type' => 'INT', 'constraint' => 11],
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'source_id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'source_event' => ['type' => 'VARCHAR', 'constraint' => 64],
            'direction' => ['type' => 'VARCHAR', 'constraint' => 16],
            'quantity' => ['type' => 'BIGINT', 'constraint' => 20],
            'unit_cost' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'value' => ['type' => 'BIGINT', 'constraint' => 20],
            'balance_quantity' => ['type' => 'BIGINT', 'constraint' => 20],
            'balance_value' => ['type' => 'BIGINT', 'constraint' => 20],
            'reverses_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_type', 'source_id', 'source_event'], false, true, 'uniq_inventory_source_event');
        $this->forge->addKey(['item_id', 'unit_id', 'id'], false, false, 'idx_inventory_item_unit');
        $this->forge->addKey('reverses_id', false, true, 'uniq_inventory_reversal');
        $this->forge->createTable('inventory_ledger_entries', true);
        $entries = $this->db->prefixTable('inventory_ledger_entries');
        foreach (['update' => 'UPDATE', 'delete' => 'DELETE'] as $suffix => $operation) {
            $name = 'inventory_ledger_no_' . $suffix;
            $exists = $this->db->query('SELECT COUNT(*) AS total FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = ?', [$name])->getRow();
            if ((int) $exists->total === 0) {
                $this->db->query("CREATE TRIGGER {$name} BEFORE {$operation} ON {$entries} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Inventory ledger entries are immutable'");
            }
        }
    }

    public function down()
    {
        if ($this->db->table('inventory_ledger_entries')->countAllResults() > 0) {
            throw new \RuntimeException('Ledger berisi transaksi; rollback ditolak.');
        }
        $this->db->query('DROP TRIGGER IF EXISTS inventory_ledger_no_update');
        $this->db->query('DROP TRIGGER IF EXISTS inventory_ledger_no_delete');
        $this->forge->dropTable('inventory_ledger_entries', true);
        $this->forge->dropTable('inventory_ledger_balances', true);
    }
}
