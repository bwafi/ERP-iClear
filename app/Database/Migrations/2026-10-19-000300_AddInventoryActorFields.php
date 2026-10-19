<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddInventoryActorFields extends Migration
{
    public function up()
    {
        foreach (['stok_awal', 'garansi_sparepart_keluar'] as $table) {
            if (! $this->db->fieldExists('input_by', $table)) {
                $this->forge->addColumn($table, [
                    'input_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                ]);
            }
        }
    }

    public function down()
    {
        throw new \RuntimeException('Rollback kolom petugas harus memakai snapshot TEST pra-migrasi.');
    }
}
