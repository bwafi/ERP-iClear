<?php

namespace Tests\unit;

use App\Services\Service\ServiceDpService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Koreksi DP service (salah catat metode/rekening/nominal).
 *
 * DDL/DML nyata di group `tests` (MySQL `erp_test`, prefix dikosongkan),
 * mengikuti pola ServiceDpLedgerTest.
 *
 * @internal
 */
final class ServiceDpKoreksiTest extends CIUnitTestCase
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

        $tabel = [
            'service_dp_koreksi', 'service_dp', 'jurnal', 'template_jurnal',
            'bank', 'akun_kas_bank', 'alokasi_saldo_kas_bank', 'pembayaran_bank', 'service',
        ];
        foreach ($tabel as $nama) {
            $this->db->query("DROP TABLE IF EXISTS {$nama}");
        }

        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            no_service VARCHAR(255) NULL,
            status_service INT NULL,
            unit_idunit INT NULL,
            total_service BIGINT NULL,
            dp_bayar INT DEFAULT 0,
            bayar INT DEFAULT 0,
            bayar_tunai INT DEFAULT 0,
            updated_at DATETIME NULL)');

        $this->db->query('CREATE TABLE service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_idservice INT NOT NULL,
            kode_dp VARCHAR(40) NOT NULL,
            tanggal DATE NULL,
            jumlah BIGINT DEFAULT 0,
            metode VARCHAR(10) NULL,
            bank_idbank VARCHAR(20) NULL,
            referensi VARCHAR(100) NULL,
            status VARCHAR(15) NOT NULL DEFAULT \'aktif\',
            keterangan TEXT NULL,
            unit_id INT NULL,
            input_by INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            refund_idkas_keluar INT NULL,
            refund_at DATETIME NULL,
            UNIQUE KEY uniq_sdp_kode (kode_dp))');

        $this->db->query('CREATE TABLE service_dp_koreksi (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            service_dp_id BIGINT UNSIGNED NOT NULL,
            data_sebelum TEXT NULL,
            data_sesudah TEXT NULL,
            alasan VARCHAR(120) NULL,
            oleh INT NULL,
            created_at DATETIME NULL)');

        $this->db->query('CREATE TABLE jurnal (
            idjurnal INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NULL,
            no_akun VARCHAR(255) NULL,
            nama_akun VARCHAR(255) NULL,
            debet INT DEFAULT 0,
            kredit INT DEFAULT 0,
            keterangan TEXT NULL,
            id_referensi VARCHAR(255) NULL,
            tabel_referensi VARCHAR(255) NULL,
            id_unit INT NULL,
            id_akun INT NULL)');

        $this->db->query('CREATE TABLE template_jurnal (
            idtemplate_jurnal INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            kode_template VARCHAR(255) NULL,
            no_akun VARCHAR(255) NULL,
            nama_akun VARCHAR(255) NULL,
            debet_kredit VARCHAR(10) NULL,
            array_value INT DEFAULT 0,
            keterangan TEXT NULL)');

        $this->db->query('CREATE TABLE bank (
            idbank VARCHAR(20) NOT NULL PRIMARY KEY,
            jenis_bank VARCHAR(20) NULL,
            nama_bank VARCHAR(255) NULL)');

        $this->db->query('CREATE TABLE akun_kas_bank (
            idakun_kas_bank INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            unit_id INT NULL,
            tipe VARCHAR(20) NULL,
            nama_akun VARCHAR(255) NULL,
            bank_idbank VARCHAR(20) NULL,
            no_akun_coa VARCHAR(20) NULL,
            status VARCHAR(20) NULL,
            is_shared INT DEFAULT 0,
            is_finance_ho INT DEFAULT 0)');

        $this->db->query('CREATE TABLE alokasi_saldo_kas_bank (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            akun_kas_bank_id INT NULL,
            unit_id INT NULL)');

        $this->db->query('CREATE TABLE pembayaran_bank (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            kode_pembayaran VARCHAR(64) NULL,
            bank_idbank VARCHAR(20) NULL,
            jumlah BIGINT NULL,
            tabel_referensi VARCHAR(32) NULL,
            id_referensi INT NULL)');

        $this->db->table('template_jurnal')->insertBatch([
            ['kode_template' => 'dp_service_tunai', 'no_akun' => '1010101000', 'nama_akun' => 'Kas Besar', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'dp_service_tunai', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka', 'debet_kredit' => 'kredit', 'array_value' => 0],
            ['kode_template' => 'dp_service_bank', 'no_akun' => '1010102000', 'nama_akun' => 'Kas di Bank', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'dp_service_bank', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka', 'debet_kredit' => 'kredit', 'array_value' => 0],
        ]);

        $this->db->table('bank')->insertBatch([
            ['idbank' => '2', 'jenis_bank' => 'bank', 'nama_bank' => 'BCA'],
            ['idbank' => '9', 'jenis_bank' => 'bank', 'nama_bank' => 'Bank Unit Lain'],
        ]);

        $this->db->table('akun_kas_bank')->insertBatch([
            ['idakun_kas_bank' => 10, 'unit_id' => 1, 'tipe' => 'BANK', 'nama_akun' => 'BCA Unit 1', 'bank_idbank' => '2', 'status' => 'aktif', 'is_shared' => 0, 'is_finance_ho' => 0],
            ['idakun_kas_bank' => 11, 'unit_id' => 2, 'tipe' => 'BANK', 'nama_akun' => 'Bank Unit 2', 'bank_idbank' => '9', 'status' => 'aktif', 'is_shared' => 0, 'is_finance_ho' => 0],
        ]);

        $this->db->table('alokasi_saldo_kas_bank')->insert([
            'akun_kas_bank_id' => 10,
            'unit_id' => 1,
        ]);

        $this->db->table('service')->insert([
            'idservice' => 1,
            'no_service' => 'SRV120261020001',
            'status_service' => 2,
            'unit_idunit' => 1,
            'total_service' => 1000000,
            'dp_bayar' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        $tabel = [
            'service_dp_koreksi', 'service_dp', 'jurnal', 'template_jurnal',
            'bank', 'akun_kas_bank', 'alokasi_saldo_kas_bank', 'pembayaran_bank', 'service',
        ];
        foreach ($tabel as $nama) {
            $this->db->query("DROP TABLE IF EXISTS {$nama}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function service(): ServiceDpService
    {
        return new ServiceDpService($this->db);
    }

    private function catatDp(int $jumlah, string $metode = 'tunai', ?string $bank = null): int
    {
        $hasil = $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => $jumlah,
            'metode' => $metode,
            'bank_idbank' => $bank,
            'referensi' => $metode === 'transfer' ? 'TRF-ASAL' : null,
            'kode_dp' => 'DP-TEST-' . $jumlah . '-' . $metode,
            'tanggal' => '2026-10-20',
            'unit_id' => 1,
            'input_by' => 7,
        ]);

        return (int) $hasil['id'];
    }

    private function koreksi(int $idDp, array $opsi = []): void
    {
        $this->service()->koreksiDp($idDp, array_merge([
            'metode' => 'transfer',
            'jumlah' => 300000,
            'bank_idbank' => '2',
            'referensi' => 'TRF-KOREKSI',
            'alasan' => 'Salah input metode',
        ], $opsi), 43, 1);
    }

    public function testTunaiKeTransferMenggantiAkunJurnalDanRekening(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');

        $this->koreksi($idDp);

        $dp = $this->db->table('service_dp')->where('id', $idDp)->get()->getRow();
        $this->assertSame('transfer', $dp->metode);
        $this->assertSame('2', (string) $dp->bank_idbank);
        $this->assertSame('TRF-KOREKSI', $dp->referensi);
        $this->assertSame(300000, (int) $dp->jumlah);

        $jurnal = $this->db->table('jurnal')
            ->where('tabel_referensi', 'service_dp')
            ->where('id_referensi', (string) $idDp)
            ->orderBy('idjurnal', 'ASC')
            ->get()
            ->getResult();

        $this->assertCount(2, $jurnal);
        $this->assertSame('1010102000', $jurnal[0]->no_akun);
        $this->assertSame(300000, (int) $jurnal[0]->debet);
        $this->assertSame(0, (int) $jurnal[0]->kredit);
        $this->assertSame('2010302000', $jurnal[1]->no_akun);
        $this->assertSame(300000, (int) $jurnal[1]->kredit);

        $service = $this->db->table('service')->where('idservice', 1)->get()->getRow();
        $this->assertSame(300000, (int) $service->dp_bayar);

        $audit = $this->db->table('service_dp_koreksi')->where('service_dp_id', $idDp)->get()->getRow();
        $this->assertNotNull($audit);
        $this->assertSame('Salah input metode', $audit->alasan);
        $this->assertSame(43, (int) $audit->oleh);
        $this->assertStringContainsString('tunai', (string) $audit->data_sebelum);
        $this->assertStringContainsString('transfer', (string) $audit->data_sesudah);
    }

    public function testTransferKeTunaiMengosongkanBankDanMemakaiAkunKas(): void
    {
        $idDp = $this->catatDp(500000, 'transfer', '2');

        $this->koreksi($idDp, [
            'metode' => 'tunai',
            'jumlah' => 500000,
            'bank_idbank' => '2',
            'referensi' => 'TRF-HARUSNYA-DIHAPUS',
        ]);

        $dp = $this->db->table('service_dp')->where('id', $idDp)->get()->getRow();
        $this->assertSame('tunai', $dp->metode);
        $this->assertNull($dp->bank_idbank);
        $this->assertNull($dp->referensi);

        $jurnal = $this->db->table('jurnal')
            ->where('tabel_referensi', 'service_dp')
            ->where('id_referensi', (string) $idDp)
            ->orderBy('idjurnal', 'ASC')
            ->get()
            ->getResult();

        $this->assertSame('1010101000', $jurnal[0]->no_akun);
        $this->assertSame(500000, (int) $jurnal[0]->debet);
    }

    public function testNominalSajaMemperbaruiJurnalDanProyeksi(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');

        $this->koreksi($idDp, [
            'metode' => 'tunai',
            'jumlah' => 450000,
            'bank_idbank' => null,
            'referensi' => null,
        ]);

        $dp = $this->db->table('service_dp')->where('id', $idDp)->get()->getRow();
        $this->assertSame(450000, (int) $dp->jumlah);

        $jurnal = $this->db->table('jurnal')
            ->where('tabel_referensi', 'service_dp')
            ->where('id_referensi', (string) $idDp)
            ->orderBy('idjurnal', 'ASC')
            ->get()
            ->getResult();

        $this->assertSame(450000, (int) $jurnal[0]->debet);
        $this->assertSame(450000, (int) $jurnal[1]->kredit);

        $service = $this->db->table('service')->where('idservice', 1)->get()->getRow();
        $this->assertSame(450000, (int) $service->dp_bayar);
    }

    public function testDpYangSudahDirefundTidakBisaDikoreksi(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');
        $this->db->table('service_dp')->where('id', $idDp)->update([
            'refund_idkas_keluar' => 99,
            'refund_at' => '2026-10-21 10:00:00',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->koreksi($idDp);
    }

    public function testNominalTidakBisaDiubahSetelahServiceDiambil(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');
        $this->db->table('service')->where('idservice', 1)->update(['status_service' => 4]);

        $this->expectException(\RuntimeException::class);
        $this->koreksi($idDp, ['jumlah' => 450000]);
    }

    public function testMetodeMasihBisaDiubahSetelahServiceDiambil(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');
        $this->db->table('service')->where('idservice', 1)->update(['status_service' => 4]);

        $this->koreksi($idDp, ['jumlah' => 500000]);

        $dp = $this->db->table('service_dp')->where('id', $idDp)->get()->getRow();
        $this->assertSame('transfer', $dp->metode);
    }

    public function testBankDiLuarScopeUnitDitolak(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');

        $this->expectException(\RuntimeException::class);
        $this->koreksi($idDp, ['bank_idbank' => '9']);
    }

    public function testNominalMelebihiTotalTagihanDitolak(): void
    {
        $idDp = $this->catatDp(300000, 'tunai');
        $this->db->table('service')->where('idservice', 1)->update(['total_service' => 400000]);

        $this->expectException(\RuntimeException::class);
        $this->koreksi($idDp, ['jumlah' => 500000]);
    }

    public function testRoleSelainAdminRootDitolak(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');

        $this->expectException(\RuntimeException::class);
        $this->service()->koreksiDp($idDp, [
            'metode'      => 'transfer',
            'jumlah'      => 300000,
            'bank_idbank' => '2',
            'referensi'   => 'TRF-KOREKSI',
            'alasan'      => 'Salah input metode',
        ], 43, 2);
    }

    public function testMetodeKosongDitolak(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');

        try {
            $this->koreksi($idDp, ['metode' => '']);
            $this->fail('Metode kosong seharusnya ditolak.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $dp = $this->db->table('service_dp')->where('id', $idDp)->get()->getRow();
        $this->assertSame('tunai', $dp->metode);
        $this->assertSame(500000, (int) $dp->jumlah);
        $this->assertSame(0, $this->db->table('service_dp_koreksi')->countAllResults());
    }

    public function testJurnalRusakMembatalkanSeluruhKoreksi(): void
    {
        $idDp = $this->catatDp(500000, 'tunai');
        $this->db->table('jurnal')->where('tabel_referensi', 'service_dp')->delete();

        try {
            $this->koreksi($idDp);
            $this->fail('Koreksi seharusnya ditolak saat jurnal DP tidak ada.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $dp = $this->db->table('service_dp')->where('id', $idDp)->get()->getRow();
        $this->assertSame('tunai', $dp->metode);
        $this->assertSame(500000, (int) $dp->jumlah);
        $this->assertSame(0, $this->db->table('service_dp_koreksi')->countAllResults());
    }
}
