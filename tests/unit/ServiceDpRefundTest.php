<?php

namespace Tests\unit;

use App\Services\Service\ServiceDpService;
use App\Models\ModelService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ServiceDpRefundTest extends CIUnitTestCase
{
    protected $db;

    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (['sparepart_invoice_guard', 'detail_penjualan', 'penjualan', 'service_dp', 'service', 'pelanggan', 'service_sparepart', 'kategori_kas', 'pembayaran_bank', 'kas_keluar', 'jurnal', 'template_jurnal', 'no_akun'] as $table) {
            $this->db->query("DROP TABLE IF EXISTS {$table}");
        }

        $this->db->query('CREATE TABLE service (
            idservice INT PRIMARY KEY,
            no_service VARCHAR(40) NULL,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            status_service INT NULL,
            bayar_tunai BIGINT DEFAULT 0,
            dp_bayar BIGINT DEFAULT 0,
            tanggal_bisa_diambil DATETIME NULL,
            updated_at DATETIME NULL) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE pelanggan (
            id_pelanggan INT PRIMARY KEY,
            nama VARCHAR(100) NULL)');
        $this->db->query('CREATE TABLE service_sparepart (
            idservice_sparepart INT AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NULL) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE penjualan (
            idpenjualan INT PRIMARY KEY,
            service_idservice INT NULL,
            total_penjualan BIGINT NULL,
            harus_dibayar BIGINT NULL) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE detail_penjualan (
            iddetail_penjualan INT AUTO_INCREMENT PRIMARY KEY,
            penjualan_idpenjualan INT NULL,
            barang_idbarang INT NULL,
            jumlah INT NULL,
            sub_total BIGINT NULL) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE sparepart_invoice_guard (
            id INT PRIMARY KEY,
            penjualan_id INT NOT NULL,
            CONSTRAINT fk_sparepart_invoice_guard FOREIGN KEY (penjualan_id)
                REFERENCES penjualan(idpenjualan)) ENGINE=InnoDB');
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
            keterangan TEXT NULL,
            unit_id INT NULL,
            input_by INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            refund_idkas_keluar INT NULL,
            refund_tanggal DATE NULL,
            refund_metode VARCHAR(15) NULL,
            refund_bank_idbank VARCHAR(20) NULL,
            refund_referensi VARCHAR(100) NULL,
            refund_alasan TEXT NULL,
            refund_by INT NULL,
            refund_at DATETIME NULL) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE kategori_kas (
            idkategori_kas INT PRIMARY KEY,
            kategori VARCHAR(60) NOT NULL,
            jenis_kas VARCHAR(30) NULL)');
        $this->db->query('CREATE TABLE pembayaran_bank (
            idpembayaran_bank INT AUTO_INCREMENT PRIMARY KEY,
            jumlah BIGINT DEFAULT 0,
            tabel_referensi VARCHAR(40) NULL,
            id_referensi INT NULL)');
        $this->db->query('CREATE TABLE kas_keluar (
            idkas_keluar INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            no_akun VARCHAR(20) NULL,
            kategori_idkategori INT NULL,
            deskripsi VARCHAR(255) NULL,
            jumlah BIGINT DEFAULT 0,
            jenis VARCHAR(10) NULL,
            penerima VARCHAR(100) NULL,
            idbank VARCHAR(20) NULL,
            idunit INT NULL,
            created_on DATETIME NULL) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE jurnal (
            idjurnal INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            no_akun VARCHAR(20) NULL,
            nama_akun VARCHAR(100) NULL,
            debet BIGINT DEFAULT 0,
            kredit BIGINT DEFAULT 0,
            keterangan VARCHAR(255) NULL,
            id_referensi VARCHAR(40) NULL,
            tabel_referensi VARCHAR(40) NULL,
            id_unit INT NULL,
            id_akun INT NULL) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE template_jurnal (
            idtemplate_jurnal INT AUTO_INCREMENT PRIMARY KEY,
            kode_template VARCHAR(80) NULL,
            no_akun VARCHAR(20) NULL,
            nama_akun VARCHAR(100) NULL,
            debet_kredit VARCHAR(10) NULL,
            array_value INT DEFAULT 0,
            keterangan VARCHAR(255) NULL)');
        $this->db->query('CREATE TABLE no_akun (
            no_akun VARCHAR(20) PRIMARY KEY,
            nama_akun VARCHAR(100) NULL)');

        $this->db->table('pelanggan')->insert(['id_pelanggan' => 1, 'nama' => 'Ayu']);
        $this->db->table('service')->insert([
            'idservice' => 1,
            'no_service' => 'SRV-REFUND-1',
            'pelanggan_id_pelanggan' => 1,
            'unit_idunit' => 1,
            'status_service' => 2,
            'dp_bayar' => 50000,
        ]);
        $this->db->table('service_dp')->insert([
            'id' => 1,
            'service_idservice' => 1,
            'kode_dp' => 'DP-REFUND-1',
            'tanggal' => '2026-10-13',
            'jumlah' => 50000,
            'metode' => 'tunai',
            'status' => 'aktif',
            'unit_id' => 1,
        ]);
        $this->db->table('kategori_kas')->insert([
            'idkategori_kas' => 17,
            'kategori' => 'REFUND',
            'jenis_kas' => 'kas_keluar',
        ]);
        $this->db->table('no_akun')->insert([
            'no_akun' => '2010302000',
            'nama_akun' => 'Pendapatan Diterima di Muka',
        ]);
        $this->db->table('template_jurnal')->insertBatch([
            ['kode_template' => 'refund_dp_service_tunai', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'refund_dp_service_tunai', 'no_akun' => '1010101000', 'nama_akun' => 'Kas Besar', 'debet_kredit' => 'kredit', 'array_value' => 0],
            ['kode_template' => 'refund_dp_service_bank', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'refund_dp_service_bank', 'no_akun' => '1010102000', 'nama_akun' => 'Kas di Bank', 'debet_kredit' => 'kredit', 'array_value' => 0],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['sparepart_invoice_guard', 'detail_penjualan', 'penjualan', 'service_dp', 'service', 'pelanggan', 'service_sparepart', 'kategori_kas', 'pembayaran_bank', 'kas_keluar', 'jurnal', 'template_jurnal', 'no_akun'] as $table) {
            $this->db->query("DROP TABLE IF EXISTS {$table}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function service(): ServiceDpService
    {
        return new ServiceDpService($this->db);
    }

    private function tambahSparepartKeluar(): void
    {
        $this->db->table('service_sparepart')->insert(['service_idservice' => 1]);
        $this->db->table('penjualan')->insert([
            'idpenjualan' => 101,
            'service_idservice' => 1,
        ]);
        $this->db->table('detail_penjualan')->insertBatch([
            ['penjualan_idpenjualan' => 101, 'barang_idbarang' => 501, 'jumlah' => 1, 'sub_total' => 100000],
            ['penjualan_idpenjualan' => 101, 'barang_idbarang' => 502, 'jumlah' => 2, 'sub_total' => 50000],
        ]);
        $this->db->table('jurnal')->insert([
            'tanggal' => '2026-10-14',
            'no_akun' => '1010101000',
            'nama_akun' => 'Kas Besar',
            'debet' => 150000,
            'kredit' => 0,
            'id_referensi' => '101',
            'tabel_referensi' => 'penjualan',
            'id_unit' => 1,
        ]);
    }

    public function testRefundPenuhMencatatKasKeluarJurnalDanTetapMenyimpanPenerimaanAsal(): void
    {
        $refund = $this->service()->refund(1, 'Pelanggan membatalkan service', null, 7, 1);

        $this->assertSame('ok', $refund['status']);
        $this->assertSame(50000, $refund['jumlah']);

        $dp = $this->db->table('service_dp')->where('id', 1)->get()->getRow();
        $this->assertSame('aktif', $dp->status, 'Penerimaan asal tetap menjadi bagian histori kas masuk.');
        $this->assertSame($refund['idkas_keluar'], (int) $dp->refund_idkas_keluar);
        $this->assertSame('Pelanggan membatalkan service', $dp->refund_alasan);
        $service = $this->db->table('service')->where('idservice', 1)->get()->getRow();
        $this->assertSame(90, (int) $service->status_service, 'Refund otomatis membatalkan service.');
        $this->assertNotEmpty($service->tanggal_bisa_diambil, 'Waktu pembatalan service ikut tercatat.');
        $this->assertSame(0, (int) $service->dp_bayar);

        $keluar = $this->db->table('kas_keluar')->where('idkas_keluar', $refund['idkas_keluar'])->get()->getRow();
        $this->assertSame(50000, (int) $keluar->jumlah);
        $this->assertSame('2010302000', $keluar->no_akun);
        $this->assertNull($keluar->idbank, 'Refund tunai mengurangi laci kas.');

        $jurnal = $this->db->table('jurnal')->where('tabel_referensi', 'kas_keluar')->get()->getResult();
        $this->assertCount(2, $jurnal);
        $this->assertSame(50000, array_sum(array_map(static fn ($row) => (int) $row->debet, $jurnal)));
        $this->assertSame(50000, array_sum(array_map(static fn ($row) => (int) $row->kredit, $jurnal)));

        $this->assertFalse((new ModelService())->hardDeleteService(1), 'Service yang direfund tidak boleh menghapus histori kas DP.');
        $this->assertSame(1, $this->db->table('service')->where('idservice', 1)->countAllResults());
    }

    public function testRefundKeduaDitolakDanTidakMenggandakanKasKeluar(): void
    {
        $this->service()->refund(1, 'Service batal', null, 7, 1);

        $this->expectException(\RuntimeException::class);
        $this->service()->refund(1, 'Coba refund ulang', null, 7, 1);
    }

    public function testDPTidakBisaDirefundSetelahServiceSelesai(): void
    {
        $this->db->table('service')->where('idservice', 1)->update(['status_service' => 4]);

        $this->expectException(\RuntimeException::class);
        $this->service()->refund(1, 'Service sudah selesai', null, 7, 1);
    }

    public function testRefundDpDitolakJikaAdaPembayaranLain(): void
    {
        $this->db->table('service')->where('idservice', 1)->update(['bayar_tunai' => 10000]);

        try {
            $this->service()->refund(1, 'Refund DP saja', null, 7, 1);
            $this->fail('Refund DP saja tidak boleh membatalkan service yang punya pembayaran lain.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pembayaran selain DP', $e->getMessage());
        }

        $this->assertSame('aktif', $this->db->table('service_dp')->where('id', 1)->get()->getRow()->status);
        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testRefundOtomatisMemakaiHardDeleteSparepartTerkait(): void
    {
        $this->tambahSparepartKeluar();
        $this->db->table('penjualan')->insert([
            'idpenjualan' => 202,
            'service_idservice' => 2,
        ]);
        $this->db->table('detail_penjualan')->insert([
            'penjualan_idpenjualan' => 202,
            'barang_idbarang' => 777,
            'jumlah' => 1,
        ]);
        $this->db->table('jurnal')->insert([
            'tanggal' => '2026-10-14',
            'no_akun' => '1010101000',
            'nama_akun' => 'Kas Besar',
            'debet' => 100000,
            'kredit' => 0,
            'id_referensi' => '202',
            'tabel_referensi' => 'penjualan',
            'id_unit' => 1,
        ]);

        // Role tidak diubah: pemanggil yang sebelumnya boleh refund tetap boleh
        // melakukannya; hard delete ikut transaksi refund yang sama.
        $hasil = $this->service()->refund(1, 'Service dibatalkan', null, 7, 1);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(0, $this->db->table('detail_penjualan')->where('penjualan_idpenjualan', 101)->countAllResults());
        $this->assertSame(0, $this->db->table('penjualan')->where('idpenjualan', 101)->countAllResults());
        $this->assertSame(0, $this->db->table('jurnal')->where('tabel_referensi', 'penjualan')->where('id_referensi', '101')->countAllResults());
        $this->assertSame(1, $this->db->table('service_sparepart')->where('service_idservice', 1)->countAllResults());
        $this->assertSame(1, $this->db->table('detail_penjualan')->where('penjualan_idpenjualan', 202)->countAllResults());
        $this->assertSame(1, $this->db->table('penjualan')->where('idpenjualan', 202)->countAllResults());
        $this->assertSame(1, $this->db->table('jurnal')->where('tabel_referensi', 'penjualan')->where('id_referensi', '202')->countAllResults());
        $this->assertSame(1, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testKegagalanHardDeleteTidakMeninggalkanPerubahanParsial(): void
    {
        $this->tambahSparepartKeluar();
        $this->db->table('sparepart_invoice_guard')->insert([
            'id' => 1,
            'penjualan_id' => 101,
        ]);

        try {
            $this->service()->refund(1, 'Service dibatalkan', null, 7, 1);
            $this->fail('Invoice guard harus menggagalkan hard delete dan refund.');
        } catch (\Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame(2, $this->db->table('detail_penjualan')->where('penjualan_idpenjualan', 101)->countAllResults());
        $this->assertSame(1, $this->db->table('penjualan')->where('idpenjualan', 101)->countAllResults());
        $this->assertSame(1, $this->db->table('jurnal')->where('tabel_referensi', 'penjualan')->where('id_referensi', '101')->countAllResults());
        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
        $this->assertNull($this->db->table('service_dp')->where('id', 1)->get()->getRow()->refund_idkas_keluar);
        $this->assertSame(2, (int) $this->db->table('service')->where('idservice', 1)->get()->getRow()->status_service);
    }

    public function testKegagalanJurnalRefundMengembalikanHardDeleteDanInvoice(): void
    {
        $this->tambahSparepartKeluar();
        $this->db->table('template_jurnal')
            ->where('kode_template', 'refund_dp_service_tunai')
            ->where('no_akun', '1010101000')
            ->update(['debet_kredit' => 'debet']);

        try {
            $this->service()->refund(1, 'Service dibatalkan', null, 7, 1);
            $this->fail('Jurnal tidak seimbang harus membatalkan seluruh transaksi refund.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('jurnal refund dp gagal', strtolower($e->getMessage()));
        }

        $this->assertSame(2, $this->db->table('detail_penjualan')->where('penjualan_idpenjualan', 101)->countAllResults());
        $this->assertSame(1, $this->db->table('penjualan')->where('idpenjualan', 101)->countAllResults());
        $this->assertSame(1, $this->db->table('jurnal')->where('tabel_referensi', 'penjualan')->where('id_referensi', '101')->countAllResults());
        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
        $this->assertNull($this->db->table('service_dp')->where('id', 1)->get()->getRow()->refund_idkas_keluar);
        $this->assertSame(2, (int) $this->db->table('service')->where('idservice', 1)->get()->getRow()->status_service);
    }
}
