<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Refund pembayaran klaim garansi.
 *
 * Audit refund disimpan di `service` karena `kas_keluar` tidak punya kolom
 * referensi. Qty sparepart yang kembali ke stok dicatat di
 * `service_sparepart.jumlah_refund_garansi`; view stok mengurangkan qty itu
 * supaya barang baik tidak dihitung keluar lagi.
 */
class AddGaransiServiceRefund extends Migration
{
    private const KOLOM_SERVICE = [
        'refund_garansi_idkas_keluar' => 'INT NULL',
        'refund_garansi_tanggal'      => 'DATE NULL',
        'refund_garansi_metode'       => 'VARCHAR(10) NULL',
        'refund_garansi_idbank'       => 'VARCHAR(20) NULL',
        'refund_garansi_referensi'    => 'VARCHAR(100) NULL',
        'refund_garansi_alasan'       => 'VARCHAR(255) NULL',
        'refund_garansi_jumlah'       => 'INT NOT NULL DEFAULT 0',
        'refund_garansi_by'           => 'INT NULL',
        'refund_garansi_at'           => 'DATETIME NULL',
    ];

    private const TEMPLATE = [
        ['refund_garansi_tunai', '2010302000', 'Pendapatan Diterima di Muka (Jasa Service)', 'debet'],
        ['refund_garansi_tunai', '1010101000', 'Kas Besar (Cash on Hand)', 'kredit'],
        ['refund_garansi_bank', '2010302000', 'Pendapatan Diterima di Muka (Jasa Service)', 'debet'],
        ['refund_garansi_bank', '1010102000', 'Kas di Bank', 'kredit'],
    ];

    public function up()
    {
        foreach (self::KOLOM_SERVICE as $kolom => $definisi) {
            $this->tambahKolom('service', $kolom, $definisi);
        }

        $this->tambahKolom('service_sparepart', 'jumlah_refund_garansi', 'INT NOT NULL DEFAULT 0');

        foreach (self::TEMPLATE as [$kode, $noAkun, $namaAkun, $debetKredit]) {
            $this->tambahTemplate($kode, $noAkun, $namaAkun, $debetKredit);
        }

        $this->db->query($this->viewSql(true));
    }

    public function down()
    {
        if ($this->db->fieldExists('refund_garansi_at', 'service')
            && $this->db->table('service')->where('refund_garansi_at IS NOT NULL', null, false)->countAllResults() > 0) {
            throw new \RuntimeException('Refund garansi sudah dicatat; audit refund tidak dapat dihapus lewat rollback migrasi.');
        }

        $this->db->query($this->viewSql(false));

        if ($this->db->tableExists('template_jurnal')) {
            $this->db->table('template_jurnal')
                ->whereIn('kode_template', ['refund_garansi_tunai', 'refund_garansi_bank'])
                ->delete();
        }

        foreach (array_keys(self::KOLOM_SERVICE) as $kolom) {
            $this->hapusKolom('service', $kolom);
        }
        $this->hapusKolom('service_sparepart', 'jumlah_refund_garansi');
    }

    private function tambahKolom(string $table, string $column, string $definition): void
    {
        $tabel = $this->db->prefixTable($table);
        $ada = $this->db->query(
            'SELECT COUNT(*) AS jumlah FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabel, $column]
        )->getRow()->jumlah;

        if ((int) $ada === 0) {
            $this->db->query("ALTER TABLE {$tabel} ADD COLUMN {$column} {$definition}");
        }
    }

    private function hapusKolom(string $table, string $column): void
    {
        $tabel = $this->db->prefixTable($table);
        $ada = $this->db->query(
            'SELECT COUNT(*) AS jumlah FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabel, $column]
        )->getRow()->jumlah;

        if ((int) $ada > 0) {
            $this->db->query("ALTER TABLE {$tabel} DROP COLUMN {$column}");
        }
    }

    private function tambahTemplate(string $kode, string $noAkun, string $namaAkun, string $debetKredit): void
    {
        if (! $this->db->tableExists('template_jurnal')) {
            return;
        }

        $ada = $this->db->table('template_jurnal')
            ->where('kode_template', $kode)
            ->where('no_akun', $noAkun)
            ->where('debet_kredit', $debetKredit)
            ->where('array_value', 0)
            ->countAllResults();

        if ($ada === 0) {
            $this->db->table('template_jurnal')->insert([
                'kode_template' => $kode,
                'no_akun'       => $noAkun,
                'nama_akun'     => $namaAkun,
                'debet_kredit'  => $debetKredit,
                'array_value'   => 0,
                'keterangan'    => 'Refund garansi service',
            ]);
        }
    }

    private function viewSql(bool $denganRefund): string
    {
        $pemakaian = $denganRefund
            ? 'SUM(service_sparepart.jumlah_tambahan_garansi - service_sparepart.jumlah_refund_garansi)'
            : 'SUM(service_sparepart.jumlah_tambahan_garansi)';

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
        SELECT service_sparepart.barang_idbarang AS idbarang, service_sparepart.unit_idunit AS idunit, 0 AS stok_awal, 0 AS stok_opname, 0 AS total_pembelian, 0 AS total_penjualan, 0 AS total_mutasi_keluar, 0 AS total_mutasi_masuk, 0 AS total_retur_pelanggan, 0 AS total_retur_supplier, 0 AS total_kerusakan, {$pemakaian} AS total_pemakaian_garansi
        FROM service_sparepart
        JOIN service ON service.idservice = service_sparepart.service_idservice
        WHERE service.tanggal_claim_garansi IS NOT NULL
          AND service_sparepart.jumlah_tambahan_garansi > 0
        GROUP BY service_sparepart.barang_idbarang, service_sparepart.unit_idunit
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
    }
}
