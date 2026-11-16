<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Input stok awal untuk produk yang nonaktif di unit itu harus mengaktifkan
 * kembali produk tersebut, supaya stok yang masuk tidak tersembunyi dari
 * stok opname/penjualan.
 */
final class StokAwalReaktivasiTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    private const TABEL = [
        'unit', 'akun', 'jabatan', 'menu', 'barang', 'barang_unit',
        'stok_awal', 'hpp_unit', 'stok_barang', 'service', 'pelanggan',
        'harga_jual_unit',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->close();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (idmenu INT PRIMARY KEY AUTO_INCREMENT, urutan INT NULL, nama_menu TEXT NULL,
                roles TEXT NULL, url TEXT NULL, show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');
        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL, deleted TEXT NULL DEFAULT "0")');
        $q('CREATE TABLE barang_unit (
                id INT PRIMARY KEY AUTO_INCREMENT,
                barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                aktif TINYINT NOT NULL DEFAULT 1,
                updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE stok_awal (idstok_awal INT PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, jumlah REAL NULL, barang_idbarang INT NULL,
                harga_beli REAL NULL, satuan_terkecil TEXT NULL, unit_idunit INT NULL,
                suplier_id_suplier VARCHAR(45) NULL, pelanggan_id_pelanggan INT NULL)');
        $q('CREATE TABLE hpp_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                hpp REAL NULL, sumber TEXT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE harga_jual_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                harga_jual REAL NOT NULL, UNIQUE KEY (barang_idbarang, unit_idunit))');

        // BaseController::initController membaca notifikasi stok minimum.
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL,
                stok_akhir REAL NULL, stok_minimum REAL NULL)');

        // BaseController::initController membaca tabel ini.
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL,
                kabupaten TEXT NULL, kecamatan TEXT NULL, deleted TEXT NULL)');
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LOGO) VALUES (1, 'Unit Satu', 'logo.png'), (2, 'Unit Dua', 'logo.png')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES (43, 1, 35, 'Kasir Satu')");
        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli, idkategori, deleted) VALUES
            (1, 'P01', 'Produk 01', 100000, 70000, 1, '0'),
            (2, 'P02', 'Produk 02', 200000, 150000, 1, '0')");
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => 43,
            'ID_UNIT'      => 1,
            'ID_JABATAN'   => 35,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function postStokAwal(string $kode, int $unit, int $jumlah)
    {
        return $this->post('insert/stokawal', [
            'selected_products' => [$kode],
            'global_unit'       => $unit,
            'jumlah'            => [$kode => $jumlah],
            'satuan_terkecil'   => [$kode => 'pcs'],
            'tipe_relasi'       => [$kode => ''],
            'id_suplier_text'   => [$kode => ''],
            'id_pelanggan_text' => [$kode => ''],
        ]);
    }

    private function statusUnit(int $idbarang, int $idunit): ?object
    {
        return $this->db->table('barang_unit')
            ->where(['barang_idbarang' => $idbarang, 'unit_idunit' => $idunit])
            ->get()
            ->getRow();
    }

    public function testStokAwalMengaktifkanKembaliProdukNonaktifDiUnitItu(): void
    {
        $this->db->query('INSERT INTO barang_unit (barang_idbarang, unit_idunit, aktif) VALUES (1, 1, 0), (1, 2, 0)');
        $this->sesi();

        $result = $this->postStokAwal('P01', 1, 5);

        $result->assertRedirect();
        $this->assertSame(1, (int) $this->statusUnit(1, 1)->aktif, 'produk aktif lagi di unit 1');
        $this->assertSame(0, (int) $this->statusUnit(1, 2)->aktif, 'unit lain tidak berubah');
        $this->assertSame(1, $this->db->table('stok_awal')->countAllResults(), 'stok awal tersimpan');
    }

    public function testStokAwalProdukAktifTidakMembuatBarisBarangUnit(): void
    {
        $this->sesi();

        $result = $this->postStokAwal('P02', 1, 3);

        $result->assertRedirect();
        $this->assertNull($this->statusUnit(2, 1), 'produk yang sudah aktif tidak menambah baris');
        $this->assertSame(1, $this->db->table('stok_awal')->countAllResults());
    }
}
