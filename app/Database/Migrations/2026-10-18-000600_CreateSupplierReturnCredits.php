<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSupplierReturnCredits extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'no_retur_suplier' => ['type' => 'VARCHAR', 'constraint' => 40],
            'suplier_id' => ['type' => 'INT', 'constraint' => 11],
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'menunggu'],
            'nominal' => ['type' => 'BIGINT', 'constraint' => 20, 'default' => 0],
            'sisa_kredit' => ['type' => 'BIGINT', 'constraint' => 20, 'default' => 0],
            'nomor_nota_kredit' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'akun_debet' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'akun_piutang' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'akun_kredit' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'disetujui_oleh' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'disetujui_pada' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('no_retur_suplier', false, true, 'uniq_kredit_no_retur');
        $this->forge->addKey(['suplier_id', 'nomor_nota_kredit'], false, true, 'uniq_kredit_nota_supplier');
        $this->forge->addKey(['suplier_id', 'unit_id', 'status'], false, false, 'idx_kredit_suplier');
        $this->forge->createTable('kredit_retur_suplier', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'kredit_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'pembelian_id' => ['type' => 'INT', 'constraint' => 11],
            'nominal' => ['type' => 'BIGINT', 'constraint' => 20],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['kredit_id', 'pembelian_id'], false, true, 'uniq_kredit_pembelian');
        $this->forge->createTable('alokasi_kredit_retur_suplier', true);
    }

    public function down()
    {
        if ($this->db->table('kredit_retur_suplier')->countAllResults() > 0) {
            throw new \RuntimeException('Rollback ditolak: dokumen kredit retur sudah tercatat.');
        }
        $this->forge->dropTable('alokasi_kredit_retur_suplier', true);
        $this->forge->dropTable('kredit_retur_suplier', true);
    }
}
