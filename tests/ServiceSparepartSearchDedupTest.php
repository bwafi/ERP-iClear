<?php

/**
 * Regresi pencarian sparepart di modal "Pilih Sparepart" (dipakai halaman
 * /service dan /detail/riwayat_service?tab=sparepart).
 *
 * Dulu query `Service::search_sparepart_ajax` men-join `stok_awal`/`suplier`
 * yang dikelompokkan per (barang, supplier). Bila satu barang punya stok awal
 * dari beberapa supplier, join itu menggandakan baris sehingga sparepart yang
 * sama tampil dobel di hasil pencarian ("kadang double").
 *
 * Test menyemai 1 sparepart dengan 2 supplier dan memastikan endpoint hanya
 * mengembalikan satu baris. Tabel prefix `db_` (group 'tests'), mengikuti pola
 * ServicePembayaranRouteGuardTest.
 */

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ServiceSparepartSearchDedupTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const URI = 'service/search_sparepart';

    private const TABEL = [
        'barang',
        'stok_barang',
        'unit',
        'stok_awal',
        'suplier',
        'service',
        'pelanggan',
        'akun',
        'jabatan',
    ];

    private function db()
    {
        return \Config\Database::connect('tests', false);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $q = fn (string $sql) => $this->db()->query($sql);

        // Bersihkan sisa run sebelumnya supaya test bisa dijalankan berdiri sendiri.
        foreach (self::TABEL as $tabel) {
            $q('DROP TABLE IF EXISTS db_' . $tabel);
        }

        $q('CREATE TABLE db_barang (
            idbarang INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_barang VARCHAR(64) NULL,
            nama_barang VARCHAR(255) NULL,
            harga BIGINT NULL DEFAULT 0,
            harga_beli BIGINT NULL DEFAULT 0,
            warna VARCHAR(64) NULL,
            imei VARCHAR(64) NULL,
            deleted VARCHAR(2) NULL DEFAULT \'0\',
            PRIMARY KEY (idbarang)
        ) ENGINE=InnoDB');

        // `stok_barang` di produksi adalah VIEW (terkelompok per barang+unit).
        // Cukup tabel dengan kolom yang dipakai query pencarian.
        $q('CREATE TABLE db_stok_barang (
            idbarang INT NULL,
            id_unit INT NULL,
            kode_barang VARCHAR(64) NULL,
            nama_barang VARCHAR(255) NULL,
            harga BIGINT NULL DEFAULT 0,
            harga_beli BIGINT NULL DEFAULT 0,
            warna VARCHAR(64) NULL,
            imei VARCHAR(64) NULL,
            stok_akhir INT NULL DEFAULT 0,
            stok_minimum INT NULL DEFAULT 0
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_unit (
            idunit INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_UNIT VARCHAR(150) NULL,
            PRIMARY KEY (idunit)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_awal (
            idstok_awal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            barang_idbarang INT NULL,
            suplier_id_suplier INT NULL,
            unit_idunit INT NULL,
            jumlah INT NULL DEFAULT 0,
            PRIMARY KEY (idstok_awal)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_suplier (
            id_suplier INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama_suplier VARCHAR(150) NULL,
            PRIMARY KEY (id_suplier)
        ) ENGINE=InnoDB');

        // Tabel yang dibutuhkan BaseController::initController + AuthFilter.
        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            status_service INT NULL,
            status_proses INT NULL,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            created_at DATETIME NULL,
            tanggal_selesai DATE NULL,
            tanggal_bisa_diambil DATE NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pelanggan (
            id_pelanggan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama VARCHAR(255) NULL,
            PRIMARY KEY (id_pelanggan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_akun (
            ID_AKUN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            ID_JABATAN INT NULL,
            ID_UNIT INT NULL DEFAULT 0,
            NAMA_AKUN VARCHAR(100) NULL,
            PRIMARY KEY (ID_AKUN)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_jabatan (
            ID_JABATAN INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_JABATAN VARCHAR(150) NULL,
            ROLES_JABATAN TEXT NULL,
            PRIMARY KEY (ID_JABATAN)
        ) ENGINE=InnoDB');

        // 1 sparepart "LCD" dengan stok awal dari DUA supplier.
        $q("INSERT INTO db_barang (idbarang, kode_barang, nama_barang, harga, harga_beli, warna, deleted)
            VALUES (1, 'SPRT-0001', 'LCD iPhone 11', 500000, 400000, 'Hitam', '0')");
        $q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit 1')");
        $q("INSERT INTO db_stok_barang (idbarang, id_unit, kode_barang, nama_barang, harga, harga_beli, warna, stok_akhir, stok_minimum)
            VALUES (1, 1, 'SPRT-0001', 'LCD iPhone 11', 500000, 400000, 'Hitam', 7, 0)");
        $q("INSERT INTO db_suplier (id_suplier, nama_suplier) VALUES (1, 'Suplier A'), (2, 'Suplier B')");
        $q('INSERT INTO db_stok_awal (barang_idbarang, suplier_id_suplier, unit_idunit, jumlah)
            VALUES (1, 1, 1, 3), (1, 2, 1, 4)');
        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Admin')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin', '[]')");
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db()->query('DROP TABLE IF EXISTS db_' . $tabel);
        }
        parent::tearDown();
    }

    private function sesi()
    {
        return $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => 1,
        ])->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
    }

    public function testBarangMultiSupplierTidakTampilDobel(): void
    {
        $result = $this->sesi()->post(self::URI, ['search' => 'lcd']);

        $result->assertStatus(200);

        $data = json_decode($result->getJSON(), true);
        $this->assertIsArray($data, 'endpoint harus mengembalikan array JSON');
        $this->assertCount(1, $data, 'sparepart multi-supplier hanya boleh muncul sekali');
        $this->assertSame('SPRT-0001', $data[0]['kode_barang'] ?? null);
    }
}
