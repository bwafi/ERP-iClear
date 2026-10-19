<?php

namespace App\Database\Migrations;

class CreateWarrantyStockIssues extends LinkDamagedStockToSupplierReturn
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'claim_id' => ['type' => 'INT', 'constraint' => 11],
            'service_id' => ['type' => 'INT', 'constraint' => 11],
            'barang_id' => ['type' => 'INT', 'constraint' => 11],
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'jumlah' => ['type' => 'INT', 'constraint' => 11],
            'hpp_satuan' => ['type' => 'BIGINT', 'constraint' => 20],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['claim_id', 'barang_id'], false, true, 'uniq_garansi_claim_barang');
        $this->forge->addKey(['service_id', 'barang_id'], false, false, 'idx_garansi_service_barang');
        $this->forge->createTable('garansi_sparepart_keluar', true);
        $this->db->query($this->warrantyStockView());
    }

    public function down()
    {
        if ($this->db->table('garansi_sparepart_keluar')->countAllResults() > 0) {
            throw new \RuntimeException('Rollback ditolak: mutasi stok garansi sudah tercatat.');
        }
        $this->db->query($this->linkedStockView());
        $this->forge->dropTable('garansi_sparepart_keluar', true);
    }

    protected function warrantyStockView(): string
    {
        $sql = $this->linkedStockView();
        $sql = str_replace(
            '- combined_stocks.total_kerusakan',
            "- combined_stocks.total_kerusakan\n    - combined_stocks.total_garansi_keluar",
            $sql
        );
        $sql = str_replace(
            'SUM(combined_stocks.total_kerusakan) AS total_kerusakan',
            "SUM(combined_stocks.total_kerusakan) AS total_kerusakan,\n  SUM(combined_stocks.total_garansi_keluar) AS total_garansi_keluar",
            $sql
        );
        $sql = str_replace(
            '0 AS total_retur_terkait',
            '0 AS total_retur_terkait, 0 AS total_garansi_keluar',
            $sql
        );
        $sql = str_replace(
            'SUM(CASE WHEN retur_suplier.barang_rusak_id IS NOT NULL THEN retur_suplier.jumlah ELSE 0 END) AS total_retur_terkait',
            'SUM(CASE WHEN retur_suplier.barang_rusak_id IS NOT NULL THEN retur_suplier.jumlah ELSE 0 END) AS total_retur_terkait, 0 AS total_garansi_keluar',
            $sql
        );
        $sql = str_replace(
            "        UNION ALL\n        SELECT stok_opname.barang_idbarang",
            "        UNION ALL\n        SELECT garansi_sparepart_keluar.barang_id AS idbarang, garansi_sparepart_keluar.unit_id AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_retur_terkait, SUM(garansi_sparepart_keluar.jumlah) AS total_garansi_keluar\n        FROM garansi_sparepart_keluar\n        GROUP BY garansi_sparepart_keluar.barang_id, garansi_sparepart_keluar.unit_id\n        UNION ALL\n        SELECT stok_opname.barang_idbarang",
            $sql
        );
        return $sql;
    }
}
