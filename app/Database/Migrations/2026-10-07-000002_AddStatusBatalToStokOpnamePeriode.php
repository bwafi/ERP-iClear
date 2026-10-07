<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStatusBatalToStokOpnamePeriode extends Migration
{
    public function up()
    {
        // Modify ENUM to include BATAL
        $this->db->query("ALTER TABLE stok_opname_periode MODIFY COLUMN status ENUM('DRAFT','FINAL','BATAL') NOT NULL DEFAULT 'DRAFT'");
        
        // Add fields for cancellation audit if needed
        $fields = [
            'batal_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'after' => 'reopen_by',
            ],
            'tanggal_batal' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'tanggal_reopen',
            ],
            'alasan_batal' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'alasan_reopen',
            ],
        ];
        foreach ($fields as $name => $def) {
            $this->forge->addColumn('stok_opname_periode', [$name => $def]);
        }
    }

    public function down()
    {
        $this->db->query("ALTER TABLE stok_opname_periode MODIFY COLUMN status ENUM('DRAFT','FINAL') NOT NULL DEFAULT 'DRAFT'");
        $this->forge->dropColumn('stok_opname_periode', ['batal_by', 'tanggal_batal', 'alasan_batal']);
    }
}
