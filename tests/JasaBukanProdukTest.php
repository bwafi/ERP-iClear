<?php

namespace Tests;

use App\Database\Migrations\AddJenisBarangToBarang;
use App\Models\ModelStokBarang;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Jasa bukan produk: penanda `barang.jenis_barang`, view `stok_barang`
 * hanya memuat produk, dan picker kasir memisahkan grup Jasa.
 *
 * Sama seperti BarangUnitMigrationTest, prefix koneksi disetel '' karena
 * SQL view ditulis dengan nama tabel mentah.
 */
final class JasaBukanProdukTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    private const TABEL = [
        'barang', 'unit', 'mutasi', 'detail_mutasi', 'detail_pembelian',
        'stok_awal', 'detail_penjualan', 'retur_pelanggan', 'retur_suplier',
        'barang_rusak', 'stok_opname', 'service', 'service_sparepart',
        'barang_unit', 'kategori', 'harga_jual_unit',
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

        // Kolom jenis_barang sengaja TIDAK ada di skema awal: ditambahkan migrasi.
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
        $q('CREATE TABLE barang_unit (
                id INT AUTO_INCREMENT PRIMARY KEY, barang_idbarang INT, unit_idunit INT,
                aktif TINYINT(1) DEFAULT 1, updated_by INT NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE kategori (id INT PRIMARY KEY, nama_kategori VARCHAR(100), `delete` VARCHAR(5))');
        $q('CREATE TABLE harga_jual_unit (
                id INT AUTO_INCREMENT PRIMARY KEY, barang_idbarang INT, unit_idunit INT,
                harga_jual DECIMAL(15,2) NOT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli, idkategori,
                imei, jenis_hp, internal, warna, status, status_ppn, status_barang, stok_minimum,
                deleted, input, nama_barang_id) VALUES
            (1, 'A-1', 'Produk A', 100, 80, 1, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL),
            (2, 'PUP-1', 'PUP LCD', 150000, 5000000, 3, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL),
            (3, 'JS-1', 'JASA MESIN IP11PM', 250000, 8000000, 3, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL),
            (4, 'MS-1', 'Mesin Berat X', 300000, 7000000, 3, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL),
            (5, 'RS-1', 'RESTORE DATA', 120000, 3000000, 3, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL)");
        $q("INSERT INTO kategori VALUES (1, 'HP', '0'), (3, 'Sparepart', '0')");
        $q("INSERT INTO unit VALUES (1, 'Unit 1')");
        // Semua barang dapat stok awal "dummy" seperti pola lama agar jasa bisa dijual.
        $q('INSERT INTO stok_awal VALUES (1, 1, 10), (2, 1, 10), (3, 1, 10), (4, 1, 10), (5, 1, 10)');
    }

    private function migrasi(): AddJenisBarangToBarang
    {
        $path = APPPATH . 'Database/Migrations/2026-11-08-000100_AddJenisBarangToBarang.php';
        if (! file_exists($path)) {
            $this->fail('Migrasi AddJenisBarangToBarang belum ada.');
        }

        require_once $path;

        return new AddJenisBarangToBarang(Database::forge('tests'));
    }

    public function testUpMenambahKolomDanMenandaiSemuaAwalanJasa(): void
    {
        $this->migrasi()->up();

        // fieldExists() di up() mengisi cache field sebelum kolom ditambahkan.
        $this->db->resetDataCache();
        $this->assertTrue($this->db->fieldExists('jenis_barang', 'barang'));

        $jasa = $this->db->query(
            "SELECT idbarang FROM barang WHERE jenis_barang = 'jasa' ORDER BY idbarang"
        )->getResultArray();
        $this->assertSame([2, 3, 4, 5], array_map(static fn ($r) => (int) $r['idbarang'], $jasa));

        $produk = (int) $this->db->query("SELECT COUNT(*) AS c FROM barang WHERE jenis_barang = 'produk'")
            ->getRow()->c;
        $this->assertSame(1, $produk);
    }

    public function testViewHanyaProdukJadiNilaiStokBersih(): void
    {
        $this->migrasi()->up();

        $ids = array_map(
            static fn ($r) => (int) $r->idbarang,
            $this->db->query('SELECT idbarang FROM stok_barang WHERE id_unit = 1')->getResult()
        );
        $this->assertSame([1], $ids, 'jasa tidak muncul di stok_barang walau punya stok awal');

        // Replikasi summary nilai stok ModelKartuStok: stok_akhir × HPP.
        $nilai = (float) $this->db->query(
            'SELECT COALESCE(SUM(sb.stok_akhir * b.harga_beli), 0) AS nilai
               FROM stok_barang sb JOIN barang b ON b.idbarang = sb.idbarang'
        )->getRow()->nilai;
        $this->assertSame(800.0, $nilai, 'hanya produk A (10 × 80); tarif jasa besar tidak dihitung');
    }

    public function testSeedOpnameTidakMengambilJasa(): void
    {
        $this->migrasi()->up();

        // Replikasi builder seed StokOpnameService::createPeriode.
        $rows = $this->db->table('stok_barang')
            ->where('id_unit', 1)
            ->where('aktif_unit', 1)
            ->orderBy('kode_barang', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]['idbarang']);
    }

    public function testPickerKasirMemisahkanProdukDanJasa(): void
    {
        $this->migrasi()->up();

        session()->set('ID_UNIT', 1);
        $stokModel = new ModelStokBarang();

        $produk = $stokModel->getAllBarang2();
        $this->assertCount(1, $produk);
        $this->assertSame('Produk A', $produk[0]->nama_barang);

        $jasa = $stokModel->getJasa();
        $this->assertCount(4, $jasa);
        $namaJasa = array_map(static fn ($r) => $r->nama_barang, $jasa);
        $this->assertContains('PUP LCD', $namaJasa);
        $this->assertContains('JASA MESIN IP11PM', $namaJasa);
        $this->assertNotContains('Produk A', $namaJasa);
    }

    public function testDownMengembalikanViewDanMenghapusKolom(): void
    {
        $this->migrasi()->up();
        $migrasi = $this->migrasi();
        $migrasi->down();

        $this->assertFalse($this->db->fieldExists('jenis_barang', 'barang'));

        $ids = array_map(
            static fn ($r) => (int) $r->idbarang,
            $this->db->query('SELECT idbarang FROM stok_barang WHERE id_unit = 1 ORDER BY idbarang')
                ->getResult()
        );
        $this->assertSame([1, 2, 3, 4, 5], $ids, 'view lama kembali memuat jasa');
    }
}
