<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

/**
 * Filter unit /sparepart_keluar.
 *
 * Role toko terkunci ke unit sesi — juga di endpoint ajax, bukan cuma dropdown.
 * Hanya Admin Root, Manager, Finance, dan SPV yang boleh memilih unit, dan
 * pilihannya dibatasi unit operasional (Head Office tidak masuk).
 */
final class SparepartKeluarUnitFilterTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const TABEL = [
        'unit', 'akun', 'jabatan', 'stok_barang', 'service', 'pelanggan',
        'penjualan', 'detail_penjualan', 'barang', 'service_sparepart',
    ];

    /** @var \CodeIgniter\Database\BaseConnection */
    protected $db;

    private string $prefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect('tests', false);
        $this->prefix = (string) $this->db->DBPrefix;

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $db = Database::connect('tests', false);
        foreach (self::TABEL as $tabel) {
            $db->query('DROP TABLE IF EXISTS ' . $this->prefix . $tabel);
        }

        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);
        $t = fn (string $nama) => $this->prefix . $nama;

        foreach (self::TABEL as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $t($tabel));
        }

        $q("CREATE TABLE {$t('unit')} (
            idunit INT PRIMARY KEY,
            NAMA_UNIT VARCHAR(150) NULL,
            LOGO VARCHAR(255) NULL,
            is_operational TINYINT NOT NULL DEFAULT 1
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('akun')} (
            ID_AKUN INT PRIMARY KEY,
            ID_UNIT INT NULL,
            ID_JABATAN INT NULL,
            NAMA_AKUN VARCHAR(100) NULL,
            ROLES TEXT NULL
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('jabatan')} (
            ID_JABATAN INT PRIMARY KEY,
            NAMA_JABATAN VARCHAR(100) NULL,
            ROLES_JABATAN TEXT NULL
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('stok_barang')} (
            idbarang INT PRIMARY KEY,
            id_unit INT NULL,
            stok_akhir DOUBLE NULL,
            stok_minimum DOUBLE NULL,
            nama_barang VARCHAR(150) NULL,
            kode_barang VARCHAR(50) NULL,
            total_penjualan DOUBLE NULL
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('service')} (
            idservice INT PRIMARY KEY,
            no_service VARCHAR(64) NULL,
            status_service INT NULL,
            status_proses INT NULL,
            pelanggan_id_pelanggan INT NULL,
            unit_idunit INT NULL,
            no_hp VARCHAR(32) NULL,
            alamat TEXT NULL,
            keluhan TEXT NULL,
            keterangan TEXT NULL,
            tipe_hp VARCHAR(64) NULL,
            imei VARCHAR(64) NULL,
            prioritas INT NULL,
            service_by INT NULL,
            garansi_hari INT NULL,
            tanggal_selesai DATETIME NULL,
            tanggal_bisa_diambil DATE NULL,
            tanggal_claim_garansi DATETIME NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('pelanggan')} (
            id_pelanggan INT PRIMARY KEY,
            nama VARCHAR(150) NULL,
            kabupaten VARCHAR(100) NULL,
            kecamatan VARCHAR(100) NULL,
            deleted INT NULL
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('penjualan')} (
            idpenjualan INT PRIMARY KEY AUTO_INCREMENT,
            kode_invoice VARCHAR(64) NULL,
            tanggal DATETIME NULL,
            unit_idunit INT NULL,
            id_pelanggan INT NULL
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('detail_penjualan')} (
            iddetail_penjualan INT PRIMARY KEY AUTO_INCREMENT,
            penjualan_idpenjualan INT NULL,
            barang_idbarang INT NULL,
            jumlah INT NULL DEFAULT 1,
            harga_penjualan DOUBLE NULL DEFAULT 0,
            diskon_penjualan DOUBLE NULL DEFAULT 0,
            sub_total DOUBLE NULL DEFAULT 0,
            hpp_penjualan DOUBLE NULL DEFAULT 0
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('barang')} (
            idbarang INT PRIMARY KEY,
            nama_barang VARCHAR(150) NULL
        ) ENGINE=InnoDB");

        $q("CREATE TABLE {$t('service_sparepart')} (
            idservice_sparepart INT PRIMARY KEY AUTO_INCREMENT,
            service_idservice INT NULL,
            barang_idbarang INT NULL,
            unit_idunit INT NULL,
            jumlah_tambahan_garansi INT NULL DEFAULT 0,
            sub_total_garansi DOUBLE NULL DEFAULT 0,
            hpp_penjualan_garansi DOUBLE NULL DEFAULT 0
        ) ENGINE=InnoDB");
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);
        $t = fn (string $nama) => $this->prefix . $nama;
        $tahun = date('Y');

        $q("INSERT INTO {$t('unit')} (idunit, NAMA_UNIT, LOGO, is_operational) VALUES
            (1, 'Unit Alpha', 'logo.png', 1),
            (2, 'Unit Beta', 'logo.png', 1),
            (50, 'Head Office', 'logo.png', 0)");

        $q("INSERT INTO {$t('jabatan')} (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (0, 'Finance', '[]'), (1, 'Admin root', '[]'), (2, 'Direktur', '[]'),
            (34, 'Manager', '[]'), (35, 'ADMIN / KASIR', '[]'), (40, 'SPV', '[]'),
            (41, 'Kepala Toko', '[]')");

        $q("INSERT INTO {$t('akun')} (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES
            (1, 50, 40, 'Tester')");

        $q("INSERT INTO {$t('barang')} (idbarang, nama_barang) VALUES (1, 'LCD')");

        $q("INSERT INTO {$t('penjualan')} (idpenjualan, kode_invoice, tanggal, unit_idunit) VALUES
            (10, 'INV-ALPHA', '{$tahun}-02-10 09:00:00', 1),
            (20, 'INV-BETA', '{$tahun}-02-11 09:00:00', 2)");

        $q("INSERT INTO {$t('detail_penjualan')}
            (penjualan_idpenjualan, barang_idbarang, jumlah, harga_penjualan, diskon_penjualan, sub_total, hpp_penjualan) VALUES
            (10, 1, 1, 100000, 0, 100000, 60000),
            (20, 1, 1, 100000, 0, 100000, 60000)");
    }

    /**
     * @return list<string> kode invoice yang dikembalikan endpoint ajax
     */
    private function ajax(array $post, int $unit, int $jabatan): array
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => 1,
            'ID_UNIT'      => $unit,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);

        $response = $this->post('sparepart_keluar/ajax', array_merge([
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
            'search' => ['value' => ''],
            'day'    => '',
        ], $post));

        $body = (string) $response->response()->getBody();
        $json = json_decode($body, true);
        $this->assertIsArray($json, 'Respons ajax bukan JSON: ' . $body);

        return array_column($json['data'], 'kode_invoice');
    }

    public function testRoleTokoTerkunciKeUnitSesiWalauMemintaUnitLain(): void
    {
        // Kasir, Kepala Toko, dan Direktur tidak termasuk role lintas unit.
        foreach ([35, 41, 2] as $role) {
            $this->assertSame(
                ['INV-BETA'],
                $this->ajax(['unit' => 1], 2, $role),
                'Role ' . $role . ' tidak boleh melihat unit lain lewat ajax'
            );
        }
    }

    public function testAdminManagerFinanceSpvBolehMemilihUnit(): void
    {
        foreach ([0, 1, 34, 40] as $role) {
            $this->assertSame(['INV-ALPHA'], $this->ajax(['unit' => 1], 50, $role), 'Role ' . $role . ' unit 1');
            $this->assertSame(['INV-BETA'], $this->ajax(['unit' => 2], 50, $role), 'Role ' . $role . ' unit 2');
        }
    }

    public function testUnitNonOperasionalTidakBisaDipilih(): void
    {
        // Head Office (50) bukan unit operasional -> jatuh ke unit operasional pertama.
        $this->assertSame(['INV-ALPHA'], $this->ajax(['unit' => 50], 50, 40));
    }

    public function testDropdownTerkunciUntukRoleToko(): void
    {
        $html = view('riwayat/sparepart_keluar', [
            'list_unit'         => [['idunit' => 2, 'NAMA_UNIT' => 'Unit Beta']],
            'selected_unit'     => 2,
            'selected_day'      => '',
            'can_see_all_units' => false,
            'is_admin'          => false,
        ]);

        $this->assertStringContainsString('id="unitFilter"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('Unit Beta', $html);
        $this->assertStringNotContainsString('Unit Alpha', $html);
    }

    public function testDropdownTerbukaUntukRoleLintasUnit(): void
    {
        $html = view('riwayat/sparepart_keluar', [
            'list_unit'         => [
                ['idunit' => 1, 'NAMA_UNIT' => 'Unit Alpha'],
                ['idunit' => 2, 'NAMA_UNIT' => 'Unit Beta'],
            ],
            'selected_unit'     => 1,
            'selected_day'      => '',
            'can_see_all_units' => true,
            'is_admin'          => false,
        ]);

        $this->assertStringNotContainsString('disabled', $html);
        $this->assertStringContainsString('<option value="1" selected>', $html);
        $this->assertStringContainsString('<option value="2"', $html);
    }
}
