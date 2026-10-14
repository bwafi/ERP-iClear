<?php

namespace Tests\unit;

use App\Services\Service\ServiceSparepartPenjualanSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

final class ServiceSparepartPenjualanSyncTest extends CIUnitTestCase
{
    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect('tests', false);
        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests', false);
        foreach (['db_jurnal', 'db_detail_penjualan', 'db_penjualan'] as $table) {
            $db->query("DROP TABLE IF EXISTS {$table}");
        }
        parent::tearDown();
    }

    private function buatTabel(): void
    {
        $this->db->query('CREATE TABLE db_penjualan (
            idpenjualan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_invoice VARCHAR(64),
            tanggal DATETIME,
            total_penjualan INTEGER,
            harus_dibayar INTEGER,
            bayar INTEGER,
            keterangan VARCHAR(64),
            unit_idunit INTEGER,
            service_idservice INT NULL,
            PRIMARY KEY (idpenjualan)
        ) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE db_detail_penjualan (
            iddetail_penjualan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            penjualan_idpenjualan INT,
            barang_idbarang INT,
            jumlah INT,
            harga_penjualan INT,
            sub_total INT,
            hpp_penjualan INT,
            unit_idunit INT,
            PRIMARY KEY (iddetail_penjualan)
        ) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE db_jurnal (
            idjurnal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tabel_referensi VARCHAR(64),
            id_referensi VARCHAR(64),
            PRIMARY KEY (idjurnal)
        ) ENGINE=InnoDB');
    }

    /** @return array<int,object> */
    private function detailLama(): array
    {
        return [
            (object) ['barang_idbarang' => 4414, 'jumlah' => 1, 'harga_penjualan' => 350000, 'sub_total' => 350000],
            (object) ['barang_idbarang' => 4246, 'jumlah' => 1, 'harga_penjualan' => 450000, 'sub_total' => 450000],
        ];
    }

    private function insertInvoice(array $data, array $details): int
    {
        $this->db->table('penjualan')->insert($data);
        $id = (int) $this->db->insertID();
        foreach ($details as $detail) {
            $detail['penjualan_idpenjualan'] = $id;
            $this->db->table('detail_penjualan')->insert($detail);
        }
        return $id;
    }

    private function detailRows(): array
    {
        return [
            ['barang_idbarang' => 4414, 'jumlah' => 1, 'harga_penjualan' => 350000, 'sub_total' => 350000],
            ['barang_idbarang' => 4246, 'jumlah' => 1, 'harga_penjualan' => 450000, 'sub_total' => 450000],
        ];
    }

    public function testInvoiceServiceTertautDihapusUtuhSebelumPenggantian(): void
    {
        $id = $this->insertInvoice([
            'kode_invoice' => 'SRV20261014064344',
            'total_penjualan' => 800000,
            'harus_dibayar' => 800000,
            'bayar' => 0,
            'keterangan' => 'Belum Lunas',
            'unit_idunit' => 1,
            'service_idservice' => 3391,
        ], $this->detailRows());
        $this->db->table('jurnal')->insert(['tabel_referensi' => 'penjualan', 'id_referensi' => (string) $id]);

        $this->db->transBegin();
        (new ServiceSparepartPenjualanSyncService($this->db))->hapusTransaksiLama(3391, $this->detailLama(), 1);
        $this->db->transCommit();

        $this->assertSame(0, (int) $this->db->table('penjualan')->countAllResults());
        $this->assertSame(0, (int) $this->db->table('detail_penjualan')->countAllResults());
        $this->assertSame(0, (int) $this->db->table('jurnal')->countAllResults());
    }

    public function testInvoiceLegacyYangCocokPersisDitautkanLaluDihapus(): void
    {
        $this->insertInvoice([
            'kode_invoice' => 'SRV20261014064344',
            'total_penjualan' => 800000,
            'harus_dibayar' => 800000,
            'bayar' => 0,
            'keterangan' => 'Belum Lunas',
            'unit_idunit' => 1,
            'service_idservice' => null,
        ], $this->detailRows());

        $this->db->transBegin();
        (new ServiceSparepartPenjualanSyncService($this->db))->hapusTransaksiLama(3391, $this->detailLama(), 1);
        $this->db->transCommit();

        $this->assertSame(0, (int) $this->db->table('penjualan')->countAllResults());
        $this->assertSame(0, (int) $this->db->table('detail_penjualan')->countAllResults());
    }

    public function testInvoiceLegacyAmbiguTidakDihapus(): void
    {
        $data = [
            'total_penjualan' => 800000,
            'harus_dibayar' => 800000,
            'bayar' => 0,
            'keterangan' => 'Belum Lunas',
            'unit_idunit' => 1,
            'service_idservice' => null,
        ];
        $this->insertInvoice($data + ['kode_invoice' => 'SRV20261014064344'], $this->detailRows());
        $this->insertInvoice($data + ['kode_invoice' => 'SRV20261014064345'], $this->detailRows());

        $this->db->transBegin();
        try {
            (new ServiceSparepartPenjualanSyncService($this->db))->hapusTransaksiLama(3391, $this->detailLama(), 1);
            $this->fail('Invoice ambiguous seharusnya tidak dihapus otomatis.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('beberapa invoice lama', $e->getMessage());
        } finally {
            $this->db->transRollback();
        }

        $this->assertSame(2, (int) $this->db->table('penjualan')->countAllResults());
        $this->assertSame(4, (int) $this->db->table('detail_penjualan')->countAllResults());
    }
}
