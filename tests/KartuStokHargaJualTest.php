<?php

namespace Tests;

use App\Models\ModelKartuStok;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * Harga jual per produk+unit dari Kartu Stok: role, validasi, dan override
 * hanya untuk unit yang dipilih. `barang.harga` tetap harga master.
 */
final class KartuStokHargaJualTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'barang', 'stok_barang', 'service', 'service_kerusakan',
        'service_sparepart', 'pelanggan', 'unit', 'akun', 'jabatan',
        'harga_jual_unit', 'hpp_unit', 'hpp_barang', 'kategori',
        'detail_pembelian', 'pembelian', 'suplier', 'stok_awal',
    ];

    protected $db;
    protected $shared;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests', false);
        $this->shared = Database::connect();
        $this->prefixAsli = (string) $this->shared->DBPrefix;
        $this->db->setPrefix('');
        $this->shared->setPrefix('');
        $this->buatTabel();
        $this->db->table('barang')->insert([
            'idbarang' => 1, 'kode_barang' => 'P01', 'nama_barang' => 'LCD',
            'harga' => 150000, 'harga_beli' => 100000, 'status_ppn' => 1,
            'idkategori' => 1, 'deleted' => '0',
        ]);
        $this->db->table('kategori')->insert(['id' => 1, 'nama_kategori' => 'Sparepart', 'delete' => 0]);
        $this->db->table('stok_barang')->insertBatch([
            ['idbarang' => 1, 'id_unit' => 1, 'kode_barang' => 'P01', 'nama_barang' => 'LCD', 'harga' => 150000, 'aktif_unit' => 1],
            ['idbarang' => 1, 'id_unit' => 2, 'kode_barang' => 'P01', 'nama_barang' => 'LCD', 'harga' => 150000, 'aktif_unit' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        $this->shared->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatTabel(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach (self::TABEL as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY AUTO_INCREMENT, kode_barang VARCHAR(50) NULL,
                nama_barang VARCHAR(100) NULL, harga INT NULL, harga_beli INT NULL, jenis_hp VARCHAR(50) NULL,
                warna VARCHAR(50) NULL, status_ppn INT NULL, idkategori INT NULL, deleted VARCHAR(2) NULL)');
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL, kode_barang VARCHAR(50) NULL,
                nama_barang VARCHAR(100) NULL, imei VARCHAR(50) NULL, nama_unit VARCHAR(100) NULL,
                harga INT NULL, stok_awal INT NULL DEFAULT 0, stok_akhir INT NULL DEFAULT 0,
                total_pembelian INT NULL DEFAULT 0, total_penjualan INT NULL DEFAULT 0,
                total_retur_pelanggan INT NULL DEFAULT 0, total_retur_supplier INT NULL DEFAULT 0,
                total_mutasi_masuk INT NULL DEFAULT 0, total_mutasi_keluar INT NULL DEFAULT 0,
                stok_minimum INT NULL DEFAULT 0, aktif_unit INT NULL DEFAULT 1)');
        $q('CREATE TABLE harga_jual_unit (id INT PRIMARY KEY AUTO_INCREMENT,
                barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL, harga_jual DECIMAL(15,2) NOT NULL,
                updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE hpp_unit (barang_idbarang INT NULL, unit_idunit INT NULL, hpp DECIMAL(15,2) NULL,
                sumber VARCHAR(20) NULL, updated_by INT NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE hpp_barang (idbarang INT NULL, hpp DECIMAL(15,2) NULL)');
        $q('CREATE TABLE kategori (id INT PRIMARY KEY, nama_kategori VARCHAR(100) NULL, `delete` INT NULL DEFAULT 0)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama VARCHAR(255) NULL)');
        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(150) NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_JABATAN INT NULL,
                ID_UNIT INT NULL, NAMA_AKUN VARCHAR(100) NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY,
                NAMA_JABATAN VARCHAR(150) NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY AUTO_INCREMENT,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                status_service INT NOT NULL DEFAULT 0, status_proses INT NULL,
                tanggal_selesai DATETIME NULL, tanggal_bisa_diambil DATE NULL,
                created_at DATETIME NULL)');
        $q('CREATE TABLE service_kerusakan (idservice_kerusakan INT PRIMARY KEY AUTO_INCREMENT,
                service_idservice INT NULL)');
        $q('CREATE TABLE service_sparepart (idservice_sparepart INT PRIMARY KEY AUTO_INCREMENT,
                service_idservice INT NULL)');
        $q('CREATE TABLE detail_pembelian (barang_idbarang INT NULL, unit_idunit INT NULL,
                tanggal DATE NULL, pembelian_idpembelian INT NULL)');
        $q('CREATE TABLE pembelian (idpembelian INT PRIMARY KEY, suplier_id_suplier INT NULL)');
        $q('CREATE TABLE suplier (id_suplier INT PRIMARY KEY, nama_suplier VARCHAR(100) NULL)');
        $q('CREATE TABLE stok_awal (idstok_awal INT PRIMARY KEY AUTO_INCREMENT,
                barang_idbarang INT NULL, unit_idunit INT NULL, suplier_id_suplier INT NULL, tanggal DATE NULL)');
    }

    private function postHargaJual(int $jabatan, string $harga, int $idbarang = 1, int $idunit = 1)
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => $jabatan,
        ]);

        return $this->post('kartu_stok/harga-jual', [
            'idbarang'       => $idbarang,
            'id_unit'        => $idunit,
            'harga'          => $harga,
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    private function postHpp(int $jabatan, string $hpp, int $idbarang = 1, int $idunit = 1)
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => $jabatan,
        ]);

        return $this->post('kartu_stok/hpp', [
            'idbarang'       => $idbarang,
            'id_unit'        => $idunit,
            'hpp'            => $hpp,
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    private function hargaMaster(): float
    {
        return (float) $this->db->table('barang')->where('idbarang', 1)->get()->getRow()->harga;
    }

    private function override(int $unit = 1): ?float
    {
        $row = $this->db->table('harga_jual_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => $unit])
            ->get()->getRow();

        return $row === null ? null : (float) $row->harga_jual;
    }

    public function testRolePengelolaMenyimpanHargaUnit(): void
    {
        $res = $this->postHargaJual(35, '175000');

        $res->assertStatus(200);
        $json = json_decode((string) $res->getJSON(), true);
        $this->assertSame('success', $json['status']);
        $this->assertSame(175000.0, (float) $json['harga']);
        $this->assertSame(175000.0, $this->override());
        $this->assertSame(150000.0, $this->hargaMaster(), 'harga master tidak berubah');
    }

    public function testHargaSamaDenganMasterMenghapusOverride(): void
    {
        $this->db->table('harga_jual_unit')->insert([
            'barang_idbarang' => 1, 'unit_idunit' => 1, 'harga_jual' => 175000,
        ]);

        $res = $this->postHargaJual(35, '150000');

        $res->assertStatus(200);
        $json = json_decode((string) $res->getJSON(), true);
        $this->assertSame(150000.0, (float) $json['harga']);
        $this->assertNull($this->override());
    }

    public function testUnitLainTidakBerubah(): void
    {
        $this->postHargaJual(35, '175000', 1, 1);
        $this->postHargaJual(35, '200000', 1, 2);

        $this->assertSame(175000.0, $this->override(1));
        $this->assertSame(200000.0, $this->override(2));
    }

    public function testRoleLainDitolak(): void
    {
        $res = $this->postHargaJual(99, '175000');

        $res->assertStatus(403);
        $this->assertNull($this->override());
        $this->assertSame(150000.0, $this->hargaMaster());
    }

    public function testHargaTidakValidDitolak(): void
    {
        foreach (['0', '-1'] as $harga) {
            $res = $this->postHargaJual(35, $harga);
            $res->assertStatus(422);
        }

        $this->assertNull($this->override());
    }

    public function testHargaDenganFormatRupiahDiterima(): void
    {
        $res = $this->postHargaJual(35, 'Rp 175.000');

        $res->assertStatus(200);
        $this->assertSame(175000.0, $this->override());
    }

    public function testHppDenganFormatRupiahDiterima(): void
    {
        $res = $this->postHpp(35, '120.000');

        $res->assertStatus(200);
        $row = $this->db->table('hpp_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow();
        $this->assertSame(120000.0, (float) $row->hpp);
    }

    public function testHppNegatifDitolak(): void
    {
        $this->postHpp(35, '-1')->assertStatus(422);

        $this->assertSame(0, $this->db->table('hpp_unit')->countAllResults());
    }

    public function testPasanganProdukUnitTidakAda(): void
    {
        $res = $this->postHargaJual(35, '175000', 1, 99);

        $res->assertStatus(404);
    }

    public function testProdukTidakDitemukan(): void
    {
        $res = $this->postHargaJual(35, '175000', 999);

        $res->assertStatus(404);
    }

    public function testDaftarKartuStokMemakaiHargaUnit(): void
    {

        $this->db->table('harga_jual_unit')->insert([
            'barang_idbarang' => 1, 'unit_idunit' => 1, 'harga_jual' => 175000,
        ]);

        $rows = (new ModelKartuStok())->getKartuStokDT(10, 0);
        $this->assertCount(2, $rows);

        $unitSatu = null;
        foreach ($rows as $row) {
            if ((int) $row->id_unit === 1) {
                $unitSatu = $row;
            }
        }

        $this->assertNotNull($unitSatu);
        $this->assertSame(175000.0, (float) $unitSatu->harga);
        $this->assertSame(175000.0, (float) $unitSatu->harga_jual_unit);
        $this->assertSame(150000.0, (float) $unitSatu->harga_master);
    }
}
