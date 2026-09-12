<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSpvUnitsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => false,
                'auto_increment' => true,
            ],
            'spv_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false,
                'comment'    => 'ID_AKUN dari SPV (akun.ID_AKUN)',
            ],
            'unit_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => false,
                'comment'    => 'ID unit (unit.idunit)',
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
        $this->forge->addUniqueKey(['spv_id', 'unit_id'], 'unique_spv_unit');
        
        $this->forge->createTable('spv_units', true);
    }

    public function down()
    {
        $this->forge->dropTable('spv_units', true);
    }
}
