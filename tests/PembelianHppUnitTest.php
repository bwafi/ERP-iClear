<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Pembelian adalah pintu stok masuk: HPP unit mengikuti harga beli terakhir,
 * hanya untuk unit pembelian, dan tidak mengubah master barang.
 */
final class PembelianHppUnitTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    private const TABEL = [
        'unit', 'jabatan', 'akun', 'pelanggan', 'suplier', 'barang',
        'stok_awal', 'hpp_barang', 'hpp_unit', 'stok_barang', 'service',
        'pembelian', 'detail_pembelian', 'pembayaran_bank', 'template_jurnal',
        'hutang_piutang', 'harga_jual_unit',
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

        $this->db->close();
        $this->db->setPrefix($this->prefixAsli);
        parent::tearDown();
    }

    private function buatSkema(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, STATUS_PEGAWAI INT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL, deleted TEXT NULL)');
        $q('CREATE TABLE suplier (id_suplier INT PRIMARY KEY, nama_suplier TEXT NULL)');
        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY, kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL, status_barang INT NULL,
                status_ppn INT NULL, deleted TEXT NULL, jenis_barang TEXT NULL)');
        $q('CREATE TABLE stok_awal (idstok_awal INT PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, jumlah REAL NULL, barang_idbarang INT NULL,
                harga_beli REAL NULL, satuan_terkecil TEXT NULL, unit_idunit INT NULL,
                suplier_id_suplier TEXT NULL, pelanggan_id_pelanggan INT NULL)');
        $q('CREATE TABLE hpp_barang (idbarang INT PRIMARY KEY, hpp REAL NULL)');
        $q('CREATE TABLE hpp_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                hpp REAL NULL, sumber TEXT NULL, updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE harga_jual_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                harga_jual DECIMAL(15,2) NOT NULL, updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL,
                stok_akhir REAL NULL, stok_minimum REAL NULL)');
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                tipe_hp TEXT NULL, created_at TEXT NULL)');

        $q('CREATE TABLE pembelian (idpembelian INT PRIMARY KEY AUTO_INCREMENT,
                no_nota_supplier TEXT NULL, tanggal_masuk DATETIME NULL, unit_idunit INT NULL,
                suplier_id_suplier INT NULL, pelanggan_id_pelanggan INT NULL,
                sisa INT NULL, status TEXT NULL, total_transaksi INT NULL,
                total_diskon INT NULL, total_ppn INT NULL, total_bayar INT NULL,
                bayar INT NULL, bayar_tunai INT NULL, bayar_bank INT NULL,
                bank_idbank TEXT NULL, jatuh_tempo TEXT NULL, frontliner TEXT NULL,
                foto_nota TEXT NULL, input_by INT NULL)');
        $q('CREATE TABLE detail_pembelian (iddetail_pembelian INT PRIMARY KEY AUTO_INCREMENT,
                no_batch TEXT NULL, tanggal DATETIME NULL, jumlah INT NULL, hrg_beli INT NULL,
                diskon INT NULL, ppn INT NULL, hitung_hpp INT NULL, total_harga INT NULL,
                satuan_beli TEXT NULL, barang_idbarang INT NULL, pembelian_idpembelian INT NULL,
                unit_idunit INT NULL, biaya_tambahan INT NULL, keterangan_tambahan TEXT NULL)');
        $q('CREATE TABLE pembayaran_bank (idpembayaran_bank INT PRIMARY KEY AUTO_INCREMENT,
                kode_pembayaran TEXT NULL, bank_idbank INT NULL, jumlah INT NULL,
                tabel_referensi TEXT NULL, id_referensi INT NULL)');
        $q('CREATE TABLE template_jurnal (idtemplate_jurnal INT PRIMARY KEY AUTO_INCREMENT,
                kode_template TEXT NULL, no_akun TEXT NULL, nama_akun TEXT NULL,
                array_value INT NULL, debet_kredit TEXT NULL)');
        $q('CREATE TABLE hutang_piutang (id INT PRIMARY KEY AUTO_INCREMENT,
                kode TEXT NULL, jenis TEXT NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                is_projection INT NULL, pihak_tipe TEXT NULL, pihak_id INT NULL,
                nama_pihak TEXT NULL, tanggal TEXT NULL, jatuh_tempo TEXT NULL,
                uraian TEXT NULL, total INT NULL, total_dibayar INT NULL, sisa INT NULL,
                status TEXT NULL, unit_id INT NULL, input_by INT NULL, deleted INT NULL,
                created_at DATETIME NULL, updated_at DATETIME NULL)');
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1, 'Unit Satu'), (2, 'Unit Dua')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI)
            VALUES (43, 1, 35, 'Kasir Satu', 1)");
        $q("INSERT INTO pelanggan (id_pelanggan, nama, deleted) VALUES (1, 'Pelanggan', '0')");
        $q("INSERT INTO suplier (id_suplier, nama_suplier) VALUES (1, 'Supplier')");
        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli,
                idkategori, status_barang, status_ppn, deleted, jenis_barang)
            VALUES (1, 'P01', 'LCD iPhone 11', 150000, 100000, 3, 0, 0, '0', 'produk')");
        $q("INSERT INTO hpp_barang (idbarang, hpp) VALUES (1, 100000)");
        $q("INSERT INTO hpp_unit (barang_idbarang, unit_idunit, hpp, sumber)
            VALUES (1, 1, 100000, 'manual'), (1, 2, 90000, 'manual')");
        $q("INSERT INTO stok_barang (idbarang, id_unit, stok_akhir, stok_minimum)
            VALUES (1, 1, 5, 0), (1, 2, 5, 0)");
    }

    private function sesi(int $jabatan = 35): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => 43,
            'ID_UNIT'      => 1,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function postPembelian(float $hargaBeli, int $jumlah, float $diskon = 0.0, float $biaya = 0.0, string $frontliner = '', string $hargaJual = '150000')
    {
        return $this->post('insert_pembelian', [
            'produk' => [
                1 => [
                    'id'             => '1',
                    'kode'           => 'P01',
                    'nama'           => 'LCD iPhone 11',
                    'kategori'       => 'Sparepart',
                    'harga'          => $hargaJual,
                    'harga_beli'     => (string) $hargaBeli,
                    'jumlah'         => (string) $jumlah,
                    'biaya_tambahan' => (string) $biaya,
                    'keterangan'     => '',
                    'diskon'         => (string) $diskon,
                    'ppn'            => null,
                ],
            ],
            'suplier'         => 'Supplier',
            'id_suplier_text' => '1',
            'id_pelanggan'    => '1',
            'tanggal_masuk'   => date('Y-m-d'),
            'jatuh_tempo'     => date('Y-m-d'),
            'hutang'          => 'Rp 0',
            'total-harga'     => 'Rp ' . number_format($hargaBeli * $jumlah, 0, ',', '.'),
            'total-diskon'    => 'Rp ' . number_format($diskon, 0, ',', '.'),
            'total-ppn'       => 'Rp 0',
            'bayar'           => 'Rp ' . number_format($hargaBeli * $jumlah - $diskon + $biaya, 0, ',', '.'),
            'frontliner'      => $frontliner,
        ]);
    }

    public function testPembelianMemakaiHargaBeliTerakhirHanyaUntukUnitItu(): void
    {
        $this->sesi();

        $result = $this->postPembelian(120000.0, 1);

        $result->assertRedirect();
        $hppUnit1 = $this->db->table('hpp_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow();
        $hppUnit2 = $this->db->table('hpp_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => 2])->get()->getRow();

        $this->assertSame(120000.0, (float) $hppUnit1->hpp);
        $this->assertSame('pembelian', $hppUnit1->sumber);
        $this->assertSame(90000.0, (float) $hppUnit2->hpp, 'unit lain tidak berubah');
        $this->assertSame(100000.0, (float) $this->db->table('barang')
            ->where('idbarang', 1)->get()->getRow()->harga_beli, 'master tidak berubah');
        $this->assertSame(1, $this->db->table('detail_pembelian')->countAllResults());
    }

    public function testPembelianMenghitungHppBersihPerUnit(): void
    {
        $this->sesi();

        $result = $this->postPembelian(120000.0, 2, 40000.0, 20000.0);

        $result->assertRedirect();
        $hppUnit = $this->db->table('hpp_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow();

        $this->assertSame(110000.0, (float) $hppUnit->hpp);
    }

    public function testFrontlinerSelaluMengikutiUserLoginDanMengabaikanPost(): void
    {
        $this->sesi();

        $result = $this->postPembelian(120000.0, 1, 0.0, 0.0, '999');

        $result->assertRedirect();
        $pembelian = $this->db->table('pembelian')->get()->getRow();
        $this->assertSame(43, (int) $pembelian->frontliner, 'frontliner dari POST harus diabaikan');
        $this->assertSame(43, (int) $pembelian->input_by, 'frontliner sama dengan input_by');
    }

    public function testPembelianBatalBilaHppTidakValid(): void
    {
        $this->sesi();

        $result = $this->postPembelian(120000.0, 0);

        $result->assertRedirect();
        $this->assertSame(0, $this->db->table('pembelian')->countAllResults(), 'header tidak tersimpan');
        $this->assertSame(0, $this->db->table('detail_pembelian')->countAllResults(), 'detail tidak tersimpan');
        $hppUnit = $this->db->table('hpp_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow();
        $this->assertSame(100000.0, (float) $hppUnit->hpp, 'HPP lama dipertahankan');
    }

    private function override(int $unit = 1): ?float
    {
        $row = $this->db->table('harga_jual_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => $unit])->get()->getRow();

        return $row === null ? null : (float) $row->harga_jual;
    }

    private function hargaMaster(): float
    {
        return (float) $this->db->table('barang')->where('idbarang', 1)->get()->getRow()->harga;
    }

    public function testHargaJualPembelianDisimpanPerUnit(): void
    {
        $this->sesi();

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '175000')->assertRedirect();

        $this->assertSame(175000.0, $this->override());
        $this->assertSame(150000.0, $this->hargaMaster(), 'harga master tidak berubah');
        $this->assertSame(1, $this->db->table('detail_pembelian')->countAllResults());
    }

    public function testHargaJualPembelianTidakMengubahUnitLain(): void
    {
        $this->sesi();

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '175000')->assertRedirect();

        $this->assertSame(175000.0, $this->override(1));
        $this->assertNull($this->override(2));
    }

    public function testHargaJualSamaDenganMasterMenghapusOverride(): void
    {
        $this->db->table('harga_jual_unit')->insert([
            'barang_idbarang' => 1, 'unit_idunit' => 1, 'harga_jual' => 175000,
        ]);
        $this->sesi();

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '150000')->assertRedirect();

        $this->assertNull($this->override());
    }

    public function testHargaJualDiabaikanUntukRoleNonPengelola(): void
    {
        $this->sesi(99);

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '175000')->assertRedirect();

        $this->assertNull($this->override(), 'role non-pengelola tidak menulis override');
        $this->assertSame(150000.0, $this->hargaMaster());
        $this->assertSame(1, $this->db->table('detail_pembelian')->countAllResults(), 'pembelian tetap tersimpan');
    }

    public function testPembelianBatalBilaHargaJualTidakValid(): void
    {
        $this->sesi();

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '0')->assertRedirect();

        $this->assertSame(0, $this->db->table('pembelian')->countAllResults(), 'header dibatalkan');
        $this->assertSame(0, $this->db->table('detail_pembelian')->countAllResults(), 'detail dibatalkan');
        $this->assertNull($this->override());
        $this->assertSame(150000.0, $this->hargaMaster());
    }
}
