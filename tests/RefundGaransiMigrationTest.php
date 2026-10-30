<?php

namespace Tests;

use App\Database\Migrations\AddGaransiServiceRefund;
use App\Database\Migrations\AddRefundGaransiOmset;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Refund garansi menambah kolom audit, template jurnal, dan mengubah view stok
 * agar qty yang sudah direfund (barang baik) tidak lagi mengurangi stok.
 */
final class RefundGaransiMigrationTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefixAsli = '';

    private const TABEL = [
        'barang', 'unit', 'mutasi', 'detail_mutasi', 'detail_pembelian',
        'stok_awal', 'detail_penjualan', 'retur_pelanggan', 'retur_suplier',
        'barang_rusak', 'barang_rusak_claim', 'stok_opname', 'service',
        'service_sparepart', 'template_jurnal', 'no_akun',
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

        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function hapusStokBarang(): void
    {
        $row = $this->db->query(
            "SELECT TABLE_TYPE FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stok_barang'"
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
        $q('CREATE TABLE barang_rusak_claim (barang_idbarang INT, unit_idunit INT, jumlah DECIMAL(15,2))');
        $q('CREATE TABLE stok_opname (barang_idbarang INT, unit_idunit INT, is_reverted INT)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, tanggal_claim_garansi DATETIME NULL)');
        $q('CREATE TABLE service_sparepart (
                service_idservice INT, barang_idbarang INT, unit_idunit INT,
                jumlah_tambahan_garansi DECIMAL(15,2))');
        $q('CREATE TABLE template_jurnal (
                idtemplate_jurnal INT AUTO_INCREMENT PRIMARY KEY,
                kode_template VARCHAR(50), no_akun VARCHAR(20), nama_akun VARCHAR(100),
                debet_kredit VARCHAR(10), array_value INT DEFAULT 0, keterangan VARCHAR(100))');
        $q('CREATE TABLE no_akun (no_akun VARCHAR(20) PRIMARY KEY, nama_akun VARCHAR(100))');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        $q("INSERT INTO barang VALUES
            (1, 'A-1', 'Produk A', 100, 80, 1, NULL, NULL, 0, NULL, 1, 0, 1, 0, '0', 'x', NULL)");
        $q("INSERT INTO unit VALUES (1, 'Unit 1'), (2, 'Unit 2')");
        $q('INSERT INTO stok_awal VALUES (1, 1, 10)');
        $q("INSERT INTO service VALUES (1, '2026-10-20 10:00:00'), (2, NULL)");
        $q('INSERT INTO service_sparepart VALUES (1, 1, 1, 2), (2, 1, 1, 3)');
    }

    private function migrasi(): AddGaransiServiceRefund
    {
        $path = APPPATH . 'Database/Migrations/2026-10-26-000100_AddGaransiServiceRefund.php';
        if (! file_exists($path)) {
            $this->fail('Migrasi AddGaransiServiceRefund belum ada.');
        }

        require_once $path;

        return new AddGaransiServiceRefund(Database::forge('tests'));
    }

    private function stokUnitSatu(): float
    {
        $row = $this->db->query('SELECT stok_akhir FROM stok_barang WHERE id_unit = 1')->getRow();

        return (float) $row->stok_akhir;
    }

    public function testMigrasiMenambahKolomRefundDanMengurangiStokSesuaiQtyRefund(): void
    {
        $this->migrasi()->up();

        $this->assertSame(8.0, $this->stokUnitSatu(), 'hanya service yang sudah klaim yang mengurangi stok');

        $this->db->query('UPDATE service_sparepart SET jumlah_refund_garansi = 1 WHERE service_idservice = 1');
        $this->assertSame(9.0, $this->stokUnitSatu(), 'qty yang direfund kembali ke stok');

        $this->db->query('UPDATE service_sparepart SET jumlah_refund_garansi = 2 WHERE service_idservice = 1');
        $this->assertSame(10.0, $this->stokUnitSatu(), 'refund penuh menghapus pemakaian garansi');

        $this->assertTrue($this->db->fieldExists('refund_garansi_at', 'service'));
        $this->assertTrue($this->db->fieldExists('jumlah_refund_garansi', 'service_sparepart'));
    }

    public function testMigrasiPorsiOmsetMengisiRefundLamaDenganNominalPenuh(): void
    {
        $this->migrasi()->up();
        $this->db->query("UPDATE service
            SET refund_garansi_at = '2026-10-20 10:00:00', refund_garansi_jumlah = 4000
            WHERE idservice = 1");

        $path = APPPATH . 'Database/Migrations/2026-10-30-000300_AddRefundGaransiOmset.php';
        if (! file_exists($path)) {
            $this->fail('Migrasi AddRefundGaransiOmset belum ada.');
        }

        require_once $path;
        (new AddRefundGaransiOmset(Database::forge('tests')))->up();

        $ada = $this->db->query(
            "SELECT COUNT(*) AS jumlah FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service'
                AND COLUMN_NAME = 'refund_garansi_omset'"
        )->getRow();
        $this->assertSame(1, (int) $ada->jumlah);

        $service = $this->db->table('service')->where('idservice', 1)->get()->getRow();
        $this->assertSame(4000, (int) $service->refund_garansi_omset);
    }

    public function testMigrasiMenyiapkanTemplateJurnalRefund(): void
    {
        $this->migrasi()->up();

        $rows = $this->db->table('template_jurnal')
            ->whereIn('kode_template', ['refund_garansi_tunai', 'refund_garansi_bank'])
            ->orderBy('kode_template', 'ASC')
            ->orderBy('debet_kredit', 'ASC')
            ->get()
            ->getResult();

        $this->assertCount(4, $rows, 'dua template, masing-masing debet dan kredit');

        $harapan = [
            'refund_garansi_tunai' => ['debet' => '2010302000', 'kredit' => '1010101000'],
            'refund_garansi_bank'  => ['debet' => '2010302000', 'kredit' => '1010102000'],
        ];

        foreach ($rows as $row) {
            $this->assertSame(
                $harapan[$row->kode_template][$row->debet_kredit],
                $row->no_akun,
                $row->kode_template . ' ' . $row->debet_kredit
            );
        }
    }
}
