<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryAdjustmentAccounts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'inventory_account' => ['type' => 'VARCHAR', 'constraint' => 10],
            'shortage_account' => ['type' => 'VARCHAR', 'constraint' => 10],
            'surplus_account' => ['type' => 'VARCHAR', 'constraint' => 10],
        ]);
        $this->forge->addKey('unit_id', true);
        $this->forge->createTable('inventory_adjustment_accounts', true);
    }

    public function down()
    {
        if ($this->db->table('inventory_adjustment_accounts')->countAllResults() > 0) {
            throw new \RuntimeException('Pemetaan akun penyesuaian sudah digunakan; rollback ditolak.');
        }
        $this->forge->dropTable('inventory_adjustment_accounts', true);
    }
}
