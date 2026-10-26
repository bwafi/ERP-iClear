<?php

namespace Tests\unit;

use App\Services\Service\GaransiRefundService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Tests\Support\Entitlement\EntitlementFixture;
use Tests\Support\Entitlement\Fase1;

/**
 * Refund uang service: kas keluar, jurnal seimbang, dan keputusan kondisi
 * sparepart pengganti per baris. Nominal = dp_bayar + bayar; garansi aktif.
 */
final class GaransiRefundServiceTest extends CIUnitTestCase
{
    use EntitlementFixture;

    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        $this->buatSchemaEntitlement();
        $this->seedEntitlementCanonical();
        $this->buatTabel();
        $this->seed();

        $_SESSION['ID_AKUN']    = 43;
        $_SESSION['ID_UNIT']    = 1;
        $_SESSION['ID_JABATAN'] = Fase1::ROLE_KASIR;
    }

    protected function tearDown(): void
    {
        foreach ([
            'db_retur_klaim_item', 'db_barang_rusak_claim', 'db_kas_keluar', 'db_jurnal',
            'db_template_jurnal', 'db_kategori_kas', 'db_service_sparepart', 'db_service',
            'db_pelanggan',
        ] as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        parent::tearDown();
    }

    private function buatTabel(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        foreach ([
            'db_retur_klaim_item', 'db_barang_rusak_claim', 'db_kas_keluar', 'db_jurnal',
            'db_template_jurnal', 'db_kategori_kas', 'db_service_sparepart', 'db_service',
            'db_pelanggan',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE db_pelanggan (
            id_pelanggan INT PRIMARY KEY,
            nama VARCHAR(100) NULL) ENGINE=InnoDB');
        $q('CREATE TABLE db_service (
            idservice INT PRIMARY KEY,
            no_service VARCHAR(40) NULL,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            status_service INT NULL,
            tanggal_selesai DATETIME NULL,
            garansi_hari INT NOT NULL DEFAULT 0,
            tanggal_claim_garansi DATETIME NULL,
            dp_bayar INT NOT NULL DEFAULT 0,
            bayar INT NOT NULL DEFAULT 0,
            bayar_garansi INT NOT NULL DEFAULT 0,
            service_by INT NULL,
            refund_garansi_idkas_keluar INT NULL,
            refund_garansi_tanggal DATE NULL,
            refund_garansi_metode VARCHAR(10) NULL,
            refund_garansi_idbank VARCHAR(20) NULL,
            refund_garansi_referensi VARCHAR(100) NULL,
            refund_garansi_alasan VARCHAR(255) NULL,
            refund_garansi_jumlah INT NOT NULL DEFAULT 0,
            refund_garansi_by INT NULL,
            refund_garansi_at DATETIME NULL) ENGINE=InnoDB');
        $q('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            unit_idunit INT NULL,
            jumlah_tambahan_garansi INT NOT NULL DEFAULT 0,
            hpp_penjualan_garansi INT NOT NULL DEFAULT 0,
            status_retur VARCHAR(20) NULL,
            hpp_omset_garansi INT NOT NULL DEFAULT 0,
            tanggal_hpp_klaim DATE NULL,
            jumlah_refund_garansi INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
        $q('CREATE TABLE db_barang_rusak_claim (
            idbarang_rusak_claim INT AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            unit_idunit INT NULL,
            input_by INT NULL,
            created_at DATETIME NULL) ENGINE=InnoDB');
        $q('CREATE TABLE db_retur_klaim_item (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_sparepart_idservice_sparepart INT NULL) ENGINE=InnoDB');
        $q('CREATE TABLE db_kategori_kas (
            idkategori_kas INT PRIMARY KEY,
            kategori VARCHAR(50) NULL,
            jenis_kas VARCHAR(20) NULL) ENGINE=InnoDB');
        $q('CREATE TABLE db_kas_keluar (
            idkas_keluar INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            no_akun VARCHAR(20) NULL,
            kategori_idkategori INT NULL,
            deskripsi TEXT NULL,
            jumlah INT NULL,
            jenis VARCHAR(10) NULL,
            penerima VARCHAR(100) NULL,
            idbank VARCHAR(20) NULL,
            idunit INT NULL,
            created_on DATETIME NULL) ENGINE=InnoDB');
        $q('CREATE TABLE db_jurnal (
            idjurnal INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            no_akun VARCHAR(20) NULL,
            nama_akun VARCHAR(100) NULL,
            debet INT NOT NULL DEFAULT 0,
            kredit INT NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            id_referensi VARCHAR(40) NULL,
            tabel_referensi VARCHAR(40) NULL,
            id_unit INT NULL,
            id_akun INT NULL) ENGINE=InnoDB');
        $q('CREATE TABLE db_template_jurnal (
            idtemplate_jurnal INT AUTO_INCREMENT PRIMARY KEY,
            kode_template VARCHAR(50) NULL,
            no_akun VARCHAR(20) NULL,
            nama_akun VARCHAR(100) NULL,
            debet_kredit VARCHAR(10) NULL,
            array_value INT NOT NULL DEFAULT 0,
            keterangan VARCHAR(100) NULL) ENGINE=InnoDB');
    }

    private function seed(): void
    {
        $this->db->table('pelanggan')->insert(['id_pelanggan' => 1, 'nama' => 'Pelanggan A']);

        $this->db->table('service')->insert([
            'idservice'              => 1,
            'no_service'             => 'SRV-001',
            'pelanggan_id_pelanggan' => 1,
            'unit_idunit'            => 1,
            'status_service'         => 4,
            'tanggal_selesai'        => date('Y-m-d H:i:s'),
            'garansi_hari'           => 7,
            'tanggal_claim_garansi'  => null,
            'dp_bayar'               => 200000,
            'bayar'                  => 300000,
            'service_by'             => 7,
        ]);

        $this->db->table('service_sparepart')->insert([
            'service_idservice'        => 1,
            'barang_idbarang'          => 10,
            'unit_idunit'              => 1,
            'jumlah_tambahan_garansi'  => 2,
            'hpp_penjualan_garansi'    => 4000,
            'status_retur'             => 'bisa',
            'hpp_omset_garansi'        => 0,
            'jumlah_refund_garansi'    => 0,
        ]);

        $this->db->table('kategori_kas')->insert([
            'idkategori_kas' => 17,
            'kategori'       => 'REFUND',
            'jenis_kas'      => 'kas_keluar',
        ]);

        $this->db->table('no_akun')->insertBatch([
            ['no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka (Jasa Service)'],
            ['no_akun' => '1010101000', 'nama_akun' => 'Kas Besar (Cash on Hand)'],
            ['no_akun' => '1010102000', 'nama_akun' => 'Kas di Bank'],
        ]);

        $this->db->table('template_jurnal')->insertBatch([
            ['kode_template' => 'refund_garansi_tunai', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka (Jasa Service)', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'refund_garansi_tunai', 'no_akun' => '1010101000', 'nama_akun' => 'Kas Besar (Cash on Hand)', 'debet_kredit' => 'kredit', 'array_value' => 0],
            ['kode_template' => 'refund_garansi_bank', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka (Jasa Service)', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'refund_garansi_bank', 'no_akun' => '1010102000', 'nama_akun' => 'Kas di Bank', 'debet_kredit' => 'kredit', 'array_value' => 0],
        ]);
    }

    public function testRefundTunaiMengembalikanSparepartBaikDanMencatatKasJurnal(): void
    {
        $hasil = (new GaransiRefundService())->refund(
            1,
            'tunai',
            null,
            null,
            'Pelanggan membatalkan',
            [1 => 'baik'],
            43,
            1
        );

        $this->assertSame(500000, $hasil['jumlah']);
        $this->assertGreaterThan(0, $hasil['idkas_keluar']);
        $this->assertSame('tunai', $hasil['metode']);

        $kas = $this->db->table('kas_keluar')->get()->getResult();
        $this->assertCount(1, $kas);
        $this->assertSame(500000, (int) $kas[0]->jumlah);
        $this->assertSame('debet', $kas[0]->jenis);
        $this->assertSame('2010302000', $kas[0]->no_akun);
        $this->assertSame(17, (int) $kas[0]->kategori_idkategori);
        $this->assertNull($kas[0]->idbank);
        $this->assertSame(1, (int) $kas[0]->idunit);

        $jurnal = $this->db->table('jurnal')
            ->where('tabel_referensi', 'kas_keluar')
            ->where('id_referensi', (string) $hasil['idkas_keluar'])
            ->get()
            ->getResult();
        $this->assertCount(2, $jurnal);

        $nilai = [];
        foreach ($jurnal as $baris) {
            $nilai[$baris->no_akun] = [(int) $baris->debet, (int) $baris->kredit];
        }
        $this->assertSame([500000, 0], $nilai['2010302000']);
        $this->assertSame([0, 500000], $nilai['1010101000']);

        $service = $this->db->table('service')->where('idservice', 1)->get()->getRow();
        $this->assertSame(500000, (int) $service->refund_garansi_jumlah);
        $this->assertSame('tunai', $service->refund_garansi_metode);
        $this->assertSame(date('Y-m-d'), $service->refund_garansi_tanggal);
        $this->assertNotNull($service->refund_garansi_at);
        $this->assertSame(43, (int) $service->refund_garansi_by);

        $sparepart = $this->db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $this->assertSame(2, (int) $sparepart->jumlah_refund_garansi);
        $this->assertSame('bisa', $sparepart->status_retur);
        $this->assertSame(0, (int) $sparepart->hpp_omset_garansi);

        $this->assertSame(0, $this->db->table('barang_rusak_claim')->countAllResults());
    }

    private function harapGagal(callable $aksi, string $pesan = ''): void
    {
        try {
            $aksi();
            $this->fail('Refund seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            if ($pesan !== '') {
                $this->assertStringContainsString($pesan, $e->getMessage());
            } else {
                $this->assertNotSame('', trim($e->getMessage()));
            }
        }
    }

    public function testRefundRusakMencatatBarangRusakDanHppDiBulanRefund(): void
    {
        (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Barang tidak layak', [1 => 'rusak'], 43, 1);

        $sparepart = $this->db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $this->assertSame('tidak_bisa', $sparepart->status_retur);
        $this->assertSame(4000, (int) $sparepart->hpp_omset_garansi);
        $this->assertSame(date('Y-m-d'), $sparepart->tanggal_hpp_klaim);

        $rusak = $this->db->table('barang_rusak_claim')->get()->getResult();
        $this->assertCount(1, $rusak);
        $this->assertSame(10, (int) $rusak[0]->barang_idbarang);
        $this->assertSame(2.0, (float) $rusak[0]->jumlah);
        $this->assertStringStartsWith('Refund:', $rusak[0]->keterangan);
        $this->assertSame(1, (int) $rusak[0]->unit_idunit);
    }

    public function testRefundRusakTidakMenimpaHppLama(): void
    {
        $this->db->table('service_sparepart')->where('idservice_sparepart', 1)->update([
            'status_retur'       => 'tidak_bisa',
            'hpp_omset_garansi'  => 4000,
            'tanggal_hpp_klaim'  => '2026-10-20',
        ]);

        (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Barang tidak layak', [1 => 'rusak'], 43, 1);

        $sparepart = $this->db->table('service_sparepart')->where('idservice_sparepart', 1)->get()->getRow();
        $this->assertSame(4000, (int) $sparepart->hpp_omset_garansi);
        $this->assertSame('2026-10-20', $sparepart->tanggal_hpp_klaim);
    }

    public function testRefundDitolakBilaBarisSudahReturSupplier(): void
    {
        $this->db->table('retur_klaim_item')->insert(['service_sparepart_idservice_sparepart' => 1]);

        $this->harapGagal(
            fn () => (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 1),
            'supplier'
        );

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testBarangBaikDitolakBilaKerugianSudahTercatat(): void
    {
        $this->db->table('service_sparepart')->where('idservice_sparepart', 1)->update([
            'status_retur'      => 'tidak_bisa',
            'hpp_omset_garansi' => 4000,
        ]);

        $this->harapGagal(
            fn () => (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 1)
        );

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testRefundKeduaDitolak(): void
    {
        (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 1);

        $this->harapGagal(
            fn () => (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal lagi', [1 => 'baik'], 43, 1)
        );

        $this->assertSame(1, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testKondisiSparepartWajibLengkap(): void
    {
        $this->harapGagal(
            fn () => (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [], 43, 1)
        );

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testRefundServiceBelumDiklaimTetapBisa(): void
    {
        // Nominal harus dari dp_bayar + bayar, bukan bayar_garansi lama.
        $this->db->table('service')->where('idservice', 1)->update(['bayar_garansi' => 999]);

        $hasil = (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 1);

        $this->assertSame(500000, $hasil['jumlah']);
        $this->assertSame(1, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testRefundServiceSudahDiklaimTetapBisa(): void
    {
        $this->db->table('service')->where('idservice', 1)->update([
            'tanggal_claim_garansi' => date('Y-m-d H:i:s'),
        ]);

        $hasil = (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 1);

        $this->assertSame(500000, $hasil['jumlah']);
    }

    public function testRefundDitolakBilaGaransiKadaluarsa(): void
    {
        $this->db->table('service')->where('idservice', 1)->update([
            'tanggal_selesai' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'garansi_hari'    => 7,
        ]);

        $this->harapGagal(
            fn () => (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 1)
        );

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testServiceTanpaPembayaranDitolak(): void
    {
        $this->db->table('service')->where('idservice', 1)->update(['dp_bayar' => 0, 'bayar' => 0]);

        $this->harapGagal(
            fn () => (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 1)
        );

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testUnitDiLuarWewenangDitolak(): void
    {
        $this->harapGagal(
            fn () => (new GaransiRefundService())->refund(1, 'tunai', null, null, 'Batal', [1 => 'baik'], 43, 2)
        );

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testRefundBankMemakaiRekeningUnitDanReferensi(): void
    {
        $hasil = (new GaransiRefundService())->refund(
            1,
            'bank',
            Fase1::IDBANK_CV,
            'TRX-123',
            'Refund transfer',
            [1 => 'baik'],
            43,
            1
        );

        $kas = $this->db->table('kas_keluar')->get()->getRow();
        $this->assertSame(500000, (int) $kas->jumlah);
        $this->assertSame(Fase1::IDBANK_CV, $kas->idbank);

        $jurnal = $this->db->table('jurnal')
            ->where('tabel_referensi', 'kas_keluar')
            ->where('id_referensi', (string) $hasil['idkas_keluar'])
            ->get()
            ->getResult();

        $nilai = [];
        foreach ($jurnal as $baris) {
            $nilai[$baris->no_akun] = [(int) $baris->debet, (int) $baris->kredit];
        }
        $this->assertSame([500000, 0], $nilai['2010302000']);
        $this->assertSame([0, 500000], $nilai['1010102000']);

        $service = $this->db->table('service')->where('idservice', 1)->get()->getRow();
        $this->assertSame(Fase1::IDBANK_CV, $service->refund_garansi_idbank);
        $this->assertSame('TRX-123', $service->refund_garansi_referensi);
    }

    public function testRefundBankTanpaReferensiDitolak(): void
    {
        try {
            (new GaransiRefundService())->refund(
                1,
                'bank',
                Fase1::IDBANK_CV,
                '',
                'Refund transfer',
                [1 => 'baik'],
                43,
                1
            );
            $this->fail('Refund bank tanpa referensi harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Referensi', $e->getMessage());
        }

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }

    public function testRefundBankRekeningUnitLainDitolak(): void
    {
        try {
            (new GaransiRefundService())->refund(
                1,
                'bank',
                Fase1::IDBANK_SABRINA,
                'TRX-9',
                'Refund transfer',
                [1 => 'baik'],
                43,
                1
            );
            $this->fail('Rekening unit lain harus ditolak.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('belum didukung', $e->getMessage());
            $this->assertNotSame('', trim($e->getMessage()));
        }

        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults());
    }
}
