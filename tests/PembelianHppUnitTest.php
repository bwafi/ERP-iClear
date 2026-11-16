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
        'hutang_piutang', 'harga_jual_unit', 'kas_keluar', 'kategori_kas',
        'bank', 'akun_kas_bank', 'alokasi_saldo_kas_bank',
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

        $q('CREATE TABLE unit (idunit INT PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL,
                is_operational INT NULL)');
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
        $q('CREATE TABLE kategori_kas (idkategori_kas INT PRIMARY KEY, kategori TEXT NULL,
                kode_template_jurnal TEXT NULL, jenis_kas TEXT NULL)');
        $q('CREATE TABLE kas_keluar (idkas_keluar INT PRIMARY KEY AUTO_INCREMENT,
                tanggal DATE NULL, kategori_idkategori INT NULL, deskripsi TEXT NULL,
                jumlah INT NULL, penerima VARCHAR(255) NULL, idunit INT NULL,
                jenis VARCHAR(255) NULL, idbank VARCHAR(11) NULL, created_on VARCHAR(255) NULL,
                updated_on DATETIME NULL, no_akun VARCHAR(255) NULL)');
        $q('CREATE TABLE bank (idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL,
                nama_bank TEXT NULL, atas_nama TEXT NULL, isppn TEXT NULL, norek TEXT NULL,
                gambar_qris TEXT NULL, created_on DATETIME NULL, updated_on DATETIME NULL)');
        $q('CREATE TABLE akun_kas_bank (idakun_kas_bank INT PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL, tipe VARCHAR(10) NULL, nama_akun TEXT NULL,
                bank_idbank VARCHAR(20) NULL, no_akun_coa VARCHAR(255) NULL,
                status VARCHAR(10) NULL, is_shared INT NULL, is_finance_ho INT NULL,
                created_by INT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)');
        $q('CREATE TABLE alokasi_saldo_kas_bank (id INT PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal INT NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at DATETIME NULL,
                updated_at DATETIME NULL)');
    }

    private function sebarData(): void
    {
        $q = fn (string $sql) => $this->db->query($sql);

        $q("INSERT INTO unit (idunit, NAMA_UNIT, is_operational) VALUES (1, 'Unit Satu', 1), (2, 'Unit Dua', 1)");
        $q("INSERT INTO jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES (0, 'Finance', '[]'), (35, 'ADMIN / KASIR', '[]')");
        $q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, STATUS_PEGAWAI)
            VALUES (43, 1, 35, 'Kasir Satu', 1), (44, 1, 0, 'Finance Satu', 1)");
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
        $q("INSERT INTO kategori_kas (idkategori_kas, kategori, jenis_kas) VALUES (7, 'HPP', 'kas_keluar')");
        // Rekening resmi bank '2' (config) dialokasikan ke unit 1 & 2, supaya
        // jalur Lunas transfer bisa diuji end-to-end tanpa bypass validator.
        $q("INSERT INTO bank (idbank, jenis_bank, nama_bank, atas_nama, norek)
            VALUES ('2', 'bank', 'BCA', 'ICLEAR DIGITAL SOLUSI CV', '0393778773')");
        $q("INSERT INTO akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank,
                status, is_shared, is_finance_ho)
            VALUES (2, NULL, 'BANK', 'BCA CV', '2', 'aktif', 1, 0)");
        $q("INSERT INTO alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal)
            VALUES (2, 1, 0), (2, 2, 0)");
    }

    private function sesi(int $jabatan = 35, int $idAkun = 43): void
    {
        $this->withSession([
            'logged_in'    => true,
            'ID_AKUN'      => $idAkun,
            'ID_UNIT'      => 1,
            'ID_JABATAN'   => $jabatan,
            'NAMA'         => 'Tester',
            'NAMA_JABATAN' => 'Tester',
            'EMAIL'        => 'tester@example.com',
        ]);
    }

    private function postPembelian(float $hargaBeli, int $jumlah, float $diskon = 0.0, float $biaya = 0.0, string $frontliner = '', string $hargaJual = '150000', ?int $unitId = null, ?string $jatuhTempo = null, string $jenis = 'lunas', string $metode = 'tunai', array $bank = [])
    {
        $payload = [
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
            'jenis_pembayaran' => $jenis,
            'metode_bayar'    => $metode,
            'hutang'          => 'Rp 0',
            'total-harga'     => 'Rp ' . number_format($hargaBeli * $jumlah, 0, ',', '.'),
            'total-diskon'    => 'Rp ' . number_format($diskon, 0, ',', '.'),
            'total-ppn'       => 'Rp 0',
            'bayar'           => 'Rp ' . number_format($hargaBeli * $jumlah - $diskon + $biaya, 0, ',', '.'),
            'frontliner'      => $frontliner,
            'unit_idunit'     => $unitId === null ? null : (string) $unitId,
        ];

        if ($bank !== []) {
            $payload['bank'] = $bank;
        }

        // $jatuhTempo = '' berarti field sengaja TIDAK dikirim (kasus ditolak).
        if ($jatuhTempo !== '') {
            $payload['jatuh_tempo'] = $jatuhTempo ?? date('Y-m-d');
        }

        return $this->post('insert_pembelian', $payload);
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

    public function testTimTokoTidakBisaMemilihUnitLewatPost(): void
    {
        $this->sesi(35, 43);

        // Kasir unit 1 mengirim unit 2 lewat POST: harus diabaikan.
        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '150000', 2)->assertRedirect();

        $pembelian = $this->db->table('pembelian')->get()->getRow();
        $this->assertSame(1, (int) $pembelian->unit_idunit, 'unit POST harus diabaikan untuk tim toko');
        $this->assertSame(
            120000.0,
            (float) $this->db->table('hpp_unit')
                ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow()->hpp,
            'HPP tetap tercatat di unit akun kasir'
        );
    }

    public function testFinanceBolehMemilihUnitLewatPost(): void
    {
        // Akun Finance (jabatan 0) boleh mencatat pembelian untuk unit lain.
        $this->sesi(0, 44);

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '150000', 2)->assertRedirect();

        $pembelian = $this->db->table('pembelian')->get()->getRow();
        $this->assertSame(2, (int) $pembelian->unit_idunit, 'unit pilihan Finance harus dipakai');
        $this->assertSame(
            120000.0,
            (float) $this->db->table('hpp_unit')
                ->where(['barang_idbarang' => 1, 'unit_idunit' => 2])->get()->getRow()->hpp,
            'HPP masuk ke unit yang dipilih'
        );
        $this->assertSame(
            100000.0,
            (float) $this->db->table('hpp_unit')
                ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow()->hpp,
            'unit akun Finance tidak ikut berubah'
        );
    }

    public function testPembelianDitolakBilaJatuhTempoKosong(): void
    {
        $this->sesi();

        // Field jatuh_tempo sengaja tidak dikirim (form tidak lagi prefill).
        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '150000', null, '', 'tempo')->assertRedirect();

        $this->assertSame(0, $this->db->table('pembelian')->countAllResults(), 'header tidak tersimpan');
        $this->assertSame(0, $this->db->table('detail_pembelian')->countAllResults(), 'detail tidak tersimpan');
        $this->assertSame(
            'Jatuh tempo wajib diisi dengan tanggal yang valid.',
            session()->getFlashdata('gagal')
        );
    }

    public function testPembelianDitolakBilaJatuhTempoBukanTanggalSah(): void
    {
        $this->sesi();

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '150000', null, '2026-02-30', 'tempo')->assertRedirect();

        $this->assertSame(0, $this->db->table('pembelian')->countAllResults(), 'header tidak tersimpan');
        $this->assertSame(
            100000.0,
            (float) $this->db->table('hpp_unit')
                ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow()->hpp,
            'HPP lama dipertahankan'
        );
    }

    public function testPembelianBatalBilaHppTidakValid(): void
    {
        $this->sesi();

        $result = $this->postPembelian(120000.0, 0, 0.0, 0.0, '', '150000', null, date('Y-m-d'), 'tempo');

        $result->assertRedirect();
        $this->assertSame(0, $this->db->table('pembelian')->countAllResults(), 'header tidak tersimpan');
        $this->assertSame(0, $this->db->table('detail_pembelian')->countAllResults(), 'detail tidak tersimpan');
        $hppUnit = $this->db->table('hpp_unit')
            ->where(['barang_idbarang' => 1, 'unit_idunit' => 1])->get()->getRow();
        $this->assertSame(100000.0, (float) $hppUnit->hpp, 'HPP lama dipertahankan');
    }

    public function testTempoMenjadikanSeluruhTotalHutangTanpaKasKeluar(): void
    {
        $this->sesi();

        $this->postPembelian(120000.0, 2, 0.0, 0.0, '', '150000', null, '2026-12-31', 'tempo')
            ->assertRedirect();

        $pembelian = $this->db->table('pembelian')->get()->getRow();
        $this->assertSame('Belum Lunas', $pembelian->status);
        $this->assertSame(240000, (int) $pembelian->sisa);
        $this->assertSame(0, (int) $pembelian->total_bayar);
        $this->assertSame('2026-12-31', $pembelian->jatuh_tempo);
        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults(), 'Tempo tidak boleh keluar kas');
    }

    public function testLunasTunaiMencatatKasKeluarPenuh(): void
    {
        $this->sesi();

        $this->postPembelian(120000.0, 2)->assertRedirect();

        $pembelian = $this->db->table('pembelian')->get()->getRow();
        $this->assertSame('Lunas', $pembelian->status);
        $this->assertSame(0, (int) $pembelian->sisa);
        $this->assertSame(240000, (int) $pembelian->total_bayar);
        $this->assertSame(240000, (int) $pembelian->bayar_tunai);
        $this->assertSame(0, (int) $pembelian->bayar_bank);

        $kasKeluar = $this->db->table('kas_keluar')->get()->getRow();
        $this->assertNotNull($kasKeluar);
        $this->assertSame(240000, (int) $kasKeluar->jumlah);
        $this->assertSame(1, (int) $kasKeluar->idunit);
        $this->assertSame(7, (int) $kasKeluar->kategori_idkategori);
        $this->assertNull($kasKeluar->idbank, 'tunai tidak boleh menunjuk rekening bank');
        $this->assertSame('debet', $kasKeluar->jenis);
        $this->assertSame('1030100000', $kasKeluar->no_akun);
        $this->assertSame(date('Y-m-d'), $kasKeluar->tanggal);
        $this->assertStringContainsString('Supplier', $kasKeluar->penerima);
    }

    public function testLunasTransferValidMencatatKasKeluarDenganIdbank(): void
    {
        $this->sesi();

        $this->postPembelian(
            120000.0,
            2,
            0.0,
            0.0,
            '',
            '150000',
            null,
            null,
            'lunas',
            'transfer',
            [['id' => '2', 'jumlah' => '240000']]
        )->assertRedirect();

        $pembelian = $this->db->table('pembelian')->get()->getRow();
        $this->assertSame('Lunas', $pembelian->status);
        $this->assertSame(0, (int) $pembelian->sisa);
        $this->assertSame(240000, (int) $pembelian->total_bayar);
        $this->assertSame(0, (int) $pembelian->bayar_tunai);
        $this->assertSame(240000, (int) $pembelian->bayar_bank);

        $pembayaran = $this->db->table('pembayaran_bank')->get()->getRow();
        $this->assertSame('2', (string) $pembayaran->bank_idbank);
        $this->assertSame(240000, (int) $pembayaran->jumlah);

        $kasKeluar = $this->db->table('kas_keluar')->get()->getRow();
        $this->assertNotNull($kasKeluar);
        $this->assertSame('2', (string) $kasKeluar->idbank, 'transfer harus menunjuk rekening bank');
        $this->assertSame(240000, (int) $kasKeluar->jumlah);
        $this->assertSame('1030100000', $kasKeluar->no_akun);
    }

    public function testLunasTransferTanpaRekeningDitolak(): void
    {        $this->sesi();

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '150000', null, null, 'lunas', 'transfer')
            ->assertRedirect();

        $this->assertSame(0, $this->db->table('pembelian')->countAllResults(), 'header tidak tersimpan');
        $this->assertSame(0, $this->db->table('kas_keluar')->countAllResults(), 'kas keluar tidak tersimpan');
        $this->assertSame(
            'Pembayaran Lunas via Transfer wajib memilih tepat satu rekening bank.',
            session()->getFlashdata('gagal')
        );
    }

    public function testJenisPembayaranTidakDikenalDitolak(): void
    {
        $this->sesi();

        $this->postPembelian(120000.0, 1, 0.0, 0.0, '', '150000', null, null, 'kredit')
            ->assertRedirect();

        $this->assertSame(0, $this->db->table('pembelian')->countAllResults(), 'header tidak tersimpan');
        $this->assertSame(
            'Jenis pembayaran wajib dipilih: Tempo atau Lunas.',
            session()->getFlashdata('gagal')
        );
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
