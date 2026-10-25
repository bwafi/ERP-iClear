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
        'db_penjualan',
        'db_detail_penjualan',
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
            status_retur VARCHAR(20) NULL DEFAULT NULL,
            hpp_omset_garansi INT NOT NULL DEFAULT 0,
            tanggal_hpp_klaim DATE NULL DEFAULT NULL,
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

        $q('CREATE TABLE db_penjualan (
            idpenjualan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_invoice VARCHAR(64) NULL,
            tanggal DATETIME NULL,
            unit_idunit INT NULL,
            PRIMARY KEY (idpenjualan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_detail_penjualan (
            iddetail_penjualan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            penjualan_idpenjualan INT NULL,
            barang_idbarang INT NULL,
            jumlah INT NULL DEFAULT 0,
            harga_penjualan INT NULL DEFAULT 0,
            diskon_penjualan INT NULL DEFAULT 0,
            sub_total INT NULL DEFAULT 0,
            hpp_penjualan INT NULL DEFAULT 0,
            PRIMARY KEY (iddetail_penjualan)
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
        $this->fresh()->table('service')->where('idservice', 1)->update([
            'status_proses' => 4,
            'total_service_garansi' => 111,
            'total_diskon_garansi' => 22,
            'biaya_tambahan_garansi' => 33,
            'harus_dibayar_garansi' => 44,
        ]);
        $this->fresh()->table('proses_service')->insert(['service_idservice' => 1]);
        $sebelum = $this->fresh()->table('service')->where('idservice', 1)->get()->getRow();

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'LCD rusak karena pecah',
            'produk' => [['id' => 10, 'qty' => 2, 'status_retur' => 'bisa']],
            'barang_rusak' => [['id' => 11, 'qty' => 1, 'keterangan' => 'LCD pecah']],
        ])->assertRedirect();

        $db = $this->fresh();
        $service = $db->table('service')->where('idservice', 1)->get()->getRow();

        $this->assertSame(4, (int) $service->status_service);
        $this->assertSame($sebelum->tanggal_selesai, $service->tanggal_selesai);
        $this->assertSame($sebelum->tanggal_bisa_diambil, $service->tanggal_bisa_diambil);
        $this->assertSame(4, (int) $service->status_proses);
        $this->assertSame(111, (int) $service->total_service_garansi);
        $this->assertSame(22, (int) $service->total_diskon_garansi);
        $this->assertSame(33, (int) $service->biaya_tambahan_garansi);
        $this->assertSame(44, (int) $service->harus_dibayar_garansi);
        $this->assertSame(1, $db->table('proses_service')->where('service_idservice', 1)->countAllResults());
        $this->assertNotEmpty($service->tanggal_claim_garansi);

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
        $this->assertSame('bisa', $sparepart->status_retur);
        $this->assertSame(0, (int) $sparepart->hpp_omset_garansi);
        $this->assertNull($sparepart->tanggal_hpp_klaim);
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

    public function testHppAsliKlaimDisimpan(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Ganti LCD',
            'produk' => [['id' => 10, 'qty' => 2, 'status_retur' => 'bisa']],
        ])->assertRedirect();

        $sparepart = $this->fresh()->table('service_sparepart')
            ->where('service_idservice', 1)
            ->where('barang_idbarang', 10)
            ->get()
            ->getRow();

        $this->assertNotNull($sparepart);
        $this->assertSame(4000, (int) $sparepart->hpp_penjualan_garansi);
        $this->assertSame(0, (int) $sparepart->hpp_omset_garansi);
        $this->assertNull($sparepart->tanggal_hpp_klaim);
    }

    public function testKlaimTidakBisaReturMenyimpanHppOmsetDanTanggal(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Tidak bisa retur',
            'produk' => [['id' => 10, 'qty' => 2, 'status_retur' => 'tidak_bisa']],
        ])->assertRedirect();

        $db = $this->fresh();
        $sparepart = $db->table('service_sparepart')
            ->where('service_idservice', 1)
            ->where('barang_idbarang', 10)
            ->get()
            ->getRow();
        $service = $db->table('service')->where('idservice', 1)->get()->getRow();

        $this->assertNotNull($sparepart);
        $this->assertSame(4000, (int) $sparepart->hpp_penjualan_garansi);
        $this->assertSame('tidak_bisa', $sparepart->status_retur);
        $this->assertSame(4000, (int) $sparepart->hpp_omset_garansi);
        $this->assertSame(date('Y-m-d', strtotime($service->tanggal_claim_garansi)), $sparepart->tanggal_hpp_klaim);
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

    public function testBarisSparepartNormalTidakHilangSaatKlaim(): void
    {
        $this->seedService(1);

        $this->fresh()->table('service_sparepart')->insert([
            'service_idservice' => 1,
            'barang_idbarang' => 12,
            'unit_idunit' => 1,
            'jumlah' => 2,
            'harga_penjualan' => 50000,
            'sub_total' => 100000,
            'hpp_penjualan' => 2000,
            'satuan_jual' => 'pcs',
            'diskon_penjualan' => 0,
            'jumlah_tambahan_garansi' => 0,
            'harga_penjualan_garansi' => 0,
            'sub_total_garansi' => 0,
            'diskon_penjualan_garansi' => 0,
            'hpp_penjualan_garansi' => 0,
        ]);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Ganti LCD',
            'produk' => [['id' => 10, 'qty' => 1]],
        ])->assertRedirect();

        $db = $this->fresh();
        $normal = $db->table('service_sparepart')
            ->where('service_idservice', 1)
            ->where('barang_idbarang', 12)
            ->get()
            ->getRow();

        $this->assertNotNull($normal);
        $this->assertSame(2, (int) $normal->jumlah);
        $this->assertSame(50000, (int) $normal->harga_penjualan);
        $this->assertSame(100000, (int) $normal->sub_total);
        $this->assertSame(2000, (int) $normal->hpp_penjualan);
        $this->assertSame(0, (int) $normal->jumlah_tambahan_garansi);
        $this->assertSame(0, (int) $normal->harga_penjualan_garansi);
        $this->assertSame(0, (int) $normal->sub_total_garansi);
        $this->assertSame(0, (int) $normal->hpp_penjualan_garansi);

        $this->assertNotNull($db->table('service_sparepart')
            ->where('service_idservice', 1)
            ->where('barang_idbarang', 10)
            ->get()
            ->getRow());
    }

    public function testBarangRusakTanpaPilihanTidakDisimpan(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Hanya ganti sparepart',
            'produk' => [['id' => 10, 'qty' => 1]],
            'barang_rusak' => [['qty' => 2, 'keterangan' => 'Tidak dicentang']],
        ])->assertRedirect();

        $this->assertSame(
            0,
            $this->fresh()->table('barang_rusak_claim')->where('service_idservice', 1)->countAllResults()
        );
    }

    public function testRiwayatKlaimMenampilkanCatatan(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'LCD rusak karena pecah',
            'produk' => [['id' => 10, 'qty' => 1]],
            'barang_rusak' => [['id' => 11, 'qty' => 1, 'keterangan' => 'Baterai kembung']],
        ])->assertRedirect();

        $this->sesi();
        $result = $this->get('riwayat-klaim/1');
        $json = json_decode((string) $result->getJSON(), true);

        $this->assertIsArray($json);
        $this->assertStringContainsString('LCD rusak karena pecah', $json['html']);
        $this->assertStringContainsString('Sparepart Rusak', $json['html']);
        $this->assertStringContainsString('Baterai Tes', $json['html']);
        $this->assertStringContainsString('Baterai kembung', $json['html']);
        $this->assertStringContainsString('Sparepart Pengganti', $json['html']);
        $this->assertStringContainsString('LCD Tes', $json['html']);
    }

    public function testSparepartKlaimTampilDiSparepartKeluar(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Ganti LCD',
            'produk' => [['id' => 10, 'qty' => 2, 'status_retur' => 'bisa']],
        ])->assertRedirect();

        $now = date('Y-m-d H:i:s');
        $db = $this->fresh();
        $db->table('penjualan')->insert([
            'idpenjualan' => 1,
            'kode_invoice' => 'INV-1',
            'tanggal' => $now,
            'unit_idunit' => 1,
        ]);
        $db->table('detail_penjualan')->insert([
            'iddetail_penjualan' => 1,
            'penjualan_idpenjualan' => 1,
            'barang_idbarang' => 12,
            'jumlah' => 1,
            'harga_penjualan' => 75000,
            'diskon_penjualan' => 0,
            'sub_total' => 75000,
            'hpp_penjualan' => 2000,
        ]);

        $this->sesi();
        $result = $this->post('sparepart_keluar/ajax', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'unit' => 1,
            'day' => '',
            'search' => ['value' => 'SRV-1'],
            'order' => [['column' => 1, 'dir' => 'desc']],
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $json = json_decode((string) $result->getJSON(), true);

        $this->assertIsArray($json);
        $this->assertSame(2, $json['recordsTotal']);
        $this->assertSame(1, $json['recordsFiltered']);
        $this->assertCount(1, $json['data']);

        $row = $json['data'][0];
        $this->assertSame('klaim', $row['sumber']);
        $this->assertSame('SRV-1', $row['kode_invoice']);
        $this->assertSame('LCD Tes', $row['nama_barang']);
        $this->assertSame(2, (int) $row['jumlah']);
        $this->assertSame(4000, (int) $row['hpp_penjualan']);
        $this->assertSame(0, (int) $row['sub_total']);
        $this->assertNull($row['iddetail_penjualan']);
    }

    public function testSparepartKlaimHppAsliTampilAsliDiSparepartKeluar(): void
    {
        $this->seedService(1);

        $this->postClaim([
            'idservice' => 1,
            'catatan' => 'Ganti LCD',
            'produk' => [['id' => 10, 'qty' => 2, 'status_retur' => 'bisa']],
        ])->assertRedirect();

        $this->sesi();
        $result = $this->post('sparepart_keluar/ajax', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'unit' => 1,
            'day' => '',
            'search' => ['value' => 'SRV-1'],
            'order' => [['column' => 1, 'dir' => 'desc']],
            'csrf_test_name' => service('security')->getHash(),
        ]);
        $json = json_decode((string) $result->getJSON(), true);

        $this->assertIsArray($json);
        $this->assertSame(1, $json['recordsFiltered']);
        $this->assertSame(4000, (int) $json['data'][0]['hpp_penjualan']);
    }
}
