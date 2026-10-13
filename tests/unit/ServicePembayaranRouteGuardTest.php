<?php

namespace Tests\unit;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Guard jalur pembayaran service NET vs GROSS.
 *
 * Handler `Riwayat_Service::insert_pembayaran` menulis `harus_dibayar` NET
 * (`total - dp_bayar`), sedangkan alur UI yang aktif memakai handler GROSS
 * `Service::insert_pembayaran` (route `insert/service/savePembayaran`).
 * Integrasi DP di TutupKasir (`dpServiceSelesai()`) mengasumsikan `harus_dibayar`
 * GROSS, jadi jalur NET harus tetap nonaktif agar DP tidak dihitung dua kali.
 *
 * Test memakai DDL/DML nyata di group `tests` (prefix `db_`), mengikuti pola
 * ServiceCancelDpTest: verifikasi lewat koneksi fresh karena AuthFilter menutup
 * koneksi shared setelah tiap request.
 *
 * @internal
 */
final class ServicePembayaranRouteGuardTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const URI_NET     = 'update_insert/service/savePembayaran';
    private const URI_GROSS   = 'insert/service/savePembayaran';
    private const URI_GARANSI = 'update_service_garansi/savePembayaran';

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect('tests', false);
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('tests', false);

        foreach (['db_service', 'db_pembayaran_bank', 'db_pelanggan', 'db_unit', 'db_stok_barang', 'db_akun', 'db_jabatan'] as $tabel) {
            $db->query("DROP TABLE IF EXISTS {$tabel}");
        }

        parent::tearDown();
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
     * Tabel yang dipakai handler `Service::insert_pembayaran` + query notifikasi
     * `BaseController::initController` (stok minimum, proses/rekap service,
     * akun aktif). Status service seed dibuat NULL agar tidak masuk filter
     * notifikasi (`status IN (1,2) / (3)`) sehingga join janji tidak digejar.
     */
    private function buatTabelPembayaran(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE db_service (
            idservice INT UNSIGNED NOT NULL AUTO_INCREMENT,
            no_service VARCHAR(64) NULL,
            status_service INT NULL,
            status_proses INT NULL,
            prioritas INT NULL,
            unit_idunit INT NULL,
            service_by INT NULL,
            tipe_hp VARCHAR(50) NULL,
            no_hp VARCHAR(30) NULL,
            pelanggan_id_pelanggan INT NULL,
            created_at DATETIME NULL,
            tanggal_selesai DATE NULL,
            tanggal_bisa_diambil DATE NULL,
            total_service BIGINT NULL,
            total_diskon BIGINT NULL,
            total_diskon_garansi BIGINT NULL,
            total_service_garansi BIGINT NULL,
            biaya_tambahan_garansi BIGINT NULL,
            harga_penjualan_garansi BIGINT NULL,
            harus_dibayar BIGINT NULL,
            bayar BIGINT NULL,
            bayar_tunai BIGINT NULL,
            bayar_bank VARCHAR(64) NULL,
            PRIMARY KEY (idservice)
        ) ENGINE=InnoDB');

        $q('CREATE TABLE db_pembayaran_bank (
            idpembayaran_bank INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kode_pembayaran VARCHAR(64) NULL,
            bank_idbank INT NULL,
            jumlah BIGINT NULL,
            tabel_referensi VARCHAR(64) NULL,
            id_referensi INT NULL,
            PRIMARY KEY (idpembayaran_bank)
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

        $q("INSERT INTO db_akun (ID_AKUN, ID_JABATAN, ID_UNIT, NAMA_AKUN) VALUES (43, 1, 1, 'Akun Tes')");
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (1, 'Admin Tes', NULL)");
    }

    public function testRouteNetTidakTerdaftarPadaRouteCollection(): void
    {
        $routes = service('routes');
        $postRoutes = array_keys($routes->getRoutes('post'));

        $this->assertNotContains(self::URI_NET, $postRoutes, 'Route NET harus nonaktif (tidak boleh bisa dipanggil).');
        $this->assertContains(self::URI_GROSS, $postRoutes, 'Route GROSS harus tetap terdaftar.');
        $this->assertContains(self::URI_GARANSI, $postRoutes, 'Route pembayaran garansi harus tetap terdaftar.');
    }

    public function testPostKeRouteNetMenghasilkan404(): void
    {
        $this->sesi();

        // Route NET sudah dihapus; panggilan POST apa pun harus "route not
        // found" (autoRoute=false). Handler `Riwayat_Service::insert_pembayaran`
        // tidak pernah dieksekusi sehingga tidak ada tulisan `harus_dibayar` NET.
        $this->expectException(PageNotFoundException::class);
        $this->post(self::URI_NET, [
            'idservice_p'            => 1,
            'total_harga_pembayaran' => '80000',
            'bayar_pembayaran'       => '50000',
        ]);
    }

    public function testPostKeRouteGrossTetapMenulisPaymentGross(): void
    {
        $this->sesi();
        $this->buatTabelPembayaran();

        $this->db->table('service')->insert([
            'idservice'     => 1,
            'no_service'    => 'SRV-GROSS',
            'harus_dibayar' => 1000000,
            'bayar_tunai'   => 0,
            'bayar'         => 0,
        ]);

        $r = $this->post(self::URI_GROSS, [
            'idservice_p'            => 1,
            'total_harga_pembayaran' => '1000000',
            'bayar_pembayaran'       => '750000',
            'bank'                   => [['id' => 3, 'jumlah' => '250000']],
        ]);

        $r->assertStatus(302);

        $db = \Config\Database::connect('tests', false);
        $row = $db->table('service')->where('idservice', 1)->get()->getRow();
        $this->assertSame(1000000, (int) $row->harus_dibayar, 'harus_dibayar GROSS (termasuk DP).');
        $this->assertSame(750000, (int) $row->bayar_tunai, 'bayar_tunai tersimpan.');
        $this->assertSame(1000000, (int) $row->bayar, 'bayar = tunai + bank.');
        $this->assertSame(
            1,
            (int) $db->table('pembayaran_bank')
                ->where('tabel_referensi', 'service_baru')
                ->where('id_referensi', 1)
                ->countAllResults(),
            'Baris pembayaran bank tersimpan.'
        );
    }
}