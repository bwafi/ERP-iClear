<?php

namespace Tests;

use App\Libraries\ModeKasBank;
use App\Models\ModelHutangPiutang;
use App\Models\ModelTransaksiKasBank;
use App\Models\ModelAkunKasBank;
use App\Services\Finance\KasBankScopeService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

class KasBankTest extends CIUnitTestCase
{
    protected ModeKasBank $kasbank;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();
        $this->createSchema();
        $this->seed();

        $_SESSION['ID_AKUN'] = 9;

        $this->kasbank = new ModeKasBank();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $q = function (string $sql): void {
            $this->db->query($sql);
        };

        foreach ([
            'db_transaksi_kas_bank',
            'db_pembayaran_hutang_piutang',
            'db_akun_kas_bank',
            'db_alokasi_saldo_kas_bank',
            'db_saldo_awal_kas_bank',
            'db_hutang_piutang',
            'db_kas_masuk',
            'db_kas_keluar',
            'db_pembayaran_hutang',
            'db_pembayaran_piutang',
            'db_mutasi',
            'db_detail_mutasi',
            'db_piutang',
            'db_pembelian',
            'db_unit',
            'db_bank',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE IF NOT EXISTS db_unit (idunit INTEGER PRIMARY KEY AUTO_INCREMENT, NAMA_UNIT TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_bank (idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL, nama_bank TEXT NULL, atas_nama TEXT NULL, norek TEXT NULL, isppn INT NULL, gambar_qris TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0,
                is_finance_ho TINYINT(1) DEFAULT 0,
                created_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                keterangan TEXT NULL, bukti TEXT NULL, input_by INT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
$colsTkb = $this->db->query("SHOW COLUMNS FROM db_transaksi_kas_bank LIKE 'submission_key'")->getResultArray();
        if (! $colsTkb) {
            $q('ALTER TABLE db_transaksi_kas_bank ADD COLUMN submission_key VARCHAR(64) NULL');
        }
        $ixTkb = $this->db->query("SHOW INDEX FROM db_transaksi_kas_bank WHERE Key_name = 'uniq_tkb_submission'")->getResultArray();
        if (! $ixTkb) {
            $q('ALTER TABLE db_transaksi_kas_bank ADD UNIQUE KEY uniq_tkb_submission (submission_key)');
        }
        $q('CREATE TABLE IF NOT EXISTS db_alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_hutang_piutang (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                kode TEXT NULL, jenis TEXT NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                is_projection INT NULL, pihak_tipe TEXT NULL, pihak_id INT NULL,
                lawan_unit_id INT NULL, nama_pihak TEXT NULL, tanggal TEXT NULL,
                jatuh_tempo TEXT NULL, uraian TEXT NULL, total REAL NULL, total_dibayar REAL NULL,
                sisa REAL NULL, status TEXT NULL, keterangan TEXT NULL, unit_id INT NULL,
                input_by INT NULL, deleted INT DEFAULT 0, created_at TEXT NULL, updated_at TEXT NULL,
                scope TEXT NULL DEFAULT \'active\', cutoff_closed_at TEXT NULL,
                cutoff_closed_by INT NULL, cutoff_reason TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_pembayaran_hutang_piutang (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                hutang_piutang_id INT NULL, tanggal_bayar TEXT NULL, jumlah_bayar REAL NULL,
                bayar_tunai REAL NULL, bayar_bank REAL NULL, bank_idbank TEXT NULL,
                sumber TEXT NULL, referensi_tipe TEXT NULL, referensi_id INT NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_mutasi (
                idmutasi INTEGER PRIMARY KEY AUTO_INCREMENT,
                no_nota_mutasi TEXT NULL, tanggal_kirim TEXT NULL, tanggal_terima TEXT NULL,
                status TEXT NULL, kirim_idunit INT NULL, terima_idunit INT NULL,
                input_by INT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_detail_mutasi (
                iddetail_mutasi INTEGER PRIMARY KEY AUTO_INCREMENT,
                mutasi_idmutasi INT NULL, jumlah_kirim REAL NULL, jumlah_terima REAL NULL,
                harga_mutasi REAL NULL, hpp_barang REAL NULL, barang_idbarang INT NULL,
                kirim_idunit INT NULL, terima_idunit INT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_kas_masuk (
                idkas_masuk INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, kategori_idkategori INT NULL, no_akun TEXT NULL,
                deskripsi TEXT NULL, jumlah REAL NULL, jenis TEXT NULL, penerima TEXT NULL,
                idbank TEXT NULL, idunit INT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, kategori_idkategori INT NULL, no_akun TEXT NULL,
                deskripsi TEXT NULL, jumlah REAL NULL, jenis TEXT NULL, penerima TEXT NULL,
                idbank TEXT NULL, idunit INT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_pembelian (
                idpembelian INTEGER PRIMARY KEY AUTO_INCREMENT,
                jatuh_tempo TEXT NULL, unit_idunit INT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_pembayaran_hutang (
                idpembayaran_hutang INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal_bayar TEXT NULL, bayar REAL NULL, bayar_tunai REAL NULL,
                bayar_bank REAL NULL, sisa_hutang REAL NULL, pembelian_idpembelian INT NULL,
                bank_idbank TEXT NULL, input_by INT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_piutang (
                idpiutang INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_idunit INT NULL, tanggal TEXT NULL, pegawai_idpegawai INT NULL,
                jumlah_hutang REAL NULL, sisa_hutang REAL NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_pembayaran_piutang (
                idpembayaran_piutang INTEGER PRIMARY KEY AUTO_INCREMENT,
                idpiutang INT NULL, jumlah_bayar REAL NULL, sisa_hutang REAL NULL,
                bayar_tunai REAL NULL, bayar_bank REAL NULL, bank_idbank TEXT NULL, input_by INT NULL)');
    }

    private function seed(): void
    {
        $this->db->query('DELETE FROM db_transaksi_kas_bank');
        $this->db->query('DELETE FROM db_akun_kas_bank');
        $this->db->query('DELETE FROM db_alokasi_saldo_kas_bank');
        $this->db->query('DELETE FROM db_saldo_awal_kas_bank');
        $this->db->query('DELETE FROM db_hutang_piutang');
        $this->db->query('DELETE FROM db_pembayaran_hutang_piutang');
        $this->db->query('DELETE FROM db_detail_mutasi');
        $this->db->query('DELETE FROM db_mutasi');
        $this->db->query('DELETE FROM db_kas_masuk');
        $this->db->query('DELETE FROM db_kas_keluar');
        $this->db->query('DELETE FROM db_pembayaran_hutang');
        $this->db->query('DELETE FROM db_pembayaran_piutang');
        $this->db->query('DELETE FROM db_unit');
        $this->db->query('DELETE FROM db_pembelian');
        $this->db->query('DELETE FROM db_bank');
        $this->db->query('DELETE FROM db_no_akun');

        $this->db->query("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit A'), (2, 'Unit B'), (3, 'Unit C'), (4, 'Unit D')");
        $this->db->query("INSERT INTO db_bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('BNI-001', 'Bank BNI', '555', 'PT Contoh'),
            ('IRA-001', 'Bank BCA', '0391943558', 'PT Contoh Finance'),
            ('IRA-002', 'Bank BCA', '3251427508', 'PT Contoh HO')");

        $this->db->query("INSERT INTO db_akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared) VALUES
            (1, 1, 'KAS',  'Kas Unit A',  NULL, 'aktif', 0),
            (2, NULL, 'BANK', 'Bank BNI Bersama', 'BNI-001', 'aktif', 1),
            (3, 2, 'KAS',  'Kas Unit B',  NULL, 'aktif', 0),
            (5, 1, 'BANK', 'Bank Nonaktif', 'BNI-001', 'nonaktif', 0),
            (7, 3, 'BANK', 'Bank Privat Unit C', 'BNI-002', 'aktif', 0),
            (8, 4, 'BANK', 'Bank Privat Unit D', 'BNI-003', 'aktif', 0)");

        // Rekening Finance/HO: BENTUKNYA SAMA dengan shared (unit_id NULL,
        // is_shared = 1) tapi TIDAK butuh alokasi. Itulah sebabnya jenis ini
        // wajib dipisah dengan flag eksplisit is_finance_ho.
        $this->db->query("INSERT INTO db_akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (9, NULL, 'BANK', 'Rekening IRA/BCA Finance', 'IRA-001', 'aktif', 1, 1),
            (10, NULL, 'BANK', 'Rekening HO seconda', 'IRA-002', 'aktif', 1, 1)");

        // Rekening bersama (2) dialokasikan ke Unit 1 + Unit 2 saja. Unit 3 dan 4
        // TIDAK punya hak atas rekening ini meski user-nya boleh mengaksesnya.
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES (2, 1, 200000), (2, 2, 300000)");

        $this->db->query("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan) VALUES (2, '2026-01-01', 500000, 'Saldo awal BNI Bersama')");
    }

    public function testResolveAkunBankHanyaUnitYangPunyaHak(): void
    {
        // Rekening BNI Bersama (akun 2) dialokasikan ke Unit 1 + Unit 2 saja.
        $this->assertSame(2, $this->kasbank->resolveAkun(1, 'BNI-001'));
        $this->assertSame(2, $this->kasbank->resolveAkun(2, 'BNI-001'));

        // Unit 3 punya akses ke perusahaan, tapi TIDAK punya hak atas rekening
        // ini -> harus di-skip, bukan dialihkan ke KAS Unit 3.
        $this->assertNull($this->kasbank->resolveAkun(3, 'BNI-001'));
        $this->assertNull($this->kasbank->resolveAkun(4, 'BNI-001'));
    }

    public function testResolveAkunTidakFallbackKeKasSaatBankTidakDitemukan(): void
    {
        // $bankId kosong -> boleh pakai KAS unit tsb.
        $this->assertSame(1, $this->kasbank->resolveAkun(1, null));

        // $bankId diberikan tapi rekeningnya tidak terdaftar: HARUS gagal.
        // Dulu jatuh ke KAS dan classifying uang bank sebagai kas tunai.
        $this->assertNull($this->kasbank->resolveAkun(1, 'BANK-TIDAK-ADA'));
        $this->assertNull($this->kasbank->resolveAkun(2, 'BANK-TIDAK-ADA'));

        // Bank nonaktif: tidak ada rekening aktif -> gagal, bukan fallback KAS.
        $this->db->query("UPDATE db_akun_kas_bank SET status = 'nonaktif' WHERE idakun_kas_bank = 2");
        $this->assertNull($this->kasbank->resolveAkun(1, 'BNI-001'));
    }

    public function testResolveAkunNullSaatTidakAdaAkunAktif(): void
    {
        $this->assertNull($this->kasbank->resolveAkun(99, null));
    }

    public function testPostingKasMasukIdempotent(): void
    {
        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank) VALUES (1, '2026-10-05', 'Penjualan tunai', 50000, 1, 'BNI-001')");

        $r1 = $this->kasbank->postingKasMasuk(1);
        $this->assertSame('inserted', $r1['status']);

        $row = $this->db->query("SELECT * FROM db_transaksi_kas_bank WHERE sumber_tipe = 'kas_masuk' AND sumber_id = 1")->getRow();
        $this->assertNotNull($row);
        $this->assertSame('MASUK', $row->arah);
        $this->assertSame('PEMASUKAN', $row->jenis);
        $this->assertSame('2', (string)$row->akun_kas_bank_id);
        $this->assertSame('50000', (string)$row->jumlah);

        $r2 = $this->kasbank->postingKasMasuk(1);
        $this->assertSame('skipped', $r2['status']);
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) AS c FROM db_transaksi_kas_bank WHERE sumber_tipe = 'kas_masuk' AND sumber_id = 1")->getRow()->c);
    }

    public function testPostingKasKeluarIdempotent(): void
    {
        $this->db->query("INSERT INTO db_kas_keluar (idkas_keluar, tanggal, deskripsi, jumlah, idunit, idbank) VALUES (1, '2026-02-02', 'Belanja operasional', 75000, 2, NULL)");

        $r1 = $this->kasbank->postingKasKeluar(1);
        $this->assertSame('inserted', $r1['status']);

        $row = $this->db->query("SELECT * FROM db_transaksi_kas_bank WHERE sumber_tipe = 'kas_keluar' AND sumber_id = 1")->getRow();
        $this->assertNotNull($row);
        $this->assertSame('KELUAR', $row->arah);
        $this->assertSame('PENGELUARAN', $row->jenis);
        $this->assertSame('3', (string)$row->akun_kas_bank_id);

        $r2 = $this->kasbank->postingKasKeluar(1);
        $this->assertSame('skipped', $r2['status']);
    }

    public function testPostingKasMasukSkipSaatAkunBelumDikonfigurasi(): void
    {
        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank) VALUES (2, '2026-02-01', 'Unit tanpa akun', 50000, 99, NULL)");
        $r = $this->kasbank->postingKasMasuk(2);
        $this->assertSame('skipped', $r['status']);
    }

    public function testPostingCicilanHutangPakaiAkunBank(): void
    {
        $this->db->query("INSERT INTO db_pembelian (idpembelian, unit_idunit, jatuh_tempo) VALUES (10, 1, '2026-03-01')");
        $this->db->query("INSERT INTO db_pembayaran_hutang (idpembayaran_hutang, tanggal_bayar, bayar, bayar_tunai, bayar_bank, pembelian_idpembelian, bank_idbank) VALUES
            (10, '2026-02-10', 100000, 0, 100000, 10, 'BNI-001')");

        $r = $this->kasbank->postingCicilanHutang(10);
        $this->assertSame('inserted', $r['status']);

        $row = $this->db->query("SELECT * FROM db_transaksi_kas_bank WHERE sumber_tipe = 'pembayaran_hutang' AND sumber_id = 10")->getRow();
        $this->assertNotNull($row);
        $this->assertSame('KELUAR', $row->arah);
        $this->assertSame('2', (string)$row->akun_kas_bank_id);
        $this->assertSame('100000', (string)$row->jumlah);
    }

    public function testPostingCicilanHutangSplitTunaiDanBankDuaLeg(): void
    {
        $this->db->query("INSERT INTO db_pembelian (idpembelian, unit_idunit, jatuh_tempo) VALUES (11, 1, '2026-03-01')");
        $this->db->query("INSERT INTO db_pembayaran_hutang (idpembayaran_hutang, tanggal_bayar, bayar, bayar_tunai, bayar_bank, pembelian_idpembelian, bank_idbank) VALUES
            (11, '2026-02-10', 250000, 100000, 150000, 11, 'BNI-001')");

        $r = $this->kasbank->postingCicilanHutang(11);
        $this->assertSame('inserted', $r['status']);

        $rows = $this->db->query("SELECT * FROM db_transaksi_kas_bank WHERE sumber_tipe = 'pembayaran_hutang' AND sumber_id = 11 ORDER BY akun_kas_bank_id")->getResult();
        $this->assertCount(2, $rows);
        $tipe0 = $this->db->query("SELECT tipe FROM db_akun_kas_bank WHERE idakun_kas_bank = '{$rows[0]->akun_kas_bank_id}'")->getRow()->tipe;
        $tipe1 = $this->db->query("SELECT tipe FROM db_akun_kas_bank WHERE idakun_kas_bank = '{$rows[1]->akun_kas_bank_id}'")->getRow()->tipe;
        $this->assertSame('KAS', $tipe0);
        $this->assertSame('100000', (string)$rows[0]->jumlah);
        $this->assertSame('BANK', $tipe1);
        $this->assertSame('150000', (string)$rows[1]->jumlah);

        $r2 = $this->kasbank->postingCicilanHutang(11);
        $this->assertSame('skipped', $r2['status']);
        $this->assertSame(2, (int)$this->db->query("SELECT COUNT(*) AS c FROM db_transaksi_kas_bank WHERE sumber_tipe = 'pembayaran_hutang' AND sumber_id = 11")->getRow()->c);
    }

    public function testSeedAkunDefaultPerUnitIdempotent(): void
    {
        $this->db->query("DELETE FROM db_akun_kas_bank");

        // Seed membuat satu akun KAS per unit yang belum punya (4 unit di fixture).
        $r1 = $this->kasbank->seedAkunDefault();
        $this->assertSame(4, $r1['created']);
        $this->assertSame(0, $r1['skipped']);

        $kasA = $this->db->query("SELECT * FROM db_akun_kas_bank WHERE unit_id = 1 AND tipe = 'KAS'")->getRow();
        $this->assertNotNull($kasA);
        $this->assertSame('Kas Unit A', $kasA->nama_akun);
        $this->assertSame('1010101000', $kasA->no_akun_coa);
        $this->assertSame('aktif', $kasA->status);

        $r2 = $this->kasbank->seedAkunDefault();
        $this->assertSame(0, $r2['created']);
        $this->assertSame(4, $r2['skipped']);
    }

    public function testPostingBayarPiutang(): void
    {
        $this->db->query("INSERT INTO db_pembayaran_piutang (idpembayaran_piutang, idpiutang, jumlah_bayar, bank_idbank) VALUES (20, 99, 250000, 'BNI-001')");

        $r = $this->kasbank->postingBayarPiutang(20, 1);
        $this->assertSame('inserted', $r['status']);

        $row = $this->db->query("SELECT * FROM db_transaksi_kas_bank WHERE sumber_tipe = 'pembayaran_piutang' AND sumber_id = 20")->getRow();
        $this->assertNotNull($row);
        $this->assertSame('MASUK', $row->arah);
        $this->assertSame('PEMASUKAN', $row->jenis);
        $this->assertSame('2', (string)$row->akun_kas_bank_id);
        $this->assertSame('250000', (string)$row->jumlah);
    }

    public function testSaldoFisikGabunganSemuaUnit(): void
    {
        // BCA Bersama (akun 6) dipakai unit 1 & unit 2: saldo fisik = gabungan
        // seluruh transaksi antar unit, saldo awal satu tarikan fisik.
        $this->db->query("INSERT INTO db_akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared) VALUES
            (6, NULL, 'BANK', 'BCA Bersama Jember', 'BCA-001', 'aktif', 1)");
        $this->db->query("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan) VALUES (6, '2026-01-01', 800000, 'Saldo awal BCA')");
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES (6, 1, 300000), (6, 2, 500000)");
        $this->db->query("INSERT INTO db_transaksi_kas_bank (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah) VALUES
            ('2026-10-01', 1, 6, 'PEMASUKAN', 'MASUK', 100000),
            ('2026-10-02', 2, 6, 'PEMASUKAN', 'MASUK', 50000),
            ('2026-10-03', 1, 6, 'PENGELUARAN', 'KELUAR', 50000),
            ('2026-10-04', 2, 6, 'PENGELUARAN', 'KELUAR', 30000)");

        $model = new ModelTransaksiKasBank();

        // Fisik: 800rb + 150rb masuk - 80rb keluar = 870rb.
        $this->assertSame(870000, $model->getSaldoFisikAkun(6));
        $this->assertSame(870000, $model->getSaldoAkun(6));

        // Per unit (alokasi + transaksi unit): unit 1 = 300rb+100rb-50rb=350rb;
        // unit 2 = 500rb+50rb-30rb=520rb. Total atribusi = fisik = 870rb.
        $this->assertSame(350000, $model->getSaldoUnitAkun(6, 1));
        $this->assertSame(520000, $model->getSaldoUnitAkun(6, 2));
        $this->assertSame(800000, $model->getTotalAlokasiUnit(6));
        $this->assertSame($model->getSaldoUnitAkun(6, 1) + $model->getSaldoUnitAkun(6, 2), $model->getSaldoFisikAkun(6));
    }

    public function testAlokasiSaldoModelUpsertPerUnit(): void
    {
        $this->db->query("DELETE FROM db_alokasi_saldo_kas_bank");
        $model = new \App\Models\ModelAlokasiSaldoKasBank();
        $model->insert(['akun_kas_bank_id' => 2, 'unit_id' => 1, 'nominal' => 100000]);
        $model->insert(['akun_kas_bank_id' => 2, 'unit_id' => 2, 'nominal' => 150000]);

        $this->assertSame(250000, $model->sumByAkun(2));
        $this->assertNotNull($model->getByAkunUnit(2, 1));

        $index = $model->indexByAkun();
        $this->assertCount(2, $index[2] ?? []);
    }

    public function testNilaiDetailMutasiGunakanHargaMutasi(): void
    {
        $this->assertSame(2000, $this->kasbank->nilaiDetailMutasi(['jumlah_kirim' => 2, 'harga_mutasi' => 1000, 'hpp_barang' => 999]));
    }

    public function testNilaiDetailMutasiFallbackKeHpp(): void
    {
        $this->assertSame(1500, $this->kasbank->nilaiDetailMutasi(['jumlah_kirim' => 3, 'harga_mutasi' => 0, 'hpp_barang' => 500]));
    }

    public function testNilaiDetailMutasiFallbackKeHppSaatHargaKosong(): void
    {
        $this->assertSame(500, $this->kasbank->nilaiDetailMutasi(['jumlah_terima' => 1, 'hpp_barang' => 500]));
    }

    public function testBuatHutangPiutangDariMutasi(): void
    {
        $this->db->query("INSERT INTO db_mutasi (idmutasi, no_nota_mutasi, tanggal_kirim, status, kirim_idunit, terima_idunit, input_by) VALUES (1, 'M-2026-001', '2026-02-05', 'terima', 1, 2, 9)");
        $this->db->query("INSERT INTO db_detail_mutasi (iddetail_mutasi, mutasi_idmutasi, jumlah_kirim, harga_mutasi, hpp_barang, kirim_idunit, terima_idunit) VALUES
            (1, 1, 2, 1000, 800, 1, 2),
            (2, 1, 3, 0, 500, 1, 2)");

        $r = $this->kasbank->buatHutangPiutangDariMutasi(1, 9);
        $this->assertSame('inserted', $r['status']);
        $this->assertSame(3500, $r['total']);

        $model = new ModelHutangPiutang();
        $rows = $model->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', 1)->findAll();
        $this->assertCount(2, $rows);

        $piutang = array_values(array_filter($rows, fn ($x) => $x->jenis === 'piutang'))[0];
        $hutang  = array_values(array_filter($rows, fn ($x) => $x->jenis === 'hutang'))[0];

        $this->assertSame('unit', $piutang->pihak_tipe);
        $this->assertSame('1', (string)$piutang->unit_id);
        $this->assertSame('1', (string)$piutang->pihak_id);
        $this->assertSame('2', (string)$piutang->lawan_unit_id);
        $this->assertSame('MUT-1-1-P', $piutang->kode);
        $this->assertSame('3500', (string)$piutang->total);
        $this->assertSame('belum_lunas', $piutang->status);

        $this->assertSame('2', (string)$hutang->unit_id);
        $this->assertSame('2', (string)$hutang->pihak_id);
        $this->assertSame('1', (string)$hutang->lawan_unit_id);
        $this->assertSame('MUT-2-1-H', $hutang->kode);

        $r2 = $this->kasbank->buatHutangPiutangDariMutasi(1, 9);
        $this->assertSame('skipped', $r2['status']);
    }

    public function testBuatHutangPiutangDariMutasiJatuhTempo(): void
    {
        // tanggal_terima diketahui -> default jatuh tempo = tanggal_terima + 3 hari.
        $this->db->query("INSERT INTO db_mutasi (idmutasi, no_nota_mutasi, tanggal_kirim, tanggal_terima, status, kirim_idunit, terima_idunit, input_by) VALUES (1, 'M-2026-002', '2026-02-05', '2026-02-08 10:00:00', '1', 1, 2, 9)");
        $this->db->query("INSERT INTO db_detail_mutasi (iddetail_mutasi, mutasi_idmutasi, jumlah_kirim, harga_mutasi, hpp_barang, kirim_idunit, terima_idunit) VALUES
            (1, 1, 2, 1000, 800, 1, 2)");

        $model = new ModelHutangPiutang();
        $this->kasbank->buatHutangPiutangDariMutasi(1, 9);

        $rows = $model->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', 1)->findAll();
        $this->assertCount(2, $rows);
        $kode = array_map(fn ($x) => $x->kode, $rows);
        $this->assertContains('MUT-1-1-P', $kode);
        $this->assertContains('MUT-2-1-H', $kode);
        foreach ($rows as $r) {
            $this->assertSame('2026-02-11', $r->jatuh_tempo);
        }

        // Parameter $jatuhTempo eksplisit dipakai (bukan tanggal_terima + 3).
        $model->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', 1)->delete();
        $this->kasbank->buatHutangPiutangDariMutasi(1, 9, '2026-03-01');
        $rows = $model->where('sumber_tipe', 'mutasi_unit')->where('sumber_id', 1)->findAll();
        $this->assertCount(2, $rows);
        foreach ($rows as $r) {
            $this->assertSame('2026-03-01', $r->jatuh_tempo);
        }
    }

    public function testTerapkanDanRestorePembayaranHP(): void
    {
        $model = new ModelHutangPiutang();
        $hpId  = $model->insert([
            'kode' => 'TST-1', 'jenis' => 'piutang', 'sumber_tipe' => 'tes', 'sumber_id' => 1,
            'pihak_tipe' => 'unit', 'pihak_id' => 1, 'unit_id' => 1, 'is_projection' => 0,
            'total' => 1000, 'total_dibayar' => 0, 'sisa' => 1000, 'status' => 'belum_lunas', 'deleted' => 0,
        ]);

        $r1 = $this->kasbank->terapkanPembayaranHP((int)$hpId, 400);
        $this->assertSame('ok', $r1['status']);
        $this->assertSame('sebagian', $r1['status_hp']);

        $r2 = $this->kasbank->terapkanPembayaranHP((int)$hpId, 600);
        $this->assertSame('lunas', $r2['status_hp']);
        $this->assertSame(0, $r2['sisa']);

        $over = $this->kasbank->terapkanPembayaranHP((int)$hpId, 1);
        $this->assertSame('failed', $over['status']);

        $this->kasbank->restorePembayaranHP((int)$hpId, 600);
        $hp = $model->find((int)$hpId);
        $this->assertSame('sebagian', $hp->status);
        $this->assertSame('600', (string)$hp->sisa);

        $this->kasbank->restorePembayaranHP((int)$hpId, 400);
        $hp = $model->find((int)$hpId);
        $this->assertSame('belum_lunas', $hp->status);
        $this->assertSame('1000', (string)$hp->sisa);
    }

    public function testTerbatasUnitMenyembunyikanRekeningUnitLain(): void
    {
        // Rekening bersama TANPA alokasi: belum ada unit yang punya hak.
        $this->db->query("DELETE FROM db_alokasi_saldo_kas_bank");

        $model = new ModelAkunKasBank($this->db);
        $ids  = function () use ($model): array {
            $arr = array_map('intval', array_column($model->getDalamScopeUnit([1]), 'idakun_kas_bank'));
            sort($arr);
            return $arr;
        };
        $aktif = function () use ($model): array {
            $arr = array_map('intval', array_column($model->getDalamScopeUnit([1], null, true), 'idakun_kas_bank'));
            sort($arr);
            return $arr;
        };

        // Tanpa alokasi: unit 1 melihat KAS miliknya + BANK non-shared miliknya
        // (trm. nonaktif 5) + rekening Finance/HO (9, 10) karena HO boleh jadi
        // tujuan dari unit mana pun. Rekening bersama (2) dan KAS unit 2 (3)
        // tidak terlihat.
        $this->assertSame([1, 5, 9, 10], $ids());
        $this->assertSame([1, 9, 10], $aktif());

        // Setelah BNI Bersama dialokasikan ke unit 1 -> menjadi rekening unit 1.
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES (2, 1, 250000)");
        $this->assertSame([1, 2, 5, 9, 10], $ids());
        $this->assertSame([1, 2, 9, 10], $aktif());

        // Unit 2 tetap tidak melihat rekening bersama: hanya KAS miliknya
        // sendiri + rekening Finance/HO (tujuan bersama semua unit).
        $idsU2 = array_map('intval', array_column($model->getDalamScopeUnit([2]), 'idakun_kas_bank'));
        $aktifU2 = array_map('intval', array_column($model->getDalamScopeUnit([2], null, true), 'idakun_kas_bank'));
        sort($idsU2);
        sort($aktifU2);
        $this->assertSame([3, 9, 10], $idsU2);
        $this->assertSame([3, 9, 10], $aktifU2);
    }

    public function testSaldoModel(): void
    {
        $this->testPostingKasMasukIdempotent();

        $saldo = (new ModelTransaksiKasBank())->getSaldoAkun(2);
        $this->assertSame(550000, $saldo);
    }

    public function testInsertIdempotentGuard(): void
    {
        $model = new ModelTransaksiKasBank();
        $id1 = $this->kasbank->insertIdempotent([
            'tanggal' => '2026-02-01', 'unit_id' => 1, 'akun_kas_bank_id' => 2,
            'jenis' => 'PEMASUKAN', 'arah' => 'MASUK', 'jumlah' => 100,
            'sumber_tipe' => 'tes', 'sumber_id' => 77,
        ]);
        $this->assertGreaterThan(0, $id1);

        $id2 = $this->kasbank->insertIdempotent([
            'tanggal' => '2026-02-02', 'unit_id' => 1, 'akun_kas_bank_id' => 2,
            'jenis' => 'PEMASUKAN', 'arah' => 'MASUK', 'jumlah' => 100,
            'sumber_tipe' => 'tes', 'sumber_id' => 77,
        ]);
        $this->assertSame($id1, $id2);

        $this->assertSame(1, $model->where('sumber_tipe', 'tes')->where('sumber_id', 77)->countAllResults());
    }

    public function testSubmissionKeyUniqueMemblokirSubmitDuplikat(): void
    {
        $model = new ModelTransaksiKasBank();
        $base  = [
            'tanggal'          => '2026-02-01',
            'unit_id'          => 1,
            'akun_kas_bank_id' => 2,
            'jenis'            => 'TRANSFER_INTERNAL',
            'arah'             => 'KELUAR',
            'jumlah'           => 150000,
            'transfer_ref'     => 'TRF-DUP-1',
        ];

        try {
            $model->insert($base + ['submission_key' => 'DUPTOK-1']);
            $model->insert($base + ['submission_key' => 'DUPTOK-1']);
            $this->fail('Insert kedua dengan submission_key sama tidak gagal (seharusnya kena UNIQUE).');
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            $this->assertSame(1, $model->where('submission_key', 'DUPTOK-1')->countAllResults());
        }
    }

    // =====================================================================
    // ACCOUNT SCOPE — dua lapis scope (user scope & account scope)
    //
    // Fixture:
    //   akun 1 = KAS  Unit 1, non-shared
    //   akun 2 = BANK shared, alokasi -> Unit 1 (200rb) + Unit 2 (300rb)
    //   akun 3 = KAS  Unit 2, non-shared
    //   akun 7 = BANK non-shared Unit 3
    //   akun 8 = BANK non-shared Unit 4
    // =====================================================================

    private function scope(): \App\Services\Finance\KasBankScopeService
    {
        $scope = new \App\Services\Finance\KasBankScopeService();
        $scope->flushCache();

        return $scope;
    }

    private function akun(int $id): object
    {
        return (new ModelAkunKasBank($this->db))->find($id);
    }

    /** [1] Shared Unit 1+2 TIDAK terlihat/terpakai Unit 3. */
    public function testSharedUnit1Dan2TidakTerlihatUnit3(): void
    {
        $scope = $this->scope();
        $a2    = $this->akun(2);

        // Account scope: hanya Unit 1 & 2 yang punya hak.
        $this->assertSame([1, 2], $scope->accountAllowedUnits($a2));
        $this->assertTrue($scope->isUnitEntitled($a2, 1));
        $this->assertTrue($scope->isUnitEntitled($a2, 2));
        $this->assertFalse($scope->isUnitEntitled($a2, 3));
        $this->assertFalse($scope->isUnitEntitled($a2, 4));

        // Dipilih Unit 3 -> rekening bersama tidak boleh muncul walau user
        // punya akses Unit 3.
        $ids = array_map('intval', array_column($scope->akunTerlihat([1, 2, 3], 3), 'idakun_kas_bank'));
        $this->assertNotContains(2, $ids);
        $this->assertContains(7, $ids, 'Rekening non-shared Unit 3 tetap boleh untuk Unit 3.');
    }

    /** [2] Non-shared Unit 3 tidak dapat dipakai Unit 1. */
    public function testNonSharedUnit3TidakDapatDipakaiUnit1(): void
    {
        $scope = $this->scope();
        $a7    = $this->akun(7);

        $this->assertSame([3], $scope->accountAllowedUnits($a7));
        $this->assertFalse($scope->cekAkunUntukUnit($a7, 1));
        $this->assertFalse($scope->bolehPakaiRekening($a7, 1));
        $this->assertTrue($scope->cekAkunUntukUnit($a7, 3));

        $ids = array_map('intval', array_column($scope->akunTerlihat([1]), 'idakun_kas_bank'));
        $this->assertNotContains(7, $ids);
    }

    /** [3] User multi-unit hanya mendapat rekening yang relevan. */
    public function testUserMultiUnitHanyaMendapatRekeningRelevan(): void
    {
        $scope = $this->scope();

        // User scope 1+2+3.
        $ids = array_map('intval', array_column($scope->akunTerlihat([1, 2, 3]), 'idakun_kas_bank'));
        sort($ids);

        // 1 = KAS u1, 2 = shared (u1+u2), 3 = KAS u2, 5 = BANK nonaktif u1,
        // 7 = BANK privat u3, 9+10 = Finance/HO (tujuan bersama semua unit).
        // Akun 8 milik Unit 4 di luar user scope.
        $this->assertSame([1, 2, 3, 5, 7, 9, 10], $ids);
        $this->assertNotContains(8, $ids);
    }

    /** [4] Konsolidasi tidak memasukkan rekening di luar account scope. */
    public function testKonsolidasiTidakMasukkanRekeningLuarAccountScope(): void
    {
        $scope = $this->scope();

        // Konsolidasi (unit null) = rekening yang account scope-nya beririsan
        // dengan user scope — bukan seluruh rekening.
        $ids = array_map('intval', array_column($scope->akunTerlihat([1, 2, 3], null), 'idakun_kas_bank'));
        sort($ids);
        $this->assertSame([1, 2, 3, 5, 7, 9, 10], $ids);

        // Master: rekening shared tanpa alokasi tetap tampil agar bisa dikonfigurasi.
        $this->db->query("DELETE FROM db_alokasi_saldo_kas_bank");
        $scope->flushCache();
        $master = array_map('intval', array_column($scope->akunTerlihat([1, 2, 3], null, false, true), 'idakun_kas_bank'));
        $this->assertContains(2, $master, 'Rekening shared tanpa alokasi harus bisa dikonfigurasi dari master.');
        $this->assertNotContains(8, $master, 'Rekening privat Unit 4 tetap tidak boleh di user scope 1+2+3.');
    }

    /** [5] Saldo shared tidak double-count. */
    public function testSaldoSharedTidakDoubleCount(): void
    {
        // Rekening 2: saldo awal 500rb, alokasi 200rb (u1) + 300rb (u2).
        $this->db->query("INSERT INTO db_transaksi_kas_bank (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah) VALUES
            ('2026-10-01', 1, 2, 'PEMASUKAN', 'MASUK', 100000),
            ('2026-10-02', 2, 2, 'PENGELUARAN', 'KELUAR', 50000)");

        $model = new ModelTransaksiKasBank();

        $fisik  = $model->getSaldoFisikAkun(2);   // 500rb + 100rb - 50rb
        $hakU1  = $model->getSaldoUnitAkun(2, 1);  // 200rb + 100rb
        $hakU2  = $model->getSaldoUnitAkun(2, 2);  // 300rb - 50rb

        $this->assertSame(550000, $fisik);
        $this->assertSame(300000, $hakU1);
        $this->assertSame(250000, $hakU2);

        // Konsolidasi = saldo FISIK satu kali, bukan penjumlahan hak unit.
        $this->assertSame($fisik, $model->getSaldoFisikAkun(2));
        $this->assertNotSame(550000 + 300000 + 250000, $fisik);

        // attributed unit punya hak hanya dari alokasi + arus unitnya.
        $this->assertSame(0, $model->getSaldoUnitAkun(2, 3), 'Unit tanpa alokasi tidak punya hak');
    }

    /** [6] cekAkunUntukUnit() menolak unit yang tidak punya hak. */
    public function testCekAkunUntukUnitMenolakUnitTanpaHak(): void
    {
        $scope = $this->scope();

        // Shared: unit harus punya baris alokasi.
        $this->assertTrue($scope->cekAkunUntukUnit($this->akun(2), 1));
        $this->assertTrue($scope->cekAkunUntukUnit($this->akun(2), 2));
        $this->assertFalse($scope->cekAkunUntukUnit($this->akun(2), 3));
        $this->assertFalse($scope->cekAkunUntukUnit($this->akun(2), 0));

        // Non-shared: hanya unit pemilik.
        $this->assertTrue($scope->cekAkunUntukUnit($this->akun(7), 3));
        $this->assertFalse($scope->cekAkunUntukUnit($this->akun(7), 1));
        $this->assertFalse($scope->cekAkunUntukUnit($this->akun(1), 2));

        // Tidak ada rekening yang "BANK => true".
        foreach ([1, 2, 3, 7, 8] as $id) {
            $akun = $this->akun($id);
            foreach ([1, 2, 3, 4] as $unit) {
                if (! $scope->isUnitEntitled($akun, $unit)) {
                    $this->assertFalse(
                        $scope->cekAkunUntukUnit($akun, $unit),
                        "akun {$id} tidak boleh true untuk unit {$unit}"
                    );
                }
            }
        }
    }

    /** [7] Invalid bankId tidak fallback ke KAS. */
    public function testInvalidBankIdTidakFallbackKeKas(): void
    {
        // Sudah ada di testResolveAkunTidakFallbackKeKasSaatBankTidakDitemukan;
        // di sini pastikan posting benar-benar di-skip, bukan salah classify.
        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank) VALUES (50, '2026-10-05', 'Setoran bank tanpa rekening', 90000, 1, 'BANK-TIDAK-ADA')");

        $r = $this->kasbank->postingKasMasuk(50);
        $this->assertSame('skipped', $r['status']);
        $this->assertSame(0, (int) $this->db->query("SELECT COUNT(*) AS c FROM db_transaksi_kas_bank WHERE sumber_tipe = 'kas_masuk' AND sumber_id = 50")->getRow()->c);
    }

    /** [8] getSaldoUnitAkun() menghormati account scope. */
    public function testGetSaldoUnitAkunMenghormatiAccountScope(): void
    {
        $model = new ModelTransaksiKasBank();

        // Unit 1 punya alokasi di akun 2 -> boleh.
        $this->assertSame(200000, $model->getSaldoUnitAkun(2, 1));
        // Unit 3 tidak punya alokasi -> 0, bukan alokasi 0 milik unit lain.
        $this->assertSame(0, $model->getSaldoUnitAkun(2, 3));
        // Non-shared milik Unit 3 -> unit 1 tidak boleh menarik saldo darinya.
        $this->assertSame(0, $model->getSaldoUnitAkun(7, 1));
        $this->assertSame(0, $model->getSaldoUnitAkun(7, 3));
    }

    // =====================================================================
    // AKSES BERDASARKAN ARAH — Finance/HO vs Unit vs Shared
    //
    // Fixture tambahan:
    //   akun 9  = Finance/HO (IRA) — unit_id NULL, is_shared 1, is_finance_ho 1
    //   akun 10 = Finance/HO kedua, untuk memastikan rule berlaku umum
    //   akun 2  = shared antar-unit, alokasi -> Unit 1 + Unit 2
    //   akun 7  = rekening UNIT, milik Unit 3
    //
    // Role: 0 = ADMIN CENTER, 1 = Admin root, 34 = Manager, 35 = ADMIN/KASIR
    // =====================================================================

    private const HO   = 9;   // IRA/BCA Finance
    private const HO2  = 10;  // HO kedua
    private const SH   = 2;   // shared Unit 1+2
    private const U3   = 7;   // rekening milik Unit 3

    private const ROLE_ADMIN_CENTER = 0;
    private const ROLE_ROOT         = 1;
    private const ROLE_MANAGER      = 34;
    private const ROLE_KASIR        = 35;

    /** [1] Unit 1 -> IRA = boleh sebagai TUJUAN. */
    public function testUnit1KeIraBoleh(): void
    {
        $s = $this->scope();

        $this->assertSame(KasBankScopeService::KIND_FINANCE_HO, $s->accountKind($this->akun(self::HO)));
        $this->assertTrue(
            $s->canUseAsDestination($this->akun(self::HO), 1, self::ROLE_KASIR),
            'Unit 1 harus boleh menerima dana ke IRA sebagai rekening tujuan'
        );
    }

    /** [2] Unit 2 -> IRA = juga boleh sebagai TUJUAN. */
    public function testUnit2KeIraBoleh(): void
    {
        $s = $this->scope();

        $this->assertTrue(
            $s->canUseAsDestination($this->akun(self::HO), 2, self::ROLE_KASIR),
            'Unit 2 harus boleh menerima dana ke IRA sebagai rekening tujuan'
        );
        $this->assertTrue($s->canUseAsDestination($this->akun(self::HO2), 2, self::ROLE_MANAGER));
    }

    /** [3] User biasa memakai IRA sebagai SUMBER = DITOLAK. */
    public function testUserBiasaTidakBolehIraSebagaiSumber(): void
    {
        $s = $this->scope();
        $ho = $this->akun(self::HO);

        // Manager, Admin/Kasir, dan Incluso role yang TIDAK ada di allowlist.
        $this->assertFalse($s->canUseAsSource($ho, 1, self::ROLE_MANAGER));
        $this->assertFalse($s->canUseAsSource($ho, 1, self::ROLE_KASIR));
        $this->assertFalse($s->canUseAsSource($ho, 1, 41));  // Kepala Toko
        $this->assertFalse($s->canUseAsSource($ho, 1, 40));  // SPV

        // Recording "boleh transfer ke IRA" tidak berarti boleh mencairkan IRA:
        // dua arah itu berbeda.
        $this->assertTrue($s->canUseAsDestination($ho, 1, self::ROLE_KASIR));
        $this->assertFalse($s->canUseAsSource($ho, 1, self::ROLE_KASIR));
    }

    /** [4] Admin Center boleh IRA sebagai SUMBER. */
    public function testAdminCenterBolehIraSebagaiSumber(): void
    {
        $s = $this->scope();

        $this->assertTrue(KasBankScopeService::roleBolehFinanceHoSource(self::ROLE_ADMIN_CENTER));
        $this->assertTrue($s->canUseAsSource($this->akun(self::HO), 1, self::ROLE_ADMIN_CENTER));
        $this->assertTrue($s->canUseAsSource($this->akun(self::HO2), 3, self::ROLE_ADMIN_CENTER));
    }

    /** [5] Admin Root boleh IRA sebagai SUMBER. */
    public function testAdminRootBolehIraSebagaiSumber(): void
    {
        $s = $this->scope();

        $this->assertTrue(KasBankScopeService::roleBolehFinanceHoSource(self::ROLE_ROOT));
        $this->assertTrue($s->canUseAsSource($this->akun(self::HO), 1, self::ROLE_ROOT));
        $this->assertTrue($s->canUseAsSource($this->akun(self::HO2), 4, self::ROLE_ROOT));
    }

    /** [6] Rekening Unit 3 tidak dapat dipakai Unit 1 (dua arah). */
    public function testRekeningUnit3TidakBisaDipakaiUnit1(): void
    {
        $s = $this->scope();
        $u3 = $this->akun(self::U3);

        $this->assertSame(KasBankScopeService::KIND_UNIT, $s->accountKind($u3));

        // Role sekuat apa pun tetap tidak boleh memakai rekening unit lain:
        // izin rekening UNIT ditentukan kepemilikan unit, bukan role.
        foreach ([self::ROLE_ROOT, self::ROLE_ADMIN_CENTER, self::ROLE_MANAGER, self::ROLE_KASIR] as $role) {
            $this->assertFalse(
                $s->canUseAsSource($u3, 1, $role),
                "role {$role} tidak boleh memakai rekening Unit 3 sebagai sumber untuk Unit 1"
            );
            $this->assertFalse($s->canUseAsDestination($u3, 1, $role));
        }

        // Pemiliknya sendiri tetap boleh.
        $this->assertTrue($s->canUseAsSource($u3, 3, self::ROLE_KASIR));
        $this->assertTrue($s->canUseAsDestination($u3, 3, self::ROLE_KASIR));
    }

    /** [7] Shared Unit 1+2 TIDAK bisa dipakai Unit 3. */
    public function testSharedUnit1Dan2TidakBisaDipakaiUnit3(): void
    {
        $s = $this->scope();
        $sh = $this->akun(self::SH);

        $this->assertSame(KasBankScopeService::KIND_SHARED, $s->accountKind($sh));

        foreach ([self::ROLE_ROOT, self::ROLE_ADMIN_CENTER, self::ROLE_MANAGER, self::ROLE_KASIR] as $role) {
            $this->assertFalse(
                $s->canUseAsSource($sh, 3, $role),
                "role {$role} tidak boleh memakai shared Unit 1+2 dari Unit 3"
            );
            $this->assertFalse($s->canUseAsDestination($sh, 3, $role));
        }
    }

    /** [8] Shared Unit 1+2 BISA dipakai Unit 1. */
    public function testSharedBisaDipakaiUnit1(): void
    {
        $s = $this->scope();
        $sh = $this->akun(self::SH);

        $this->assertTrue($s->canUseAsSource($sh, 1, self::ROLE_KASIR));
        $this->assertTrue($s->canUseAsDestination($sh, 1, self::ROLE_KASIR));
    }

    /** [9] Shared Unit 1+2 BISA dipakai Unit 2. */
    public function testSharedBisaDipakaiUnit2(): void
    {
        $s = $this->scope();
        $sh = $this->akun(self::SH);

        $this->assertTrue($s->canUseAsSource($sh, 2, self::ROLE_KASIR));
        $this->assertTrue($s->canUseAsDestination($sh, 2, self::ROLE_KASIR));
    }

    /** [10] bankId invalid TIDAK fallback ke KAS (fix sebelumnya). */
    public function testBankIdInvalidTidakFallbackKeKas(): void
    {
        // ARMATURASALAH: rekening yang TIDAK ditemukan -> null, bukan KAS.
        $this->assertNull($this->kasbank->resolveAkun(1, 'BANK-HANTU', ModeKasBank::ARAH_KELUAR));

        // Rekening shared yang tidak dialokasikan ke unit tsb -> null.
        $this->assertNull($this->kasbank->resolveAkun(3, 'BNI-001', ModeKasBank::ARAH_KELUAR));

        // FINANCE/HO tanpa izin role -> null.
        $this->assertNull($this->kasbank->resolveAkun(1, 'IRA-001', ModeKasBank::ARAH_KELUAR, self::ROLE_KASIR));

        // Fallback KAS hanya jalan bila bankId memang tidak diberikan.
        $this->assertSame(1, $this->kasbank->resolveAkun(1, null, ModeKasBank::ARAH_KELUAR));
    }

    /** [10b] Finance/HO sebagai SUMBER hanya untuk ROOT / ADMIN CENTER. */
    public function testResolveAkunIraSebagaiSumberHanyaUntukRootDanCenter(): void
    {
        $this->assertSame(
            self::HO,
            $this->kasbank->resolveAkun(1, 'IRA-001', ModeKasBank::ARAH_KELUAR, self::ROLE_ROOT)
        );
        $this->assertSame(
            self::HO,
            $this->kasbank->resolveAkun(1, 'IRA-001', ModeKasBank::ARAH_KELUAR, self::ROLE_ADMIN_CENTER)
        );

        foreach ([self::ROLE_MANAGER, self::ROLE_KASIR, 2 /* Direktur */] as $role) {
            $this->assertNull(
                $this->kasbank->resolveAkun(1, 'IRA-001', ModeKasBank::ARAH_KELUAR, $role),
                "role {$role} tidak boleh menarik dana dari IRA"
            );
        }
    }

    /** [10c] Finance/HO sebagai TUJUAN boleh dari unit mana pun. */
    public function testResolveAkunIraSebagaiTujuanBolehDariUnitManaPun(): void
    {
        foreach ([1, 2, 3, 4] as $unit) {
            $this->assertSame(
                self::HO,
                $this->kasbank->resolveAkun($unit, 'IRA-001', ModeKasBank::ARAH_MASUK, self::ROLE_KASIR),
                "Unit {$unit} -> IRA harus boleh"
            );
        }
    }

    /** [11] Konsolidasi tidak double-count saldo shared. */
    public function testKonsolidasiTidakDoubleCountSaldoShared(): void
    {
        $this->db->query("INSERT INTO db_transaksi_kas_bank (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah) VALUES
            ('2026-10-01', 1, 2, 'PEMASUKAN', 'MASUK', 100000),
            ('2026-10-02', 2, 2, 'PENGELUARAN', 'KELUAR', 50000)");

        $model = new ModelTransaksiKasBank();
        $fisik = $model->getSaldoFisikAkun(self::SH);
        $u1    = $model->getSaldoUnitAkun(self::SH, 1);
        $u2    = $model->getSaldoUnitAkun(self::SH, 2);

        // Saldo fisik: opening 500rb + 100rb - 50rb = 550rb, dihitung SEKALI.
        $this->assertSame(550000, $fisik);

        // Hak alokasi per unit TIDAK dijumlahkan jadi saldo konsolidasi.
        $this->assertSame(300000, $u1);
        $this->assertSame(250000, $u2);
        $this->assertSame($fisik, $model->getSaldoFisikAkun(self::SH));
        $this->assertNotSame($fisik + $u1 + $u2, $fisik);
    }

    /** [11b] Konsolidasi tidak memasukkan rekening HO yang belum ditandai. */
    public function testKonsolidasiTidakMasukkanRekeningSharedTanpaAlokasi(): void
    {
        $s = $this->scope();

        // Konsolidasi user 1+2+3: rekening 2 (shared, dialokasikan) ikut, tapi
        // rekening shared tanpa alokasi TIDAK ikut.
        $this->db->query("INSERT INTO db_akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (11, NULL, 'BANK', 'Shared tanpa alokasi', 'BNI-004', 'aktif', 1, 0)");
        $s->flushCache();

        $ids = array_map('intval', array_column($s->akunTerlihat([1, 2, 3], null), 'idakun_kas_bank'));
        $this->assertContains(self::SH, $ids, 'Shared yang dialokasikan ikut konsolidasi');
        $this->assertContains(self::HO, $ids, 'Finance/HO ikut: boleh jadi tujuan dari unit mana pun');
        $this->assertNotContains(11, $ids, 'Shared tanpa alokasi tidak boleh bocor ke konsolidasi');
    }

    // =====================================================================
    // VERIFIKASI WAJIB — akun 1 sebagai Finance/HO, akun 4 tetap shared
    // =====================================================================

    private const IRA      = 1;  // Finance/HO
    private const NON_HO_4 = 4;  // shared tanpa alokasi, BUKAN HO

    /** Bridge ke data produksi: akun 1 = IRA/HO, akun 4 = shared biasa. */
    private function akunProduksi(int $id): ?object
    {
        $db  = \Config\Database::connect('default', false);
        $row = $db->query('SELECT * FROM akun_kas_bank WHERE idakun_kas_bank = ' . $id)->getRow();
        if ($row === null) {
            $this->markTestSkipped('akun_kas_bank tidak ada di DB default');
        }

        return $row;
    }

    public function testAkun1TerpantauFinanceHo(): void
    {
        $a = $this->akunProduksi(self::IRA);

        $this->assertSame('Bank BCA Admin Center/Finance 0391943558 (IRA)', $a->nama_akun);
        $this->assertSame(1, (int) $a->is_finance_ho, 'akun 1 harus ditandai Finance/HO');
        $this->assertNull($a->unit_id, 'unit_id tetap NULL');
        $this->assertSame(1, (int) $a->is_shared, 'is_shared tetap 1');
        $this->assertSame('aktif', $a->status);
    }

    public function testAkun1TidakButuhAlokasi(): void
    {
        $s  = $this->scope();
        $a1 = $this->akunProduksi(self::IRA);

        $this->assertTrue($s->isFinanceHo($a1));
        $this->assertSame([], $s->accountAllowedUnits($a1), 'HO tidak punya unit pemilik');
        $this->assertSame([], $s->entitledUnitIds(self::IRA), 'HO tidak punya alokasi');
        $this->assertFalse($s->cekAkunUntukUnit($a1, 1), 'HO tidak punya hak unit');

        // Tetap bisa dipakai dari setiap unit sebagai TUJUAN.
        foreach ([1, 2, 3] as $u) {
            $this->assertTrue($s->canUseAsDestination($a1, $u, self::ROLE_KASIR));
        }
    }

    public function testAkun4TetapSharedBukanFinanceHo(): void
    {
        $s  = $this->scope();
        $a4 = $this->akunProduksi(self::NON_HO_4);

        $this->assertSame(0, (int) $a4->is_finance_ho, 'akun 4 TIDAK boleh jadi Finance/HO');
        $this->assertFalse($s->isFinanceHo($a4));
        $this->assertSame(
            KasBankScopeService::KIND_SHARED,
            $s->accountKind($a4),
            'akun 4 tetap Shared Antar Unit, jadi butuh alokasi'
        );
        // Tanpa alokasi -> tidak entitled ke unit mana pun.
        $this->assertSame([], $s->entitledUnitIds(self::NON_HO_4));
        $this->assertFalse($s->canUseAsDestination($a4, 1, self::ROLE_ROOT));
        $this->assertFalse($s->canUseAsSource($a4, 1, self::ROLE_ROOT));
    }

    public function testSharedTetapButuhAlokasi(): void
    {
        $s  = $this->scope();
        $sh = $this->akun(self::SH);   // shared Unit 1+2, ada alokasi

        $this->assertSame(
            KasBankScopeService::KIND_SHARED,
            $s->accountKind($sh)
        );
        $this->assertTrue($s->canUseAsSource($sh, 1, self::ROLE_KASIR));
        $this->assertTrue($s->canUseAsSource($sh, 2, self::ROLE_KASIR));
        $this->assertFalse($s->canUseAsSource($sh, 3, self::ROLE_ROOT));
    }

    public function testAkun1TujuanBolehUntukSemuaUnit(): void
    {
        $s  = $this->scope();
        $a1 = $this->akunProduksi(self::IRA);

        foreach ([1, 2, 3, 4] as $unit) {
            $this->assertTrue(
                $s->canUseAsDestination($a1, $unit, self::ROLE_KASIR),
                "Unit {$unit} -> IRA harus boleh"
            );
        }
    }

    public function testAkun1SumberHanyaRole0Dan1(): void
    {
        $s  = $this->scope();
        $a1 = $this->akunProduksi(self::IRA);

        $this->assertTrue($s->canUseAsSource($a1, 1, self::ROLE_ADMIN_CENTER));
        $this->assertTrue($s->canUseAsSource($a1, 1, self::ROLE_ROOT));

        foreach ([self::ROLE_MANAGER, self::ROLE_KASIR, 2, 40, 41] as $role) {
            $this->assertFalse(
                $s->canUseAsSource($a1, 1, $role),
                "role {$role} tidak boleh memakai IRA sebagai sumber"
            );
        }
    }

    public function testResolveAkunTetapTidakFallbackKeKas(): void
    {
        // bankId tidak dikenal -> null, bukan KAS unit.
        $this->assertNull($this->kasbank->resolveAkun(1, 'BANK-TIDAK-ADA', ModeKasBank::ARAH_KELUAR));
        $this->assertNull($this->kasbank->resolveAkun(2, 'BANK-TIDAK-ADA', ModeKasBank::ARAH_MASUK));

        // Hanya tanpa bankId-lah KAS yang dipakai.
        $this->assertSame(1, $this->kasbank->resolveAkun(1, null, ModeKasBank::ARAH_KELUAR));
    }

    public function testRoleBolehFinanceHoSourceSesuaiKonfigurasi(): void
    {
        $roles = KasBankScopeService::financeHoSourceRoles();
        sort($roles);

        $this->assertSame([self::ROLE_ADMIN_CENTER, self::ROLE_ROOT], $roles);
    }
}
