<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\ModelDetailPembelian;

/**
 * Regresi rincian barang pada /daftar_tagihan.
 *
 * Halaman ini dulu hanya menampilkan header nota, sehingga kasir tidak tahu
 * pembayaran itu untuk sparepart apa. Test ini mengunci jalur penuh:
 * controller mengambil detail sekali untuk semua nota, view menampilkan
 * ringkasan per baris, dan modal bayar mendapat rincian lewat template.
 */
final class DaftarTagihanRincianTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    private const TABEL = [
        'unit', 'jabatan', 'akun', 'menu', 'service', 'pelanggan',
        'bank', 'akun_kas_bank', 'alokasi_saldo_kas_bank', 'no_akun',
        'stok_barang', 'barang', 'pembelian', 'detail_pembelian',
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
        // Sidebar & BaseController memakai menu dan service.
        $q('CREATE TABLE menu (idmenu INT PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL, show_menu INT NULL,
                sub INT NULL, parent INT NULL, utama INT NULL, categories INT NULL,
                icon TEXT NULL, manualbook TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, no_hp TEXT NULL,
                deleted TEXT NULL)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL, stok_akhir REAL NULL,
                stok_minimum REAL NULL, aktif_unit INT NULL)');

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

        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                deleted INT NULL)');
        $q('CREATE TABLE pembelian (idpembelian INT PRIMARY KEY, no_nota_supplier TEXT NULL,
                unit_idunit INT NULL, suplier_id_suplier INT NULL, tanggal_masuk TEXT NULL,
                jatuh_tempo TEXT NULL, sisa REAL NULL, total_bayar REAL NULL,
                total_transaksi REAL NULL, status TEXT NULL)');
        $q('CREATE TABLE detail_pembelian (iddetail_pembelian INT PRIMARY KEY AUTO_INCREMENT,
                pembelian_idpembelian INT NULL, barang_idbarang INT NULL,
                jumlah REAL NULL, hrg_beli REAL NULL, diskon REAL NULL,
                total_harga REAL NULL, satuan_beli TEXT NULL, unit_idunit INT NULL)');
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q("INSERT INTO unit (idunit, NAMA_UNIT, is_operational)
            VALUES (1, 'ICLEAR Jember', 1)");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN)
            VALUES (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES, STATUS_PEGAWAI)
            VALUES (43, 1, 35, 'Kasir Jember', '[]', 1)");
        $q("INSERT INTO pelanggan (id_pelanggan, nama, no_hp, deleted)
            VALUES (1, 'Pelanggan', '0812', '0')");

        $q("INSERT INTO bank (idbank, nama_bank, atas_nama, norek)
            VALUES ('BCA-1', 'BCA', 'Toko Jember', '111')");
        $q("INSERT INTO akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank,
                status, is_shared, is_finance_ho)
            VALUES (11, 1, 'BANK', 'Rekening Jember', 'BCA-1', 'aktif', 0, 0)");

        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, deleted) VALUES
            (1, 'P01', 'LCD iPhone 11', 0),
            (2, 'P02', 'Baterai iPhone 11', 0),
            (3, 'P03', 'Konektor Charger', 0)");

        // Nota 11: 3 barang. Nota 12: legacy tanpa detail_pembelian.
        $q("INSERT INTO pembelian (idpembelian, no_nota_supplier, unit_idunit, suplier_id_suplier,
                tanggal_masuk, jatuh_tempo, sisa, total_bayar, total_transaksi, status)
            VALUES
            (11, 'PCR1001', 1, 1, '2026-10-01', '2026-11-01', 750000, 0, 750000, 'Belum Lunas'),
            (12, 'PCR1002', 1, 1, '2026-10-02', '2026-11-02', 300000, 0, 300000, 'Belum Lunas')");

        $q("INSERT INTO detail_pembelian (pembelian_idpembelian, barang_idbarang, jumlah,
                hrg_beli, diskon, total_harga, satuan_beli, unit_idunit) VALUES
            (11, 1, 2, 100000, 0, 200000, 'Pcs', 1),
            (11, 2, 3, 50000, 5000, 0, 'Pcs', 1),
            (11, 3, 1, 75000, 0, 75000, 'Pcs', 1)");
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => 43,
            'ID_UNIT'      => 1,
            'ID_JABATAN'   => 35,
            'NAMA'         => 'Kasir Jember',
            'NAMA_JABATAN' => 'ADMIN / KASIR',
            'EMAIL'        => 'kasir@example.com',
        ]);
    }

    private function halaman(): string
    {
        $this->db->setPrefix('');

        // getBody() pada TestResponse melewati DOMParser (mengubah entitas);
        // response()->getBody() memberi HTML asli yang dikirim controller.
        return (string) $this->get('daftar_tagihan')->response()->getBody();
    }

    public function testModelMengambilDetailHanyaUntukNotaYangDiminta(): void
    {
        $this->db->setPrefix('');
        $rows = (new ModelDetailPembelian())->getRingkasanPembelian([11]);

        $this->assertCount(3, $rows);
        $this->assertSame('LCD iPhone 11', $rows[0]->nama_barang);
        $this->assertSame('P01', $rows[0]->kode_barang);
        $this->assertSame('Konektor Charger', $rows[2]->nama_barang, 'Urutan mengikuti iddetail_pembelian');

        $this->assertSame([], (new ModelDetailPembelian())->getRingkasanPembelian([]));
        $this->assertSame([], (new ModelDetailPembelian())->getRingkasanPembelian([0, -1]));
    }

    public function testHalamanMenampilkanRingkasanBarisDanRincianModal(): void
    {
        $this->sesi();
        $html = $this->halaman();

        // Ringkasan baris untuk nota 11 dan fallback untuk nota 12.
        $this->assertStringContainsString('3 barang · LCD iPhone 11, Baterai iPhone 11 +1', $html);
        $this->assertStringContainsString('Rincian barang tidak tersedia', $html);

        // Template rincian: hanya nota yang punya detail.
        $this->assertStringContainsString('data-items-for="11"', $html);
        $this->assertStringNotContainsString('data-items-for="12"', $html);

        // Rincian lengkap untuk modal bayar.
        $this->assertStringContainsString('id="dt-pay-items"', $html);
        $this->assertStringContainsString('LCD iPhone 11', $html);
        $this->assertStringContainsString('P02', $html);
        $this->assertStringContainsString('3 Pcs', $html);
        $this->assertStringContainsString('× Rp 50.000', $html);
        $this->assertStringContainsString('Diskon Rp 5.000', $html);
        $this->assertStringContainsString('Rp 145.000', $html, 'Subtotal legacy total_harga=0 dihitung ulang');

        // Alur bayar tidak berubah.
        $this->assertStringContainsString('data-sisa="750000"', $html);
        $this->assertStringContainsString('name="bank_idbank"', $html);
    }

    public function testPencarianIkutMencocokkanNamaBarang(): void
    {
        $this->sesi();
        $html = $this->halaman();

        $this->assertMatchesRegularExpression(
            '/data-search="[^"]*lcd iphone 11[^"]*"/',
            $html,
            'Nama barang harus ikut di data-search baris tagihan'
        );
        $this->assertStringContainsString('Cari nota, unit, atau barang', $html);
    }

    public function testTanpaTagihanTidakAdaRingkasanBarang(): void
    {
        $this->db->setPrefix('');
        $this->db->query('DELETE FROM detail_pembelian');
        $this->db->query('DELETE FROM pembelian');

        $this->sesi();
        $html = $this->halaman();

        $this->assertStringContainsString('Tidak ada tagihan terbuka', $html);
        $this->assertStringNotContainsString('id="dt-items-store"', $html);
        $this->assertStringNotContainsString('class="dt-doc__items"', $html);
    }
}
