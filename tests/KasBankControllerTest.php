<?php

namespace Tests\Support;

use App\Libraries\ModeKasBank;
use App\Models\ModelHutangPiutang;
use App\Models\ModelPembayaranHutangPiutang;
use App\Models\ModelTransaksiKasBank;
use App\Services\Finance\HutangPiutangService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Feature test modul Kas & Bank: duplikat submit transfer / antar unit,
 * reversal hanya via POST, dan update kas masuk/keluar dalam satu transaksi
 * (posting ikut di-refresh, idempotent).
 *
 * Catatan: AuthFilter.after() menutup koneksi shared tiap request, jadi setiap
 * query verifikasi dibuat via koneksi fresh + reconnect agar deterministik.
 */
class KasBankControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->buatSkema();
        $this->sebarData();
    }

    protected function buatSkema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'db_transaksi_kas_bank',
            'db_pembayaran_hutang_piutang',
            'db_akun_kas_bank',
            'db_alokasi_saldo_kas_bank',
            'db_saldo_awal_kas_bank',
            'db_hutang_piutang',
            'db_kas_masuk',
            'db_kas_keluar',
            'db_bank',
            'db_akun',
            'db_unit',
            'db_spv_units',
            'db_mutasi',
            'db_detail_mutasi',
            'db_barang',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        $q('CREATE TABLE db_unit (idunit INTEGER PRIMARY KEY AUTO_INCREMENT, NAMA_UNIT TEXT NULL)');
        $q('CREATE TABLE db_bank (idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL, nama_bank TEXT NULL, atas_nama TEXT NULL, norek TEXT NULL, isppn INT NULL, gambar_qris TEXT NULL)');
        $q('CREATE TABLE db_akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE db_akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0,
                created_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE db_saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE db_alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE db_transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                keterangan TEXT NULL, bukti TEXT NULL, input_by INT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
        $q('ALTER TABLE db_transaksi_kas_bank ADD UNIQUE KEY uniq_tkb_submission (submission_key)');
        $q('CREATE TABLE db_hutang_piutang (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                kode TEXT NULL, jenis TEXT NULL, sumber_tipe TEXT NULL, sumber_id INT NULL,
                is_projection INT NULL, pihak_tipe TEXT NULL, pihak_id INT NULL,
                lawan_unit_id INT NULL, nama_pihak TEXT NULL, tanggal TEXT NULL,
                jatuh_tempo TEXT NULL, uraian TEXT NULL, total REAL NULL, total_dibayar REAL NULL,
                sisa REAL NULL, status TEXT NULL, keterangan TEXT NULL, unit_id INT NULL,
                input_by INT NULL, deleted INT DEFAULT 0, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE db_pembayaran_hutang_piutang (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                hutang_piutang_id INT NULL, tanggal_bayar TEXT NULL, jumlah_bayar REAL NULL,
                bayar_tunai REAL NULL, bayar_bank REAL NULL, bank_idbank TEXT NULL,
                sumber TEXT NULL, referensi_tipe TEXT NULL, referensi_id INT NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE db_kas_masuk (
                idkas_masuk INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, kategori_idkategori INT NULL, no_akun TEXT NULL,
                deskripsi TEXT NULL, jumlah REAL NULL, jenis TEXT NULL, penerima TEXT NULL,
                idbank TEXT NULL, idunit INT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        $q('CREATE TABLE db_kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, kategori_idkategori INT NULL, no_akun TEXT NULL,
                deskripsi TEXT NULL, jumlah REAL NULL, jenis TEXT NULL, penerima TEXT NULL,
                idbank TEXT NULL, idunit INT NULL, created_on TEXT NULL, updated_on TEXT NULL)');

        // Tabel pendukung yang dipakai BaseController.initController (notifikasi stok/service)
        // pada setiap request. Dibuat minimal agar FeatureTest tidak gagal.
        $q('CREATE TABLE IF NOT EXISTS db_stok_barang (
                idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL, stok_minimum REAL NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_service (
                id_service INT PRIMARY KEY, no_service TEXT NULL, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        $q('CREATE TABLE db_mutasi (
                idmutasi INTEGER PRIMARY KEY AUTO_INCREMENT,
                no_nota_mutasi TEXT NULL, tanggal_kirim TEXT NULL, tanggal_terima TEXT NULL,
                status TEXT NULL, kirim_idunit INT NULL, terima_idunit INT NULL,
                input_by INT NULL, created_on TEXT NULL, updated_on TEXT NULL)');
        $q('CREATE TABLE db_detail_mutasi (
                iddetail_mutasi INTEGER PRIMARY KEY AUTO_INCREMENT,
                mutasi_idmutasi INT NULL, jumlah_kirim REAL NULL, jumlah_terima REAL NULL,
                satuan TEXT NULL, harga_mutasi REAL NULL, hpp_barang REAL NULL, barang_idbarang INT NULL,
                kirim_idunit INT NULL, terima_idunit INT NULL)');
        $q('CREATE TABLE db_barang (idbarang INTEGER PRIMARY KEY, nama_barang TEXT NULL)');
    }

    protected function sebarData(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'db_transaksi_kas_bank',
            'db_pembayaran_hutang_piutang',
            'db_akun_kas_bank',
            'db_alokasi_saldo_kas_bank',
            'db_saldo_awal_kas_bank',
            'db_hutang_piutang',
            'db_kas_masuk',
            'db_kas_keluar',
            'db_bank',
            'db_akun',
            'db_unit',
            'db_spv_units',
            'db_mutasi',
            'db_detail_mutasi',
            'db_barang',
        ] as $tabel) {
            $q('DELETE FROM ' . $tabel);
        }

        $q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit A'), (2, 'Unit B')");
        $q("INSERT INTO db_bank (idbank, nama_bank, atas_nama, norek) VALUES ('BNI-001', 'BNI', 'PT Contoh', '5551')");
        $q("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES (43, 1, 1, 'Admin Center')");
        $q("INSERT INTO db_akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa, status, is_shared) VALUES
            (1, 1, 'KAS', 'Kas Unit A', NULL, '1010101000', 'aktif', 0),
            (2, 1, 'BANK', 'BNI Unit A', 'BNI-001', '1010202000', 'aktif', 0),
            (3, 2, 'KAS', 'Kas Unit B', NULL, '1010102000', 'aktif', 0),
            (4, 2, 'BANK', 'BNI Unit B', 'BNI-001', '1010202010', 'aktif', 0),
            (5, NULL, 'BANK', 'BCA Bersama Jember', 'BCA-001', '1010202020', 'aktif', 1)");
        $q("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan) VALUES
            (5, '2026-01-01', 800000, 'Saldo awal BCA Bersama')");
        $q("INSERT INTO db_hutang_piutang (id, kode, jenis, sumber_tipe, sumber_id, is_projection, pihak_tipe, pihak_id, lawan_unit_id, nama_pihak, tanggal, uraian, total, total_dibayar, sisa, status, unit_id, input_by, deleted, created_at) VALUES
            (901, 'H-901', 'hutang', 'mutasi_unit', 999, 0, 'unit', 1, 2, 'Unit A', '2026-09-01', 'Transfer barang', 500000, 0, 500000, 'belum_lunas', 1, 43, 0, '2026-09-01 08:00:00'),
            (902, 'P-902', 'piutang', 'mutasi_unit', 999, 0, 'unit', 2, 1, 'Unit B', '2026-09-01', 'Transfer barang', 500000, 0, 500000, 'belum_lunas', 2, 43, 0, '2026-09-01 08:00:00')");

        $q("INSERT INTO db_mutasi (idmutasi, no_nota_mutasi, tanggal_kirim, tanggal_terima, status, kirim_idunit, terima_idunit, input_by) VALUES
            (1, 'MTS1000921001', '2026-09-20 09:00:00', NULL, '0', 1, 2, 43)");
        $q("INSERT INTO db_detail_mutasi (iddetail_mutasi, mutasi_idmutasi, jumlah_kirim, harga_mutasi, hpp_barang, barang_idbarang, kirim_idunit, terima_idunit) VALUES
            (1, 1, 2, 1000, 800, 1001, 1, 2)");
        $q("INSERT INTO db_barang (idbarang, nama_barang) VALUES (1001, 'Barang X')");
    }

    private function sesiDenganToken(string $tok): void
    {
        $this->withSession([
            'logged_in'        => true,
            'ID_AKUN'          => 43,
            'ID_UNIT'          => 1,
            'ID_JABATAN'       => 1,
            'kb_submit_' . $tok => time(),
        ]);
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
     * Model baru dengan koneksi pasti hidup (mengatasi AuthFilter yang
     * menutup koneksi shared setelah tiap request).
     */
    private function model(string $class): object
    {
        // AuthFilter menutup koneksi shared setelah tiap request; koneksi yang
        // sama di-close lalu di-initialize ulang kehilangan database terpilih.
        // Pakai koneksi non-shared yang selalu fresh ke group 'tests'.
        $db = \Config\Database::connect('tests', false);
        $m  = new $class($db);

        return $m;
    }

    public function testTransferSaveDanDuplicateSubmitDitolak(): void
    {
        $tok = 'tok-trf-dup1';
        $this->sesiDenganToken($tok);

        $payload = [
            'akun_asal_id'   => '1',
            'akun_tujuan_id' => '3',
            'jumlah'         => '100000',
            'tanggal'        => '2026-09-01',
            'keterangan'     => 'Transfer A->B',
            'submit_token'   => $tok,
        ];

        // Submit pertama sukses: 2 baris ledger (KELUAR + MASUK).
        $r1 = $this->post('kas_bank/transfer/save', $payload);
        $r1->assertStatus(302);
        $this->assertSame(2, $this->model(ModelTransaksiKasBank::class)->where('jenis', ModeKasBank::JENIS_TRANSFER)->countAllResults());

        // Submit kedua (double-click) ditolak: token terkonsumsi / UNIQUE submission_key.
        $r2 = $this->post('kas_bank/transfer/save', $payload);
        $r2->assertStatus(302);
        $this->assertSame(2, $this->model(ModelTransaksiKasBank::class)->where('jenis', ModeKasBank::JENIS_TRANSFER)->countAllResults());
    }

    public function testReversalTransferHanyaViaPost(): void
    {
        $m   = $this->model(ModelTransaksiKasBank::class);
        $ref = 'TRF-RVT';
        $m->insert([
            'tanggal'          => '2026-09-01',
            'unit_id'          => 1,
            'akun_kas_bank_id' => 1,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => ModeKasBank::ARAH_KELUAR,
            'jumlah'           => 50000,
            'akun_tujuan_id'   => 3,
            'transfer_ref'     => $ref,
        ]);
        $idKeluar = (int) $m->insertID();
        $m->insert([
            'tanggal'          => '2026-09-01',
            'unit_id'          => 2,
            'akun_kas_bank_id' => 3,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => ModeKasBank::ARAH_MASUK,
            'jumlah'           => 50000,
            'akun_tujuan_id'   => 1,
            'transfer_ref'     => $ref,
        ]);

        // Route sekarang POST-only: GET harus ditolak (PageNotFoundException).
        try {
            $this->get('kas_bank/transfer/reversal/' . $idKeluar);
            $this->fail('GET ke route reversal POST-only seharusnya ditolak.');
        } catch (PageNotFoundException $e) {
            $this->addToAssertionCount(1);
        }

        $this->sesi();
        $r = $this->post('kas_bank/transfer/reversal/' . $idKeluar);
        $r->assertStatus(302);
        $this->assertSame(0, $this->model(ModelTransaksiKasBank::class)->where('transfer_ref', $ref)->countAllResults());
    }

    public function testAntarUnitSaveDuplicateDanReversal(): void
    {
        $tok = 'tok-but-dup1';
        $this->sesiDenganToken($tok);

        $payload = [
            'hutang_piutang_id' => '901',
            'akun_pengirim_id'  => '1',
            'akun_penerima_id'  => '3',
            'jumlah'            => '200000',
            'tanggal'           => '2026-09-01',
            'keterangan'        => 'Bayar antar unit',
            'submit_token'      => $tok,
        ];

        // Submit pertama: 2 ledger + 2 baris pembayaran + sisa H/P berkurang.
        $r1 = $this->post('kas_bank/antar-unit/save', $payload);
        $r1->assertStatus(302);
        $this->assertSame(2, $this->model(ModelTransaksiKasBank::class)->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)->countAllResults());
        $this->assertSame(2, $this->model(ModelPembayaranHutangPiutang::class)->countAllResults());
        $this->assertSame(300000, (int) $this->model(ModelHutangPiutang::class)->find(901)->sisa);
        $this->assertSame(300000, (int) $this->model(ModelHutangPiutang::class)->find(902)->sisa);

        // Double submit ditolak: tidak ada baris baru, sisa tidak berubah.
        $r2 = $this->post('kas_bank/antar-unit/save', $payload);
        $r2->assertStatus(302);
        $this->assertSame(2, $this->model(ModelTransaksiKasBank::class)->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)->countAllResults());
        $this->assertSame(2, $this->model(ModelPembayaranHutangPiutang::class)->countAllResults());
        $this->assertSame(300000, (int) $this->model(ModelHutangPiutang::class)->find(901)->sisa);

        // Reversal via POST: semua dibersihkan, sisa H/P dikembalikan.
        $idKeluar = (int) $this->model(ModelTransaksiKasBank::class)
            ->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)
            ->where('arah', ModeKasBank::ARAH_KELUAR)
            ->first()->idtransaksi;

        $rR = $this->post('kas_bank/antar-unit/reversal/' . $idKeluar);
        $rR->assertStatus(302);
        $this->assertSame(0, $this->model(ModelTransaksiKasBank::class)->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)->countAllResults());
        $this->assertSame(0, $this->model(ModelPembayaranHutangPiutang::class)->countAllResults());
        $this->assertSame(500000, (int) $this->model(ModelHutangPiutang::class)->find(901)->sisa);
        $this->assertSame(500000, (int) $this->model(ModelHutangPiutang::class)->find(902)->sisa);
    }

    public function testUpdateKasMasukRefreshPostingDalamSatuTransaksi(): void
    {
        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, idunit, idbank, tanggal, kategori_idkategori, deskripsi, jumlah, jenis, penerima, updated_on)
            VALUES (700, 1, NULL, '2026-09-01', 1, 'Penjualan', 50000, 'debet', 'Pembeli', '2026-09-01 09:00:00')");
        $this->sesi();

        $payload = [
            'idkas_masuk'         => '700',
            'tanggal'             => '2026-09-01',
            'kategori_idkategori' => '1',
            'deskripsi'           => 'Penjualan update',
            'jumlah'              => '75000',
            'penerima'            => 'BNI-001',
            'posisi_drk'          => 'debet',
        ];

        $r = $this->post('update_kas_masuk', $payload);
        $r->assertStatus(302);

        $ledger = $this->model(ModelTransaksiKasBank::class)
            ->where('sumber_tipe', 'kas_masuk')
            ->where('sumber_id', 700)
            ->findAll();
        $this->assertCount(1, $ledger);
        $this->assertSame(75000, (int) $ledger[0]->jumlah);
        $this->assertSame(ModeKasBank::ARAH_MASUK, $ledger[0]->arah);

        $this->db->reconnect();
        $row = $this->db->query('SELECT * FROM db_kas_masuk WHERE idkas_masuk = 700')->getRow();
        $this->assertSame(75000, (int) $row->jumlah);
        $this->assertSame('BNI-001', $row->idbank);

        // Update ulang (data sama) -> repost idempotent, ledger tetap 1 baris.
        $this->post('update_kas_masuk', $payload);
        $this->assertCount(1, $this->model(ModelTransaksiKasBank::class)
            ->where('sumber_tipe', 'kas_masuk')
            ->where('sumber_id', 700)
            ->findAll());
    }

    public function testUpdateKasKeluarRefreshPostingDalamSatuTransaksi(): void
    {
        $this->db->query("INSERT INTO db_kas_keluar (idkas_keluar, idunit, idbank, tanggal, kategori_idkategori, deskripsi, jumlah, jenis, penerima, updated_on)
            VALUES (800, 1, NULL, '2026-09-01', 1, 'Pembelian', 60000, 'kredit', 'Supplier', '2026-09-01 09:00:00')");
        $this->sesi();

        $payload = [
            'idkas_keluar'        => '800',
            'tanggal'             => '2026-09-01',
            'kategori_idkategori' => '1',
            'deskripsi'           => 'Pembelian update',
            'jumlah'              => '90000',
            'penerima'            => 'BNI-001',
            'posisi_drk'          => 'kredit',
        ];

        $r = $this->post('update_kas_keluar', $payload);
        $r->assertStatus(302);

        $ledger = $this->model(ModelTransaksiKasBank::class)
            ->where('sumber_tipe', 'kas_keluar')
            ->where('sumber_id', 800)
            ->findAll();
        $this->assertCount(1, $ledger);
        $this->assertSame(90000, (int) $ledger[0]->jumlah);
        $this->assertSame(ModeKasBank::ARAH_KELUAR, $ledger[0]->arah);

        $this->db->reconnect();
        $row = $this->db->query('SELECT * FROM db_kas_keluar WHERE idkas_keluar = 800')->getRow();
        $this->assertSame(90000, (int) $row->jumlah);
        $this->assertSame('BNI-001', $row->idbank);
    }

    /**
     * Skenario rekening fisik bersama (BCA Jember + Probolinggo):
     * transfer internal TIDAK mengubah total kas fisik; keran hanya antar
     * rekening FISIK yang berbeda.
     */
    public function testTransferTidakMengubahTotalKasFisik(): void
    {
        $tok = 'tok-shared-trf';
        $this->sesiDenganToken($tok);

        $payload = [
            'akun_asal_id'   => '1',
            'akun_tujuan_id' => '3',
            'jumlah'         => '250000',
            'tanggal'        => '2026-09-02',
            'keterangan'     => 'Transfer fisik A->B',
            'submit_token'   => $tok,
        ];

        $m = $this->model(ModelTransaksiKasBank::class);
        $fisik = static function () use ($m): int {
            $total = 0;
            foreach ([1, 2, 3, 4, 5] as $akunId) {
                $total += $m->getSaldoFisikAkun($akunId);
            }
            return $total;
        };

        $sebelum = $fisik();
        $r = $this->post('kas_bank/transfer/save', $payload);
        $r->assertStatus(302);

        $this->assertSame(2, $m->where('jenis', ModeKasBank::JENIS_TRANSFER)->countAllResults());
        // Net nol: KELUAR 250k di akun 1, MASUK 250k di akun 3.
        $this->assertSame($sebelum, $fisik());
    }

    public function testTransferRekeningFisikSamaDitolak(): void
    {
        $tok = 'tok-shared-same';
        $this->sesiDenganToken($tok);

        // Akun 5 = BCA Bersama (rekening fisik lintas unit). Memindahkan antar
        // unit yang memakai rekening SAMA bukan transfer internal.
        $payload = [
            'akun_asal_id'   => '5',
            'akun_tujuan_id' => '5',
            'jumlah'         => '100000',
            'tanggal'        => '2026-09-02',
            'keterangan'     => 'Bukan transfer',
            'submit_token'   => $tok,
        ];

        $r = $this->post('kas_bank/transfer/save', $payload);
        $r->assertStatus(302);
        $this->assertSame(0, $this->model(ModelTransaksiKasBank::class)
            ->where('jenis', ModeKasBank::JENIS_TRANSFER)
            ->countAllResults());
    }

    public function testAlokasiSaldoTidakBolehMelebihiFisik(): void
    {
        $this->sesi();

        // BCA Bersama (akun 5): saldo awal fisik 800rb, alokasi unit A 500rb -> ok.
        $r1 = $this->post('kas_bank/saldo-alokasi/save', [
            'akun_kas_bank_id' => '5',
            'unit_id'          => '1',
            'nominal'          => '500000',
            'keterangan'       => 'Alokasi Jember',
        ]);
        $r1->assertStatus(302);

        $db = \Config\Database::connect('tests', false);
        $total = (int) $db->table('alokasi_saldo_kas_bank')->selectSum('nominal')
            ->where('akun_kas_bank_id', 5)->get()->getRow()->nominal;
        $this->assertSame(500000, $total);

        // Alokasi unit B 400rb lagi -> total 900rb > fisik 800rb -> ditolak.
        $r2 = $this->post('kas_bank/saldo-alokasi/save', [
            'akun_kas_bank_id' => '5',
            'unit_id'          => '2',
            'nominal'          => '400000',
            'keterangan'       => 'Alokasi Probolinggo',
        ]);
        $r2->assertStatus(302);

        $total = (int) $db->table('alokasi_saldo_kas_bank')->selectSum('nominal')
            ->where('akun_kas_bank_id', 5)->get()->getRow()->nominal;
        $this->assertSame(500000, $total);
    }

/**
 * Admin cabang (non-lintas, ID_JABATAN 35, unit 1) hanya boleh akses
 * rekening unitnya: KAS unit + BANK milik unit + rekening bersama yang
 * dialokasikan ke unit tsb. Rekening unit lain ditolak (guard server).
 */
public function testAdminCabangHanyaAksesRekeningUnitnya(): void
{
        $this->db->query("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES (44, 1, 35, 'Admin Cabang')");
        // BCA Bersama (akun 5) dialokasikan ke unit 1 -> menjadi "rekening unit 1".
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal) VALUES (5, 1, 300000)");

        // Visibility (model): unit 1 hanya melihat KAS 1, BANK milik 2, dan
        // rekening bersama 5 yang dialokasikan; akun 3/4 (unit 2) tidak ada.
        $db = \Config\Database::connect('tests', false);
        $model = new \App\Models\ModelAkunKasBank($db);
        $ids = array_map('intval', array_column($model->getAllWithUnitTerbatas(1), 'idakun_kas_bank'));
        sort($ids);
        $this->assertSame([1, 2, 5], $ids);
        $idsAktif = array_map('intval', array_column($model->getAktifUntukUnitTerbatas(1), 'idakun_kas_bank'));
        sort($idsAktif);
        $this->assertSame([1, 2, 5], $idsAktif);

        // Guard server: transfer memakai akun unit lain (akun 3/KAS Unit B) ditolak.
        $tok = 'tok-cabang-trf';
        $this->withSession([
            'logged_in'         => true,
            'ID_AKUN'           => 44,
            'ID_UNIT'           => 1,
            'ID_JABATAN'        => 35,
            'kb_submit_' . $tok => time(),
        ]);
        $r2 = $this->post('kas_bank/transfer/save', [
            'akun_asal_id'   => '1',
            'akun_tujuan_id' => '3',
            'jumlah'         => '100000',
            'tanggal'        => '2026-09-02',
            'keterangan'     => 'Coba lintas unit',
            'submit_token'   => $tok,
        ]);
        $r2->assertStatus(302);
        $this->assertSame(0, $this->model(ModelTransaksiKasBank::class)
            ->where('jenis', ModeKasBank::JENIS_TRANSFER)
            ->countAllResults());
    }

    /**
     * Konfirmasi terima mutasi oleh admin cabang unit penerima membuat
     * HUTANG/PIUTANG antar unit otomatis (jatuh tempo = tanggal terima + 3),
     * dan menandai mutasi "diterima" (idempotent).
     */
    public function testTerimaMutasiMembuatHutangPiutangDenganJatuhTempo(): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 44,
            'ID_UNIT'    => 2,
            'ID_JABATAN' => 35,
        ]);

        $r = $this->post('mutasi_stok/terima/1');
        $r->assertStatus(302);

        $db = \Config\Database::connect('tests', false);
        $mutasi = $db->table('mutasi')->where('idmutasi', 1)->get()->getRow();
        $this->assertSame('1', (string) $mutasi->status);
        $this->assertNotEmpty($mutasi->tanggal_terima);
        $this->assertSame('2', (string) $mutasi->terima_idunit);

        $rows = $db->table('hutang_piutang')
            ->where('sumber_tipe', 'mutasi_unit')
            ->where('sumber_id', 1)
            ->get()->getResult();
        $this->assertCount(2, $rows);

        $jt = date('Y-m-d', strtotime(date('Y-m-d', strtotime($mutasi->tanggal_terima)) . ' +3 days'));
        $kode = array_map(fn ($h) => $h->kode, $rows);
        $this->assertContains('MUT-1-1-P', $kode);
        $this->assertContains('MUT-2-1-H', $kode);
        foreach ($rows as $h) {
            $this->assertSame($jt, $h->jatuh_tempo);
        }

        // Submit ganda tidak membuat H/P lagi (idempotent).
        $r2 = $this->post('mutasi_stok/terima/1');
        $r2->assertStatus(302);
        $db = \Config\Database::connect('tests', false);
        $rows2 = $db->table('hutang_piutang')
            ->where('sumber_tipe', 'mutasi_unit')
            ->where('sumber_id', 1)
            ->get()->getResult();
        $this->assertCount(2, $rows2);
    }

    public function testCetakBuktiHutangPiutangMutasiMemuatListBarang(): void
    {
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 44,
            'ID_UNIT'    => 2,
            'ID_JABATAN' => 35,
        ]);

        $r = $this->post('mutasi_stok/terima/1');
        $r->assertStatus(302);

        $db = \Config\Database::connect('tests', false);
        $rows = $db->table('hutang_piutang')
            ->where('sumber_tipe', 'mutasi_unit')
            ->where('sumber_id', 1)
            ->get()->getResult();

        $svc = new HutangPiutangService();
        foreach ($rows as $hp) {
            $detail = $svc->getDetailTransaksi($hp);

            $this->assertSame('Mutasi Stok Antar Unit', $detail['sumber_label']);
            $this->assertSame('MTS1000921001', $detail['referensi']);
            $this->assertNotEmpty($detail['items']);

            $it = $detail['items'][0];
            $this->assertSame('Barang X', $it['nama']);
            $this->assertSame(2.0, $it['qty']);
            $this->assertSame(1000.0, $it['harga']);
            $this->assertSame(2000.0, $it['subtotal']);
            $this->assertContains($hp->kode, ['MUT-1-1-P', 'MUT-2-1-H']);
        }
    }

    public function testAntarUnitRekeningFisikSamaTanpaGerakanKas(): void
    {
        $tok = 'tok-shared-but';
        $this->sesiDenganToken($tok);

        // BCA Bersama (akun 5) untuk pengirim & penerima: H/P antar unit
        // diselesaikan TANPA gerakan kas (uang tidak berpindah rekening).
        $payload = [
            'hutang_piutang_id' => '901',
            'akun_pengirim_id'  => '5',
            'akun_penerima_id'  => '5',
            'jumlah'            => '200000',
            'tanggal'           => '2026-09-02',
            'keterangan'        => 'Settlement antar unit BCA bersama',
            'submit_token'      => $tok,
        ];

        $r = $this->post('kas_bank/antar-unit/save', $payload);
        $r->assertStatus(302);

        // Tidak ada baris ledger antar-unit (0 gerakan kas).
        $this->assertSame(0, $this->model(ModelTransaksiKasBank::class)
            ->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)
            ->countAllResults());

        // Sisa H/P berkurang, pasangan piutang ikut berkurang.
        $this->assertSame(300000, (int) $this->model(ModelHutangPiutang::class)->find(901)->sisa);
        $this->assertSame(300000, (int) $this->model(ModelHutangPiutang::class)->find(902)->sisa);

        // Catatan pembayaran tercatat sebagai atribusi (referensi hutang).
        $pembayaran = $this->model(ModelPembayaranHutangPiutang::class)
            ->where('sumber', 'antar_unit')
            ->where('referensi_tipe', 'antar_unit_atribusi')
            ->where('referensi_id', 901)
            ->findAll();
        $this->assertCount(2, $pembayaran);

        // Reversal atribusi mengembalikan sisa H/P, tanpa menyentuh kas.
        $this->sesi();
        $rev = $this->post('kas_bank/antar-unit/reversal-atribusi/901');
        $rev->assertStatus(302);

        $this->assertSame(500000, (int) $this->model(ModelHutangPiutang::class)->find(901)->sisa);
        $this->assertSame(500000, (int) $this->model(ModelHutangPiutang::class)->find(902)->sisa);
        $this->assertSame(0, $this->model(ModelPembayaranHutangPiutang::class)
            ->where('referensi_tipe', 'antar_unit_atribusi')
            ->countAllResults());
    }
}
