<?php

namespace Tests;

use App\Models\ModelBarang;
use App\Models\ModelBarangUnit;
use App\Models\ModelStokBarang;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * Nonaktif produk per unit: guard role, blokir saat masih ada stok, dan
 * penyaringan daftar produk/picker unit tersebut.
 *
 * Sama seperti MutasiStokPickerTest, prefix koneksi disetel '' karena
 * `ModelBarang::getProdukPicker` memakai SQL mentah.
 */
final class BarangNonaktifUnitTest extends CIUnitTestCase
{
    use FeatureTestTrait;

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

    private const TABEL = [
        'unit', 'akun', 'jabatan', 'menu', 'barang', 'kategori',
        'stok_barang', 'barang_unit', 'stok_awal', 'suplier',
        'service', 'pelanggan',
    ];

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

        foreach (self::TABEL as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (idmenu INT PRIMARY KEY AUTO_INCREMENT, urutan INT NULL, nama_menu TEXT NULL,
                roles TEXT NULL, url TEXT NULL, show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');

        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL, imei TEXT NULL,
                jenis_hp TEXT NULL, internal TEXT NULL, warna TEXT NULL,
                status TEXT NULL, status_barang TEXT NULL, input TEXT NULL,
                deleted TEXT NULL DEFAULT "0", status_ppn INT NULL)');
        $q('CREATE TABLE kategori (id INT PRIMARY KEY, nama_kategori TEXT NULL, `delete` TEXT NULL)');

        // Di produksi stok_barang adalah VIEW; di tes cukup tabel dengan kolom
        // yang dipakai controller/picker.
        $q('CREATE TABLE stok_barang (
                idbarang INT NOT NULL, id_unit INT NOT NULL,
                kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL,
                imei TEXT NULL, deleted TEXT NULL, status_ppn INT NULL,
                jenis_hp TEXT NULL, warna TEXT NULL, status_barang TEXT NULL,
                input TEXT NULL, internal TEXT NULL,
                stok_awal REAL NULL DEFAULT 0, stok_akhir REAL NULL,
                stok_minimum REAL NULL, total_pembelian REAL NULL DEFAULT 0,
                total_penjualan REAL NULL DEFAULT 0, total_retur_pelanggan REAL NULL DEFAULT 0,
                total_retur_supplier REAL NULL DEFAULT 0, total_mutasi_masuk REAL NULL DEFAULT 0,
                total_mutasi_keluar REAL NULL DEFAULT 0, aktif_unit INT NULL DEFAULT 1,
                nama_unit TEXT NULL,
                PRIMARY KEY (idbarang, id_unit))');
        $q('CREATE TABLE barang_unit (
                id INT PRIMARY KEY AUTO_INCREMENT,
                barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                aktif TINYINT NOT NULL DEFAULT 1,
                updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');

        $q('CREATE TABLE stok_awal (idstok_awal INT PRIMARY KEY AUTO_INCREMENT,
                unit_idunit INT NULL, barang_idbarang INT NULL,
                suplier_id_suplier VARCHAR(45) NULL, jumlah REAL NULL,
                satuan_terkecil TEXT NULL, tanggal TEXT NULL)');
        $q('CREATE TABLE suplier (id_suplier VARCHAR(45) PRIMARY KEY, nama_suplier TEXT NULL,
                unit_idunit INT NULL, deleted TEXT NULL)');

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

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LOGO) VALUES
            (1, 'Unit Satu', 'logo.png'), (2, 'Unit Dua', 'logo.png')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (1, 'Admin root', '[]'), (35, 'ADMIN / KASIR', '[]'), (36, 'Teknisi', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (1, 1, 1, 'Admin Root'), (43, 1, 35, 'Kasir Satu'), (44, 1, 36, 'Teknisi Satu')");
        $q("INSERT INTO kategori (id, nama_kategori, `delete`) VALUES (1, 'Sparepart', '0')");

        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli, idkategori, deleted, status_ppn) VALUES
            (1, 'P01', 'Produk 01', 100000, 70000, 1, '0', 0),
            (2, 'P02', 'Produk 02', 200000, 150000, 1, '0', 0),
            (3, 'P03', 'Produk 03', 300000, 250000, 1, '0', 0)");

        // Produk 1 stok 0 (boleh nonaktif), produk 2 stok 5, produk 3 stok 3.
        $q("INSERT INTO stok_barang
            (idbarang, id_unit, kode_barang, nama_barang, harga, harga_beli, idkategori, deleted,
             status_ppn, stok_akhir, stok_minimum, total_penjualan, aktif_unit, nama_unit)
            VALUES
            (1, 1, 'P01', 'Produk 01', 100000, 70000, 1, '0', 0, 0, 0, 0, 1, 'Unit Satu'),
            (2, 1, 'P02', 'Produk 02', 200000, 150000, 1, '0', 0, 5, 0, 0, 1, 'Unit Satu'),
            (3, 1, 'P03', 'Produk 03', 300000, 250000, 1, '0', 0, 3, 0, 0, 1, 'Unit Satu'),
            (1, 2, 'P01', 'Produk 01', 100000, 70000, 1, '0', 0, 2, 0, 0, 1, 'Unit Dua')");
    }

    private function sesi(int $akun, int $unit, int $jabatan): void
    {
        $data = [
            'logged_in'    => true,
            'ID_AKUN'      => $akun,
            'ID_UNIT'      => $unit,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ];
        $this->withSession($data);
        // Panggilan model langsung (di luar request) membaca session service.
        session()->set($data);
    }

    private function postStatus(int $idbarang, int $idunit, int $aktif)
    {
        return $this->post('kartu_stok/status', [
            'idbarang'       => $idbarang,
            'id_unit'        => $idunit,
            'aktif'          => $aktif,
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    private function statusTersimpan(int $idbarang, int $idunit): ?object
    {
        return $this->db->table('barang_unit')
            ->where(['barang_idbarang' => $idbarang, 'unit_idunit' => $idunit])
            ->get()
            ->getRow();
    }

    public function testRoleBerwenangBolehMenonaktifkanSaatStokNol(): void
    {
        $this->sesi(43, 1, 35);

        $result = $this->postStatus(1, 1, 0);
        $result->assertStatus(200);

        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertSame('success', $json['status'] ?? null);
        $this->assertSame(0, (int) $this->statusTersimpan(1, 1)->aktif);
    }

    public function testNonaktifDitolakSaatStokTidakNol(): void
    {
        $this->sesi(43, 1, 35);

        $result = $this->postStatus(2, 1, 0);
        $result->assertStatus(422);

        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertStringContainsString('stok', strtolower((string) ($json['message'] ?? '')));
        $this->assertNull($this->statusTersimpan(2, 1));
    }

    public function testNonaktifDitolakSaatStokNegatif(): void
    {
        $this->db->table('stok_barang')
            ->where(['idbarang' => 3, 'id_unit' => 1])
            ->update(['stok_akhir' => -2]);
        $this->sesi(43, 1, 35);

        $result = $this->postStatus(3, 1, 0);

        $result->assertStatus(422);
        $this->assertNull($this->statusTersimpan(3, 1));
    }

    public function testRoleTidakBerwenangDitolak(): void
    {
        $this->sesi(44, 1, 36);

        $result = $this->postStatus(1, 1, 0);

        $result->assertStatus(403);
        $this->assertNull($this->statusTersimpan(1, 1));
    }

    public function testAktivasiKembaliSelaluBoleh(): void
    {
        (new ModelBarangUnit())->setAktif(2, 1, false, 1);
        $this->sesi(43, 1, 35);

        $result = $this->postStatus(2, 1, 1);

        $result->assertStatus(200);
        $this->assertSame(1, (int) $this->statusTersimpan(2, 1)->aktif);
    }

    public function testProdukNonaktifHilangDariDaftarDanPickerUnitItuSaja(): void
    {
        (new ModelBarangUnit())->setAktif(2, 1, false, 1);
        // Di produksi `aktif_unit` dihitung view dari barang_unit; di test kolomnya statis.
        $this->db->table('stok_barang')
            ->where(['idbarang' => 2, 'id_unit' => 1])
            ->update(['aktif_unit' => 0]);

        $this->sesi(43, 1, 35);
        $penjualan = array_map(
            static fn ($row) => (int) $row->idbarang,
            (new ModelStokBarang())->getAllBarang2()
        );
        $this->assertNotContains(2, $penjualan, 'Picker penjualan unit 1 tidak boleh memuat produk nonaktif');
        $this->assertContains(3, $penjualan);

        $pembelian = array_map(
            static fn ($row) => (int) $row->idbarang,
            (new ModelBarang())->getAllBarang()
        );
        $this->assertNotContains(2, $pembelian);
        $this->assertContains(1, $pembelian);

        $picker = (new ModelBarang())->getProdukPicker(1, '', 'nama', 'asc', 20, 0);
        $this->assertNotContains(2, array_map(static fn ($row) => (int) $row->idbarang, $picker));

        // Unit lain tidak terpengaruh: produk 1 tetap aktif di unit 2.
        $this->sesi(43, 2, 35);
        $unitDua = array_map(
            static fn ($row) => (int) $row->idbarang,
            (new ModelStokBarang())->getAllBarang2()
        );
        $this->assertContains(1, $unitDua);
    }
}
