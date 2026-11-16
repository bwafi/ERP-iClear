<?php

namespace Tests;

use App\Database\Migrations\CreateBarangUnit;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Migrasi status aktif produk per unit: tabel `barang_unit` dan kolom
 * `aktif_unit` pada view `stok_barang`.
 *
 * Sama seperti RefundGaransiMigrationTest, prefix disetel '' karena SQL view
 * ditulis dengan nama tabel mentah.
 */
final class BarangUnitMigrationTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    private const TABEL = [
        'barang', 'unit', 'mutasi', 'detail_mutasi', 'detail_pembelian',
        'stok_awal', 'detail_penjualan', 'retur_pelanggan', 'retur_suplier',
        'barang_rusak', 'stok_opname', 'service', 'service_sparepart',
        'barang_unit',
    ];

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

        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        // tableExists() meng-cache daftar tabel per koneksi; buang agar test
        // berikutnya melihat kondisi tabel yang baru dibuat ulang.
        $this->db->dataCache = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusStokBarang(): void
    {
        $row = $this->db->query(
            'SELECT TABLE_TYPE FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'stok_barang\''
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

        foreach (self::TABEL as $tabel) {
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
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, tanggal_claim_garansi DATETIME NULL)');
        $q('CREATE TABLE service_sparepart (
                service_idservice INT, barang_idbarang INT, unit_idunit INT,
                jumlah_tambahan_garansi DECIMAL(15,2), jumlah_refund_garansi DECIMAL(15,2) DEFAULT 0)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $q("INSERT INTO barang VALUES
            (1, 'A-1', 'Produk A', 100, 80, 1, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL)");
        $q("INSERT INTO unit VALUES (1, 'Unit 1'), (2, 'Unit 2')");
        $q('INSERT INTO stok_awal VALUES (1, 1, 10), (1, 2, 4)');
    }

    private function migrasi(): CreateBarangUnit
    {
        $path = APPPATH . 'Database/Migrations/2026-11-07-000200_CreateBarangUnit.php';
        if (! file_exists($path)) {
            $this->fail('Migrasi CreateBarangUnit belum ada.');
        }

        require_once $path;

        return new CreateBarangUnit(Database::forge('tests'));
    }

    public function testUpMenambahKolomDefaultAktifDanMenghormatiStatusPerUnit(): void
    {
        $this->migrasi()->up();

        $this->assertTrue($this->db->tableExists('barang_unit'));
        $this->assertTrue($this->db->fieldExists('aktif_unit', 'stok_barang'));

        $unitSatu = $this->db->query('SELECT stok_akhir, aktif_unit FROM stok_barang WHERE id_unit = 1')->getRow();
        $this->assertSame(10.0, (float) $unitSatu->stok_akhir, 'stok lama tidak berubah');
        $this->assertSame(1, (int) $unitSatu->aktif_unit, 'tanpa baris barang_unit = aktif');

        $this->db->query('INSERT INTO barang_unit (barang_idbarang, unit_idunit, aktif) VALUES (1, 1, 0)');

        $unitSatu = $this->db->query('SELECT aktif_unit FROM stok_barang WHERE id_unit = 1')->getRow();
        $unitDua  = $this->db->query('SELECT aktif_unit FROM stok_barang WHERE id_unit = 2')->getRow();
        $this->assertSame(0, (int) $unitSatu->aktif_unit, 'nonaktif hanya untuk unit 1');
        $this->assertSame(1, (int) $unitDua->aktif_unit, 'unit lain tetap aktif');
    }

    public function testDownMenghapusTabelDanKolomTanpaMerusakView(): void
    {
        $this->migrasi()->up();
        $migrasi = $this->migrasi();
        $migrasi->down();

        $this->assertFalse($this->db->tableExists('barang_unit'));
        $this->assertFalse($this->db->fieldExists('aktif_unit', 'stok_barang'));

        $row = $this->db->query('SELECT stok_akhir FROM stok_barang WHERE id_unit = 1')->getRow();
        $this->assertSame(10.0, (float) $row->stok_akhir);
    }
}
