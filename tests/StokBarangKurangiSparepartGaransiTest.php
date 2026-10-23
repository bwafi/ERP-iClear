<?php

namespace Tests;

use App\Database\Migrations\StokBarangKurangiSparepartGaransi;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Sparepart pengganti pada klaim garansi mengurangi stok lewat view, sedangkan
 * barang rusak klaim tidak boleh menyentuh stok. View memakai nama tabel tanpa
 * prefix, jadi test ini menyetel prefix koneksi ke ''.
 */
class StokBarangKurangiSparepartGaransiTest extends CIUnitTestCase
{
    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->hapusStokBarang();

        foreach ([
            'barang', 'unit', 'mutasi', 'detail_mutasi', 'detail_pembelian',
            'stok_awal', 'detail_penjualan', 'retur_pelanggan', 'retur_suplier',
            'barang_rusak', 'barang_rusak_claim', 'stok_opname', 'service',
            'service_sparepart',
        ] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusStokBarang(): void
    {
        $row = $this->db->query(
            "SELECT TABLE_TYPE FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stok_barang'"
        )->getRow();

        if ($row) {
            $this->db->query($row->TABLE_TYPE === 'VIEW' ? 'DROP VIEW stok_barang' : 'DROP TABLE stok_barang');
        }
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $this->hapusStokBarang();

        foreach ([
            'barang', 'unit', 'mutasi', 'detail_mutasi', 'detail_pembelian',
            'stok_awal', 'detail_penjualan', 'retur_pelanggan', 'retur_suplier',
            'barang_rusak', 'barang_rusak_claim', 'stok_opname', 'service',
            'service_sparepart',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE barang (
                idbarang INT PRIMARY KEY, kode_barang VARCHAR(50), nama_barang VARCHAR(100),
                harga DECIMAL(15,2), harga_beli DECIMAL(15,2), idkategori INT,
                imei VARCHAR(50), jenis_hp VARCHAR(50), internal INT, warna VARCHAR(50),
                status INT, status_ppn INT, status_barang INT, stok_minimum INT,
                deleted VARCHAR(5), input VARCHAR(50), nama_barang_id INT)');
        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(100))');
        $q('CREATE TABLE mutasi (idmutasi INT PRIMARY KEY, status INT)');
        $q('CREATE TABLE detail_mutasi (
                id INT AUTO_INCREMENT PRIMARY KEY, mutasi_idmutasi INT,
                barang_idbarang INT, kirim_idunit INT, terima_idunit INT,
                jumlah_kirim DECIMAL(15,2), jumlah_terima DECIMAL(15,2))');
        $q('CREATE TABLE detail_pembelian (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE stok_awal (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE detail_penjualan (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE retur_pelanggan (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE retur_suplier (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE barang_rusak (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE barang_rusak_claim (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE stok_opname (barang_idbarang INT, unit_idunit INT, is_reverted INT)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, tanggal_claim_garansi DATETIME NULL)');
        $q('CREATE TABLE service_sparepart (
                service_idservice INT, barang_idbarang INT, unit_idunit INT,
                jumlah_tambahan_garansi DECIMAL(15,2))');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $q("INSERT INTO barang VALUES
            (1, 'A-1', 'Produk A', 100, 80, 1, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL)");
        $q("INSERT INTO unit VALUES (1, 'Unit 1'), (2, 'Unit 2')");
        $q('INSERT INTO stok_awal VALUES (1, 1, 10)');
        $q("INSERT INTO service VALUES (1, '2026-10-20 10:00:00'), (2, NULL)");
        $q('INSERT INTO service_sparepart VALUES (1, 1, 1, 2), (2, 1, 1, 3)');
    }

    private function migrasi(): StokBarangKurangiSparepartGaransi
    {
        require_once APPPATH . 'Database/Migrations/2026-10-23-000500_StokBarangKurangiSparepartGaransi.php';

        return new StokBarangKurangiSparepartGaransi(Database::forge('tests'));
    }

    private function stokUnitSatu(): float
    {
        $row = $this->db->query('SELECT stok_akhir FROM stok_barang WHERE id_unit = 1')->getRow();

        return (float) $row->stok_akhir;
    }

    public function testSparepartGaransiMengurangiStokDanBarangRusakKlaimTidak(): void
    {
        $this->migrasi()->up();

        $this->assertSame(8.0, $this->stokUnitSatu(), 'service yang sudah klaim mengurangi stok; yang belum klaim tidak');

        $this->db->query('INSERT INTO barang_rusak VALUES (1, 1, 1)');
        $this->assertSame(7.0, $this->stokUnitSatu(), 'barang rusak lama tetap mengurangi stok');

        $this->db->query('INSERT INTO barang_rusak_claim VALUES (1, 1, 100)');
        $this->assertSame(7.0, $this->stokUnitSatu(), 'barang rusak klaim tidak boleh mengurangi stok');

        $this->db->query('UPDATE service_sparepart SET jumlah_tambahan_garansi = 5 WHERE service_idservice = 1');
        $this->assertSame(4.0, $this->stokUnitSatu(), 'qty pengganti mengikuti nilai terbaru');
    }
}
