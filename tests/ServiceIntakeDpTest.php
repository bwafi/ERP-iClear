<?php

/**
 * Feature test intake service (`insert/pelanggan_service`) terkait DP.
 *
 * Menguji jalur UI -> backend:
 *  - DP Tunai > 0: service tersimpan, ledger `service_dp` metode tunai,
 *    jurnal DP seimbang, `service.dp_bayar` diproyeksikan;
 *  - DP Transfer > 0 dengan rekening: ledger metode transfer + bank_idbank +
 *    referensi terkirim dengan benar;
 *  - DP Transfer tanpa rekening: DITOLAK (validasi server-side tetap
 *    berlaku), seluruh transaksi di-rollback (tanpa service, ledger, jurnal);
 *  - DP 0: service tersimpan tanpa baris ledger maupun jurnal DP;
 *  - metode default 'tunai' bila tidak dikirim (DP>0 tetap dicatat tunai).
 *
 * Mengikuti pola ServicePengambilanDpTest: tabel prefix `db_` (group 'tests'),
 * verifikasi lewat koneksi fresh karena AuthFilter menutup koneksi shared
 * setelah tiap request. Test berjalan di database terisolasi (erp_test).
 */

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ServiceIntakeDpTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'db_service',
        'db_service_dp',
        'db_service_kerusakan',
        'db_service_sparepart',
        'db_pelanggan',
        'db_unit',
        'db_stok_barang',
        'db_akun',
        'db_jabatan',
        'db_jurnal',
        'db_template_jurnal',
        'db_pembayaran_bank',
        'db_bank',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->koneksiFresh();
        $this->buatTabel();
    }

    protected function tearDown(): void
    {
        $db = $this->koneksiFresh();

        foreach (self::TABEL as $t) {
            $db->query("DROP TABLE IF EXISTS {$t}");
        }

        parent::tearDown();
    }

    private function koneksiFresh()
    {
        return \Config\Database::connect('tests', false);
    }

    private function buatTabel(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        foreach (self::TABEL as $t) {
            $q("DROP TABLE IF EXISTS {$t}");
        }

        // Kolom mengikuti ModelService::allowedFields + data insert_service().
        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            status_service INT NOT NULL DEFAULT 0,
            no_hp VARCHAR(64) NULL,
            imei VARCHAR(64) NULL,
            tipe_hp VARCHAR(64) NULL,
            passcode VARCHAR(64) NULL,
            email_icloud VARCHAR(64) NULL,
            password_icloud VARCHAR(64) NULL,
            keluhan TEXT NULL,
            keterangan TEXT NULL,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            service_by INT NULL,
            input_by INT NULL,
            total_service INT NULL DEFAULT 0,
            harus_dibayar INT NULL DEFAULT 0,
            bayar_tunai INT NULL DEFAULT 0,
            dp_bayar INT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            tanggal_selesai DATETIME NULL,
            tanggal_bisa_diambil DATE NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            kode_dp VARCHAR(64) NULL,
            tanggal DATE NULL,
            jumlah DOUBLE NOT NULL DEFAULT 0,
            metode VARCHAR(20) NULL,
            bank_idbank VARCHAR(20) NULL,
            referensi VARCHAR(100) NULL,
            status VARCHAR(20) NOT NULL DEFAULT "aktif",
            keterangan TEXT NULL,
            unit_id INT NULL,
            input_by INT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_kerusakan (
            idservice_kerusakan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            PRIMARY KEY (idservice_kerusakan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_sparepart (
            idservice_sparepart INT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            PRIMARY KEY (idservice_sparepart)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pelanggan (
            id_pelanggan INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama VARCHAR(255) NULL,
            no_hp VARCHAR(64) NULL,
            provinsi VARCHAR(150) NULL,
            kabupaten VARCHAR(150) NULL,
            kecamatan VARCHAR(150) NULL,
            PRIMARY KEY (id_pelanggan)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_unit (
            idunit INT UNSIGNED NOT NULL AUTO_INCREMENT,
            NAMA_UNIT VARCHAR(150) NULL,
            PRIMARY KEY (idunit)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_stok_barang (
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

        $q('CREATE TABLE db_jurnal (
            idjurnal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tanggal DATE NULL,
            no_akun VARCHAR(64) NULL,
            nama_akun VARCHAR(150) NULL,
            debet INT NULL DEFAULT 0,
            kredit INT NULL DEFAULT 0,
            keterangan TEXT NULL,
            id_referensi VARCHAR(64) NULL,
            tabel_referensi VARCHAR(64) NULL,
            id_unit INT NULL,
            id_akun INT NULL,
            PRIMARY KEY (idjurnal)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_template_jurnal (
            idtemplate_jurnal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_template VARCHAR(64) NULL,
            no_akun VARCHAR(64) NULL,
            nama_akun VARCHAR(150) NULL,
            debet_kredit VARCHAR(10) NULL,
            array_value INT NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            PRIMARY KEY (idtemplate_jurnal)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pembayaran_bank (
            idpembayaran_bank INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_pembayaran VARCHAR(64) NULL,
            jumlah INT NULL DEFAULT 0,
            bank_idbank VARCHAR(20) NULL,
            tabel_referensi VARCHAR(64) NULL,
            id_referensi VARCHAR(64) NULL,
            PRIMARY KEY (idpembayaran_bank)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_bank (
            idbank VARCHAR(20) NOT NULL,
            jenis_bank VARCHAR(20) NULL,
            nama_bank VARCHAR(150) NULL,
            PRIMARY KEY (idbank)
        ) ENGINE=InnoDB');

        $q("INSERT INTO db_pelanggan (id_pelanggan, nama, no_hp, provinsi, kabupaten, kecamatan)
            VALUES (10, 'Pelanggan Tes', '081234567890', 'Jawa Timur', 'Surabaya', 'Wonokromo')");
        $q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Tes')");
        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Akun Tes')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin Tes', NULL)");

        // Template jurnal DP (dipakai ServiceDpService::TEMPLATE).
        $q("INSERT INTO db_template_jurnal (kode_template, no_akun, nama_akun, debet_kredit, array_value) VALUES
            ('dp_service_tunai', '1010101000', 'Kas Besar', 'debet', 0),
            ('dp_service_tunai', '2010302000', 'Pendapatan Diterima di Muka', 'kredit', 0),
            ('dp_service_bank', '1010102000', 'Kas di Bank', 'debet', 0),
            ('dp_service_bank', '2010302000', 'Pendapatan Diterima di Muka', 'kredit', 0)");

        $q("INSERT INTO db_bank (idbank, jenis_bank, nama_bank) VALUES ('2', 'bank', 'BCA')");
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 43,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => 1,
        ]);
    }

    /**
     * Post standar intake service lengkap; isi dimutakhirkan per test.
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'selectedidpelanggan' => 10,
            'no_hp'               => '081234567890',
            'imei'                => 'IMEI-TEST-001',
            'tipe_hp'             => 'iPhone 13',
            'passcode'            => '1234',
            'email_icloud'        => 'tes@icloud.com',
            'password_icloud'     => 'rahasia',
            'keluhan'             => 'Layar retak',
            'keterangan'          => 'Ganti LCD',
            'domisili_provinsi'   => 'Jawa Timur',
            'domisili_kabupaten'  => 'Surabaya',
            'domisili_kecamatan'  => 'Wonokromo',
        ], $override);
    }

    private function jumlahService(): int
    {
        return (int) $this->db->table('service')->countAll();
    }

    private function barisLedgerMetode(string $metode): array
    {
        return $this->db->table('service_dp')
            ->where('metode', $metode)
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
    }

    public function testIntakeDpTunaiMencatatLedgerMetodeTunai(): void
    {
        $this->sesi();
        $r = $this->post('insert/pelanggan_service', $this->payload([
            'dp_bayar'    => '25000',
            'metode_bayar' => 'tunai',
        ]));
        $r->assertStatus(302);

        $this->assertSame(1, $this->jumlahService(), 'Satu service tersimpan.');

        $db = $this->koneksiFresh();
        $service = $db->table('service')->get()->getRow();
        $this->assertSame(25000, (int) $service->dp_bayar, 'service.dp_bayar diproyeksikan ke nominal DP.');

        $ledger = $this->barisLedgerMetode('tunai');
        $this->assertCount(1, $ledger, 'Ledger DP tunai tercatat.');
        $this->assertSame(25000, (int) $ledger[0]['jumlah']);
        $this->assertNull($ledger[0]['bank_idbank'], 'Tunai tidak membawa rekening.');

        $jurnal = $this->koneksiFresh()->table('jurnal')
            ->where('tabel_referensi', 'service_dp')
            ->orderBy('idjurnal', 'ASC')
            ->get()->getResultArray();
        $this->assertCount(2, $jurnal, 'Dr Kas + Cr PDM.');
        $this->assertSame(25000, (int) $jurnal[0]['debet'] + (int) $jurnal[1]['debet']);
        $this->assertSame(25000, (int) $jurnal[0]['kredit'] + (int) $jurnal[1]['kredit']);
    }

    public function testIntakeDpTransferDenganRekeningMencatatBankDanReferensi(): void
    {
        $this->sesi();
        $r = $this->post('insert/pelanggan_service', $this->payload([
            'dp_bayar'     => '30000',
            'metode_bayar' => 'transfer',
            'bank_idbank'  => '2',
            'referensi_dp' => 'TRF-120',
        ]));
        $r->assertStatus(302);

        $ledger = $this->barisLedgerMetode('transfer');
        $this->assertCount(1, $ledger, 'Ledger DP transfer tercatat.');
        $this->assertSame(30000, (int) $ledger[0]['jumlah']);
        $this->assertSame('2', (string) $ledger[0]['bank_idbank'], 'Rekening tujuan terkirim ke ledger.');
        $this->assertSame('TRF-120', (string) $ledger[0]['referensi'], 'Referensi terkirim ke ledger.');
        $this->assertSame(30000, (int) $this->koneksiFresh()->table('service')->get()->getRow()->dp_bayar);
    }

    public function testIntakeDpTransferTanpaRekeningDitolakDanRollbackTotal(): void
    {
        $this->sesi();
        $r = $this->post('insert/pelanggan_service', $this->payload([
            'dp_bayar'     => '30000',
            'metode_bayar' => 'transfer',
        ]));
        $r->assertStatus(302);

        $db = $this->koneksiFresh();
        $this->assertSame(0, (int) $db->table('service')->countAll(), 'Service tidak tersimpan (rollback).');
        $this->assertSame(0, (int) $db->table('service_dp')->countAll(), 'Tidak ada ledger DP.');
        $this->assertSame(0, (int) $db->table('jurnal')->countAll(), 'Tidak ada jurnal DP.');
    }

    public function testIntakeDpTunaiTanpaMetodeDefaultTunaiTetapTercatat(): void
    {
        $this->sesi();
        // Metode tidak dikirim -> backend default 'tunai'.
        $r = $this->post('insert/pelanggan_service', $this->payload([
            'dp_bayar' => '15000',
        ]));
        $r->assertStatus(302);

        $ledger = $this->barisLedgerMetode('tunai');
        $this->assertCount(1, $ledger, 'DP tanpa metode dikirim tetap dicatat sebagai tunai.');
        $this->assertSame(15000, (int) $ledger[0]['jumlah']);
        $this->assertSame(15000, (int) $this->koneksiFresh()->table('service')->get()->getRow()->dp_bayar);
    }

    public function testIntakeDpNolServiceTersimpanTanpaLedgerDanJurnal(): void
    {
        $this->sesi();
        $r = $this->post('insert/pelanggan_service', $this->payload([
            'dp_bayar'     => '0',
            'metode_bayar' => 'transfer', // dp 0: metode tidak relevan, tidak wajib
        ]));
        $r->assertStatus(302);

        $db = $this->koneksiFresh();
        $this->assertSame(1, (int) $db->table('service')->countAll(), 'Service tetap tersimpan.');
        $this->assertSame(0, (int) $db->table('service')->get()->getRow()->dp_bayar);
        $this->assertSame(0, (int) $db->table('service_dp')->countAll(), 'DP 0 tidak membuat ledger.');
        $this->assertSame(0, (int) $db->table('jurnal')->countAll(), 'DP 0 tidak membuat jurnal.');
    }
}