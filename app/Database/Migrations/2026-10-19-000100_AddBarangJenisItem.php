<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBarangJenisItem extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('jenis_item', 'barang')) {
            $this->forge->addColumn('barang', [
                'jenis_item' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'null' => true,
                    'default' => null,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('jenis_item', 'barang')) {
            if ($this->db->table('barang')->where('jenis_item IS NOT NULL', null, false)->countAllResults() > 0) {
                throw new \RuntimeException('Rollback ditolak: klasifikasi jenis item sudah dipakai.');
            }
            $this->forge->dropColumn('barang', 'jenis_item');
        }
    }
}
