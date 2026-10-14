<?php

namespace Tests\unit;

use App\Models\ModelServiceDp;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ModelServiceDpListingTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (['service_dp', 'service', 'pelanggan', 'unit', 'bank', 'penjualan', 'detail_penjualan', 'service_sparepart'] as $table) {
            $this->db->query("DROP TABLE IF EXISTS {$table}");
        }

        $this->db->query('CREATE TABLE service (
            idservice INT PRIMARY KEY,
            no_service VARCHAR(40) NULL,
            pelanggan_id_pelanggan INT NULL,
            tipe_hp VARCHAR(80) NULL,
            unit_idunit INT NULL,
            status_service INT NULL)');
        $this->db->query('CREATE TABLE pelanggan (
            id_pelanggan INT PRIMARY KEY,
            nama VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE unit (
            idunit INT PRIMARY KEY,
            NAMA_UNIT VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE bank (
            idbank VARCHAR(20) PRIMARY KEY,
            nama_bank VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT PRIMARY KEY,
            service_idservice INT NULL)');
        $this->db->query('CREATE TABLE detail_penjualan (
            iddetail_penjualan INT AUTO_INCREMENT PRIMARY KEY,
            penjualan_idpenjualan INT NULL,
            barang_idbarang INT NULL,
            jumlah INT NULL)');
        $this->db->query('CREATE TABLE service_sparepart (
            idservice_sparepart INT AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NULL)');
        $this->db->query('CREATE TABLE service_dp (
            id BIGINT PRIMARY KEY,
            service_idservice INT NOT NULL,
            kode_dp VARCHAR(40) NOT NULL,
            tanggal DATE NULL,
            jumlah BIGINT DEFAULT 0,
            metode VARCHAR(15) NULL,
            bank_idbank VARCHAR(20) NULL,
            referensi VARCHAR(100) NULL,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            keterangan TEXT NULL)');

        $this->db->table('pelanggan')->insertBatch([
            ['id_pelanggan' => 1, 'nama' => 'Ayu'],
            ['id_pelanggan' => 2, 'nama' => 'Bima'],
            ['id_pelanggan' => 3, 'nama' => 'Citra'],
        ]);
        $this->db->table('unit')->insertBatch([
            ['idunit' => 1, 'NAMA_UNIT' => 'Unit Satu'],
            ['idunit' => 2, 'NAMA_UNIT' => 'Unit Dua'],
        ]);
        $this->db->table('bank')->insert(['idbank' => 'BCA', 'nama_bank' => 'Bank BCA']);
        $this->db->table('service')->insertBatch([
            ['idservice' => 1, 'no_service' => 'SRV-001', 'pelanggan_id_pelanggan' => 1, 'tipe_hp' => 'Pixel 8', 'unit_idunit' => 1, 'status_service' => 1],
            ['idservice' => 2, 'no_service' => 'SRV-002', 'pelanggan_id_pelanggan' => 2, 'tipe_hp' => 'iPhone 13', 'unit_idunit' => 1, 'status_service' => 1],
            ['idservice' => 3, 'no_service' => 'SRV-003', 'pelanggan_id_pelanggan' => 3, 'tipe_hp' => 'Samsung S23', 'unit_idunit' => 2, 'status_service' => 1],
        ]);
        $this->db->table('service_dp')->insertBatch([
            ['id' => 1, 'service_idservice' => 1, 'kode_dp' => 'DP-001', 'tanggal' => '2026-10-05', 'jumlah' => 100000, 'metode' => 'tunai', 'bank_idbank' => null, 'referensi' => null, 'status' => 'aktif', 'keterangan' => null],
            ['id' => 2, 'service_idservice' => 2, 'kode_dp' => 'DP-002', 'tanggal' => '2026-10-10', 'jumlah' => 200000, 'metode' => 'transfer', 'bank_idbank' => 'BCA', 'referensi' => 'TRF-002', 'status' => 'dibatalkan', 'keterangan' => null],
            ['id' => 3, 'service_idservice' => 3, 'kode_dp' => 'DP-003', 'tanggal' => '2026-10-05', 'jumlah' => 300000, 'metode' => 'transfer', 'bank_idbank' => 'BCA', 'referensi' => null, 'status' => 'aktif', 'keterangan' => null],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['service_dp', 'service', 'pelanggan', 'unit', 'bank', 'penjualan', 'detail_penjualan', 'service_sparepart'] as $table) {
            $this->db->query("DROP TABLE IF EXISTS {$table}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    public function testDaftarDpMenerapkanFilterTanggalUnitDanPencarian(): void
    {
        $result = (new ModelServiceDp())->datatable(
            0,
            10,
            'DP-001',
            '2026-10-01',
            '2026-10-08',
            1,
            0,
            'desc'
        );

        $this->assertSame(2, $result['recordsTotal']);
        $this->assertSame(1, $result['recordsFiltered']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('DP-001', $result['data'][0]['kode_dp']);
        $this->assertSame('SRV-001', $result['data'][0]['no_service']);
        $this->assertSame('Ayu', $result['data'][0]['nama_pelanggan']);
        $this->assertSame('Unit Satu', $result['data'][0]['nama_unit']);
    }

    public function testDaftarDpLintasUnitTetapMenampilkanStatusPembatalan(): void
    {
        $result = (new ModelServiceDp())->datatable(0, 10, '', '', '', null, 0, 'desc');

        $this->assertSame(3, $result['recordsTotal']);
        $this->assertSame(3, $result['recordsFiltered']);
        $this->assertCount(3, $result['data']);
        $this->assertContains('dibatalkan', array_column($result['data'], 'status'));
    }

    public function testDaftarDpMenampilkanJumlahSparepartYangBelumDiretur(): void
    {
        $this->db->table('penjualan')->insert([
            'idpenjualan' => 901,
            'service_idservice' => 1,
        ]);
        $this->db->table('detail_penjualan')->insert([
            'penjualan_idpenjualan' => 901,
            'barang_idbarang' => 77,
            'jumlah' => 1,
        ]);

        $result = (new ModelServiceDp())->datatable(0, 10, 'DP-001', '', '', 1, 0, 'desc');
        $this->assertSame(1, (int) $result['data'][0]['sparepart_belum_diretur']);

        // Setelah detail stok diretur, histori service_sparepart boleh tetap ada
        // tetapi DP sudah layak direfund.
        $this->db->table('detail_penjualan')->where('penjualan_idpenjualan', 901)->delete();
        $result = (new ModelServiceDp())->datatable(0, 10, 'DP-001', '', '', 1, 0, 'desc');
        $this->assertSame(0, (int) $result['data'][0]['sparepart_belum_diretur']);
    }

    public function testDaftarDpTetapBerfungsiSebelumMigrasiRelasiInvoiceService(): void
    {
        $this->db->query('ALTER TABLE penjualan DROP COLUMN service_idservice');
        $this->db->resetDataCache();
        $this->db->table('service_sparepart')->insert(['service_idservice' => 1]);

        $result = (new ModelServiceDp())->datatable(0, 10, 'DP-001', '', '', 1, 0, 'desc');

        $this->assertCount(1, $result['data']);
        $this->assertSame(1, (int) $result['data'][0]['sparepart_belum_diretur']);
        $this->assertSame(0, (int) $result['data'][0]['sparepart_retur_siap']);
    }
}
