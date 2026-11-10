<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Halaman /tarif_jasa: render (ringkasan, tabel, field modal) dan handler
 * simpan/ubah yang tidak berubah setelah redesign.
 */
final class TarifJasaPageTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    private string $prefixAsli = '';

    private const TABEL = [
        'unit', 'akun', 'jabatan', 'menu', 'barang', 'barang_unit',
        'stok_awal', 'hpp_unit', 'hpp_barang', 'stok_barang', 'service', 'pelanggan',
        'harga_jual_unit',
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
        $q('CREATE TABLE akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE menu (idmenu INT PRIMARY KEY AUTO_INCREMENT, urutan INT NULL, nama_menu TEXT NULL,
                roles TEXT NULL, url TEXT NULL, show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL, manualbook TEXT NULL)');
        $q('CREATE TABLE barang (idbarang INT PRIMARY KEY AUTO_INCREMENT, kode_barang TEXT NULL, nama_barang TEXT NULL,
                harga INT NULL, harga_beli INT NULL, idkategori INT NULL, deleted TEXT NULL DEFAULT "0",
                jenis_barang TEXT NULL DEFAULT "produk", input INT NULL)');
        $q('CREATE TABLE barang_unit (
                id INT PRIMARY KEY AUTO_INCREMENT,
                barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                aktif TINYINT NOT NULL DEFAULT 1,
                updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE stok_awal (idstok_awal INT PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, jumlah REAL NULL, barang_idbarang INT NULL,
                harga_beli REAL NULL, satuan_terkecil TEXT NULL, unit_idunit INT NULL,
                suplier_id_suplier VARCHAR(45) NULL, pelanggan_id_pelanggan INT NULL)');
        $q('CREATE TABLE hpp_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                hpp REAL NULL, sumber TEXT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE harga_jual_unit (barang_idbarang INT NOT NULL, unit_idunit INT NOT NULL,
                harga_jual REAL NOT NULL, updated_by INT NULL, updated_at DATETIME NULL,
                UNIQUE KEY (barang_idbarang, unit_idunit))');
        $q('CREATE TABLE hpp_barang (idbarang INT PRIMARY KEY, kode_barang TEXT NOT NULL,
                nama_barang TEXT NOT NULL, harga INT NOT NULL, harga_beli INT NULL,
                idkategori INT NULL, hpp DECIMAL(44,0) NOT NULL DEFAULT 0,
                input VARCHAR(255) NOT NULL DEFAULT "Faisal")');

        // BaseController::initController membaca notifikasi stok minimum.
        $q('CREATE TABLE stok_barang (idbarang INT NULL, id_unit INT NULL,
                stok_akhir REAL NULL, stok_minimum REAL NULL)');

        // BaseController::initController membaca tabel ini.
        $q('CREATE TABLE service (idservice INT PRIMARY KEY, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                tipe_hp TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL,
                kabupaten TEXT NULL, kecamatan TEXT NULL, deleted TEXT NULL)');
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q("INSERT INTO unit (idunit, NAMA_UNIT, LOGO) VALUES (1, 'Unit Satu', 'logo.png')");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES (43, 1, 35, 'Kasir Satu')");
        $q("INSERT INTO barang (idbarang, kode_barang, nama_barang, harga, harga_beli, idkategori, deleted, jenis_barang) VALUES
            (1, 'J01', 'Ganti LCD', 150000, 15000, 3, '0', 'jasa'),
            (2, 'J02', 'Cleaning', 50000, 0, 3, '0', 'jasa'),
            (3, 'P01', 'Produk Biasa', 90000, 70000, 1, '0', 'produk'),
            (4, 'J99', 'Jasa Dihapus', 10000, 0, 3, '1', 'jasa')");
        $q("INSERT INTO hpp_barang (idbarang, kode_barang, nama_barang, harga, harga_beli, idkategori, hpp)
            VALUES (1, 'J01', 'Ganti LCD', 150000, 15000, 3, 15000)");
        $q("INSERT INTO hpp_unit (barang_idbarang, unit_idunit, hpp, sumber) VALUES (1, 1, 0, 'stok_awal')");
        // Legacy: fee hanya ada di hpp_unit, barang.harga_beli masih 0.
        $q("INSERT INTO hpp_unit (barang_idbarang, unit_idunit, hpp, sumber) VALUES (2, 1, 25000, 'stok_awal')");
    }

    private function sesi(): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => 43,
            'ID_UNIT'      => 1,
            'ID_JABATAN'   => 35,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function token(): array
    {
        return ['csrf_test_name' => service('security')->getHash()];
    }

    public function testHalamanMenampilkanRingkasanTabelDanFieldModal(): void
    {
        $this->sesi();

        $result = $this->get('tarif_jasa');

        $result->assertStatus(200);
        $result->assertSee('id="tj-table"');
        $result->assertSee('id="tj-tambah"');
        $result->assertSee('id="tj-ubah"');
        $result->assertSee('Rp 150.000');
        $result->assertSee('Rp 50.000');
        $result->assertSee('Rp 100.000'); // rata-rata dua jasa
        $result->assertSee('name="kode_barang"');
        $result->assertSee('name="nama_barang"');
        $result->assertSee('name="harga"');
        $result->assertSee('>HPP<');
        $result->assertSee('name="harga_beli"');
        $result->assertSee('Rp 15.000');
        $result->assertSee('data-fee="15000"');
        $result->assertSee('Rp 25.000'); // fee legacy dari hpp_unit
        $result->assertSee('data-fee="25000"');
        $result->assertSee('id="tj-fee-persen"');
        $result->assertSee('id="tj-ubah-fee-persen"');
        $result->assertSee('name="fee_persen"');
        $result->assertSee('data-order="150000"');
        $result->assertDontSee('Jasa Dihapus');
        $result->assertDontSee('Produk Biasa');

        $body = $result->getBody();
        $this->assertLessThan(strpos($body, 'Harga Jual'), strpos($body, '>HPP<'),
            'kolom HPP tampil sebelum harga jual');
    }

    public function testSimpanDanUbahTetapMemakaiFieldLama(): void
    {
        $this->sesi();

        $this->post('tarif_jasa/simpan', $this->token() + [
            'kode_barang' => 'J03',
            'nama_barang' => 'Ganti Baterai',
            'harga'       => '250000',
        ])->assertRedirect();

        $baru = $this->db->table('barang')->where('kode_barang', 'J03')->get()->getRow();
        $this->assertNotNull($baru, 'jasa baru tersimpan');
        $this->assertSame('jasa', $baru->jenis_barang);
        $this->assertSame(250000, (int) $baru->harga);

        $this->post('tarif_jasa/ubah', $this->token() + [
            'idbarang'    => $baru->idbarang,
            'nama_barang' => 'Ganti Baterai Original',
            'harga'       => '275000',
        ])->assertRedirect();

        $ubah = $this->db->table('barang')->where('idbarang', $baru->idbarang)->get()->getRow();
        $this->assertSame('Ganti Baterai Original', $ubah->nama_barang);
        $this->assertSame(275000, (int) $ubah->harga);
    }

    public function testKodeDuplikatDitolakTanpaMenambahBaris(): void
    {
        $this->sesi();

        $this->post('tarif_jasa/simpan', $this->token() + [
            'kode_barang' => 'J01',
            'nama_barang' => 'Duplikat',
            'harga'       => '1000',
        ])->assertRedirect();

        $this->assertSame(4, $this->db->table('barang')->countAllResults(), 'tidak ada baris baru');
    }

    public function testFeeTeknisiDisimpanSebagaiHpp(): void
    {
        $this->sesi();

        $this->post('tarif_jasa/simpan', $this->token() + [
            'kode_barang' => 'J03',
            'nama_barang' => 'Servis Mesin',
            'harga'       => '1000000',
            'harga_beli'  => '100000',
        ])->assertRedirect();

        $baru = $this->db->table('barang')->where('kode_barang', 'J03')->get()->getRow();
        $this->assertSame(100000, (int) $baru->harga_beli, 'fee tersimpan di master');

        $hpp = $this->db->table('hpp_barang')->where('idbarang', $baru->idbarang)->get()->getRow();
        $this->assertNotNull($hpp, 'baris hpp_barang dibuat');
        $this->assertSame(100000, (int) $hpp->hpp);

        $resolve = (new \App\Models\ModelHppUnit())->resolve((int) $baru->idbarang, 1);
        $this->assertSame(100000.0, $resolve, 'transaksi memakai fee sebagai HPP');
        $this->assertSame(900000.0, 1000000.0 - $resolve, 'fee tidak masuk omset');
    }

    public function testUbahFeeMemperbaruiHppBarangDanHppUnit(): void
    {
        $this->sesi();

        $this->post('tarif_jasa/ubah', $this->token() + [
            'idbarang'    => 1,
            'nama_barang' => 'Ganti LCD',
            'harga'       => '150000',
            'harga_beli'  => '15000',
        ])->assertRedirect();

        $barang = $this->db->table('barang')->where('idbarang', 1)->get()->getRow();
        $this->assertSame(15000, (int) $barang->harga_beli);
        $this->assertSame(15000, (int) $this->db->table('hpp_barang')->where('idbarang', 1)->get()->getRow()->hpp);

        $unit = $this->db->table('hpp_unit')->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow();
        $this->assertSame(15000, (int) $unit->hpp);
        $this->assertSame('manual', $unit->sumber);
        $this->assertSame(15000.0, (new \App\Models\ModelHppUnit())->resolve(1, 1));
    }

    public function testFeeMelebihiHargaDitolak(): void
    {
        $this->sesi();

        $this->post('tarif_jasa/ubah', $this->token() + [
            'idbarang'    => 1,
            'nama_barang' => 'Ganti LCD',
            'harga'       => '100000',
            'harga_beli'  => '150000',
        ])->assertRedirect();

        $barang = $this->db->table('barang')->where('idbarang', 1)->get()->getRow();
        $this->assertSame(150000, (int) $barang->harga, 'perubahan ditolak');
        $this->assertSame(15000, (int) $barang->harga_beli, 'fee lama tetap');
    }

    public function testPersenFeeDihitungDariHargaJual(): void
    {
        $this->sesi();

        $this->post('tarif_jasa/simpan', $this->token() + [
            'kode_barang' => 'J05',
            'nama_barang' => 'Servis Mesin Persen',
            'harga'       => '1000000',
            'harga_beli'  => '0',
            'fee_persen'  => '10',
        ])->assertRedirect();

        $baru = $this->db->table('barang')->where('kode_barang', 'J05')->get()->getRow();
        $this->assertSame(100000, (int) $baru->harga_beli, '10% dari 1.000.000');

        $hpp = $this->db->table('hpp_barang')->where('idbarang', $baru->idbarang)->get()->getRow();
        $this->assertSame(100000, (int) $hpp->hpp);
        $this->assertSame(900000.0, 1000000.0 - (new \App\Models\ModelHppUnit())->resolve((int) $baru->idbarang, 1));
    }

    public function testPersenFeeDiAtasSeratusDitolak(): void
    {
        $this->sesi();

        $this->post('tarif_jasa/ubah', $this->token() + [
            'idbarang'    => 1,
            'nama_barang' => 'Ganti LCD',
            'harga'       => '150000',
            'harga_beli'  => '0',
            'fee_persen'  => '120',
        ])->assertRedirect();

        $barang = $this->db->table('barang')->where('idbarang', 1)->get()->getRow();
        $this->assertSame(15000, (int) $barang->harga_beli, 'fee lama tetap');
    }

    private function override(int $idbarang = 1, int $unit = 1): ?float
    {
        $row = $this->db->table('harga_jual_unit')
            ->where(['barang_idbarang' => $idbarang, 'unit_idunit' => $unit])->get()->getRow();

        return $row === null ? null : (float) $row->harga_jual;
    }

    private function ubahJasa(string $hargaUnit): void
    {
        $this->post('tarif_jasa/ubah', $this->token() + [
            'idbarang'    => 1,
            'nama_barang' => 'Ganti LCD',
            'harga'       => '150000',
            'harga_beli'  => '15000',
            'harga_unit'  => $hargaUnit,
        ])->assertRedirect();
    }

    public function testHalamanMenampilkanHargaUnitUntukRolePengelola(): void
    {
        $this->sesi();
        $this->db->table('harga_jual_unit')->insert([
            'barang_idbarang' => 1, 'unit_idunit' => 1, 'harga_jual' => 175000,
        ]);

        $result = $this->get('tarif_jasa');

        $result->assertStatus(200);
        $result->assertSee('name="harga_unit"');
        $result->assertSee('Rp 175.000');
        $result->assertSee('khusus unit ini');
    }

    public function testUbahMenyimpanOverrideUnit(): void
    {
        $this->sesi();

        $this->ubahJasa('175000');

        $this->assertSame(175000.0, $this->override());
        $this->assertSame(150000.0, (float) $this->db->table('barang')
            ->where('idbarang', 1)->get()->getRow()->harga, 'harga master tidak berubah');
    }

    public function testUbahDenganHargaMasterMenghapusOverride(): void
    {
        $this->sesi();
        $this->db->table('harga_jual_unit')->insert([
            'barang_idbarang' => 1, 'unit_idunit' => 1, 'harga_jual' => 175000,
        ]);

        $this->ubahJasa('150000');

        $this->assertNull($this->override());
    }

    public function testUbahRoleNonPengelolaTidakMenulisOverride(): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => 43,
            'ID_UNIT'      => 1,
            'ID_JABATAN'   => 99,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);

        $this->ubahJasa('175000');

        $this->assertNull($this->override());
        $this->assertSame(150000.0, (float) $this->db->table('barang')
            ->where('idbarang', 1)->get()->getRow()->harga, 'perubahan master tetap tersimpan');
    }
}
