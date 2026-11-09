<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * Picker service (tab Sparepart di /service dan
 * /detail/riwayat_service/{id}?tab=sparepart) punya dua tombol: "Pilih
 * Sparepart" dan "Pilih Jasa". Endpoint `service/search_sparepart` memilah
 * hasil dari parameter `jenis`:
 * - sparepart (default): produk berstok dari view `stok_barang`, kode sprt/acc;
 * - jasa: master `barang.jenis_barang='jasa'` tanpa syarat stok.
 *
 * Jasa tidak ada di view stok_barang (bukan persediaan), tetapi teknisi
 * memakainya sebagai item service — PUP, mesin, cleaning.
 */
final class ServiceSparepartJasaTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    private const TABEL = ['barang', 'stok_barang', 'stok_awal', 'suplier', 'unit', 'akun', 'jabatan', 'menu', 'service', 'pelanggan', 'hpp_unit', 'hpp_barang'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga REAL NULL, harga_beli REAL NULL, warna TEXT NULL, imei TEXT NULL,
                jenis_barang TEXT NOT NULL DEFAULT "produk", deleted INT NOT NULL DEFAULT 0)');
        $q('CREATE TABLE stok_barang (idbarang INT, id_unit INT, kode_barang TEXT NULL,
                nama_barang TEXT NULL, harga REAL NULL, harga_beli REAL NULL, warna TEXT NULL,
                imei TEXT NULL, stok_akhir REAL NULL, stok_minimum REAL NULL, aktif_unit INT NULL)');
        $q('CREATE TABLE stok_awal (barang_idbarang INT, unit_idunit INT, suplier_id_suplier INT)');
        $q('CREATE TABLE suplier (id_suplier INT PRIMARY KEY, nama_suplier TEXT NULL)');
        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL, is_operational INT NULL DEFAULT 1)');

        // BaseController (notifikasi stok/service) ikut query tabel ini.
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                no_service TEXT NULL, tipe_hp TEXT NULL, created_at TEXT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                service_by INT NULL, prioritas INT NULL, no_hp TEXT NULL,
                tanggal_claim_garansi TEXT NULL, status_proses INT NULL,
                keterangan TEXT NULL, garansi_hari INT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, deleted INT NULL DEFAULT 0)');

        // HPP per unit; kosong berarti fallback ke master barang.harga_beli.
        $q('CREATE TABLE hpp_unit (id INT PRIMARY KEY AUTO_INCREMENT, barang_idbarang INT NULL, unit_idunit INT NULL, hpp REAL NULL DEFAULT 0)');
        $q('CREATE TABLE hpp_barang (idbarang INT NULL, hpp REAL NULL DEFAULT 0)');

        $q('INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, "Unit 1")');
        $q('INSERT INTO jabatan VALUES (35, "ADMIN / KASIR", "[]")');
        $q('INSERT INTO akun VALUES (1, 1, 35, "Tester", NULL)');
        $q('INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli, warna, imei, jenis_barang, deleted) VALUES
            (1, "sprt001", "LCD Sparepart", 150000, 100000, "Hitam", NULL, "produk", 0),
            (2, "acc001", "Anti Gores", 20000, 10000, NULL, NULL, "produk", 0),
            (3, "JASA-01", "PUP IPHONE 13", 250000, 0, NULL, NULL, "jasa", 0),
            (4, "sprt100", "CLEANING IPHONE XR", 50000, 0, NULL, NULL, "jasa", 0)');
        // Hanya produk berstok yang muncul di view; produk 2 stok 0, jasa tidak ada.
        $q('INSERT INTO stok_barang (idbarang, id_unit, kode_barang, nama_barang, harga, harga_beli, warna, imei, stok_akhir, aktif_unit) VALUES
            (1, 1, "sprt001", "LCD Sparepart", 150000, 100000, "Hitam", NULL, 5, 1),
            (2, 1, "acc001", "Anti Gores", 20000, 10000, NULL, NULL, 0, 1)');
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }
        $this->db->dataCache = [];
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function cari(string $search, string $jenis = 'sparepart'): array
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 1,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit 1',
        ]);

        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('service/search_sparepart', ['search' => $search, 'jenis' => $jenis]);

        $result->assertStatus(200);

        return json_decode((string) $result->response()->getBody(), true) ?: [];
    }

    /** @return array<string, object> idbarang => row */
    private function peta(array $rows): array
    {
        $peta = [];
        foreach ($rows as $row) {
            $peta[(int) $row['idbarang']] = (object) $row;
        }

        return $peta;
    }

    public function testJasaMunculWalauTidakAdaDiViewStok(): void
    {
        $peta = $this->peta($this->cari('PUP', 'jasa'));

        $this->assertArrayHasKey(3, $peta);
        $this->assertSame('jasa', $peta[3]->jenis_barang);
        $this->assertSame(0.0, (float) $peta[3]->stok_akhir);
        $this->assertSame('Unit 1', $peta[3]->NAMA_UNIT);
    }

    public function testJasaBerawalanSprtJugaTetapBisaDipilih(): void
    {
        $peta = $this->peta($this->cari('CLEANING', 'jasa'));

        $this->assertArrayHasKey(4, $peta, 'jasa kode sprt tidak boleh hilang dari picker service');
        $this->assertSame('jasa', $peta[4]->jenis_barang);
    }

    public function testProdukTidakMunculDiModeJasa(): void
    {
        $peta = $this->peta($this->cari('LCD', 'jasa'));

        $this->assertArrayNotHasKey(1, $peta, 'mode jasa hanya menampilkan master jasa');
        $this->assertSame([], $peta);
    }

    public function testJasaTidakMunculDiModeSparepart(): void
    {
        $peta = $this->peta($this->cari('IPHONE', 'sparepart'));

        $this->assertArrayNotHasKey(3, $peta);
        $this->assertArrayNotHasKey(4, $peta);
    }

    public function testProdukSparepartBerstokTetapMuncul(): void
    {
        $peta = $this->peta($this->cari('LCD', 'sparepart'));

        $this->assertArrayHasKey(1, $peta);
        $this->assertSame('produk', $peta[1]->jenis_barang);
        $this->assertSame(5.0, (float) $peta[1]->stok_akhir);
        $this->assertSame(100000.0, (float) $peta[1]->hpp,
            'tanpa hpp_unit/hpp_barang, HPP picker fallback ke master');
    }

    public function testProdukStokNolTidakMuncul(): void
    {
        $peta = $this->peta($this->cari('Anti Gores', 'sparepart'));

        $this->assertArrayNotHasKey(2, $peta, 'produk stok 0 tetap disaring seperti sebelumnya');
    }

    public function testTanpaJenisDianggapModeSparepart(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 1,
            'ID_UNIT'   => 1,
            'NAMA_UNIT' => 'Unit 1',
        ]);

        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('service/search_sparepart', ['search' => 'LCD']);

        $result->assertStatus(200);
        $peta = $this->peta(json_decode((string) $result->response()->getBody(), true) ?: []);

        $this->assertArrayHasKey(1, $peta);
        $this->assertArrayNotHasKey(3, $peta);
    }
}
