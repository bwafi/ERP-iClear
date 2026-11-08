<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Status aktif produk per unit.
 *
 * Soft delete master (`barang.deleted`) bersifat global. Tabel ini menyimpan
 * pengecualian per produk+unit: baris `aktif = 0` menyembunyikan produk dari
 * stok opname dan picker transaksi unit itu saja. Tanpa baris = aktif, jadi
 * tabel tetap sparse. View `stok_barang` mengekspos `aktif_unit` supaya semua
 * query bisa memfilter dengan satu kolom.
 */
class CreateBarangUnit extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('barang_unit')) {
            $this->forge->addField([
                'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'barang_idbarang' => ['type' => 'INT'],
                'unit_idunit'     => ['type' => 'INT'],
                'aktif'           => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'updated_by'      => ['type' => 'INT', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['barang_idbarang', 'unit_idunit']);
            $this->forge->createTable('barang_unit', true);
        }

        $this->db->query($this->viewSql(true));
    }

    public function down()
    {
        $this->db->query($this->viewSql(false));
        $this->forge->dropTable('barang_unit', true);
    }

    private function viewSql(bool $denganStatus): string
    {
        $aktif = $denganStatus
            ? "  COALESCE(MAX(barang_unit.aktif), 1) AS aktif_unit,\n"
            : '';
        $join = $denganStatus
            ? "    LEFT JOIN barang_unit ON (barang_unit.barang_idbarang = barang.idbarang"
                . " AND barang_unit.unit_idunit = combined_stocks.idunit)\n"
            : '';

        return <<<SQL
CREATE OR REPLACE VIEW stok_barang AS
SELECT
  barang.idbarang,
  barang.kode_barang,
  barang.nama_barang,
  barang.harga,
  barang.harga_beli,
  barang.idkategori,
  barang.imei,
  barang.jenis_hp,
  barang.internal,
  barang.warna,
  barang.status,
  barang.status_ppn,
  barang.status_barang,
  barang.stok_minimum,
  barang.deleted,
  barang.input,
  barang.nama_barang_id,
{$aktif}  combined_stocks.idunit AS id_unit,
  unit.NAMA_UNIT AS nama_unit,
  SUM(
    0
    + combined_stocks.stok_awal
    + combined_stocks.total_pembelian
    + combined_stocks.total_mutasi_masuk
    + combined_stocks.total_retur_pelanggan
    - combined_stocks.total_penjualan
    - combined_stocks.total_mutasi_keluar
    - combined_stocks.total_retur_supplier
    - combined_stocks.total_kerusakan
    - combined_stocks.total_pemakaian_garansi
  ) AS stok_akhir,
  SUM(combined_stocks.stok_awal) AS stok_awal,
  SUM(combined_stocks.stok_opname) AS stok_opname,
  SUM(combined_stocks.total_pembelian) AS total_pembelian,
  SUM(combined_stocks.total_mutasi_masuk) AS total_mutasi_masuk,
  SUM(combined_stocks.total_retur_pelanggan) AS total_retur_pelanggan,
  SUM(combined_stocks.total_penjualan) AS total_penjualan,
  SUM(combined_stocks.total_mutasi_keluar) AS total_mutasi_keluar,
  SUM(combined_stocks.total_retur_supplier) AS total_retur_supplier,
  SUM(combined_stocks.total_kerusakan) AS total_kerusakan,
  SUM(combined_stocks.total_pemakaian_garansi) AS total_pemakaian_garansi
FROM
  (
    (
      barang
      JOIN (
        SELECT detail_pembelian.barang_idbarang AS idbarang, detail_pembelian.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, SUM(detail_pembelian.jumlah) AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM detail_pembelian
        GROUP BY detail_pembelian.barang_idbarang, detail_pembelian.unit_idunit
        UNION ALL
        SELECT stok_awal.barang_idbarang AS idbarang, stok_awal.unit_idunit AS idunit, SUM(stok_awal.jumlah) AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM stok_awal
        GROUP BY stok_awal.barang_idbarang, stok_awal.unit_idunit
        UNION ALL
        SELECT detail_penjualan.barang_idbarang AS idbarang, detail_penjualan.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, SUM(detail_penjualan.jumlah) AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM detail_penjualan
        GROUP BY detail_penjualan.barang_idbarang, detail_penjualan.unit_idunit
        UNION ALL
        SELECT detail_mutasi.barang_idbarang AS idbarang, detail_mutasi.kirim_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, SUM(detail_mutasi.jumlah_kirim) AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM detail_mutasi
        JOIN mutasi ON mutasi.idmutasi = detail_mutasi.mutasi_idmutasi
        WHERE mutasi.status = 1
        GROUP BY detail_mutasi.barang_idbarang, detail_mutasi.kirim_idunit
        UNION ALL
        SELECT detail_mutasi.barang_idbarang AS idbarang, detail_mutasi.terima_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, SUM(detail_mutasi.jumlah_terima) AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM detail_mutasi
        JOIN mutasi ON mutasi.idmutasi = detail_mutasi.mutasi_idmutasi
        WHERE mutasi.status = 1
        GROUP BY detail_mutasi.barang_idbarang, detail_mutasi.terima_idunit
        UNION ALL
        SELECT retur_pelanggan.barang_idbarang AS idbarang, retur_pelanggan.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, SUM(retur_pelanggan.jumlah) AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM retur_pelanggan
        GROUP BY retur_pelanggan.barang_idbarang, retur_pelanggan.unit_idunit
        UNION ALL
        SELECT retur_suplier.barang_idbarang AS idbarang, retur_suplier.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, SUM(retur_suplier.jumlah) AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM retur_suplier
        GROUP BY retur_suplier.barang_idbarang, retur_suplier.unit_idunit
        UNION ALL
        SELECT barang_rusak.barang_idbarang AS idbarang, barang_rusak.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, SUM(barang_rusak.jumlah) AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM barang_rusak
        GROUP BY barang_rusak.barang_idbarang, barang_rusak.unit_idunit
        UNION ALL
        SELECT stok_opname.barang_idbarang AS idbarang, stok_opname.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, 0 AS total_pemakaian_garansi
        FROM stok_opname
        WHERE stok_opname.is_reverted = 0
        GROUP BY stok_opname.barang_idbarang, stok_opname.unit_idunit
        UNION ALL
        SELECT service_sparepart.barang_idbarang AS idbarang, service_sparepart.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, SUM(service_sparepart.jumlah_tambahan_garansi - service_sparepart.jumlah_refund_garansi) AS total_pemakaian_garansi
        FROM service_sparepart
        JOIN service ON service.idservice = service_sparepart.service_idservice
        WHERE service.tanggal_claim_garansi IS NOT NULL
          AND service_sparepart.jumlah_tambahan_garansi > 0
        GROUP BY service_sparepart.barang_idbarang, service_sparepart.unit_idunit
      ) combined_stocks ON (barang.idbarang = combined_stocks.idbarang)
    )
    JOIN unit ON (unit.idunit = combined_stocks.idunit)
{$join}  )
WHERE
  barang.deleted = '0'
GROUP BY
  barang.idbarang,
  combined_stocks.idunit
SQL;
    }
}
