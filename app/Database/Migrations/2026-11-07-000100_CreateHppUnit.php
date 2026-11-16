<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * HPP per unit.
 *
 * HPP tidak lagi hanya menempel di master `barang.harga_beli` (global). Satu
 * baris per produk+unit menyimpan HPP yang dipakai halaman Kartu Stok, diisi
 * dari stok awal terakhir yang harganya positif, dan boleh di-override admin.
 *
 * `sumber` membedakan nilai hasil stok awal ('stok_awal') dari koreksi admin
 * ('manual'). Sync stok awal berikutnya tidak boleh menimpa nilai manual.
 */
class CreateHppUnit extends Migration
{
    public function up()
    {
        // Forge/DDL tidak membersihkan cache skema; reset supaya up() idempoten.
        $this->db->dataCache['table_names'] = [];

        if (!$this->db->tableExists('hpp_unit')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'barang_idbarang' => ['type' => 'INT', 'constraint' => 11],
                'unit_idunit'     => ['type' => 'INT', 'constraint' => 11],
                'hpp'             => ['type' => 'DECIMAL', 'constraint' => [15, 2], 'default' => 0],
                'sumber'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'stok_awal'],
                'updated_by'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['barang_idbarang', 'unit_idunit'], 'uk_hpp_unit_barang_unit');
            $this->forge->createTable('hpp_unit', true);
            $this->db->dataCache['table_names'] = [];
        }

        $this->backfillDariStokAwal();
    }

    public function down()
    {
        $this->forge->dropTable('hpp_unit', true);
        $this->db->dataCache['table_names'] = [];
    }

    /**
     * Ambil baris stok awal terakhir yang harganya > 0 per produk+unit.
     * Override manual yang sudah ada tidak ditimpa saat migrasi diulang.
     */
    private function backfillDariStokAwal(): void
    {
        if (!$this->db->tableExists('stok_awal')) {
            return;
        }

        $hppUnit  = $this->db->prefixTable('hpp_unit');
        $stokAwal = $this->db->prefixTable('stok_awal');

        $this->db->query(
            "INSERT INTO `{$hppUnit}` (barang_idbarang, unit_idunit, hpp, sumber, updated_at)
             SELECT sa.barang_idbarang, sa.unit_idunit, sa.harga_beli, 'stok_awal', NOW()
             FROM `{$stokAwal}` sa
             JOIN (
                 SELECT barang_idbarang, unit_idunit, MAX(idstok_awal) AS idstok_awal
                 FROM `{$stokAwal}`
                 WHERE harga_beli IS NOT NULL AND harga_beli > 0
                 GROUP BY barang_idbarang, unit_idunit
             ) terakhir ON terakhir.idstok_awal = sa.idstok_awal
             ON DUPLICATE KEY UPDATE
                 hpp = IF(`{$hppUnit}`.sumber = 'manual', `{$hppUnit}`.hpp, VALUES(hpp)),
                 updated_at = IF(`{$hppUnit}`.sumber = 'manual', `{$hppUnit}`.updated_at, NOW())"
        );
    }
}
