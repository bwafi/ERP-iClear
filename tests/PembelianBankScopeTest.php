<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Dropdown "Pembayaran via Bank" di /pembelian hanya boleh menampilkan
 * rekening dalam scope unit halaman.
 *
 * Aturan yang dikunci:
 *   - rekening privat milik unit        -> tampil
 *   - rekening shared yang dialokasikan -> tampil
 *   - rekening unit lain                -> TIDAK tampil
 *   - rekening Finance/HO               -> TIDAK tampil
 *   - rekening nonaktif                 -> TIDAK tampil
 *   - unit tanpa rekening aktif         -> catatan kosong tampil
 *
 * Sumber scope tetap ModelBank::getBankDalamScopeUnit(); controller hanya
 * meneruskan unit halaman yang sudah di-resolve.
 */
final class PembelianBankScopeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    private const TABEL = [
        'unit', 'jabatan', 'akun', 'kategori', 'barang', 'stok_barang',
        'harga_jual_unit', 'hpp_unit', 'hpp_barang', 'suplier', 'pelanggan',
        'nama_handphone', 'sub_kategori', 'bank', 'akun_kas_bank',
        'alokasi_saldo_kas_bank', 'no_akun', 'service', 'menu',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->prefixAsli = (string) $this->db->DBPrefix;
        $this->db->setPrefix('');

        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->buatSkema();
        $this->sebarData();
    }

    protected function tearDown(): void
    {
        foreach (self::TABEL as $tabel) {
            $this->db->query('DROP TABLE IF EXISTS ' . $tabel);
        }

        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL,
                is_operational INT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, ROLES TEXT NULL, STATUS_PEGAWAI INT NULL)');
        $q('CREATE TABLE kategori (id INT PRIMARY KEY, nama_kategori TEXT NULL, `delete` INT NULL)');
        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL, status_barang INT NULL,
                status_ppn INT NULL, deleted INT NULL, jenis_barang TEXT NULL,
                input TEXT NULL, imei TEXT NULL, internal TEXT NULL, warna TEXT NULL)');
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL, stok_akhir REAL NULL,
                stok_minimum REAL NULL, aktif_unit INT NULL)');
        $q('CREATE TABLE harga_jual_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                harga_jual DECIMAL(15,2) NOT NULL)');
        $q('CREATE TABLE hpp_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                hpp REAL NULL, sumber TEXT NULL)');
        $q('CREATE TABLE hpp_barang (idbarang INT PRIMARY KEY, hpp REAL NULL)');
        $q('CREATE TABLE suplier (id_suplier INT PRIMARY KEY, nama_suplier TEXT NULL,
                alamat TEXT NULL, no_hp TEXT NULL, deleted TEXT NULL, unit_idunit INT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, no_hp TEXT NULL,
                deleted TEXT NULL)');
        $q('CREATE TABLE nama_handphone (id INT PRIMARY KEY AUTO_INCREMENT, nama_handphone TEXT NULL)');
        $q('CREATE TABLE sub_kategori (id_sub_kategori INT PRIMARY KEY AUTO_INCREMENT,
                nama_sub_kategori TEXT NULL, `delete` INT NULL)');
        $q('CREATE TABLE bank (idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL,
                atas_nama TEXT NULL, norek TEXT NULL)');
        $q('CREATE TABLE akun_kas_bank (idakun_kas_bank INT PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL, tipe VARCHAR(10) NULL, nama_akun TEXT NULL,
                bank_idbank VARCHAR(20) NULL, no_akun_coa VARCHAR(255) NULL,
                status VARCHAR(10) NULL, is_shared INT NULL, is_finance_ho INT NULL,
                created_by INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE alokasi_saldo_kas_bank (id INT PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal INT NULL,
                keterangan TEXT NULL)');
        $q('CREATE TABLE no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        // BaseController memuat notifikasi service.
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL)');
        $q('CREATE TABLE menu (idmenu INT PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL, show_menu INT NULL,
                sub INT NULL, parent INT NULL, utama INT NULL, categories INT NULL,
                icon TEXT NULL, manualbook TEXT NULL)');
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q("INSERT INTO unit (idunit, NAMA_UNIT, is_operational)
            VALUES (1, 'Unit Satu', 1), (2, 'Unit Dua', 1), (3, 'Unit Tiga', 1)");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN)
            VALUES (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES, STATUS_PEGAWAI)
            VALUES (43, 1, 35, 'Kasir Satu', '[]', 1), (44, 2, 35, 'Kasir Dua', '[]', 1),
            (45, 3, 35, 'Kasir Tiga', '[]', 1)");

        $q("INSERT INTO kategori (id, nama_kategori, `delete`) VALUES (3, 'Sparepart', 0)");
        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli, idkategori,
                status_barang, status_ppn, deleted, jenis_barang)
            VALUES (1, 'P01', 'LCD iPhone 11', 150000, 100000, 3, 0, 0, 0, 'produk')");
        $q("INSERT INTO stok_barang (idbarang, id_unit, stok_akhir, stok_minimum, aktif_unit)
            VALUES (1, 1, 5, 0, 1)");
        $q("INSERT INTO harga_jual_unit (barang_idbarang, unit_idunit, harga_jual) VALUES (1, 1, 175000)");
        $q("INSERT INTO hpp_unit (barang_idbarang, unit_idunit, hpp, sumber) VALUES (1, 1, 100000, 'manual')");
        $q("INSERT INTO hpp_barang (idbarang, hpp) VALUES (1, 100000)");
        $q("INSERT INTO suplier (id_suplier, nama_suplier, deleted, unit_idunit)
            VALUES (1, 'Supplier', '0', 1)");
        $q("INSERT INTO pelanggan (id_pelanggan, nama, no_hp, deleted) VALUES (1, 'Pelanggan', '0812', '0')");

        // Bank unit 1, bank unit 2, bank shared (alokasi unit 1), Finance/HO,
        // dan bank nonaktif unit 1.
        $q("INSERT INTO bank (idbank, nama_bank, atas_nama, norek) VALUES
            ('BCA-1', 'BANK UNIT SATU', 'Toko Satu', '111'),
            ('BCA-2', 'BANK UNIT DUA', 'Toko Dua', '222'),
            ('BCA-S', 'BANK SHARED SATU', 'Toko Bersama', '333'),
            ('BCA-F', 'BANK FINANCE HO', 'PT ICLEAR', '444'),
            ('BCA-X', 'BANK NONAKTIF', 'Toko Satu', '555')");
        $q("INSERT INTO akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank,
                status, is_shared, is_finance_ho) VALUES
            (11, 1, 'BANK', 'Rekening Unit Satu', 'BCA-1', 'aktif', 0, 0),
            (12, 2, 'BANK', 'Rekening Unit Dua', 'BCA-2', 'aktif', 0, 0),
            (13, NULL, 'BANK', 'Rekening Bersama', 'BCA-S', 'aktif', 1, 0),
            (14, NULL, 'BANK', 'Rekening Direksi', 'BCA-F', 'aktif', 1, 1),
            (15, 1, 'BANK', 'Rekening Nonaktif', 'BCA-X', 'nonaktif', 0, 0)");
        $q("INSERT INTO alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal)
            VALUES (13, 1, 0)");
    }

    private function sesi(int $akun, int $unit, int $jabatan = 35): void
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

    private function halaman(): string
    {
        $this->db->setPrefix('');

        return (string) $this->get('pembelian')->getBody();
    }

    public function testDropdownBankHanyaRekeningUnitDanShared(): void
    {
        $this->sesi(43, 1);
        $html = $this->halaman();

        $this->assertStringContainsString('BANK UNIT SATU', $html);
        $this->assertStringContainsString('BANK SHARED SATU', $html, 'Rekening shared yang dialokasikan ke unit harus tampil');

        $this->assertStringNotContainsString('BANK UNIT DUA', $html, 'Rekening unit lain tidak boleh tampil');
        $this->assertStringNotContainsString('BANK FINANCE HO', $html, 'Rekening Finance/HO tidak boleh tampil');
        $this->assertStringNotContainsString('BANK NONAKTIF', $html, 'Rekening nonaktif tidak boleh tampil');
    }

    public function testUnitTanpaRekeningMenampilkanCatatanKosong(): void
    {
        $this->sesi(45, 3);
        $html = $this->halaman();

        $this->assertStringContainsString('Belum ada rekening aktif untuk unit ini.', $html);
        $this->assertStringContainsString('pb-bank-note', $html);
        $this->assertStringNotContainsString('BANK UNIT SATU', $html);
        $this->assertStringNotContainsString('BANK SHARED SATU', $html);
    }
}
