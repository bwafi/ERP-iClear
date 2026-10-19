<?php

namespace App\Database\Migrations;

class LinkWarrantyDamagedStock extends CreateWarrantyStockIssues
{
    public function up()
    {
        $this->forge->addColumn('barang_rusak', [
            'claim_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        ]);
        $this->db->query('CREATE INDEX idx_barang_rusak_claim ON barang_rusak (claim_id, barang_idbarang)');
        $this->db->query($this->warrantyDamagedStockView());
    }

    public function down()
    {
        $linked = (int) $this->db->table('barang_rusak')->where('claim_id IS NOT NULL', null, false)->countAllResults();
        if ($linked > 0) {
            throw new \RuntimeException('Rollback ditolak: barang rusak sudah terhubung dengan klaim.');
        }
        $this->db->query($this->warrantyStockView());
        $this->db->query('DROP INDEX idx_barang_rusak_claim ON barang_rusak');
        $this->forge->dropColumn('barang_rusak', 'claim_id');
    }

    protected function warrantyDamagedStockView(): string
    {
        $sql = $this->warrantyStockView();
        $sql = str_replace(
            'SUM(barang_rusak.jumlah) AS total_kerusakan',
            'SUM(CASE WHEN barang_rusak.claim_id IS NULL THEN barang_rusak.jumlah ELSE 0 END) AS total_kerusakan',
            $sql
        );
        $sql = str_replace(
            "FROM retur_suplier\n        GROUP BY",
            "FROM retur_suplier\n        LEFT JOIN barang_rusak AS linked_rusak ON linked_rusak.idbarang_rusak = retur_suplier.barang_rusak_id\n        GROUP BY",
            $sql
        );
        $sql = str_replace(
            'SUM(retur_suplier.jumlah) AS total_retur_supplier',
            'SUM(CASE WHEN linked_rusak.claim_id IS NULL THEN retur_suplier.jumlah ELSE 0 END) AS total_retur_supplier',
            $sql
        );
        $sql = str_replace(
            'retur_suplier.barang_rusak_id IS NOT NULL THEN retur_suplier.jumlah',
            'linked_rusak.idbarang_rusak IS NOT NULL AND linked_rusak.claim_id IS NULL THEN retur_suplier.jumlah',
            $sql
        );
        return $sql;
    }
}
