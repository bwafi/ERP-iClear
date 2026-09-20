<?php

namespace Tests;

use App\Libraries\ModeKasBank;
use App\Models\ModelHutangPiutang;
use App\Models\ModelTransaksiKasBank;
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

        $q('CREATE TABLE IF NOT EXISTS db_unit (idunit INTEGER PRIMARY KEY AUTO_INCREMENT, NAMA_UNIT TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_bank (idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL, nama_bank TEXT NULL, atas_nama TEXT NULL, norek TEXT NULL, isppn INT NULL, gambar_qris TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                created_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                keterangan TEXT NULL, bukti TEXT NULL, input_by INT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_hutang_piutang (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                kode TEXT NULL, jenis TEXT NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                is_projection INT NULL, pihak_tipe TEXT NULL, pihak_id INT NULL,
                lawan_unit_id INT NULL, nama_pihak TEXT NULL, tanggal TEXT NULL,
                jatuh_tempo TEXT NULL, uraian TEXT NULL, total REAL NULL, total_dibayar REAL NULL,
                sisa REAL NULL, status TEXT NULL, keterangan TEXT NULL, unit_id INT NULL,
                input_by INT NULL, deleted INT DEFAULT 0, created_at TEXT NULL, updated_at TEXT NULL)');
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

        $this->db->query("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit A'), (2, 'Unit B')");
        $this->db->query("INSERT INTO db_bank (idbank, nama_bank, norek, atas_nama) VALUES ('BNI-001', 'Bank BNI', '555', 'PT Contoh')");

        $this->db->query("INSERT INTO db_akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status) VALUES
            (1, 1, 'KAS',  'Kas Unit A',  NULL, 'aktif'),
            (2, 1, 'BANK', 'Bank BNI A',  'BNI-001', 'aktif'),
            (3, 2, 'KAS',  'Kas Unit B',  NULL, 'aktif'),
            (4, 2, 'BANK', 'Bank BNI B',  'BNI-001', 'aktif'),
            (5, 1, 'BANK', 'Bank Nonaktif', 'BNI-001', 'nonaktif')");

        $this->db->query("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan) VALUES (2, '2026-01-01', 500000, 'Saldo awal BNI A')");
    }

    public function testResolveAkunBankLebihDiutamakanDariKas(): void
    {
        $this->assertSame(2, $this->kasbank->resolveAkun(1, 'BNI-001'));
        $this->assertSame(4, $this->kasbank->resolveAkun(2, 'BNI-001'));
    }

    public function testResolveAkunFallbackKeKasSaatBankTidakCocok(): void
    {
        $this->assertSame(1, $this->kasbank->resolveAkun(1, null));
        $this->assertSame(1, $this->kasbank->resolveAkun(1, 'BANK-TIDAK-ADA'));
    }

    public function testResolveAkunNullSaatTidakAdaAkunAktif(): void
    {
        $this->assertNull($this->kasbank->resolveAkun(99, null));
    }

    public function testPostingKasMasukIdempotent(): void
    {
        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, idunit, idbank) VALUES (1, '2026-02-01', 'Penjualan tunai', 50000, 1, 'BNI-001')");

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

        $r1 = $this->kasbank->seedAkunDefault();
        $this->assertSame(2, $r1['created']);
        $this->assertSame(0, $r1['skipped']);

        $kasA = $this->db->query("SELECT * FROM db_akun_kas_bank WHERE unit_id = 1 AND tipe = 'KAS'")->getRow();
        $this->assertNotNull($kasA);
        $this->assertSame('Kas Unit A', $kasA->nama_akun);
        $this->assertSame('1010101000', $kasA->no_akun_coa);
        $this->assertSame('aktif', $kasA->status);

        $r2 = $this->kasbank->seedAkunDefault();
        $this->assertSame(0, $r2['created']);
        $this->assertSame(2, $r2['skipped']);
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
}