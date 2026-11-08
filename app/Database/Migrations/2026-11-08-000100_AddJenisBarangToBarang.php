<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda jenis barang: produk vs jasa.
 *
 * Jasa (PUP LCD, jasa mesin, dll.) sebelumnya adalah `barang` biasa tanpa
 * penanda; qty-nya hanya "tiket" agar lolos filter `stok_akhir > 0` di
 * picker kasir, diisi lewat stok awal. Akibatnya jasa terhitung sebagai
 * persediaan — nilai HPP Kartu Stok membengkak — dan pengecualiannya
 * tambalan awalan nama yang berbeda antar modul (StokOpnameService:
 * PUP/JASA/MESIN; TutupKasir: mesin/PUP/RESTORE/FLASH/CLEANING/JASA).
 *
 * Kolom `jenis_barang` menjadi satu sumber kebenaran. View `stok_barang`
 * — dan seluruh turunannya (Kartu Stok, nilai stok, stok opname, picker
 * stok) — hanya memuat produk. Baris jasa tetap punya idbarang, jadi
 * detail_penjualan, retur, DP, tutup kasir, dan KPI tetap jalan tanpa
 * perubahan query.
 *
 * Backfill sekali pakai dari gabungan kedua daftar awalan existing.
 * Data historis tidak ditulis ulang; qty stok awal jasa lama hanya
 * berhenti dihitung sejak view diperbarui.
 */
class AddJenisBarangToBarang extends Migration
{
    /** Awalan nama jasa existing (gabungan opname + tutup kasir); MySQL LIKE case-insensitive. */
    private const JASA_AWALAN = ['PUP', 'JASA', 'MESIN', 'RESTORE', 'FLASH', 'CLEANING'];

    public function up()
    {
        if (! $this->db->fieldExists('jenis_barang', 'barang')) {
            $this->forge->addColumn('barang', [
                'jenis_barang' => [
                    'type'       => 'ENUM',
                    'constraint' => ['produk', 'jasa'],
                    'null'       => false,
                    'default'    => 'produk',
                    'after'      => 'nama_barang',
                ],
            ]);
        }

        $or = implode(' OR ', array_map(
            static fn (string $awalan) => "nama_barang LIKE '{$awalan}%'",
            self::JASA_AWALAN
        ));
        $this->db->query("UPDATE barang SET jenis_barang = 'jasa' WHERE {$or}");

        $this->db->query($this->viewSql(true));
    }

    public function down()
    {
        $this->db->query($this->viewSql(false));

        if ($this->db->fieldExists('jenis_barang', 'barang')) {
            $this->forge->dropColumn('barang', 'jenis_barang');
        }
    }

    /**
     * Definisi view `stok_barang`. Dengan $denganJenis: hanya produk
     * (kolom jenis_barang diekspos + filter); tanpa: definisi lama apa adanya.
     */
    private function viewSql(bool $denganJenis): string
    {
        $kolomJenis = $denganJenis ? "  barang.jenis_barang,\n" : '';
        $whereAkhir = $denganJenis
            ? "  barang.deleted = '0'\n  AND barang.jenis_barang = 'produk'\n"
            : "  barang.deleted = '0'\n";

        return <<<SQL
CREATE OR REPLACE VIEW stok_barang AS
SELECT
  barang.idbarang,
  barang.kode_barang,
  barang.nama_barang,
{$kolomJenis}  barang.harga,
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
  COALESCE(MAX(barang_unit.aktif), 1) AS aktif_unit,
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
    LEFT JOIN barang_unit ON (barang_unit.barang_idbarang = barang.idbarang AND barang_unit.unit_idunit = combined_stocks.idunit)
  )
WHERE
{$whereAkhir}GROUP BY
  barang.idbarang,
  combined_stocks.idunit
SQL;
    }
}
