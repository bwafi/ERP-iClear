<?php

/**
 * Feature test "Kembalikan ke Proses Service" (Admin Root).
 *
 * Service berstatus 4 (sudah diambil) dikembalikan ke 2 (proses): jurnal
 * pengambilan dan pembayaran bank lama dihapus, DP dibiarkan utuh. Setelah
 * itu operator mengisi ulang pembayaran dan menandai diambil lagi.
 *
 * Mengikuti pola ServicePengambilanDpTest: DDL/DML nyata di database test
 * terisolasi (group `tests`, prefix `db_`, erp_test).
 */

namespace Tests\unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ServiceKembalikanKeProsesTest extends CIUnitTestCase
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
        'db_tutup_kasir',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

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

        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            no_hp VARCHAR(32) NULL,
            status_service INT NOT NULL DEFAULT 0,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            total_service INT NULL DEFAULT 0,
            total_diskon INT NULL DEFAULT 0,
            harus_dibayar INT NULL DEFAULT 0,
            dp_bayar INT NULL DEFAULT 0,
            bayar INT NULL DEFAULT 0,
            bayar_tunai INT NULL DEFAULT 0,
            bayar_bank VARCHAR(64) NULL,
            garansi_hari INT NULL DEFAULT 0,
            tanggal_claim_garansi DATE NULL,
            refund_garansi_at DATETIME NULL,
            total_service_garansi INT NULL DEFAULT 0,
            biaya_tambahan_garansi INT NULL DEFAULT 0,
            total_diskon_garansi INT NULL DEFAULT 0,
            harga_penjualan_garansi INT NULL DEFAULT 0,
            created_at DATETIME NULL,
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

        $q('CREATE TABLE db_tutup_kasir (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit INT NULL,
            tanggal DATE NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB');

        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Akun Tes')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin Tes', NULL)");

        // Template jurnal pelunasan jasa service: Dr Kas, Dr PDM, Cr Pendapatan.
        $q("INSERT INTO db_template_jurnal (kode_template, no_akun, nama_akun, debet_kredit, array_value) VALUES
            ('pembayaran_service_tunai', '1010101000', 'Kas Besar', 'debet', 0),
            ('pembayaran_service_tunai', '2010302000', 'Pendapatan Diterima di Muka', 'debet', 1),
            ('pembayaran_service_tunai', '4010201000', 'Pendapatan Jasa', 'kredit', 2),
            ('pembayaran_service_bank', '1010102000', 'Kas di Bank', 'debet', 0),
            ('pembayaran_service_bank', '2010302000', 'Pendapatan Diterima di Muka', 'debet', 1),
            ('pembayaran_service_bank', '4010201000', 'Pendapatan Jasa', 'kredit', 2)");
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
     * @param array<string,mixed> $overrides
     */
    private function seedService(array $overrides = []): int
    {
        $this->db->table('service')->insert(array_merge([
            'no_service'      => 'SRV-' . uniqid(),
            'status_service'  => 3,
            'created_at'      => '2026-01-01 08:00:00',
            'tanggal_bisa_diambil' => '2026-01-02',
        ], $overrides));

        return (int) $this->db->insertID();
    }

    private function seedDp(int $idservice, int $jumlah, string $metode = 'tunai', string $status = 'aktif'): void
    {
        $this->db->table('service_dp')->insert([
            'service_idservice' => $idservice,
            'kode_dp'           => 'DP' . uniqid(),
            'jumlah'            => $jumlah,
            'metode'            => $metode,
            'status'            => $status,
        ]);
    }

    private function seedBankPaid(int $idservice, int $jumlah): void
    {
        $this->db->table('pembayaran_bank')->insert([
            'kode_pembayaran' => 'PB' . uniqid(),
            'jumlah'          => $jumlah,
            'bank_idbank'     => '2',
            'tabel_referensi' => 'service_baru',
            'id_referensi'    => $idservice,
        ]);
    }

    private function seedJurnalService(int $id, int $jumlah): void
    {
        $this->db->table('jurnal')->insertBatch([
            ['tanggal' => '2026-10-30', 'no_akun' => '1010101000', 'nama_akun' => 'Kas Besar',
             'debet' => $jumlah, 'kredit' => 0, 'id_referensi' => (string) $id, 'tabel_referensi' => 'service'],
            ['tanggal' => '2026-10-30', 'no_akun' => '4010201000', 'nama_akun' => 'Pendapatan Jasa',
             'debet' => 0, 'kredit' => $jumlah, 'id_referensi' => (string) $id, 'tabel_referensi' => 'service'],
        ]);
    }

    private function jurnalService(int $idservice): array
    {
        $db = $this->koneksiFresh();

        return $db->table('jurnal')
            ->where('tabel_referensi', 'service')
            ->where('id_referensi', (string) $idservice)
            ->orderBy('idjurnal', 'ASC')
            ->get()->getResultArray();
    }

    private function jumlahDenganAkun(array $jurnal, string $akun, string $kolom): int
    {
        $total = 0;
        foreach ($jurnal as $row) {
            if ((string) $row['no_akun'] === $akun) {
                $total += (int) $row[$kolom];
            }
        }

        return $total;
    }

    /**
     * Kirim POST dengan token CSRF baru (token lama hangus setelah dipakai).
     *
     * @param array<string,mixed> $payload
     */
    private function kirimKembalikan(array $payload)
    {
        return $this->post('service/kembalikan_ke_proses', $payload + [
            'csrf_test_name' => service('security')->getHash(),
        ]);
    }

    private function statusService(int $id): int
    {
        return (int) $this->koneksiFresh()->table('service')
            ->where('idservice', $id)->get()->getRow()->status_service;
    }

    public function testAdminRootMengembalikanServiceKeProses(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-30 10:00:00',
            'total_service' => 420000,
            'harus_dibayar' => 420000,
            'dp_bayar' => 0,
            'bayar' => 150000,
            'bayar_tunai' => 0,
            'bayar_bank' => 'ksr-lama',
        ]);
        $this->seedBankPaid($id, 150000);
        $this->seedJurnalService($id, 150000);

        $r = $this->kirimKembalikan([
            'idservice' => $id,
            'alasan' => 'Salah metode dan nominal',
        ]);

        $r->assertRedirectTo(base_url('detail/riwayat_service/' . $id . '?tab=pembayaran'));

        $service = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame(2, (int) $service->status_service);
        $this->assertNull($service->tanggal_selesai);
        $this->assertNull($service->tanggal_bisa_diambil);
        $this->assertSame(0, (int) $service->bayar);
        $this->assertSame(0, (int) $service->bayar_tunai);
        $this->assertNull($service->bayar_bank);
        $this->assertSame(420000, (int) $service->total_service, 'Tagihan tidak berubah.');
        $this->assertSame(0, (int) $this->koneksiFresh()->table('pembayaran_bank')
            ->where('tabel_referensi', 'service_baru')->where('id_referensi', $id)->countAllResults());
        $this->assertSame([], $this->jurnalService($id));
    }

    public function testSubmitKeduaDitolakKarenaStatusSudahProses(): void
    {
        $this->sesi();
        $id = $this->seedService(['status_service' => 4, 'tanggal_selesai' => '2026-10-30 10:00:00']);

        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);
        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(2, $this->statusService($id));
    }

    public function testRoleSelainAdminRootDitolak(): void
    {
        $this->withSession(['logged_in' => true, 'ID_AKUN' => 43, 'ID_UNIT' => 1, 'ID_JABATAN' => 41]);
        $id = $this->seedService(['status_service' => 4, 'tanggal_selesai' => '2026-10-30 10:00:00']);

        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(4, $this->statusService($id));
    }

    public function testServiceBukanStatusEmpatDitolak(): void
    {
        $this->sesi();
        $id = $this->seedService(['status_service' => 3]);

        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(3, $this->statusService($id));
    }

    public function testKlaimGaransiAtauRefundDitolak(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-30 10:00:00',
            'tanggal_claim_garansi' => '2026-10-30',
        ]);

        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(4, $this->statusService($id));

        $idRefund = $this->seedService([
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-30 10:00:00',
            'refund_garansi_at' => '2026-10-30 11:00:00',
        ]);

        $this->kirimKembalikan(['idservice' => $idRefund, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(4, $this->statusService($idRefund));
    }

    public function testSentinelKlaimDianggapTidakDiklaim(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-30 10:00:00',
            'tanggal_claim_garansi' => '0000-00-00',
        ]);

        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(2, $this->statusService($id));
    }

    public function testTutupKasirTanggalSamaButuhKonfirmasi(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'unit_idunit' => 1,
            'tanggal_selesai' => '2026-10-30 10:00:00',
        ]);
        $this->koneksiFresh()->table('tutup_kasir')->insert(['unit' => 1, 'tanggal' => '2026-10-30']);

        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(4, $this->statusService($id));

        $this->kirimKembalikan([
            'idservice' => $id,
            'alasan' => 'Koreksi',
            'konfirmasi_tutup_kasir' => '1',
        ])->assertStatus(302);

        $this->assertSame(2, $this->statusService($id));
    }

    public function testTutupKasirTanggalLainTidakButuhKonfirmasi(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'unit_idunit' => 1,
            'tanggal_selesai' => '2026-10-30 10:00:00',
        ]);
        $this->koneksiFresh()->table('tutup_kasir')->insert(['unit' => 1, 'tanggal' => '2026-10-29']);

        $this->kirimKembalikan(['idservice' => $id, 'alasan' => 'Koreksi'])->assertStatus(302);

        $this->assertSame(2, $this->statusService($id));
    }

    public function testPengambilanUlangMembentukJurnalBaruDariPembayaranBenar(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-30 10:00:00',
            'total_service' => 420000,
            'harus_dibayar' => 420000,
            'bayar' => 150000,
            'bayar_tunai' => 0,
        ]);
        $this->seedBankPaid($id, 150000);
        $this->seedJurnalService($id, 150000);

        $this->kirimKembalikan([
            'idservice' => $id,
            'alasan' => 'Salah metode dan nominal',
        ]);

        $this->post('insert/service/savePembayaran', [
            'idservice_p' => $id,
            'total_harga_pembayaran' => '420000',
            'metode_bayar' => 'tunai',
            'bayar_pembayaran' => '420000',
        ]);

        $this->post('service/bisa_diambil', ['idservice' => $id]);
        $this->post('service/sudah_diambil', ['idservice' => $id])->assertStatus(302);

        $jurnal = $this->jurnalService($id);
        $this->assertSame(1, count(array_filter($jurnal, static fn ($r) => (string) $r['no_akun'] === '4010201000')), 'Pendapatan diakui satu kali.');
        $this->assertSame(420000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'));
    }

    public function testDpTetapDanTidakDobelSaatPengambilanUlang(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-30 10:00:00',
            'total_service' => 420000,
            'harus_dibayar' => 420000,
            'dp_bayar' => 100000,
            'bayar' => 50000,
            'bayar_tunai' => 50000,
        ]);
        $this->seedDp($id, 100000);
        $this->seedJurnalService($id, 150000);

        $this->kirimKembalikan([
            'idservice' => $id,
            'alasan' => 'Salah input',
        ]);

        $service = $this->koneksiFresh()->table('service')->where('idservice', $id)->get()->getRow();
        $this->assertSame(100000, (int) $service->dp_bayar, 'DP tidak berubah.');

        $this->post('insert/service/savePembayaran', [
            'idservice_p' => $id,
            'total_harga_pembayaran' => '420000',
            'metode_bayar' => 'tunai',
            'bayar_pembayaran' => '320000',
        ]);
        $this->post('service/bisa_diambil', ['idservice' => $id]);
        $this->post('service/sudah_diambil', ['idservice' => $id]);

        $jurnal = $this->jurnalService($id);
        $this->assertSame(100000, $this->jumlahDenganAkun($jurnal, '2010302000', 'debet'), 'PDM dilepas sekali.');
        $this->assertSame(420000, $this->jumlahDenganAkun($jurnal, '4010201000', 'kredit'));
    }

    public function testLegacyTanpaJurnalTidakGagal(): void
    {
        $this->sesi();
        $id = $this->seedService([
            'status_service' => 4,
            'tanggal_selesai' => '2026-10-30 10:00:00',
            'total_service' => 100000,
            'harus_dibayar' => 100000,
            'bayar' => 100000,
            'bayar_tunai' => 100000,
        ]);

        $this->kirimKembalikan([
            'idservice' => $id,
            'alasan' => 'Legacy',
        ])->assertStatus(302);

        $this->assertSame(2, $this->statusService($id));
    }
}
