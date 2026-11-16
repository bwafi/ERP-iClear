<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Harga jual per produk+unit.
 *
 * Tanpa baris = ikut `barang.harga`. Tabel ini terpisah dari `hpp_unit`
 * supaya harga jual dan HPP tidak saling memengaruhi.
 */
class CreateHargaJualUnit extends Migration
{
    public function up()
    {
        // Forge/DDL tidak membersihkan cache skema; reset supaya up() idempoten.
        $this->db->dataCache['table_names'] = [];

        if (!$this->db->tableExists('harga_jual_unit')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'barang_idbarang' => ['type' => 'INT', 'constraint' => 11],
                'unit_idunit'     => ['type' => 'INT', 'constraint' => 11],
                'harga_jual'      => ['type' => 'DECIMAL', 'constraint' => [15, 2]],
                'updated_by'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['barang_idbarang', 'unit_idunit'], 'uk_harga_jual_unit_barang_unit');
            $this->forge->createTable('harga_jual_unit', true);
            $this->db->dataCache['table_names'] = [];
        }
    }

    public function down()
    {
        $this->forge->dropTable('harga_jual_unit', true);
        $this->db->dataCache['table_names'] = [];
    }
}
