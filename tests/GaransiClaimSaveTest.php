<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Feature test simpan klaim garansi dari halaman detail.
 *
 * Tabel memakai prefix `db_` (group `tests`). Verifikasi memakai koneksi fresh
 * karena filter auth menutup koneksi shared setelah tiap request.
 */
final class GaransiClaimSaveTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'db_service',
        'db_riwayat_claim_garansi',
        'db_service_sparepart',
        'db_barang_rusak_claim',
        'db_service_kerusakan',
        'db_pelanggan',
        'db_unit',
        'db_stok_barang',
        'db_akun',
        'db_jabatan',
        'db_barang',
        'db_hpp_barang',
        'db_stok_awal',
        'db_proses_service',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->fresh();
        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        $db = $this->fresh();
        foreach (self::TABEL as $tabel) {
            $db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        parent::tearDown();
    }

    private function fresh()
    {
        return Database::connect('tests', false);
    }

    private function buatTabel(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach (self::TABEL as $tabel) {
            $q("DROP TABLE IF EXISTS {$tabel}");
        }

        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            no_hp VARCHAR(32) NULL,
            alamat TEXT NULL,
            keluhan TEXT NULL,
            keterangan TEXT NULL,
            tipe_hp VARCHAR(64) NULL,
            imei VARCHAR(64) NULL,
            status_service INT NOT NULL DEFAULT 0,
            status_proses INT NULL,
            garansi_hari INT NULL DEFAULT 0,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            tanggal_selesai DATETIME NULL,
            tanggal_bisa_diambil DATE NULL,
            tanggal_claim_garansi DATETIME NULL,
            total_service_garansi INT NULL DEFAULT 0,
            total_diskon_garansi INT NULL DEFAULT 0,
            biaya_tambahan_garansi INT NULL DEFAULT 0,
            harus_dibayar_garansi INT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_riwayat_claim_garansi (
            idriwayat_claim_garansi INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            tanggal_claim DATE NULL,
            catatan TEXT NULL,
            PRIMARY KEY (idriwayat_claim_garansi)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            unit_idunit INT NULL,
            jumlah INT NULL DEFAULT 0,
            harga_penjualan INT NULL DEFAULT 0,
            harga_penjualan_garansi INT NULL DEFAULT 0,
            sub_total INT NULL DEFAULT 0,
            sub_total_garansi INT NULL DEFAULT 0,
            hpp_penjualan INT NULL DEFAULT 0,
            satuan_jual VARCHAR(32) NULL,
            diskon_penjualan INT NULL DEFAULT 0,
            diskon_penjualan_garansi INT NULL DEFAULT 0,
            jumlah_tambahan_garansi INT NULL DEFAULT 0,
            hpp_penjualan_garansi INT NULL DEFAULT 0,
            PRIMARY KEY (idservice_sparepart)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_barang_rusak_claim (
            idbarang_rusak_claim INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            unit_idunit INT NULL,
            input_by INT NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idbarang_rusak_claim)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_kerusakan (
            idservice_kerusakan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            fungsi_idfungsi INT NULL,
            keterangan TEXT NULL,
            PRIMARY KEY (idservice_kerusakan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pelanggan (
            id_pelanggan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama VARCHAR(255) NULL,
            PRIMARY KEY (id_pelanggan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_unit (
            idunit INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_UNIT VARCHAR(150) NULL,
            PRIMARY KEY (idunit)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_barang (
            idbarang INT NULL,
            id_unit INT NULL,
            stok_akhir INT NULL DEFAULT 0,
            stok_minimum INT NULL DEFAULT 0
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

        $q('CREATE TABLE db_barang (
            idbarang INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_barang VARCHAR(64) NULL,
            nama_barang VARCHAR(150) NULL,
            harga INT NULL DEFAULT 0,
            idkategori INT NULL,
            deleted VARCHAR(5) NULL DEFAULT "0",
            PRIMARY KEY (idbarang)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_hpp_barang (
            idbarang INT UNSIGNED NOT NULL,
            hpp INT NULL DEFAULT 0,
            PRIMARY KEY (idbarang)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_awal (
            idstok_awal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            barang_idbarang INT NULL,
            satuan_terkecil VARCHAR(32) NULL,
            PRIMARY KEY (idstok_awal)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_proses_service (
            idproses_service INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT NULL,
            PRIMARY KEY (idproses_service)
        ) ENGINE=InnoDB');

        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Akun Tes')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin Tes', NULL)");
        $q("INSERT INTO db_pelanggan (id_pelanggan, nama) VALUES (1, 'Pelanggan Tes')");
        $q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Tes')");

        $q("INSERT INTO db_barang (idbarang, kode_barang, nama_barang, harga, idkategori, deleted) VALUES
            (10, 'SP-10', 'LCD Tes', 100000, 3, '0'),
            (11, 'SP-11', 'Baterai Tes', 50000, 3, '0'),
            (12, 'SP-12', 'Sparepart Normal', 75000, 3, '0')");
        $q('INSERT INTO db_hpp_barang (idbarang, hpp) VALUES (10, 4000), (11, 1000), (12, 2000)');
        $q("INSERT INTO db_stok_awal (barang_idbarang, satuan_terkecil) VALUES
            (10, 'pcs'), (11, 'pcs'), (12, 'pcs')");
        $q('INSERT INTO db_stok_barang (idbarang, id_unit, stok_akhir, stok_minimum) VALUES
            (10, 1, 5, 0), (11, 1, 5, 0), (12, 1, 5, 0)');
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN' => 43,
            'ID_UNIT' => 1,
            'ID_JABATAN' => 1,
        ]);
    }

    private function seedService(int $idservice): void
    {
        $selesai = (new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta')))
            ->modify('-5 days')
            ->format('Y-m-d H:i:s');

        $this->fresh()->table('service')->insert([
            'idservice' => $idservice,
            'no_service' => 'SRV-' . $idservice,
            'status_service' => 4,
            'garansi_hari' => 30,
            'pelanggan_id_pelanggan' => 1,
            'unit_idunit' => 1,
            'tanggal_selesai' => $selesai,
            'tanggal_bisa_diambil' => substr($selesai, 0, 10),
            'created_at' => $selesai,
        ]);
    }

    private function postClaim(array $payload)
    {
        $this->sesi();

        return $this->post('claim_garansi', $payload + [
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    public function testSimpanKlaimMenyimpanCatatanPenggantiDanBarangRusak(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'LCD rusak karena pecah',
            'produk' => [['id' => 10, 'qty' => 2]],
            'barang_rusak' => [['id' => 11, 'qty' => 1, 'keterangan' => 'LCD pecah']],
        ])->assertRedirect();

        $db = $this->fresh();
        $service = $db->table('service')->where('idservice', 1)->get()->getRow();

        $this->assertSame(2, (int) $service->status_service);
        $this->assertNotEmpty($service->tanggal_claim_garansi);
        $this->assertNull($service->tanggal_selesai);
        $this->assertNull($service->tanggal_bisa_diambil);

        $riwayat = $db->table('riwayat_claim_garansi')->where('service_idservice', 1)->get()->getResultArray();
        $this->assertCount(1, $riwayat);
        $this->assertSame('LCD rusak karena pecah', $riwayat[0]['catatan']);

        $sparepart = $db->table('service_sparepart')->where('service_idservice', 1)->get()->getRow();
        $this->assertNotNull($sparepart);
        $this->assertSame(2, (int) $sparepart->jumlah_tambahan_garansi);
        $this->assertSame(0, (int) $sparepart->harga_penjualan_garansi);
        $this->assertSame(0, (int) $sparepart->sub_total_garansi);
        $this->assertSame(0, (int) $sparepart->diskon_penjualan_garansi);
        $this->assertSame(4000, (int) $sparepart->hpp_penjualan_garansi);
        $this->assertSame(0, (int) $sparepart->jumlah);
        $this->assertSame(0, (int) $sparepart->harga_penjualan);
        $this->assertSame('pcs', $sparepart->satuan_jual);

        $rusak = $db->table('barang_rusak_claim')->where('service_idservice', 1)->get()->getResultArray();
        $this->assertCount(1, $rusak);
        $this->assertSame(11, (int) $rusak[0]['barang_idbarang']);
        $this->assertSame(1.0, (float) $rusak[0]['jumlah']);
        $this->assertSame('LCD pecah', $rusak[0]['keterangan']);
        $this->assertSame(1, (int) $rusak[0]['unit_idunit']);
        $this->assertSame(43, (int) $rusak[0]['input_by']);
    }

    public function testKlaimKeduaTidakMenggandakanRiwayatDanBarangRusak(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Klaim pertama',
            'produk' => [['id' => 10, 'qty' => 2]],
            'barang_rusak' => [['id' => 11, 'qty' => 1, 'keterangan' => 'Rusak']],
        ])->assertRedirect();

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Klaim kedua',
            'produk' => [['id' => 10, 'qty' => 1]],
            'barang_rusak' => [['id' => 11, 'qty' => 1, 'keterangan' => 'Rusak lagi']],
        ])->assertRedirect();

        $db = $this->fresh();
        $this->assertSame(1, $db->table('riwayat_claim_garansi')->where('service_idservice', 1)->countAllResults());
        $this->assertSame(1, $db->table('barang_rusak_claim')->where('service_idservice', 1)->countAllResults());
        $this->assertSame(1, $db->table('service_sparepart')->where('service_idservice', 1)->countAllResults());
    }

    public function testCatatanKosongTidakMenyimpanKlaim(): void
    {
        $this->seedService(2);

        $this->postClaim([
            'idservice' => 2,
            'catatan' => '   ',
            'produk' => [['id' => 10, 'qty' => 1]],
        ])->assertRedirect();

        $db = $this->fresh();
        $service = $db->table('service')->where('idservice', 2)->get()->getRow();

        $this->assertSame(4, (int) $service->status_service);
        $this->assertNull($service->tanggal_claim_garansi);
        $this->assertSame(0, $db->table('riwayat_claim_garansi')->where('service_idservice', 2)->countAllResults());
        $this->assertSame(0, $db->table('service_sparepart')->where('service_idservice', 2)->countAllResults());
    }

    public function testQtyMelebihiStokTidakMenyimpanKlaim(): void
    {
        $this->seedService(3);

        $this->postClaim([
            'idservice' => 3,
            'catatan' => 'Stok tidak cukup',
            'produk' => [['id' => 10, 'qty' => 99]],
        ])->assertRedirect();

        $db = $this->fresh();
        $service = $db->table('service')->where('idservice', 3)->get()->getRow();

        $this->assertSame(4, (int) $service->status_service);
        $this->assertNull($service->tanggal_claim_garansi);
        $this->assertSame(0, $db->table('riwayat_claim_garansi')->where('service_idservice', 3)->countAllResults());
        $this->assertSame(0, $db->table('service_sparepart')->where('service_idservice', 3)->countAllResults());
    }
}
