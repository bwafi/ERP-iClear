<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Total Tanggungan Asset per unit per bulan (mulai Oktober 2026).
 *
 * `unit.tanggungan` adalah saldo sisa. Satu baris per unit+periode, dan
 * nominalnya tidak boleh melebihi saldo saat disimpan. Unique key mencegah
 * pemotongan ganda.
 */
class CreateTanggunganAssetBulanan extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'auto_increment' => true],
            'unit_id' => ['type' => 'INT'],
            'periode' => ['type' => 'CHAR', 'constraint' => 7],
            'nominal' => ['type' => 'DECIMAL', 'constraint' => [15, 2], 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['unit_id', 'periode'], false, true, 'uniq_tanggungan_asset_unit_periode');
        $this->forge->addForeignKey('unit_id', 'unit', 'idunit', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('tanggungan_asset_bulanan');
    }

    public function down()
    {
        $this->forge->dropTable('tanggungan_asset_bulanan');
    }
}
