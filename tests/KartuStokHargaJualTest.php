<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * Harga jual master bersifat global; endpoint Kartu Stok harus menjaga role,
 * validasi nilai, dan hanya mengubah `barang.harga`.
 */
final class KartuStokHargaJualTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'db_barang', 'db_stok_barang', 'db_service', 'db_service_kerusakan',
        'db_service_sparepart', 'db_pelanggan', 'db_unit', 'db_akun', 'db_jabatan',
    ];

    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests', false);
        $this->buatTabel();
        $this->db->table('barang')->insert(['idbarang' => 1, 'harga' => 150000]);
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        parent::tearDown();
    }

    private function buatTabel(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach (self::TABEL as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE db_barang (idbarang INT PRIMARY KEY AUTO_INCREMENT, harga INT NULL)');
        $q('CREATE TABLE db_stok_barang (idbarang INT NULL, id_unit INT NULL,
                stok_akhir INT NULL DEFAULT 0, stok_minimum INT NULL DEFAULT 0)');
        $q('CREATE TABLE db_pelanggan (id_pelanggan INT PRIMARY KEY, nama VARCHAR(255) NULL)');
        $q('CREATE TABLE db_unit (idunit INT PRIMARY KEY, NAMA_UNIT VARCHAR(150) NULL)');
        $q('CREATE TABLE db_akun (ID_AKUN INT PRIMARY KEY, ID_JABATAN INT NULL,
                ID_UNIT INT NULL, NAMA_AKUN VARCHAR(100) NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE db_jabatan (ID_JABATAN INT PRIMARY KEY,
                NAMA_JABATAN VARCHAR(150) NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE db_service (
                idservice INT PRIMARY KEY AUTO_INCREMENT,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                status_service INT NOT NULL DEFAULT 0, status_proses INT NULL,
                tanggal_selesai DATETIME NULL, tanggal_bisa_diambil DATE NULL,
                created_at DATETIME NULL)');
        $q('CREATE TABLE db_service_kerusakan (idservice_kerusakan INT PRIMARY KEY AUTO_INCREMENT,
                service_idservice INT NULL)');
        $q('CREATE TABLE db_service_sparepart (idservice_sparepart INT PRIMARY KEY AUTO_INCREMENT,
                service_idservice INT NULL)');
    }

    private function postHargaJual(int $jabatan, string $harga, int $idbarang = 1)
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => $jabatan,
        ]);

        return $this->post('kartu_stok/harga-jual', [
            'idbarang'       => $idbarang,
            'harga'          => $harga,
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    private function hargaMaster(): float
    {
        return (float) $this->db->table('barang')->where('idbarang', 1)->get()->getRow()->harga;
    }

    public function testRolePengelolaBisaMengubahHargaJual(): void
    {
        $res = $this->postHargaJual(35, '175000');

        $res->assertStatus(200);
        $json = json_decode((string) $res->getJSON(), true);
        $this->assertSame('success', $json['status']);
        $this->assertSame(175000.0, $this->hargaMaster());
    }

    public function testRoleLainDitolak(): void
    {
        $res = $this->postHargaJual(99, '175000');

        $res->assertStatus(403);
        $this->assertSame(150000.0, $this->hargaMaster());
    }

    public function testHargaTidakValidDitolak(): void
    {
        $res = $this->postHargaJual(35, '0');

        $res->assertStatus(422);
        $this->assertSame(150000.0, $this->hargaMaster());
    }

    public function testProdukTidakDitemukan(): void
    {
        $res = $this->postHargaJual(35, '175000', 999);

        $res->assertStatus(404);
    }
}
