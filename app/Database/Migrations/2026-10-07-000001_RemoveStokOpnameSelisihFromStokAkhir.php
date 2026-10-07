<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveStokOpnameSelisihFromStokAkhir extends Migration
{
    public function up()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $sql = <<<SQL
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
  combined_stocks.idunit AS id_unit,
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
  ) AS stok_akhir,
  SUM(combined_stocks.stok_awal) AS stok_awal,
  SUM(combined_stocks.stok_opname) AS stok_opname,
  SUM(combined_stocks.total_pembelian) AS total_pembelian,
  SUM(combined_stocks.total_mutasi_masuk) AS total_mutasi_masuk,
  SUM(combined_stocks.total_retur_pelanggan) AS total_retur_pelanggan,
  SUM(combined_stocks.total_penjualan) AS total_penjualan,
  SUM(combined_stocks.total_mutasi_keluar) AS total_mutasi_keluar,
  SUM(combined_stocks.total_retur_supplier) AS total_retur_supplier,
  SUM(combined_stocks.total_kerusakan) AS total_kerusakan
FROM
  (
    (
      barang
      JOIN (
        SELECT detail_pembelian.barang_idbarang AS idbarang, detail_pembelian.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, SUM(detail_pembelian.jumlah) AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan
        FROM detail_pembelian
        GROUP BY detail_pembelian.barang_idbarang, detail_pembelian.unit_idunit
        UNION ALL
        SELECT stok_awal.barang_idbarang AS idbarang, stok_awal.unit_idunit AS idunit, SUM(stok_awal.jumlah) AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan
        FROM stok_awal
        GROUP BY stok_awal.barang_idbarang, stok_awal.unit_idunit
        UNION ALL
        SELECT detail_penjualan.barang_idbarang AS idbarang, detail_penjualan.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, SUM(detail_penjualan.jumlah) AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan
        FROM detail_penjualan
        GROUP BY detail_penjualan.barang_idbarang, detail_penjualan.unit_idunit
        UNION ALL
        SELECT detail_mutasi.barang_idbarang AS idbarang, detail_mutasi.kirim_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, SUM(detail_mutasi.jumlah_kirim) AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan
        FROM detail_mutasi
        GROUP BY detail_mutasi.barang_idbarang, detail_mutasi.kirim_idunit
        UNION ALL
        SELECT detail_mutasi.barang_idbarang AS idbarang, detail_mutasi.terima_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, SUM(detail_mutasi.jumlah_terima) AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan
        FROM detail_mutasi
        GROUP BY detail_mutasi.barang_idbarang, detail_mutasi.terima_idunit
        UNION ALL
        SELECT retur_pelanggan.barang_idbarang AS idbarang, retur_pelanggan.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, SUM(retur_pelanggan.jumlah) AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan
        FROM retur_pelanggan
        GROUP BY retur_pelanggan.barang_idbarang, retur_pelanggan.unit_idunit
        UNION ALL
        SELECT retur_suplier.barang_idbarang AS idbarang, retur_suplier.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, SUM(retur_suplier.jumlah) AS total_retur_supplier, 0 AS total_kerusakan
        FROM retur_suplier
        GROUP BY retur_suplier.barang_idbarang, retur_suplier.unit_idunit
        UNION ALL
        SELECT barang_rusak.barang_idbarang AS idbarang, barang_rusak.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, SUM(barang_rusak.jumlah) AS total_kerusakan
        FROM barang_rusak
        GROUP BY barang_rusak.barang_idbarang, barang_rusak.unit_idunit
        UNION ALL
        SELECT stok_opname.barang_idbarang AS idbarang, stok_opname.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan
        FROM stok_opname
        WHERE stok_opname.is_reverted = 0
        GROUP BY stok_opname.barang_idbarang, stok_opname.unit_idunit
      ) combined_stocks ON (barang.idbarang = combined_stocks.idbarang)
    )
    JOIN unit ON (unit.idunit = combined_stocks.idunit)
  )
WHERE
  barang.deleted = '0'
GROUP BY
  barang.idbarang,
  combined_stocks.idunit
SQL;
        $this->db->query($sql);
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down()
    {
        // Tidak mengembalikan penambahan selisih ke stok_akhir sesuai requirement
    }
}
