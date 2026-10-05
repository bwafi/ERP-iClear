<?php

namespace Tests\Support;

use App\Libraries\ModeKasBank;
use App\Models\ModelHutangPiutang;
use App\Models\ModelPembayaranHutangPiutang;
use App\Models\ModelTransaksiKasBank;
use App\Services\Finance\HutangPiutangService;
use App\Services\Finance\KasBankSetorTarikService;
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
            // Tabel pendukung BaseController ikut di-drop supaya skema test
            // selalu sama dengan yang ditulis di bawah. Kalau tidak, tabel versi
            // lama dari run sebelumnya bertahan karena dipakai CREATE IF NOT EXISTS
            // dan test gagal dengan "Unknown column" yang menyesatkan.
            'db_stok_barang',
            'db_service',
            'db_penjualan',
            'db_opening_kas',
            'db_pelanggan',
            'db_jabatan',
            'db_jurnal',
            'db_pembelian',
            'db_pembayaran_hutang',
        ] as $tabel) {
            $q('DROP TABLE IF EXISTS ' . $tabel);
        }

        // LOGO dipakai inc/left_vertical.php saat merender halaman. Tanpa kolom
        // ini, test yang me-render view (bukan hanya POST) gagal di baris view.
        $q('CREATE TABLE db_unit (idunit INTEGER PRIMARY KEY AUTO_INCREMENT, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE db_bank (idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL, nama_bank TEXT NULL, atas_nama TEXT NULL, norek TEXT NULL, isppn INT NULL, gambar_qris TEXT NULL)');
        $q('CREATE TABLE db_akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE db_akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0,
                -- wajib: KasBankScopeService memfilter kolom ini untuk membedakan
                -- Finance/HO dari SHARED biasa.
                is_finance_ho TINYINT(1) DEFAULT 0,
                created_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        // `status` menandai statement sudah diverifikasi Finance atau masih
        // placeholder. Tanpa kolom ini, service tidak bisa membedakan "rekening
        // memang nol" dari "saldo belum diisi".
        $q('CREATE TABLE db_saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
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
                input_by INT NULL, deleted INT DEFAULT 0, created_at TEXT NULL, updated_at TEXT NULL,
                scope TEXT NULL DEFAULT \'active\', cutoff_closed_at TEXT NULL,
                cutoff_closed_by INT NULL, cutoff_reason TEXT NULL)');
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
        // tanggal_selesai / tanggal_bisa_diambil WAJIB ada: BaseController.initController
        // memanggil ServiceModel->getExpiredService() pada setiap request, dan
        // query itu memfilter kedua kolom itu. Tanpa keduanya semua controller
        // test gagal dengan "Unknown column".
        $q('CREATE TABLE IF NOT EXISTS db_service (
                id_service INT PRIMARY KEY, no_service TEXT NULL, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL,
                created_at TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        // Sidebar (inc/left_vertical.php -> Core::get_menu_show/get_role) dan
        // template.php membaca tabel ini. Wajib ada hanya untuk test yang
        // me-render halaman; test POST tidak menyentuhnya.
        $q('CREATE TABLE IF NOT EXISTS db_menu (
                idmenu INTEGER PRIMARY KEY AUTO_INCREMENT, urutan INT NULL,
                nama_menu TEXT NULL, roles TEXT NULL, url TEXT NULL,
                show_menu INT NULL, sub INT NULL, parent INT NULL,
                utama INT NULL, categories INT NULL, icon TEXT NULL,
                manualbook TEXT NULL)');
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN)
            VALUES (1, 'Root', '[1]'), (35, 'Admin Cabang', '[1]')");
        $q('CREATE TABLE IF NOT EXISTS db_spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE IF NOT EXISTS db_no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        // Opening KAS hidup di tabel sendiri (bukan db_saldo_awal_kas_bank) dan
        // WAJUNYA status TERVERIFIKASI di tanggal cut-off.|posisiUnit() untuk
        // rekening KAS membacanya dari sini, jadi tanpa tabel ini leg KELUAR
        // dari laci selalu terlihat Rp0 dan `cekTarikUnit()` menolaknya.
        $q('CREATE TABLE db_opening_kas (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, tanggal TEXT NULL,
                opening BIGINT NULL, real_cash BIGINT NULL, selisih BIGINT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'BELUM_VERIFIKASI\',
                keterangan TEXT NULL, input_by INT NULL, verifikasi_by INT NULL,
                verifikasi_at TEXT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
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
        // Cash In/Out mirror (KasBankSourceMovement) menjumlahkan penjualan.
        // Tanpa kolom ini test gagal "Unknown column" yang menyesatkan.
        // db_service sudah dibuat di atas; hanya kurang bayar_tunai/
        // harus_dibayar yang dipakai mirror Cash In.
        $q('CREATE TABLE db_penjualan (
                idpenjualan INTEGER PRIMARY KEY AUTO_INCREMENT,
                kode_invoice TEXT NULL, tanggal TEXT NULL, unit_idunit INT NULL,
                bayar_tunai REAL NULL, bayar_bank REAL NULL, total REAL NULL)');
        $q('ALTER TABLE db_service ADD COLUMN bayar_tunai REAL NULL');
        $q('ALTER TABLE db_service ADD COLUMN harus_dibayar REAL NULL');
        $q('CREATE TABLE db_jurnal (
                idjurnal INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, no_akun TEXT NULL, nama_akun TEXT NULL,
                debet REAL NULL, kredit REAL NULL, keterangan TEXT NULL,
                id_referensi INT NULL, tabel_referensi TEXT NULL,
                id_unit INT NULL, id_akun INT NULL)');

        // Siklus hutang: dipakai test A1 insert_cicilan (payment + status lunas
        // + ledger harus satu transaksi).
        $q('CREATE TABLE db_pembelian (
                idpembelian INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_idunit INT NULL, no_nota TEXT NULL, tanggal TEXT NULL,
                jatuh_tempo TEXT NULL, total REAL NULL, sisa REAL NULL,
                total_bayar REAL NULL, bayar REAL NULL,
                bayar_tunai REAL NULL, bayar_bank REAL NULL, status TEXT NULL)');
        $q('CREATE TABLE db_pembayaran_hutang (
                idpembayaran_hutang INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal_bayar TEXT NULL, bayar REAL NULL,
                bayar_tunai REAL NULL, bayar_bank REAL NULL,
                bank_idbank TEXT NULL, pembelian_idpembelian INT NULL,
                sisa_hutang REAL NULL, input_by INT NULL)');
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
            'db_opening_kas',
        ] as $tabel) {
            $q('DELETE FROM ' . $tabel);
        }

        $q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (1, 'Unit A'), (2, 'Unit B')");
        $q("INSERT INTO db_bank (idbank, nama_bank, atas_nama, norek) VALUES
            ('BNI-001', 'BNI', 'PT Contoh', '5551'),
            ('BNI-002', 'BNI', 'PT Contoh', '5552'),
            ('BCA-001', 'BCA', 'PT Contoh', '5553'),
            ('BCA-002', 'BCA', 'PT Contoh', '5554')");
        $q("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES (43, 1, 1, 'Admin Center')");
        $q("INSERT INTO db_akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa, status, is_shared) VALUES
            (1, 1, 'KAS', 'Kas Unit A', NULL, '1010101000', 'aktif', 0),
            (2, 1, 'BANK', 'BNI Unit A', 'BNI-001', '1010202000', 'aktif', 0),
            (3, 2, 'KAS', 'Kas Unit B', NULL, '1010102000', 'aktif', 0),
            (4, 2, 'BANK', 'BNI Unit B', 'BNI-002', '1010202010', 'aktif', 0),
            (5, NULL, 'BANK', 'BCA Bersama Jember', 'BCA-001', '1010202020', 'aktif', 1),
            (6, 1,  'BANK', 'BCA Unit A',       'BCA-002', '1010202030', 'aktif', 0)");
        // Statement terverifikasi: sudah disahkan Finance, jadi alokasi opening
        // boleh diisi terhadap angka ini.
        $q("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (5, '2026-01-01', 800000, 'Saldo awal BCA Bersama', 'VERIFIED')");
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
     * Admin cabang unit 1 (role 35, non-lintas). `unit_terpilih` jadi 1,
     * bukan null konsolidasi — itu syarat agar `canUseAsDestination()`
     * mengizinkan rekening Finance/HO sebagai tujuan.
     */
    private function sesiAdminCabang(): void
    {
        $this->db->query("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN)
            VALUES (44, 1, 35, 'Admin Cabang')");
        $this->withSession([
            'logged_in'  => true,
            'ID_AKUN'    => 44,
            'ID_UNIT'    => 1,
            'ID_JABATAN' => 35,
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

        // Pindah Saldo setelah pemisahan subtype HANYA BANK -> BANK, jadi
        // fixture lama (KAS Unit A -> KAS Unit B) sudah tidak valid: itu
        //_Setor_/_Tarik_-like dan harus lewat KasBankSetorTarikService.
        // Baris alokasi = entitlement: tanpa ini `cekTarikUnit()` dan
        // `canUseAsDestination()` akan menolak shared account.
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (6, 1, 500000, 'Hak pakai BCA Unit A'),
            (5, 1, 300000, 'Hak pakai BCA Bersama')");

        $payload = [
            'akun_asal_id'   => '6',
            'akun_tujuan_id' => '5',
            'jumlah'         => '100000',
            'tanggal'        => '2026-10-06',
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
            'tanggal'          => '2026-10-06',
            'unit_id'          => 1,
            'akun_kas_bank_id' => 1,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => ModeKasBank::ARAH_KELUAR,
            'jumlah'           => 50000,
            'akun_tujuan_id'   => 3,
            'transfer_ref'     => $ref,
            'sumber_tipe'      => KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO,
        ]);
        $idKeluar = (int) $m->insertID();
        $m->insert([
            'tanggal'          => '2026-10-06',
            'unit_id'          => 2,
            'akun_kas_bank_id' => 3,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => ModeKasBank::ARAH_MASUK,
            'jumlah'           => 50000,
            'akun_tujuan_id'   => 1,
            'transfer_ref'     => $ref,
            'sumber_tipe'      => KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO,
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

        // Leg KELUAR dari KAS Unit A kini ikut `cekTarikUnit()`. Opening laci
        // harus TERVERIFIKASI di tanggal cut-off (tabel db_opening_kas, bukan
        // statement bank) — persis aturan yang berlaku untuk Setor/Tarik.
        $this->db->query("INSERT INTO db_opening_kas
            (akun_kas_bank_id, unit_id, tanggal, opening, real_cash, selisih, status, keterangan)
            VALUES (1, 1, '2026-10-05', 500000, 500000, 0, 'TERVERIFIKASI', 'Opening laci Unit A')");

        $payload = [
            'hutang_piutang_id' => '901',
            'akun_pengirim_id'  => '1',
            'akun_penerima_id'  => '3',
            'jumlah'            => '200000',
            'tanggal'           => '2026-10-06',
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

        // Pindah Saldo setelah pemisahan subtype = BANK -> BANK. Fixture lama
        // (KAS Unit A -> KAS Unit B) bukan lagi transfer internal: uang
        // masuk/kelar laci itu Setor/Tarik dan harus lewat service-nya.
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (6, 1, 500000, 'Hak pakai BCA Unit A'),
            (5, 1, 300000, 'Hak pakai BCA Bersama')");

        $payload = [
            'akun_asal_id'   => '6',
            'akun_tujuan_id' => '5',
            'jumlah'         => '250000',
            'tanggal'        => '2026-10-06',
            'keterangan'     => 'Transfer fisik A->B',
            'submit_token'   => $tok,
        ];

        $m = $this->model(ModelTransaksiKasBank::class);
        $fisik = static function () use ($m): int {
            $total = 0;
            foreach ([1, 2, 3, 4, 5, 6] as $akunId) {
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

        // Visibility: user scope unit 1 diiriskan dengan account scope.
        // Rekening non-shared Unit 1 (akun 2 KAS + akun 6 BANK) + rekening
        // bersama 5 yang dialokasikan ke unit 1. Akun 3/4 (unit 2) tidak ada.
        $db = \Config\Database::connect('tests', false);
        $model = new \App\Models\ModelAkunKasBank($db);
        $ids = array_map('intval', array_column($model->getDalamScopeUnit([1]), 'idakun_kas_bank'));
        sort($ids);
        $this->assertSame([1, 2, 5, 6], $ids);
        $idsAktif = array_map('intval', array_column($model->getDalamScopeUnit([1], null, true), 'idakun_kas_bank'));
        sort($idsAktif);
        $this->assertSame([1, 2, 5, 6], $idsAktif);

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

        // Rekening SHARED (akun 5) tidak punya unit pemilik: hak aksesnya
        // SEPENUHNYA dari tabel alokasi. Tanpa baris alokasi, save ditolak
        // dengan "Akun pengirim ditolak" — jadi disetup di sini, mengikuti
        // model produksi di mana Bank 1/2 dialokasikan ke unit rightful-nya.
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan, input_by) VALUES
            (5, 1, 0, 'Alokasi unit 1', 43),
            (5, 2, 0, 'Alokasi unit 2', 43)");

        // BCA Bersama (akun 5) untuk pengirim & penerima: H/P antar unit
        // diselesaikan TANPA gerakan kas (uang tidak berpindah rekening).
        $payload = [
            'hutang_piutang_id' => '901',
            'akun_pengirim_id'  => '5',
            'akun_penerima_id'  => '5',
            'jumlah'            => '200000',
            'tanggal'           => '2026-10-06',
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

    public function testListPositionsDefaultMenghilangkanLegacy(): void
    {
        $m = $this->model(ModelHutangPiutang::class);
        $m->insert([
            'kode' => 'H-903', 'jenis' => 'hutang', 'sumber_tipe' => 'manual',
            'pihak_tipe' => 'supplier', 'pihak_id' => 1, 'nama_pihak' => 'Supplier Lama',
            'tanggal' => '2025-06-01', 'total' => 250000, 'total_dibayar' => 0,
            'sisa' => 250000, 'status' => 'belum_lunas', 'unit_id' => 1, 'deleted' => 0,
            'scope' => 'legacy', 'cutoff_reason' => 'LEGACY_CUTOFF',
        ]);

        $svc = new HutangPiutangService();
        $default = array_column($svc->listPositions(['jenis' => 'hutang']), 'kode');
        $this->assertNotContains('H-903', $default);
        $this->assertContains('H-901', $default);

        $semua = array_column($svc->listPositions(['jenis' => 'hutang', 'scope' => 'semua']), 'kode');
        $this->assertContains('H-903', $semua);

        $legacy = array_column($svc->listPositions(['jenis' => 'hutang', 'scope' => 'legacy']), 'kode');
        $this->assertContains('H-903', $legacy);
        $this->assertNotContains('H-901', $legacy);
    }

    public function testRingkasanTidakMenghitungLegacy(): void
    {
        // Hutang manual (authoritative) aktif -> terhitung di ringkasan.
        $m = $this->model(ModelHutangPiutang::class);
        $m->insert([
            'kode' => 'H-904', 'jenis' => 'hutang', 'sumber_tipe' => 'manual',
            'pihak_tipe' => 'supplier', 'pihak_id' => 1, 'nama_pihak' => 'Supplier Aktif',
            'tanggal' => '2026-10-15', 'total' => 100000, 'total_dibayar' => 0,
            'sisa' => 100000, 'status' => 'belum_lunas', 'unit_id' => 1, 'deleted' => 0,
            'scope' => 'active',
        ]);
        $id904 = (int) \Config\Database::connect()->table('hutang_piutang')
            ->where('kode', 'H-904')->get()->getRow()->id;
        $svc = new HutangPiutangService();
        $this->assertSame(100000, $svc->getRingkasan([1])['total_hutang']);

        // Setelah di-legacy (seperti hasil migration cut-off) -> hilang.
        $m->where('id', $id904)
            ->set(['scope' => 'legacy', 'cutoff_reason' => 'LEGACY_CUTOFF'])
            ->update();
        $this->assertSame(0, $svc->getRingkasan([1])['total_hutang']);

        // Yang sudah legacy sejak awal juga tidak dihitung.
        $m->insert([
            'kode' => 'H-905', 'jenis' => 'hutang', 'sumber_tipe' => 'manual',
            'pihak_tipe' => 'supplier', 'pihak_id' => 2, 'nama_pihak' => 'Supplier Lama',
            'tanggal' => '2025-06-01', 'total' => 250000, 'total_dibayar' => 0,
            'sisa' => 250000, 'status' => 'belum_lunas', 'unit_id' => 1, 'deleted' => 0,
            'scope' => 'legacy', 'cutoff_reason' => 'LEGACY_CUTOFF',
        ]);
        $this->assertSame(0, $svc->getRingkasan([1])['total_hutang']);
    }

    public function testKasbonOpeningTetapTerhitung(): void
    {
        $m = $this->model(ModelHutangPiutang::class);
        $m->insert([
            'kode' => 'K-ON1', 'jenis' => 'piutang', 'sumber_tipe' => 'kasbon',
            'pihak_tipe' => 'pegawai', 'pihak_id' => 43, 'nama_pihak' => 'Pegawai',
            'tanggal' => '2025-08-01', 'total' => 300000, 'total_dibayar' => 0,
            'sisa' => 300000, 'status' => 'sebagian', 'unit_id' => 1, 'deleted' => 0,
            'scope' => 'opening',
        ]);
        $m->insert([
            'kode' => 'K-LG1', 'jenis' => 'piutang', 'sumber_tipe' => 'kasbon',
            'pihak_tipe' => 'pegawai', 'pihak_id' => 43, 'nama_pihak' => 'Pegawai',
            'tanggal' => '2025-07-01', 'total' => 50000, 'total_dibayar' => 50000,
            'sisa' => 0, 'status' => 'lunas', 'unit_id' => 1, 'deleted' => 0,
            'scope' => 'legacy',
        ]);

        $svc = new HutangPiutangService();
        // Hanya opening yang masih outstanding (legacy lunas dieksklusi).
        $this->assertSame(300000, $svc->getSisaKasbonPegawai(43, 1));

        // Kasbon opening tetap tampil di daftar default (bukan legacy).
        $kod = array_column($svc->listPositions(['jenis' => 'piutang']), 'kode');
        $this->assertContains('K-ON1', $kod);
        $this->assertNotContains('K-LG1', $kod); // kasbon legacy tidak tampil di default
    }

    public function testGetSaldoAkunHanyaMenghitungTransaksiSetelahCutoff(): void
    {
        $db = \Config\Database::connect('tests');
        // Statement baseline dihitung pada tanggal CUT-OFF (30 Sep), bukan
        // tanggal mulai periode. Pernah test ini menaruh statement di
        // '2026-10-01', yang membuat mutasi 15 Sep terhitung sebagai "legacy
        // yang masih dihitung" — dua kali menghitung saldo yang sama.
        $db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 1, 'tanggal' => '2026-09-30', 'saldo' => 500000,
            'keterangan' => 'OPENING', 'status' => 'VERIFIED',
        ]);

        $m = $this->model(ModelTransaksiKasBank::class);
        // Transaksi sebelum cut-off (legacy) harus diabaikan.
        $m->insert(['tanggal' => '2026-09-15', 'unit_id' => 1, 'akun_kas_bank_id' => 1, 'jenis' => 'kas_masuk', 'arah' => 'MASUK', 'jumlah' => 100000]);
        // Transaksi aktif (pada/setelah cut-off) dihitung.
        $m->insert(['tanggal' => '2026-10-15', 'unit_id' => 1, 'akun_kas_bank_id' => 1, 'jenis' => 'kas_masuk', 'arah' => 'MASUK', 'jumlah' => 50000]);
        $m->insert(['tanggal' => '2026-11-01', 'unit_id' => 1, 'akun_kas_bank_id' => 1, 'jenis' => 'kas_keluar', 'arah' => 'KELUAR', 'jumlah' => 20000]);

        $this->assertSame(530000, $m->getSaldoAkun(1));
    }

    // =====================================================================
    // [F2] REKENING BERSAMA: PINDAH SALDO TIDAK BOLEH LEWAT SALDO UNIT LAIN
    //
    // Rekening 5 (BCA Bersama) dipakai Unit 1 dan Unit 2. `canUseAsSource()`
    // hanya membuktikan AKSES; `cekTarikUnit()` yang membuktikan POSISI.
    // Tanpa yang kedua, PINDAH SALDO jadi jalur bypass "Tarik milik 1 unit".
    // =====================================================================

    private function posisiUnit(int $akunId, int $unitId): int
    {
        $svc = new \App\Services\Finance\KasBankCutoffService();
        return $svc->posisiUnit($akunId, $unitId, '2026-10-06');
    }

    public function testPindahSaldoDitolakSaatPosisiUnitTidakCukup(): void
    {
        // Alokasi = entitlement. Nominal 0 = punya hak, saldo belum dialokasikan.
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (5, 1, 0, 'Hak pakai BCA Bersama'),
            (5, 2, 0, 'Hak pakai BCA Bersama')");

        $this->assertSame(0, $this->posisiUnit(5, 1), 'Awal: posisi Unit 1 nol');
        $this->assertSame(0, $this->posisiUnit(5, 2), 'Awal: posisi Unit 2 nol');

        // Saldo FISIK rekening shared ample (dari statement), tapi posisi Unit 2
        // nol. Guard harus menolak.
        $this->assertSame(
            800000,
            (new \App\Services\Finance\KasBankCutoffService())->saldoFisik(5, '2026-10-06'),
            'Saldo fisik shared harus ample supaya test benar-benar menguji posisi, bukan fisik'
        );

        $this->db->query("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN) VALUES (45, 2, 1, 'Finance Unit 2')");
        // Token WAJIB di sesi: tanpa ini guard token menolak duluan dan test
        // jadi hijau karena alasan yang salah.
        $this->withSession([
            'logged_in'           => true,
            'ID_AKUN'             => 45,
            'ID_UNIT'             => 2,
            'ID_JABATAN'          => 1,
            'kb_submit_tok-shared-deny' => time(),
        ]);

        // Tujuan = rekening bank milik Unit 2 sendiri, supaya guard ini
        // benar-benar menguji POSISI saldo dan bukan ditolak karena
        // destination di luar scope (akun 6 milik Unit 1).
        $r = $this->post('kas_bank/transfer/save', [
            'akun_asal_id'   => '5',
            'akun_tujuan_id' => '4',
            'jumlah'         => '100000',
            'tanggal'        => '2026-10-06',
            'keterangan'     => 'Pakai saldo unit lain',
            'submit_token'   => 'tok-shared-deny',
        ]);
        $r->assertStatus(302);

        $this->assertSame(
            0,
            $this->model(ModelTransaksiKasBank::class)->where('jenis', ModeKasBank::JENIS_TRANSFER)->countAllResults(),
            'Pindah saldo yang melebihi posisi unit harus DITOLAK walau saldo fisik cukup'
        );
    }

    public function testPindahSaldoDiterimaSaatPosisiUnitCukup(): void
    {
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (5, 1, 500000, 'Hak pakai BCA Bersama'),
            (6, 1, 0, 'Hak pakai BCA Unit A')");

        $tok = 'tok-shared-allow';
        $this->sesiDenganToken($tok);

        $r = $this->post('kas_bank/transfer/save', [
            'akun_asal_id'   => '5',
            'akun_tujuan_id' => '6',
            'jumlah'         => '100000',
            'tanggal'        => '2026-10-06',
            'keterangan'     => 'Pakai saldo sendiri',
            'submit_token'   => $tok,
        ]);
        $r->assertStatus(302);

        $m = $this->model(ModelTransaksiKasBank::class);
        $this->assertSame(2, $m->where('jenis', ModeKasBank::JENIS_TRANSFER)->countAllResults());
        $this->assertSame(400000, $this->posisiUnit(5, 1), 'Posisi Unit 1 harus berkurang sesuai nominal');

        // Subtype terekam: tab Pindah Saldo tidak boleh menampilkan Setor/Tarik.
        $this->assertSame(
            [KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO],
            array_values(array_unique(array_column($m->select('sumber_tipe')->findAll(), 'sumber_tipe'))),
            'Kedua kaki Pindah Saldo harus bertype PINDAH_SALDO'
        );
    }

    /**
     * (C) Transfer antar unit ke rekening bersama hanya menaikkan posisi unit
     * PENERIMA, bukan posisi unit pengirim.
     *
     * Ini yang hilang kalau leg MASUK antar unit di-stamp unit pengirim:
     * saldo Unit 2 masuk rekening Unit 1 atas nama Unit 1, padahal laci
     * Unit 1 tidak pernah bergerak.
     */
    public function testAntarUnitKeRekeningBersamaHanyaMenaikkanPosisiPenerima(): void
    {
        // Rekening bersama (5) dialokasikan ke Unit 1 (pemilik posisi) dan
        // punya hak saja di Unit 2; rekening 4 milik Unit 2 (penerima).
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (5, 1, 500000, 'Hak pakai BCA Bersama'),
            (5, 2, 0, 'Hak pakai BCA Bersama'),
            (4, 2, 500000, 'Hak pakai BNI Unit B')");

        $this->assertSame(500000, $this->posisiUnit(5, 1), 'Awal: posisi Unit 1 di shared');
        $this->assertSame(0, $this->posisiUnit(5, 2), 'Awal: posisi Unit 2 di shared nol');

        // Hutang 901 = piutang Unit 1 atas Unit 2. Unit 1 membayar dari
        // rekening bersama (5); dana masuk ke rekening Unit 2 (4).
        $tok = 'tok-shared-into';
        $this->sesiDenganToken($tok);

        $this->post('kas_bank/antar-unit/save', [
            'hutang_piutang_id' => '901',
            'akun_pengirim_id'  => '5',
            'akun_penerima_id'  => '4',
            'jumlah'            => '200000',
            'tanggal'           => '2026-10-06',
            'keterangan'        => 'Bayar ke Unit 2 via rekening bersama',
            'submit_token'      => $tok,
        ])->assertStatus(302);

        $this->assertSame(
            300000,
            $this->posisiUnit(5, 1),
            'Unit pengirim (1) yang kehilangan 200rb dari rekening bersama'
        );
        $this->assertSame(
            0,
            $this->posisiUnit(5, 2),
            'Posisi Unit 2 di rekening bersama tidak boleh berubah'
        );

        // Leg MASUK harus ter-stamp unit PENERIMA (2), bukan unit pengirim (1).
        $masuk = $this->model(ModelTransaksiKasBank::class)
            ->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)
            ->where('arah', ModeKasBank::ARAH_MASUK)
            ->first();
        $this->assertNotNull($masuk, 'Leg MASUK antar unit harus ada');
        $this->assertSame(
            2,
            (int) $masuk->unit_id,
            'Leg MASUK harus di-stamp unit PENERIMA'
        );
    }

    public function testPindahSaldoDenganKasDitolak(): void
    {
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (5, 1, 500000, 'Hak pakai BCA Bersama')");
        $this->db->query("INSERT INTO db_opening_kas
            (akun_kas_bank_id, unit_id, tanggal, opening, real_cash, selisih, status, keterangan)
            VALUES (1, 1, '2026-10-05', 500000, 500000, 0, 'TERVERIFIKASI', 'Opening laci Unit A')");

        // KAS <-> BANK bukan Pindah Saldo: itu Setor/Tarik dan wajib lewat
        // KasBankSetorTarikService (guard baseline + cek saldo ikut jalan).
        $tok = 'tok-kas-bank';
        $this->sesiDenganToken($tok);

        $this->post('kas_bank/transfer/save', [
            'akun_asal_id'   => '5',
            'akun_tujuan_id' => '1',
            'jumlah'         => '100000',
            'tanggal'        => '2026-10-06',
            'keterangan'     => 'Bank ke KAS',
            'submit_token'   => $tok,
        ])->assertStatus(302);

        $this->assertSame(
            0,
            $this->model(ModelTransaksiKasBank::class)->where('jenis', ModeKasBank::JENIS_TRANSFER)->countAllResults(),
            'Rekening KAS tidak boleh dipakai lewat Pindah Saldo'
        );
    }

    // =====================================================================
    // [M3] GUARD SUBTYPE PINDAH SALDO
    //
    // Semua `jenis = TRANSFER_INTERNAL` berbagi satu nilai, jadi tab "Pindah
    // Saldo" harus menyaring lewat `sumber_tipe`. Tanpa itu, Setor/Tarik tampil
    // seolah-olah Pindah Saldo dan link reversal-nya memakai tab yang salah.
    // =====================================================================

    /**
     * Satu kaki KELUAR `TRANSFER_INTERNAL` dengan subtype tertentu. Nomor
     * rekening 2 -> 6, keduanya BANK Unit 1, jadi selalu lolos scope.
     */
    private function insertSubtypeRow(string $ref, string $subtype, int $unit = 1): void
    {
        $this->db->query(
            'INSERT INTO db_transaksi_kas_bank
                (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah, akun_tujuan_id,
                 transfer_ref, submission_key, sumber_tipe, keterangan, created_at)
             VALUES (?, ?, 2, ?, ?, 100000, 6, ?, NULL, ?, ?, "2026-10-06 09:00:00")',
            [
                '2026-10-06',
                $unit,
                ModeKasBank::JENIS_TRANSFER,
                ModeKasBank::ARAH_KELUAR,
                $ref,
                $subtype,
                'subtype ' . $subtype,
            ]
        );
    }

    /**
     * Render tab Pindah Saldo dan kembalikan body HTML-nya. Assertion dilakukan
     * pada output yang benar-benar dirender user, bukan pada hasil query
     * internal — supaya filter subtitle yang salah ketahuan di test.
     */
    private function bodyPindahSaldo(): string
    {
        $this->sesi();
        $result = $this->get('kas_bank/transfer');
        $result->assertStatus(200);

        return (string) $result->getBody();
    }

    /**
     * Ambil `<option value=...>` dari satu `<select>` tertentu.
     *
     * Assertion per-dropdown, bukan per-body: kalau `akun_asal_id` dan
     * `akun_tujuan_id` dicek bareng, test tetap hijau saat policy-nya tertukar
     * (sumber dapat daftar tujuan dan sebaliknya).
     *
     * @return int[]
     */
    private function opsiDropdown(string $html, string $name): array
    {
        $ok = preg_match(
            '/<select\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>(.*?)<\/select>/si',
            $html,
            $m
        );
        $this->assertSame(1, $ok, "Select {$name} tidak ditemukan di halaman Pindah Saldo");

        preg_match_all('/<option value="(\d+)"/i', $m[1], $vals);

        return array_map('intval', $vals[1]);
    }

    /**
     * [H1] Dua sisi form PINDH SALDO memakai policy BERBEDA.
     *
     * Aturan: Rekening Finance/HO (IRA, `is_finance_ho = 1`) boleh jadi
     * TUJUAN untuk unit yang punya rekening operasional sendiri, tapi TIDAK
     * boleh jadi SUMBER — hanya ROOT/Finance yang menarik dana Direksi.
     *
     * Bug yang dikunci: dulu kedua sisi memanggil helper yang sama, jadi KASIR
     * kehilangan IRA sebagai tujuan sah (dropdown kosong) ATAU, kalau diperbaiki
     * separuh, IRA bocor ke dropdown "Dari Akun" lalu ditolak saat submit.
     * Guard server sudah benar; yang salah ada di dropdown.
     */
    public function testPindahSaldoPakaiPolicyBerbedaUntukSumberDanTujuan(): void
    {
        // Rekening Finance/HO: tanpa unit, is_finance_ho = 1.
        $this->db->query("INSERT INTO db_akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa, status, is_shared, is_finance_ho)
            VALUES (9, NULL, 'BANK', 'Kas Direksi', 'BNI-001', '1010202099', 'aktif', 0, 1)");

        // KASIR (role 35, non-lintas, unit 1).
        $this->sesiAdminCabang();
        $result = $this->get('kas_bank/transfer');
        $result->assertStatus(200);
        $html = (string) $result->getBody();

        // IRA hanya boleh masuk dropdown TUJUAN.
        $this->assertNotContains(
            9,
            $this->opsiDropdown($html, 'akun_asal_id'),
            'Rekening Finance/HO tidak boleh jadi SUMBER Pindah Saldo'
        );
        $this->assertContains(
            9,
            $this->opsiDropdown($html, 'akun_tujuan_id'),
            'Rekening Finance/HO harus tetap bisa jadi TUJUAN Pindah Saldo'
        );

        // Rekening unit yang visible tetap utuh di kedua sisi.
        foreach (['akun_asal_id', 'akun_tujuan_id'] as $select) {
            $opsi = $this->opsiDropdown($html, $select);
            $this->assertContains(6, $opsi, "Rekening BANK unit sendiri wajib ada di {$select}");
            $this->assertNotContains(4, $opsi, "Rekening BANK unit lain tidak boleh di {$select}");
            // KAS tidak boleh muncul sama sekali di Pindah Saldo.
            $this->assertNotContains(1, $opsi, "Rekening KAS tidak boleh ada di {$select}");
            $this->assertNotContains(3, $opsi, "Rekening KAS unit lain tidak boleh ada di {$select}");
        }
    }

    /**
     * Tab Pindah Saldo hanya menampilkan subtype PINDAH_SALDO.
     * SETOR_TUNAI dan PENARIKAN_TUNAI milik tab "Setor / Tarik Tunai".
     */
    public function testListPindahSaldoHanyaTampilkanSubtypePindahSaldo(): void
    {
        $this->insertSubtypeRow('TRF-SET', KasBankSetorTarikService::SUMBER_TIPE_SETOR);
        $this->insertSubtypeRow('TRF-TAR', KasBankSetorTarikService::SUMBER_TIPE_TARIK);
        $this->insertSubtypeRow('TRF-PS',  KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO);

        $body = $this->bodyPindahSaldo();

        $this->assertStringContainsString('TRF-PS', $body, 'Pindah Saldo harus tampil di tabnya');
        $this->assertStringNotContainsString('TRF-SET', $body, 'Setor Tunai tidak boleh tampil di tab Pindah Saldo');
        $this->assertStringNotContainsString('TRF-TAR', $body, 'Penarikan Tunai tidak boleh tampil di tab Pindah Saldo');
    }

    /**
     * [M2] Riwayat Pindah Saldo menampilkan NAMA rekening di kedua kolom.
     *
     * Lookup label harus memuat rekening yang HANYA sah sebagai tujuan.
     * Kalau controller mengirim `akun_sumber` saja, baris "Unit 1 -> IRA"
     * yang sah tetap dirender, tapi kolom tujuan jadi "-" karena rekening
     * IRA tidak ada di lookup.looked up by ID dari daftar source.
     *
     * Rekening IRA sengaja dipakai karena policy sumber MENOLAKNYA: jadi
     * rekening ini benar-benar hanya ada di `akun_tujuan`, tidak mungkin
     * ikut terbawa daftar source.
     */
    public function testRiwayatPindahSaldoMenampilkanLabelRekeningTujuanExclusive(): void
    {
        $this->db->query("INSERT INTO db_akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa, status, is_shared, is_finance_ho)
            VALUES (9, NULL, 'BANK', 'Kas Direksi', 'BNI-001', '1010202099', 'aktif', 0, 1)");

        // Leg sah: rekening 6 (unit 1) -> rekening 9 (Finance/HO).
        $this->db->query(
            'INSERT INTO db_transaksi_kas_bank
                (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah, akun_tujuan_id,
                 transfer_ref, submission_key, sumber_tipe, keterangan, created_at)
             VALUES ("2026-10-06", 1, 6, ?, ?, 250000, 9, "TRF-LBL", NULL, ?, "ke IRA", "2026-10-06 09:00:00")',
            [ModeKasBank::JENIS_TRANSFER, ModeKasBank::ARAH_KELUAR, KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO]
        );

        // Admin cabang (unit 1, non-lintas) supaya unit terpilih = 1. Pada
        // ROOT tanpa `unit_id`, unit_terpilih null dan IRA memang tidak sah
        // jadi tujuan — `canUseAsDestination()` menolak tanpa unit.
        $this->sesiAdminCabang();

        $result = $this->get('kas_bank/transfer');
        $result->assertStatus(200);
        $body = (string) $result->getBody();

        $baris = $this->barisRiwayatPindahSaldo($body, 'TRF-LBL');
        $this->assertStringContainsString(
            'Kas Direksi',
            $baris,
            'Kolom tujuan harus menampilkan nama rekening IRA, bukan "-"'
        );
        $this->assertStringContainsString(
            'BCA Unit A',
            $baris,
            'Kolom asal harus menampilkan nama rekening pengirim'
        );
    }

    /**
     * [M1] Badge tab Pindah Saldo tidak boleh menghitung kategori KAS.
     *
     * Badge lama "N Kas · M Bank" selalu me-render "0 Kas · M Bank" di tab ini
     * karena dataset halaman Pindah Saldo tidak pernah punya baris KAS. Itu
     * menyesatkan: user melihat angka Kas padahal tidak ada pilihan KAS sama
     * sekali di form. Badge harus menghitung rekening BANK saja.
     */
    public function testBadgePindahSaldoTidakMenampilkanKategoriKas(): void
    {
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan)
            VALUES (2, 1, 700000, 'Hak pakai BNI Unit A')");
        // Rekening 2 dibuat entitled supaya BADGE-nya ikut terhitung. Kalau
        // hanya rekening unit 1 yang visible, test tetap benar secara badge,
        // tapi tidak membuktikan KAS ikut dihitung lalu dikecualikan.
        $this->sesiAdminCabang();

        $body = $this->get('kas_bank/transfer')->getBody();

        $this->assertStringNotContainsString(
            '0 Kas',
            $body,
            'Badge Pindah Saldo tidak boleh menampilkan kategori Kas yang kosong'
        );
        $this->assertStringNotContainsString(
            'Kas ·',
            $body,
            'Badge Pindah Saldo tidak boleh memformat "Kas · Bank"'
        );
        $this->assertMatchesRegularExpression(
            '/\d+ Rekening Bank/',
            $body,
            'Badge Pindah Saldo harus menghitung rekening BANK saja'
        );
    }

    /**
     * Ambil isi `<tr>` satu baris riwayat Pindah Saldo berdasarkan transfer_ref.
     *
     * Assertion harus scoped ke baris tabel, bukan ke seluruh body: nama
     * rekening yang sama juga muncul sebagai `<option>` di dropdown form, jadi
     * cek ke seluruh body akan hijau walau kolom tabelnya render "-".
     */
    private function barisRiwayatPindahSaldo(string $html, string $ref): string
    {
        $ok = preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/si', $html, $trs, PREG_SET_ORDER);
        $this->assertGreaterThan(0, $ok, 'Tabel riwayat tidak ditemukan di halaman');

        foreach ($trs as $tr) {
            if (str_contains($tr[1], $ref)) {
                return $tr[1];
            }
        }

        $this->fail("Baris riwayat {$ref} tidak ada di halaman Pindah Saldo");
    }

    /**
     * Reversal dari tab Pindah Saldo hanya menerima PINDAH_SALDO. Saat
     * ditolak, kedua leg harus UTUH — tidak ada partial mutation.
     */
    public function testReversalPindahSaldoMenolakSetorDanTarikTanpaMerusakLeg(): void
    {
        $this->sesi();
        foreach ([
            ['TRF-RS', KasBankSetorTarikService::SUMBER_TIPE_SETOR],
            ['TRF-RT', KasBankSetorTarikService::SUMBER_TIPE_TARIK],
        ] as [$ref, $subtype]) {
            // Dua kaki, supaya "leg utuh" benar-benar berarti dua baris.
            $this->insertSubtypeRow($ref . '-K', $subtype);
            $this->db->query(
                'INSERT INTO db_transaksi_kas_bank
                    (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah, transfer_ref,
                     sumber_tipe, keterangan, created_at)
                 VALUES ("2026-10-06", 1, 6, ?, ?, 100000, ?, ?, "kaki masuk", "2026-10-06 09:00:01")',
                [ModeKasBank::JENIS_TRANSFER, ModeKasBank::ARAH_MASUK, $ref . '-K', $subtype]
            );
        }

        $m = $this->model(ModelTransaksiKasBank::class);
        foreach (['TRF-RS-K', 'TRF-RT-K'] as $ref) {
            $idKelu = (int) $m->where('transfer_ref', $ref)->where('arah', ModeKasBank::ARAH_KELUAR)->first()->idtransaksi;
            $sebelum = (int) $m->where('transfer_ref', $ref)->countAllResults();

            $this->post('kas_bank/transfer/reversal/' . $idKelu)->assertStatus(302);

            $this->assertSame(
                $sebelum,
                (int) $m->where('transfer_ref', $ref)->countAllResults(),
                "Reversal ditolak tapi leg {$ref} ikut terhapus (partial mutation)"
            );
            $this->assertSame(
                2,
                (int) $m->where('transfer_ref', $ref)->countAllResults(),
                "Kaki KELUAR + MASUK {$ref} harus sama-sama utuh"
            );
        }

        $this->assertSame(
            4,
            (int) $m->where('jenis', ModeKasBank::JENIS_TRANSFER)->countAllResults(),
            'Semua kaki harus tetap ada setelah reversal ditolak'
        );
    }

    /**
     * PINDAH_SALDO sendiri tetap diproses guard & reversal yang sudah ada:
     * dibatalkan dari tab ini dan kedua kakinya terhapus bersamaan.
     */
    public function testReversalPindahSaldoMemprosesSubtypePindahSaldo(): void
    {
        $this->insertSubtypeRow('TRF-OK-K', KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO);
        $this->db->query(
            'INSERT INTO db_transaksi_kas_bank
                (tanggal, unit_id, akun_kas_bank_id, jenis, arah, jumlah, transfer_ref,
                 sumber_tipe, keterangan, created_at)
             VALUES ("2026-10-06", 1, 6, ?, ?, 100000, ?, ?, "kaki masuk", "2026-10-06 09:00:01")',
            [
                ModeKasBank::JENIS_TRANSFER,
                ModeKasBank::ARAH_MASUK,
                'TRF-OK-K',
                KasBankSetorTarikService::SUMBER_TIPE_PINDAH_SALDO,
            ]
        );

        $m = $this->model(ModelTransaksiKasBank::class);
        $this->assertSame(1, (int) $m->where('transfer_ref', 'TRF-OK-K')->where('arah', ModeKasBank::ARAH_KELUAR)->countAllResults(), 'Kaki keluar wajib ada');

        // Harus tampil di tab Pindah Saldo, kalau tidak guard-nya tak teruji.
        $this->assertStringContainsString('TRF-OK-K', $this->bodyPindahSaldo());

        $idKelu = (int) $m->where('transfer_ref', 'TRF-OK-K')->where('arah', ModeKasBank::ARAH_KELUAR)->first()->idtransaksi;
        $this->post('kas_bank/transfer/reversal/' . $idKelu)->assertStatus(302);

        $this->assertSame(
            0,
            (int) $m->where('transfer_ref', 'TRF-OK-K')->countAllResults(),
            'Reversal Pindah Saldo harus menghapus seluruh pasangan'
        );
    }

    /**
     * [M3-C] Stamping receiver di rekening SHARED — arah "tujuan = shared".
     *
     * Test yang sudah ada hanya menguji arah "sumber = shared" (Unit 1
     * mengirim DARI rekening bersama ke rekening Unit 2). Arah ini dibalik
     * sengaja: Unit 3 membayar KE rekening bersama yang dialokasikan ke Unit 1.
     *
     * Yang diuji: kaki MASUK wajib ter-stamp unit PENERIMA (1), bukan unit
     * pengirim (3). Kalau ter-stamp 3, posisi Unit 1 di rekening bersama
     * ikut terkurang — user convincingly melihat "dana masuk" tapi saldo
     * haknya justru turun. Arah "sumber = shared" tidak akan menangkap ini.
     */
    public function testAntarUnitKeRekeningBersamaSebagaiTujuanStampUnitPenerima(): void
    {
        $this->db->query("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES (3, 'Unit C')");
        $this->db->query("INSERT INTO db_bank (idbank, nama_bank, atas_nama, norek)
            VALUES ('BCA-003', 'BCA', 'PT Contoh', '5555')");
        // Rekening BANK milik Unit 3 (pengirim) dan rekening bersama 5.
        $this->db->query("INSERT INTO db_akun_kas_bank
            (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa, status, is_shared)
            VALUES (7, 3, 'BANK', 'BCA Unit C', 'BCA-003', '1010202040', 'aktif', 0)");

        // Rekening bersama 5: alokasi 400rb untuk Unit 1, 0 untuk Unit 2
        // ( entitlementsaja, saldo belum dialokasikan ke sana).
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank
            (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (5, 1, 400000, 'Hak pakai BCA Bersama'),
            (5, 2, 0,     'Hak pakai BCA Bersama')");
        $this->db->query("INSERT INTO db_saldo_awal_kas_bank
            (akun_kas_bank_id, tanggal, saldo, keterangan, status)
            VALUES (7, '2026-10-05', 500000000, 'Saldo awal BCA Unit C', 'VERIFIED')");
        // Rekening non-shared: pembuka posisi unit = alokasi, bukan statement.
        $this->db->query("INSERT INTO db_alokasi_saldo_kas_bank
            (akun_kas_bank_id, unit_id, nominal, keterangan)
            VALUES (7, 3, 500000000, 'Saldo Unit C di BCA Unit C')");

        // Hutang Unit 3 atas Unit 1, berpasangan dari sumber yang sama.
        $this->db->query("INSERT INTO db_hutang_piutang
            (id, kode, jenis, sumber_tipe, sumber_id, is_projection, pihak_tipe, pihak_id,
             lawan_unit_id, nama_pihak, tanggal, uraian, total, total_dibayar, sisa,
             status, unit_id, input_by, deleted, created_at) VALUES
            (903, 'H-903', 'hutang',  'mutasi_unit', 777, 0, 'unit', 3, 1, 'Unit C',
             '2026-09-01', 'Transfer barang', 70000000, 0, 70000000, 'belum_lunas', 3, 43, 0, '2026-09-01 08:00:00'),
            (904, 'P-904', 'piutang', 'mutasi_unit', 777, 0, 'unit', 1, 3, 'Unit A',
             '2026-09-01', 'Transfer barang', 70000000, 0, 70000000, 'belum_lunas', 1, 43, 0, '2026-09-01 08:00:00')");

        $this->assertSame(400000, $this->posisiUnit(5, 1), 'Awal: Unit 1 punya 400rb di rekening bersama');
        $this->assertSame(0, $this->posisiUnit(5, 2), 'Awal: Unit 2 nol di rekening bersama');

        // Unit 3 (pengirim) membayar DARI rekening 7 KE rekening bersama 5.
        $tok = 'tok-shared-dest';
        $this->withSession([
            'logged_in'         => true,
            'ID_AKUN'           => 43,
            'ID_UNIT'           => 1,
            'ID_JABATAN'        => 1,
            'kb_submit_' . $tok => time(),
        ]);

        $this->post('kas_bank/antar-unit/save', [
            'hutang_piutang_id' => '903',
            'akun_pengirim_id'  => '7',
            'akun_penerima_id'  => '5',
            'jumlah'            => '70000000',
            'tanggal'           => '2026-10-06',
            'keterangan'        => 'Bayar ke rekening bersama Unit 1',
            'submit_token'      => $tok,
        ])->assertStatus(302);

        // Posisi unit di rekening bersama: 400rb pembuka + 70jt leg MASUK.
        $this->assertSame(
            70400000,
            $this->posisiUnit(5, 1),
            'Unit PENERIMA (1) harus bertambah di rekening bersama'
        );
        $this->assertSame(
            0,
            $this->posisiUnit(5, 2),
            'Posisi Unit 2 di rekening bersama tidak boleh berubah'
        );

        // Stamping kaki: KELUAR = pengirim, MASUK = penerima.
        $keluar = $this->model(ModelTransaksiKasBank::class)
            ->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)
            ->where('arah', ModeKasBank::ARAH_KELUAR)
            ->first();
        $masuk = $this->model(ModelTransaksiKasBank::class)
            ->where('jenis', ModeKasBank::JENIS_ANTAR_UNIT)
            ->where('arah', ModeKasBank::ARAH_MASUK)
            ->first();

        $this->assertNotNull($keluar, 'Leg KELUAR antar unit harus ada');
        $this->assertNotNull($masuk, 'Leg MASUK antar unit harus ada');
        $this->assertSame(3, (int) $keluar->unit_id, 'Leg KELUAR harus di-stamp unit PENGIRIM (3)');
        $this->assertSame(
            1,
            (int) $masuk->unit_id,
            'Leg MASUK harus di-stamp unit PENERIMA (1), bukan pengirim (3)'
        );
        $this->assertSame(7, (int) $keluar->akun_kas_bank_id, 'Kaki keluar dari rekening Unit 3');
        $this->assertSame(5, (int) $masuk->akun_kas_bank_id, 'Kaki masuk ke rekening bersama');
    }

    // =====================================================================
    // [A1] ATOMIKITAS SOURCE <-> LEDGER
    //
    // Aturan yang diuji: kalau posting ke transaksi_kas_bank gagal, source
    // WAJIB dibatalkan. Dulu return posting diabaikan (atau 'skipped' tidak
    // dilempar), jadi user melihat "berhasil" padahal saldo tidak bergerak.
    // =====================================================================

    /**
     * Bank yang ADA di db_bank tapi tidak punya akun kas_bank: resolve pasti
     * gagal. Sengaja dibuat per-test supaya tidak mengganggu test lain yang
     * menghitung isi db_bank.
     */
    private function seedBankTanpaAkun(string $kode = 'BCA-X'): void
    {
        $this->db->query(
            "INSERT INTO db_bank (idbank, jenis_bank, nama_bank, atas_nama, norek) "
            . "VALUES ('{$kode}', 'BANK', 'BCA X', 'PT Contoh', '9999')"
        );
    }

    private function seedNoAkun(string $no = '5-1010'): void
    {
        $this->db->query(
            "INSERT INTO db_no_akun (no_akun, nama_akun) VALUES ('{$no}', 'Beban Operasional')"
        );
    }

    private function flashGagal(): string
    {
        // FeatureTestTrait populate $_SESSION langsung, dan flash CI4 disimpan
        // sebagai key biasa + penanda di __ci_vars. session('flashdata') tidak
        // bisa dipakai karena session() mengembalikan FlashMock kosong.
        return (string) ($_SESSION['gagal'] ?? '');
    }

    private function flashSukses(): string
    {
        return (string) ($_SESSION['sukses'] ?? '');
    }

    // ---- [A1-1] CREATE kas keluar: resolve gagal -> source dibatalkan ----

    public function testA1CreateKasKeluarRollbackSaatPostingGagal(): void
    {
        $this->sesi();
        $this->seedBankTanpaAkun();
        $this->seedNoAkun();

        $r = $this->post('insert_kas_keluar', [
            'tanggal'         => '2026-10-05',
            'deskripsi'       => 'Beli ATK',
            'unit_idunit'     => '1',
            'akun'            => [[
                'no_akun'           => '5-1010',
                'jumlah'            => '75000',
                'posisi_drk'        => 'debet',
                'penerima'          => 'BCA-X',
                'no_rekening'       => 'BCA-X',
                'kategori_idkategori' => '1',
            ]],
        ]);
        $r->assertStatus(302);

        // Inti A1: source TIDAK boleh tersisa tanpa ledger.
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_kas_keluar')->getRow()->c);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_transaksi_kas_bank')->getRow()->c);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_jurnal')->getRow()->c);

        // Flash harus 'gagal' (template hanya render 'sukses'/'gagal') dan
        // alasan resolver harus sampai ke user.
        $this->assertStringContainsString('BCA-X', $this->flashGagal());
        $this->assertSame('', $this->flashSukses(), 'flash sukses tidak boleh muncul saat ledger gagal');
    }

    // ---- [A1-2] UPDATE kas keluar: posting gagal -> posting lama pulih ----

    public function testA1UpdateKasKeluarMemulihkanPostingLamaSaatGagal(): void
    {
        $this->sesi();
        $this->seedNoAkun();

        // Sumber valid + ledger lama (BNI-001 -> akun 2).
        $this->db->query("INSERT INTO db_kas_keluar (idkas_keluar, tanggal, deskripsi, jumlah, jenis, penerima, idbank, idunit)
            VALUES (1, '2026-10-05', 'Belanja lama', 75000, 'debet', 'PT Contoh', 'BNI-001', 1)");
        $this->model(\App\Libraries\ModeKasBank::class);
        $lib = new \App\Libraries\ModeKasBank();
        $lib->postingKasKeluar(1);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) AS c FROM db_transaksi_kas_bank WHERE sumber_tipe='kas_keluar' AND sumber_id=1")->getRow()->c);

        // Edit ke rekening yang TIDAK bisa di-resolve.
        $this->seedBankTanpaAkun();
        $r = $this->post('update_kas_keluar', [
            'idkas_keluar'      => '1',
            'tanggal'           => '2026-10-06',
            'deskripsi'         => 'Belanja BARU',
            'kategori_idkategori' => '1',
            'jumlah'            => '99000',
            'penerima'          => 'BCA-X',
            'posisi_drk'        => 'debet',
        ]);
        $r->assertStatus(302);

        // Sumber harus BALIK ke nilai lama (update ikut rollback).
        $row = $this->db->query('SELECT * FROM db_kas_keluar WHERE idkas_keluar=1')->getRow();
        $this->assertSame('BNI-001', $row->idbank);
        $this->assertSame('2026-10-05', $row->tanggal);
        $this->assertSame('75000', (string) (int)$row->jumlah);

        // Posting lama harus DIPULIHKAN — inilah yang hilang sebelum A1.
        $this->assertSame(
            1,
            (int) $this->db->query("SELECT COUNT(*) AS c FROM db_transaksi_kas_bank WHERE sumber_tipe='kas_keluar' AND sumber_id=1")->getRow()->c,
            'hapusPosting harus ikut rollback supaya ledger lama tidak hilang'
        );
        $this->assertStringContainsString('BCA-X', $this->flashGagal());
    }

    // ---- [A1-3] CREATE kas masuk: resolve gagal -> source dibatalkan ----

    public function testA1CreateKasMasukRollbackSaatPostingGagal(): void
    {
        $this->sesi();
        $this->seedBankTanpaAkun();
        $this->seedNoAkun();

        $r = $this->post('insert_kas_masuk', [
            'tanggal'     => '2026-10-05',
            'deskripsi'   => 'Setoran tak terpetakan',
            'unit_idunit' => '1',
            'akun'        => [[
                'no_akun'             => '5-1010',
                'jumlah'              => '90000',
                'posisi_drk'          => 'debet',
                'penerima'            => 'BCA-X',
                'no_rekening'         => 'BCA-X',
                'kategori_idkategori' => '1',
            ]],
        ]);
        $r->assertStatus(302);

        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_kas_masuk')->getRow()->c);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_transaksi_kas_bank')->getRow()->c);
        $this->assertStringContainsString('BCA-X', $this->flashGagal());
    }

    // ---- [A1-4] UPDATE kas masuk: posting gagal -> posting lama pulih ----

    public function testA1UpdateKasMasukMemulihkanPostingLamaSaatGagal(): void
    {
        $this->sesi();
        $this->seedNoAkun();

        $this->db->query("INSERT INTO db_kas_masuk (idkas_masuk, tanggal, deskripsi, jumlah, jenis, penerima, idbank, idunit)
            VALUES (1, '2026-10-05', 'Setoran lama', 90000, 'debet', 'PT Contoh', 'BNI-001', 1)");
        $lib = new \App\Libraries\ModeKasBank();
        $lib->postingKasMasuk(1);
        $this->assertSame(1, (int) $this->db->query("SELECT COUNT(*) AS c FROM db_transaksi_kas_bank WHERE sumber_tipe='kas_masuk' AND sumber_id=1")->getRow()->c);

        $this->seedBankTanpaAkun();
        $r = $this->post('update_kas_masuk', [
            'idkas_masuk'        => '1',
            'tanggal'           => '2026-10-06',
            'deskripsi'         => 'Setoran BARU',
            'kategori_idkategori' => '1',
            'jumlah'            => '120000',
            'penerima'          => 'BCA-X',
            'posisi_drk'        => 'debet',
        ]);
        $r->assertStatus(302);

        $row = $this->db->query('SELECT * FROM db_kas_masuk WHERE idkas_masuk=1')->getRow();
        $this->assertSame('BNI-001', $row->idbank);
        $this->assertSame('90000', (string) (int)$row->jumlah);
        $this->assertSame(
            1,
            (int) $this->db->query("SELECT COUNT(*) AS c FROM db_transaksi_kas_bank WHERE sumber_tipe='kas_masuk' AND sumber_id=1")->getRow()->c,
            'hapusPosting harus ikut rollback supaya ledger lama tidak hilang'
        );
        $this->assertStringContainsString('BCA-X', $this->flashGagal());
    }

    // ---- [A1-5] Cicilan hutang gagal -> hutang TIDAK boleh jadi Lunas ----

    public function testA1CicilanHutangGagalTidakMenandaiLunas(): void
    {
        $this->sesi();
        $this->seedBankTanpaAkun();

        $this->db->query("INSERT INTO db_pembelian (idpembelian, unit_idunit, total, sisa, total_bayar, bayar, bayar_tunai, bayar_bank, status, jatuh_tempo)
            VALUES (1, 1, 1000000, 1000000, 0, 0, 0, 0, 'Belum Lunas', '2026-12-31')");

        $r = $this->post('update_cicilan_hutang', [
            'bayar_tunai'  => '0',
            'bayar_bank'   => '1000000',
            'bank_idbank'  => 'BCA-X',
            'idpembelian'  => '1',
            'sisa'         => '0', // UI ingin LUNAS
        ]);
        $r->assertStatus(302);

        // Tidak boleh ada pembayaran tersimpan...
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_pembayaran_hutang')->getRow()->c);
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_transaksi_kas_bank')->getRow()->c);

        // ...dan yang paling penting: hutang TIDAK boleh berstatus Lunas.
        $p = $this->db->query('SELECT * FROM db_pembelian WHERE idpembelian=1')->getRow();
        $this->assertNotSame('Lunas', $p->status);
        $this->assertSame(0, (int)$p->total_bayar);
        $this->assertStringContainsString('BCA-X', $this->flashGagal());
    }

    // ---- [A1-6] Partial cicilan: leg sukses juga harus hilang ----

    public function testA1CicilanHutangPartialRollbackSeluruhLeg(): void
    {
        $this->sesi();
        $this->seedBankTanpaAkun();

        $this->db->query("INSERT INTO db_pembelian (idpembelian, unit_idunit, total, sisa, total_bayar, bayar, bayar_tunai, bayar_bank, status, jatuh_tempo)
            VALUES (2, 1, 1000000, 1000000, 0, 0, 0, 0, 'Belum Lunas', '2026-12-31')");

        // bayar_tunai OK (unit 1 punya Kas akun 1), bayar_bank GAGAL (BCA-X).
        $r = $this->post('update_cicilan_hutang', [
            'bayar_tunai'  => '400000',
            'bayar_bank'   => '600000',
            'bank_idbank'  => 'BCA-X',
            'idpembelian'  => '2',
            'sisa'         => '0',
        ]);
        $r->assertStatus(302);

        // Leg tunai sempat berhasil ditulis sebelum leg bank gagal. Karena
        // masih satu transaksi, leg itu HARUS hilang juga — kalau tidak, ada
        // 400rb keluar tanpa 600rb dan saldo meleset.
        $this->assertSame(
            0,
            (int) $this->db->query('SELECT COUNT(*) AS c FROM db_transaksi_kas_bank')->getRow()->c,
            'partial posting harus dibatalkan seluruhnya, bukan hanya leg yang gagal'
        );
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_pembayaran_hutang')->getRow()->c);

        $p = $this->db->query('SELECT * FROM db_pembelian WHERE idpembelian=2')->getRow();
        $this->assertNotSame('Lunas', $p->status);
        $this->assertSame(0, (int)$p->bayar_tunai);
        $this->assertSame(0, (int)$p->bayar_bank);
    }

    // ---- [A1-7]Happy path tetap commit; posting ulang tetap idempoten ----

    public function testA1KasMasukValidTetapCommitDanIdempoten(): void
    {
        $this->sesi();
        $this->seedNoAkun();

        $r = $this->post('insert_kas_masuk', [
            'tanggal'     => '2026-10-05',
            'deskripsi'   => 'Penjualan tunai',
            'unit_idunit' => '1',
            'akun'        => [[
                'no_akun'             => '5-1010',
                'jumlah'              => '50000',
                'posisi_drk'          => 'debet',
                'penerima'            => 'BNI-001',
                'no_rekening'         => 'BNI-001',
                'kategori_idkategori' => '1',
            ]],
        ]);
        $r->assertStatus(302);

        // Guard pembeda: transStart tidak boleh membuat alur valid ikut gagal.
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_kas_masuk')->getRow()->c);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_transaksi_kas_bank')->getRow()->c);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_jurnal')->getRow()->c);
        $this->assertSame('', $this->flashGagal());

        // Idempotent: posting kedua harus 'skipped' (lolos, bukan rollback).
        $lib = new \App\Libraries\ModeKasBank();
        $r2 = $lib->postingKasMasuk(1);
        $this->assertSame('skipped', $r2['status']);
        $this->assertSame('sudah terposting', $r2['reason']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) AS c FROM db_transaksi_kas_bank')->getRow()->c);
    }
}
