<?php

namespace App\Database\Migrations;

require_once __DIR__ . '/2026-10-18-000500_LinkDamagedStockToSupplierReturn.php';
require_once __DIR__ . '/2026-10-18-000700_CreateWarrantyStockIssues.php';
require_once __DIR__ . '/2026-10-18-000800_LinkWarrantyDamagedStock.php';

class CreateInventoryStockAdjustments extends LinkWarrantyDamagedStock
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'item_id' => ['type' => 'INT', 'constraint' => 11],
            'unit_id' => ['type' => 'INT', 'constraint' => 11],
            'delta' => ['type' => 'INT', 'constraint' => 11],
            'unit_cost' => ['type' => 'BIGINT', 'constraint' => 20],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'source_id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255],
            'actor_id' => ['type' => 'INT', 'constraint' => 11],
            'reverses_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['source_type', 'source_id'], false, true, 'uniq_stock_adjustment_source');
        $this->forge->addKey('reverses_id', false, true, 'uniq_stock_adjustment_reversal');
        $this->forge->addKey(['item_id', 'unit_id', 'id'], false, false, 'idx_stock_adjustment_item');
        $this->forge->createTable('inventory_stock_adjustments', true);
        $this->db->query($this->adjustedStockView());
    }

    public function down()
    {
        if ($this->db->table('inventory_stock_adjustments')->countAllResults() > 0) {
            throw new \RuntimeException('Penyesuaian stok sudah digunakan; rollback ditolak.');
        }
        $this->db->query($this->warrantyDamagedStockView());
        $this->forge->dropTable('inventory_stock_adjustments', true);
    }

    protected function adjustedStockView(): string
    {
        $sql = $this->warrantyDamagedStockView();
        $sql = str_replace(
            '- combined_stocks.total_garansi_keluar',
            "- combined_stocks.total_garansi_keluar\n    + combined_stocks.total_adjustment",
            $sql
        );
        $sql = str_replace(
            'SUM(combined_stocks.total_garansi_keluar) AS total_garansi_keluar',
            "SUM(combined_stocks.total_garansi_keluar) AS total_garansi_keluar,\n  SUM(combined_stocks.total_adjustment) AS total_adjustment",
            $sql
        );
        $sql = str_replace('0 AS total_garansi_keluar',
            '0 AS total_garansi_keluar, 0 AS total_adjustment', $sql);
        $sql = str_replace('SUM(garansi_sparepart_keluar.jumlah) AS total_garansi_keluar',
            'SUM(garansi_sparepart_keluar.jumlah) AS total_garansi_keluar, 0 AS total_adjustment', $sql);
        $needle = "        UNION ALL\n        SELECT stok_opname.barang_idbarang";
        if (substr_count($sql, $needle) !== 1) {
            throw new \RuntimeException('Struktur view stok tidak cocok untuk penyesuaian.');
        }
        return str_replace($needle,
            "        UNION ALL\n        SELECT adjustment.item_id AS idbarang, adjustment.unit_id AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_retur_terkait, 0 AS total_garansi_keluar, SUM(adjustment.delta) AS total_adjustment\n        FROM inventory_stock_adjustments adjustment\n        GROUP BY adjustment.item_id, adjustment.unit_id\n" . $needle,
            $sql);
    }
}
