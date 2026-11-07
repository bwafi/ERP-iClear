<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Regresi filter unit di /dp_service.
 *
 * Jabatan Customer Service (42) harus bisa memilih unit seperti daftar riwayat
 * service (Riwayat_Service::index/riwayat_service_ajax); role lain tetap
 * terkunci ke ID_UNIT sesi. Cakupan data diuji lewat endpoint DataTables dan
 * status select di halaman.
 */
class DpServiceUnitFilterTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'unit', 'akun', 'jabatan', 'menu', 'stok_barang', 'service',
            'pelanggan', 'service_dp', 'bank', 'penjualan', 'service_sparepart',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        // Template + BaseController (notifikasi stok/service/expired).
        $q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');
        $q('CREATE TABLE stok_barang (
                idbarang INT PRIMARY KEY AUTO_INCREMENT, id_unit INT NULL,
                stok_akhir REAL NULL, stok_minimum REAL NULL,
                nama_barang TEXT NULL, kode_barang TEXT NULL)');
        $q('CREATE TABLE service (
                idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                no_service TEXT NULL, tipe_hp TEXT NULL, created_at TEXT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                service_by INT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');

        // Tabel yang dibaca DataTables DP service.
        $q('CREATE TABLE service_dp (
                id INT PRIMARY KEY AUTO_INCREMENT, service_idservice INT NULL,
                kode_dp TEXT NULL, tanggal TEXT NULL, jumlah REAL NULL,
                metode TEXT NULL, bank_idbank TEXT NULL, referensi TEXT NULL,
                status TEXT NULL, keterangan TEXT NULL, unit_id INT NULL)');
        $q('CREATE TABLE bank (idbank INT PRIMARY KEY, nama_bank TEXT NULL)');
        $q('CREATE TABLE penjualan (idpenjualan INT PRIMARY KEY)');
        $q('CREATE TABLE service_sparepart (idservice_sparepart INT PRIMARY KEY, service_idservice INT NULL)');
    }

    private function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LOGO) VALUES
            (1, 'ICLEAR Probolinggo', 'logo.png'),
            (2, 'ICLEAR Jember', 'logo.png')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (35, 'ADMIN / KASIR', '[]'), (42, 'Customer Service', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (350, 1, 35, 'Kasir Probolinggo', NULL),
            (420, 1, 42, 'CS Probolinggo', NULL)");
        $q("INSERT INTO pelanggan (id_pelanggan, nama) VALUES (1, 'Pelanggan A')");
        $q("INSERT INTO service
            (idservice, status_service, pelanggan_id_pelanggan, unit_idunit, no_service, tipe_hp, created_at, tanggal_selesai)
            VALUES
            (1, 5, 1, 1, 'SRV-UNIT1', 'iPhone 11', '2026-10-01 09:00:00', '2026-10-01 10:00:00'),
            (2, 5, 1, 2, 'SRV-UNIT2', 'iPhone 12', '2026-10-02 09:00:00', '2026-10-02 10:00:00')");
        $q("INSERT INTO service_dp
            (id, service_idservice, kode_dp, tanggal, jumlah, metode, status)
            VALUES
            (1, 1, 'DP-UNIT1', '2026-10-01', 100000, 'tunai', 'aktif'),
            (2, 2, 'DP-UNIT2', '2026-10-02', 200000, 'tunai', 'aktif')");
    }

    private function sesi(int $akun, int $unit, int $jabatan): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => $akun,
            'ID_UNIT'      => $unit,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    /**
     * @return array{recordsTotal:int, recordsFiltered:int, data:array<int,array<string,mixed>>}
     */
    private function ajaxDp(int $unitFilter): array
    {
        $this->db->setPrefix('');
        $result = $this->post('dp_service/ajax', [
            'draw'       => 1,
            'start'      => 0,
            'length'     => 10,
            'unitFilter' => $unitFilter,
            'search'     => ['value' => ''],
        ]);

        return json_decode((string) $result->response()->getBody(), true);
    }

    public function testCustomerServiceBisaMemfilterUnitLainLewatAjax(): void
    {
        $this->sesi(420, 1, 42);

        $body = $this->ajaxDp(2);

        $this->assertCount(1, $body['data']);
        $this->assertSame('DP-UNIT2', $body['data'][0]['kode_dp']);
    }

    public function testRoleLainTetapTerkunciUnitSesi(): void
    {
        $this->sesi(350, 1, 35);

        $body = $this->ajaxDp(2);

        $this->assertCount(1, $body['data'], 'Kasir tidak boleh melihat DP unit lain');
        $this->assertSame('DP-UNIT1', $body['data'][0]['kode_dp']);
    }

    public function testSelectUnitAktifUntukCustomerService(): void
    {
        $this->sesi(420, 1, 42);

        $this->db->setPrefix('');
        $html = (string) $this->get('dp_service')->getBody();

        $this->assertMatchesRegularExpression('/<select[^>]*id="dpUnitFilter"[^>]*>/', $html);
        preg_match('/<select[^>]*id="dpUnitFilter"[^>]*>/', $html, $m);
        $this->assertStringNotContainsString('disabled', $m[0]);
        $this->assertStringContainsString('Semua Unit', $html);
    }

    public function testSelectUnitTerkunciUntukRoleLain(): void
    {
        $this->sesi(350, 1, 35);

        $this->db->setPrefix('');
        $html = (string) $this->get('dp_service')->getBody();

        preg_match('/<select[^>]*id="dpUnitFilter"[^>]*>/', $html, $m);
        $this->assertStringContainsString('disabled', $m[0]);
    }
}
