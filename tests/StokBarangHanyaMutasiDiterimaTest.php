<?php

namespace Tests;

use App\Database\Migrations\StokBarangHanyaMutasiDiterima;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Regresi #7: stok hanya boleh bergerak setelah mutasi diterima, dan kembali
 * saat penerimaan dibatalkan. View produksi memakai nama tabel tanpa prefix,
 * jadi test ini menyetel prefix koneksi ke '' seperti StokOpnameBukanProdukTest.
 */
class StokBarangHanyaMutasiDiterimaTest extends CIUnitTestCase
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
            'barang_rusak', 'stok_opname',
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
            'barang_rusak', 'stok_opname',
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
        $q('CREATE TABLE stok_opname (barang_idbarang INT, unit_idunit INT, is_reverted INT)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $q("INSERT INTO barang VALUES
            (1, 'A-1', 'Produk A', 100, 80, 1, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL)");
        $q("INSERT INTO unit VALUES (1, 'Unit 1'), (2, 'Unit 2')");
        $q('INSERT INTO stok_awal VALUES (1, 1, 100)');
        $q('INSERT INTO mutasi VALUES (1, 0), (2, 1)');
        $q('INSERT INTO detail_mutasi
            (mutasi_idmutasi, barang_idbarang, kirim_idunit, terima_idunit, jumlah_kirim, jumlah_terima)
            VALUES (1, 1, 1, 2, 10, 10), (2, 1, 1, 2, 5, 5)');
    }

    private function migrasi(): StokBarangHanyaMutasiDiterima
    {
        require_once APPPATH . 'Database/Migrations/2026-10-19-000400_StokBarangHanyaMutasiDiterima.php';

        return new StokBarangHanyaMutasiDiterima(Database::forge('tests'));
    }

    /** @return array<string, float> */
    private function stok(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT id_unit, stok_akhir FROM stok_barang ORDER BY id_unit')->getResultArray() as $r) {
            $out[(string) $r['id_unit']] = (float) $r['stok_akhir'];
        }

        return $out;
    }

    public function testStokHanyaDihitungSetelahDiterima(): void
    {
        $migrasi = $this->migrasi();

        $migrasi->down();
        $this->assertSame(['1' => 85.0, '2' => 15.0], $this->stok(), 'view lama menghitung mutasi pending');

        $migrasi->up();
        $this->assertSame(['1' => 95.0, '2' => 5.0], $this->stok(), 'mutasi pending tidak boleh menggeser stok');
    }

    public function testBatalTerimaMengembalikanStok(): void
    {
        $migrasi = $this->migrasi();
        $migrasi->up();

        $this->db->query('UPDATE mutasi SET status = 1 WHERE idmutasi = 1');
        $this->assertSame(['1' => 85.0, '2' => 15.0], $this->stok(), 'stok pindah saat diterima');

        $this->db->query('UPDATE mutasi SET status = 0 WHERE idmutasi = 1');
        $this->assertSame(['1' => 95.0, '2' => 5.0], $this->stok(), 'stok kembali saat penerimaan dibatalkan');
    }
}
