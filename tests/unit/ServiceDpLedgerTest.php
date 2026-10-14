<?php

namespace Tests\unit;

use App\Models\ModelService;
use App\Services\Service\ServiceDpService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Ledger DP service (`service_dp`) — fondasi.
 *
 * Menguji ServiceDpService: pencatatan DP, jurnal seimbang, proyeksi
 * `service.dp_bayar`, validasi bank untuk transfer, penolakan nominal negatif,
 * nominal nol, dan kontrak atomik (ledger + jurnal ikut transaksi pemanggil).
 *
 * DDL/DML nyata di group `tests` (MySQL `erp_test`, prefix dikosongkan).
 *
 * @internal
 */
final class ServiceDpLedgerTest extends CIUnitTestCase
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

        foreach (['service', 'service_dp', 'jurnal', 'template_jurnal', 'bank'] as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        $this->db->query('CREATE TABLE service (
            idservice INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            no_service VARCHAR(255) NULL,
            dp_bayar INT DEFAULT 0,
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
            UNIQUE KEY uniq_sdp_kode (kode_dp))');

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

        $this->db->table('template_jurnal')->insertBatch([
            ['kode_template' => 'dp_service_tunai', 'no_akun' => '1010101000', 'nama_akun' => 'Kas Besar', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'dp_service_tunai', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka', 'debet_kredit' => 'kredit', 'array_value' => 0],
            ['kode_template' => 'dp_service_bank', 'no_akun' => '1010102000', 'nama_akun' => 'Kas di Bank', 'debet_kredit' => 'debet', 'array_value' => 0],
            ['kode_template' => 'dp_service_bank', 'no_akun' => '2010302000', 'nama_akun' => 'Pendapatan Diterima di Muka', 'debet_kredit' => 'kredit', 'array_value' => 0],
        ]);

        $this->db->table('bank')->insert([
            'idbank' => '2',
            'jenis_bank' => 'bank',
            'nama_bank' => 'BCA',
        ]);

        $this->db->table('service')->insert([
            'idservice' => 1,
            'no_service' => 'SRV120261012001',
            'dp_bayar' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['service', 'service_dp', 'jurnal', 'template_jurnal', 'bank'] as $tabel) {
            $this->db->query("DROP TABLE IF EXISTS {$tabel}");
        }
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function service(): ServiceDpService
    {
        return new ServiceDpService($this->db);
    }

    private function barisLedger(): array
    {
        return $this->db->table('service_dp')->orderBy('id', 'ASC')->get()->getResultArray();
    }

    private function barisJurnal(): array
    {
        return $this->db->table('jurnal')->get()->getResultArray();
    }

    private function dpBayarService(int $idservice): int
    {
        $row = $this->db->table('service')->where('idservice', $idservice)->get()->getRow();

        return (int) ($row->dp_bayar ?? 0);
    }

    public function testDpTunaiMencatatLedgerJurnalSeimbangDanProyeksi(): void
    {
        $hasil = $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 100000,
            'metode' => 'tunai',
            'unit_id' => 1,
            'input_by' => 7,
        ]);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(100000, $hasil['total']);

        $ledger = $this->barisLedger();
        $this->assertCount(1, $ledger);
        $this->assertSame('tunai', $ledger[0]['metode']);
        $this->assertSame('aktif', $ledger[0]['status']);
        $this->assertSame(100000, (int) $ledger[0]['jumlah']);
        $this->assertSame('DPSRV120261012001', $ledger[0]['kode_dp']);
        $this->assertSame(1, (int) $ledger[0]['unit_id']);
        $this->assertSame(7, (int) $ledger[0]['input_by']);

        $jurnal = $this->barisJurnal();
        $this->assertCount(2, $jurnal);
        $debet = array_sum(array_column($jurnal, 'debet'));
        $kredit = array_sum(array_column($jurnal, 'kredit'));
        $this->assertSame(100000, (int) $debet);
        $this->assertSame(100000, (int) $kredit);
        $this->assertSame('service_dp', $jurnal[0]['tabel_referensi']);
        $this->assertSame((string) $ledger[0]['id'], (string) $jurnal[0]['id_referensi']);

        $this->assertSame(100000, $this->dpBayarService(1), 'Proyeksi dp_bayar harus diperbarui.');
    }

    public function testDpTransferDenganBankValidMemakaiAkunKasBank(): void
    {
        $hasil = $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 250000,
            'metode' => 'transfer',
            'bank_idbank' => '2',
            'referensi' => 'TRF-001',
            'unit_id' => 1,
        ]);

        $this->assertSame('ok', $hasil['status']);

        $ledger = $this->barisLedger();
        $this->assertCount(1, $ledger);
        $this->assertSame('transfer', $ledger[0]['metode']);
        $this->assertSame('2', (string) $ledger[0]['bank_idbank']);
        $this->assertSame('TRF-001', $ledger[0]['referensi']);

        $akun = array_column($this->barisJurnal(), 'no_akun');
        $this->assertContains('1010102000', $akun, 'DP transfer memakai akun Kas di Bank.');
        $this->assertSame(250000, $this->dpBayarService(1));
    }

    public function testDpTransferTanpaBankDitolak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 100000,
            'metode' => 'transfer',
            'unit_id' => 1,
        ]);
    }

    public function testDpTransferBankTidakTerdaftarDitolak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 100000,
            'metode' => 'transfer',
            'bank_idbank' => '999',
            'unit_id' => 1,
        ]);
    }

    public function testMetodeTidakDikenalDitolak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 100000,
            'metode' => 'qris',
            'unit_id' => 1,
        ]);
    }

    public function testUnitTidakValidDitolak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 100000,
            'metode' => 'tunai',
            'unit_id' => 0,
        ]);
    }

    public function testServiceTidakDitemukanDitolak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->catatDp([
            'service_idservice' => 999,
            'jumlah' => 100000,
            'metode' => 'tunai',
            'unit_id' => 1,
        ]);
    }

    public function testNominalNolTidakMenghasilkanLedgerMaupunJurnal(): void
    {
        $hasil = $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 0,
            'metode' => 'tunai',
            'unit_id' => 1,
        ]);

        $this->assertSame('skip', $hasil['status']);
        $this->assertNull($hasil['id']);
        $this->assertSame(0, $hasil['total']);
        $this->assertCount(0, $this->barisLedger(), 'DP 0 tidak boleh membuat baris ledger.');
        $this->assertCount(0, $this->barisJurnal(), 'DP 0 tidak boleh membuat jurnal.');
        $this->assertSame(0, $this->dpBayarService(1));
    }

    public function testNominalNolTidakMembacaTabelLedger(): void
    {
        $this->db->query('DROP TABLE service_dp');

        $hasil = $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 0,
            'metode' => 'tunai',
            'unit_id' => 1,
        ]);

        $this->assertSame('skip', $hasil['status']);
        $this->assertSame(0, $hasil['total']);
    }

    public function testNominalNegatifDitolak(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => -5000,
            'metode' => 'tunai',
            'unit_id' => 1,
        ]);
    }

    public function testTemplateJurnalHilangMembatalkanPencatatan(): void
    {
        $this->db->table('template_jurnal')->where('kode_template', 'dp_service_tunai')->delete();

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $this->service()->catatDp([
                'service_idservice' => 1,
                'jumlah' => 100000,
                'metode' => 'tunai',
                'unit_id' => 1,
            ]);
            $this->fail('catatDp seharusnya melempar ketika template jurnal tidak ada.');
        } catch (\RuntimeException $e) {
            $db->transRollback();
        }

        $this->assertCount(0, $this->barisLedger(), 'Kegagalan jurnal harus membatalkan baris ledger.');
        $this->assertCount(0, $this->barisJurnal());
    }

    public function testLedgerDanJurnalIkutTransaksiPemanggil(): void
    {
        $db = \Config\Database::connect();
        $db->transStart();

        $this->service()->catatDp([
            'service_idservice' => 1,
            'jumlah' => 100000,
            'metode' => 'tunai',
            'unit_id' => 1,
        ]);

        $this->assertCount(1, $this->barisLedger(), 'Baris ledger terlihat di dalam transaksi.');
        $this->assertCount(2, $this->barisJurnal());

        $db->transRollback();

        $this->assertCount(0, $this->barisLedger(), 'Rollback pemanggil harus menghapus ledger DP.');
        $this->assertCount(0, $this->barisJurnal(), 'Rollback pemanggil harus menghapus jurnal DP.');
    }

    public function testBeberapaDpDijumlahkanDanDiproyeksikan(): void
    {
        $this->service()->catatDp(['service_idservice' => 1, 'jumlah' => 100000, 'metode' => 'tunai', 'unit_id' => 1]);
        $this->service()->catatDp(['service_idservice' => 1, 'jumlah' => 50000, 'metode' => 'tunai', 'unit_id' => 1]);

        $ledger = $this->barisLedger();
        $this->assertCount(2, $ledger);
        $this->assertNotSame($ledger[0]['kode_dp'], $ledger[1]['kode_dp'], 'Kode DP harus unik.');
        $this->assertSame(150000, $this->service()->totalDpAktif(1));
        $this->assertSame(150000, $this->dpBayarService(1));
    }

    public function testKodeDpSamaTidakMenduplikasiLedger(): void
    {
        $payload = [
            'service_idservice' => 1,
            'jumlah' => 100000,
            'metode' => 'tunai',
            'unit_id' => 1,
            'kode_dp' => 'DP-IDEMPOTEN-1',
        ];

        $pertama = $this->service()->catatDp($payload);
        $kedua   = $this->service()->catatDp($payload);

        $this->assertSame('ok', $pertama['status']);
        $this->assertSame('duplikat', $kedua['status']);
        $this->assertCount(1, $this->barisLedger(), 'Pengulangan request dengan kode sama tidak boleh menggandakan ledger.');
        $this->assertCount(2, $this->barisJurnal(), 'Pengulangan request tidak boleh menggandakan jurnal.');
        $this->assertSame(100000, $this->dpBayarService(1));
    }

    public function testRetryIntakeBerkodeStabilDitolakDanDibatalkanSeluruhnya(): void
    {
        // Simulasi ulangan request intake (double submit): no_service SAMA
        // sehingga kode_dp (stabil 'DP' . no_service, dipakai kontroller
        // insert_service) juga SAMA -> catatDp menolak duplikat. Sesuai
        // kebijakan kontroller, DP>0 dengan status != 'ok' membatalkan
        // seluruh transaksi (rollback sampai insert service barunya hilang).
        $db = $this->db;
        $db->transStart();

        $noService = 'SRV-INTAKE-RETRY';
        $idservice = $this->insertServiceRow($noService);
        $pertama = $this->service()->catatDp([
            'service_idservice' => $idservice,
            'jumlah'            => 50000,
            'metode'            => 'tunai',
            'unit_id'           => 1,
            'kode_dp'           => 'DP' . $noService,
        ]);

        $retryId = $this->insertServiceRow($noService);
        $kedua = $this->service()->catatDp([
            'service_idservice' => $retryId,
            'jumlah'            => 50000,
            'metode'            => 'tunai',
            'unit_id'           => 1,
            'kode_dp'           => 'DP' . $noService,
        ]);

        $this->assertSame(ServiceDpService::STATUS_OK, $pertama['status']);
        $this->assertSame(ServiceDpService::STATUS_DUPLIKAT, $kedua['status']);

        if ($kedua['status'] !== ServiceDpService::STATUS_OK) {
            $db->transRollback();
        } else {
            $db->transComplete();
        }

        $this->assertSame(0, (int) $this->db->table('service')->where('no_service', $noService)->countAllResults(), 'Intake ulang yang ditolak tidak menyisakan service.');
        $this->assertCount(0, $this->barisLedger(), 'Retry yang ditolak tidak menyisakan ledger DP.');
        $this->assertCount(0, $this->barisJurnal(), 'Retry yang ditolak tidak menyisakan jurnal DP.');
    }

    private function insertServiceRow(string $noService): int
    {
        $svc = new ModelService();
        $svc->insert([
            'no_service' => $noService,
            'dp_bayar'   => 0,
        ]);

        return (int) $svc->insertID();
    }

    public function testIntakeTunaiMembuatServiceLedgerDanJurnalDalamSatuTransaksi(): void
    {
        $db = $this->db;
        $db->transStart();

        $idservice = $this->insertServiceRow('SRV-INTAKE-TUNAI');
        $this->service()->catatDp([
            'service_idservice' => $idservice,
            'jumlah'            => 75000,
            'metode'            => 'tunai',
            'unit_id'           => 1,
        ]);

        $this->assertNotFalse($db->transStatus());
        $db->transComplete();

        $this->assertSame(1, (int) $this->db->table('service')->where('idservice', $idservice)->countAllResults(), 'Service baru harus tersimpan.');
        $this->assertCount(1, $this->barisLedger());
        $this->assertCount(2, $this->barisJurnal());
        $this->assertSame(75000, $this->dpBayarService($idservice));
    }

    public function testIntakeTanpaDpTidakMembuatLedgerDanJurnal(): void
    {
        $db = $this->db;
        $db->transStart();

        $idservice = $this->insertServiceRow('SRV-INTAKE-NODP');
        $hasil = $this->service()->catatDp([
            'service_idservice' => $idservice,
            'jumlah'            => 0,
            'metode'            => 'tunai',
            'unit_id'           => 1,
        ]);

        $db->transComplete();

        $this->assertSame('skip', $hasil['status']);
        $this->assertSame(1, (int) $this->db->table('service')->where('idservice', $idservice)->countAllResults());
        $this->assertCount(0, $this->barisLedger());
        $this->assertCount(0, $this->barisJurnal());
        $this->assertSame(0, $this->dpBayarService($idservice));
    }

    public function testIntakeGagalJurnalMembatalkanSeluruhService(): void
    {
        $this->db->table('template_jurnal')->where('kode_template', 'dp_service_tunai')->delete();

        $db = $this->db;
        $db->transStart();

        $idservice = $this->insertServiceRow('SRV-INTAKE-GAGAL');
        $gagal = false;
        try {
            $this->service()->catatDp([
                'service_idservice' => $idservice,
                'jumlah'            => 100000,
                'metode'            => 'tunai',
                'unit_id'           => 1,
            ]);
        } catch (\RuntimeException $e) {
            $gagal = true;
        }
        if ($gagal) {
            $db->transRollback();
        }

        $this->assertTrue($gagal, 'catatDp harus melempar ketika template jurnal hilang.');
        $this->assertSame(0, (int) $this->db->table('service')->where('idservice', $idservice)->countAllResults(), 'Rollback harus membatalkan insert service.');
        $this->assertCount(0, $this->barisLedger());
        $this->assertCount(0, $this->barisJurnal());
    }
}
