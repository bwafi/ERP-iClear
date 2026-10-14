<?php

/**
 * Feature test kebijakan pembatalan service terkait ledger DP.
 *
 * - Service TANPA baris `service_dp` -> cancel_service tetap berjalan seperti
 *   biasa (semua baris dibatalkan dihapus).
 * - Service BER-DP -> cancel_service berjalan dan menghapus service + baris
 *   `service_dp` + jurnal DP terkait dalam satu transaksi, tanpa menghapus
 *   jurnal DP milik service lain.
 *
 * Mengikuti pola KasBankControllerTest: tabel dibuat dengan prefix `db_`
 * (group 'tests'), dan verifikasi dilakukan lewat koneksi fresh agar tidak
 * terpengaruh oleh AuthFilter yang menutup koneksi shared setelah tiap request.
 */

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ServiceCancelDpTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'db_service',
        'db_service_dp',
        'db_jurnal',
        'db_service_kerusakan',
        'db_service_sparepart',
        'db_pelanggan',
        'db_unit',
        'db_stok_barang',
        'db_akun',
        'db_jabatan',
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

    /**
     * Koneksi non-shared yang selalu fresh ke group 'tests'. AuthFilter
     * menutup koneksi shared setelah tiap request; instance baru akan lazily
     * connect pada query pertama sehingga tidak mewarisi koneksi yang ditutup.
     */
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
            status_service INT NOT NULL DEFAULT 0,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            created_at DATETIME NULL,
            tanggal_selesai DATE NULL,
            tanggal_bisa_diambil DATE NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_service_dp (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service_idservice INT UNSIGNED NOT NULL,
            kode_dp VARCHAR(64) NULL,
            jumlah DOUBLE NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT "aktif",
            PRIMARY KEY (id)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_jurnal (
            idjurnal INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_referensi VARCHAR(255) NULL,
            tabel_referensi VARCHAR(255) NULL,
            PRIMARY KEY (idjurnal)
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

        // Tabel pendukung query notifikasi BaseController (lihat ModelService:
        // ProsesServiceAktif / ServiceBisaDiambil / getExpiredService dan
        // ModelAuth::getByIdWithJabatan). `stok_barang` dipakai sebagai format
        // tabel biasa (di produksi berupa VIEW) karena query getStokMinimum
        // hanya menyentuh kolom id_unit / stok_akhir / stok_minimum.
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

        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Akun Tes')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin Tes', NULL)");
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

    public function testCancelServiceTanpaDpMenghapusSepertiBiasa(): void
    {
        $this->sesi();

        $this->db->table('service')->insert([
            'no_service'    => 'SRV-TANPA-DP',
            'status_service' => 1,
        ]);
        $id = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $id, 'Seeder service harus berhasil.');

        $this->db->table('service_kerusakan')->insert(['service_idservice' => $id]);
        $this->db->table('service_sparepart')->insert(['service_idservice' => $id]);
        $this->db->table('service_dp')->delete(['service_idservice' => $id]);

        $r = $this->get('service/cancel/' . $id);

        $r->assertStatus(302);

        $db = $this->koneksiFresh();
        $this->assertSame(0, (int) $db->table('service')->countAllResults(), 'Service tanpa DP harus tetap dibatalkan seperti biasa.');
        $this->assertSame(0, (int) $db->table('service_kerusakan')->countAllResults());
        $this->assertSame(0, (int) $db->table('service_sparepart')->countAllResults());
        $this->assertSame(0, (int) $db->table('service_dp')->countAllResults(), 'Tidak ada sisa ledger DP.');
    }

    public function testCancelServiceBerDpMenghapusServiceDanLedgerDp(): void
    {
        $this->sesi();

        $this->db->table('service')->insert([
            'no_service'    => 'SRV-BER-DP',
            'status_service' => 1,
        ]);
        $id = (int) $this->db->insertID();
        $this->assertGreaterThan(0, $id, 'Seeder service harus berhasil.');

        $this->db->table('service_kerusakan')->insert(['service_idservice' => $id]);
        $this->db->table('service_sparepart')->insert(['service_idservice' => $id]);
        $this->db->table('service_dp')->insert([
            'service_idservice' => $id,
            'kode_dp'           => 'DP-SRV-BER-DP',
            'jumlah'            => 100000,
            'status'            => 'aktif',
        ]);
        $dpId = (int) $this->db->insertID();
        $this->db->table('jurnal')->insert([
            'id_referensi'    => (string) $dpId,
            'tabel_referensi' => 'service_dp',
        ]);
        // Jurnal DP milik service lain — TIDAK boleh ikut terhapus.
        $this->db->table('jurnal')->insert([
            'id_referensi'    => '999',
            'tabel_referensi' => 'service_dp',
        ]);

        $r = $this->get('service/cancel/' . $id);

        $r->assertStatus(302);

        $db = $this->koneksiFresh();
        $this->assertSame(0, (int) $db->table('service')->countAllResults(), 'Service ber-DP harus terhapus.');
        $this->assertSame(0, (int) $db->table('service_kerusakan')->countAllResults(), 'Kerusakan harus terhapus.');
        $this->assertSame(0, (int) $db->table('service_sparepart')->countAllResults(), 'Sparepart harus terhapus.');
        $this->assertSame(0, (int) $db->table('service_dp')->countAllResults(), 'Ledger DP harus terhapus (tidak yatim).');
        $this->assertSame(
            0,
            (int) $db->table('jurnal')->where('tabel_referensi', 'service_dp')->where('id_referensi', (string) $dpId)->countAllResults(),
            'Jurnal DP milik service ini harus terhapus.'
        );
        $this->assertSame(
            1,
            (int) $db->table('jurnal')->where('tabel_referensi', 'service_dp')->where('id_referensi', '999')->countAllResults(),
            'Jurnal DP service lain tidak boleh ikut terhapus.'
        );
    }
}