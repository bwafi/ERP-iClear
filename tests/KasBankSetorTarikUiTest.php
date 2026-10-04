<?php

namespace Tests\Support;

use App\Libraries\ModeKasBank;
use App\Models\ModelTransaksiKasBank;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankSetorTarikService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Feature test UI Setor Tunai & Penarikan Tunai.
 *
 * Fokus test ini bukan saldo (itu sudah-covered di
 * KasBankCutoffFoundationTest), tapi hal-hal yang bisa salah di lapisan UI:
 * permission, scope rekening, anti double-submit, idempotensi, cutoff, dan
 * - opening statement yang belum diverifikasi.
 *
 * Catatan: AuthFilter.after() menutup koneksi shared tiap request, jadi setiap
 * query verifikasi dibuat lewat koneksi fresh `tests`.
 */
class KasBankSetorTarikUiTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;

    /** Akun login: admin root (lintas unit), sauf bila di-override. */
    private const AKUN_ROOT = 43;

    /** Admin cabang unit 1: role 3 (view), bukan input role. */
    private const AKUN_CABANG = 44;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = \Config\Database::connect();
        $this->schema();
        $this->seed();
    }

    protected function schema(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        foreach ([
            'db_transaksi_kas_bank', 'db_alokasi_saldo_kas_bank', 'db_saldo_awal_kas_bank',
            'db_tutup_kasir', 'db_akun_kas_bank', 'db_bank', 'db_unit', 'db_akun',
            'db_jabatan', 'db_spv_units', 'db_no_akun',
            // Tabel yang BaseController.initController sentuh tiap request.
            'db_stok_barang', 'db_service', 'db_pelanggan', 'db_menu',
        ] as $t) {
            $q('DROP TABLE IF EXISTS ' . $t);
        }

        // LOGO dipakai inc/left_vertical.php lewat ModelUnit->getById().
        $q('CREATE TABLE db_unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL, LOGO TEXT NULL)');
        $q('CREATE TABLE db_jabatan (ID_JABATAN INT PRIMARY KEY, NAMA_JABATAN TEXT NULL, ROLES_JABATAN TEXT NULL)');
        $q('CREATE TABLE db_akun (ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL, NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $q('CREATE TABLE db_spv_units (spv_id INT NULL, unit_id INT NULL)');
        $q('CREATE TABLE db_no_akun (no_akun TEXT NULL, nama_akun TEXT NULL)');
        $q('CREATE TABLE db_bank (idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL, nama_bank TEXT NULL, atas_nama TEXT NULL, norek TEXT NULL)');
        $q('CREATE TABLE db_akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0, is_finance_ho TINYINT(1) DEFAULT 0,
                created_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE db_saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
                input_by INT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE db_alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE db_transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL,
                sumber_tipe TEXT NULL, sumber_id INT NULL, keterangan TEXT NULL,
                bukti TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $q('CREATE TABLE db_tutup_kasir (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit INT NULL, tanggal TEXT NULL, akhir_cash REAL NULL)');

        $q('ALTER TABLE db_transaksi_kas_bank ADD UNIQUE KEY uniq_tkb_submission (submission_key)');

        // Sidebar (inc/left_vertical.php) selalu dirender template.php, jadi
        // tabel menu wajib ada walau test ini tidak menyentuh navigasi.
        $q('CREATE TABLE db_menu (
                idmenu INT PRIMARY KEY, categories INT NULL, nama_menu TEXT NULL,
                icon TEXT NULL, url TEXT NULL, roles TEXT NULL,
                utama INT NULL, parent INT NULL, show_menu INT NULL, urutan INT NULL)');

        $q('CREATE TABLE db_stok_barang (idbarang INT PRIMARY KEY, id_unit INT NULL, stok_akhir REAL NULL, stok_minimum REAL NULL)');
        $q('CREATE TABLE db_service (
                id_service INT PRIMARY KEY, no_service TEXT NULL, status_service INT NULL,
                pelanggan_id_pelanggan INT NULL, unit_idunit INT NULL,
                tanggal_selesai TEXT NULL, tanggal_bisa_diambil TEXT NULL, created_at TEXT NULL)');
        $q('CREATE TABLE db_pelanggan (id_pelanggan INT PRIMARY KEY, nama TEXT NULL)');
    }

    protected function seed(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES
            (1,'Probolinggo'), (2,'Jember'), (3,'Banyuwangi'), (4,'Pandaan'), (50,'Head Office')");
        // ROLES/ROLES_JABATAN harus JSON: Core::get_role() json_decode keduanya.
        $q("INSERT INTO db_jabatan (ID_JABATAN, NAMA_JABATAN, ROLES_JABATAN) VALUES
            (0,'Finance','[]'), (1,'Admin Root','[]'), (2,'Direktur','[]'),
            (3,'Admin Cabang','[]'), (34,'Manager','[]')");
        $q("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (43, 1, 1, 'Admin Root', '[]'), (44, 1, 3, 'Admin Cabang Unit 1', '[]'),
            (45, 2, 1, 'Admin Root 2', '[]')");
        $q("INSERT INTO db_bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('1','Bank BCA','0391796181','SABRINA'),
            ('2','Bank BCA','0393778773','ICLEAR DIGITAL SOLUSI CV'),
            ('3','Bank BCA','0391943558','PT ICLEAR FINANCE'),
            ('4','Bank BCA','3251427508','FARA DINDA AYUWANDA'),
            ('5','Bank BCA','1802016667','ALFARIZKI'),
            ('9017','Bank BCA','9000000017','Rekening test tanpa alokasi'),
            ('9018','Bank BCA','9000000018','Rekening test alokasi unit 1')");

        $this->seedRekening();
    }

    // =====================================================================
    // FIXTURE REKENING — WAJIB MIRROR SEMANTICS PRODUKSI
    // =====================================================================
    //
    // ID rekening di sini sengaja SAMA dengan akun_kas_bank produksi, karena
    // policy Fase 1 di-key oleh bank_idbank itu: `Config\Finance::$rekeningResmiByBank`,
    // `$rekeningWajibStatementByBank`, dan `$financeHoBankIds`.
    //
    // Kalau fixture memakai ID lain, `wajibStatementVerifikasi()` selalu
    // false, guard statement tidak pernah aktif, dan test hijau tanpa
    // menguji apa pun. Itu sebabnya ID tidak boleh diganti sembarangan.
    //
    // Konsekuensinya: setiap ID produksi WAJIB membawa semantics produksi.
    // Fixture lama melanggar ini -- akun 1 dipakai sebagai "Bank Bersama"
    // biasa (is_finance_ho 0, alokasi [1,2]), padahal di produksi akun 1
    // itu rekening kas Direksi yang TIDAK boleh punya alokasi. Test lama
    // tetap hijau karena jalur-nya belum pernah menyentuh policy; sekarang
    // itu sudah diperbaiki.
    //
    // Rekening yang memang tidak ada padanannya di produksi memakai band
    // 9xxx supaya tidak pernah tertukar dengan ID produksi.
    //
    //   1    Direksi/Finance  unit NULL, is_finance_ho 1, TANPA alokasi
    //   3    ALFARIZKI        unit 3,  private, alokasi [3]
    //   4    FARA             nonaktif, tanpa alokasi
    //   5    KAS unit 1
    //   6    KAS unit 2
    //   8    KAS unit 4
    //   10   KAS Head Office, nonaktif (sama seperti produksi)
    //   15   SABRINA          unit 4, private, alokasi [4], statement BELUM
    //   16   CV               shared, alokasi [1,2], statement VERIFIED
    //   9010 KAS kedua unit 2          (khusus test)
    //   9017 Shared tanpa alokasi      (khusus test)
    //   9018 Shared, alokasi unit 1    (khusus test)
    private function seedRekening(): void
    {
        $q = function (string $sql): void { $this->db->query($sql); };

        $q("INSERT INTO db_akun_kas_bank
                (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (1,    NULL, 'BANK', 'Bank BCA Finance (IRA)',      '3',    'aktif',    1, 1),
            (3,    3,    'BANK', 'Bank BCA Banyuwangi (ALFARIZKI)', '5', 'aktif',    0, 0),
            (4,    NULL, 'BANK', 'Bank BCA (FARA)',            '4',    'nonaktif', 0, 0),
            (5,    1,    'KAS',  'Kas ICLEAR Probolinggo',     NULL,   'aktif',    0, 0),
            (6,    2,    'KAS',  'Kas ICLEAR Jember',           NULL,   'aktif',    0, 0),
            (8,    4,    'KAS',  'Kas ICLEAR Pandaan',         NULL,   'aktif',    0, 0),
            (10,   50,   'KAS',  'Kas Head Office',            NULL,   'nonaktif', 0, 0),
            (15,   4,    'BANK', 'Bank BCA (SABRINA)',         '1',    'aktif',    0, 0),
            (16,   NULL, 'BANK', 'Bank BCA (ICLEAR DIGITAL CV)', '2',  'aktif',    1, 0),
            (9010, 2,    'KAS',  'Kas ICLEAR Jember (kedua)',  NULL,   'aktif',    0, 0),
            (9017, NULL, 'BANK', 'Bank Bersama Tanpa Alokasi', '9017', 'aktif',    1, 0),
            (9018, NULL, 'BANK', 'Bank Bersama Alokasi Unit 1', '9018', 'aktif',   1, 0)");

        // Statement cut-off terverifikasi. KAS juga punya statement di sini --
        // KasBankCutoffService::saldoFisik() membaca statement untuk semua
        // tipe akun. `tutup_kasir.akhir_cash` (lihat di bawah) adalah
        // cross-check unit, bukan sumber saldoFisik().
        //
        // Rekening Direksi (1) sengaja TIDAK punya statement: di produksi pun
        // begitu, karena kas Direksi tidak tunduk pada mekanisme statement.
        $q("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (16,   '2026-09-30', 100000000, 'Koran 30 Sep',     'VERIFIED'),
            (3,    '2026-09-30',  20000000, 'Koran 30 Sep',     'VERIFIED'),
            (15,   '2026-09-30',        0, 'Koran belum masuk', 'BELUM_VERIFIKASI'),
            (5,    '2026-09-30',   1486120, 'Kas Unit 1',       'VERIFIED'),
            (6,    '2026-09-30',   1115000, 'Kas Unit 2',       'VERIFIED'),
            (8,    '2026-09-30',   1115000, 'Kas Unit 4',       'VERIFIED'),
            (10,   '2026-09-30',         0, 'Kas Head Office',  'VERIFIED'),
            (9010, '2026-09-30',         0, 'Kas Unit 2 (2)',   'VERIFIED'),
            (9017, '2026-09-30',  30000000, 'Koran 30 Sep',     'VERIFIED'),
            (9018, '2026-09-30',  30000000, 'Koran 30 Sep',     'VERIFIED')");

        // Alokasi entitlement, MIRROR Config\Finance::$rekeningResmiByBank:
        //   16 => [1, 2]   CV
        //   3  => [3]      ALFARIZKI
        //   15 => [4]      SABRINA
        //   1  => []       Direksi -- WAJIB kosong (aturan M4)
        // 9018 dan 9017 adalah rekening test: 9018 punya alokasi unit 1,
        // 9017 sengaja TIDAK punya alokasi apa pun supaya test "shared tanpa
        // alokasi" benar-benar menguji entitlement, bukan akun yang tidak ada.
        $q("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (16,  1, 25000000, 'Probolinggo'),
            (16,  2, 15000000, 'Jember'),
            (3,   3, 20000000, 'Banyuwangi'),
            (15,  4,  5000000, 'Pandaan'),
            (9018, 1,  5000000, 'Unit 1, khusus test alokasi')");

        // Dari 100jt di rekening CV, hanya 40jt yang diketahui kepemilikannya.
        // Unit 50 (HO) sengaja tanpa closing.
        $q("INSERT INTO db_tutup_kasir (unit, tanggal, akhir_cash) VALUES
            (1, '2026-09-30', 1486120),
            (2, '2026-09-30', 1115000),
            (3, '2026-09-30', 1917000),
            (4, '2026-09-30', 4000)");
    }

    // =================================================================
    // Sesi & helper
    // =================================================================

    private function sesi(int $akun = self::AKUN_ROOT, string $token = '', ?int $unit = null, ?int $jabatan = null): void
    {
        $s = [
            'logged_in'  => true,
            'ID_AKUN'    => $akun,
            'ID_UNIT'    => $unit ?? 1,
            'ID_JABATAN' => $jabatan ?? 1,
        ];
        if ($token !== '') {
            $s['kb_submit_' . $token] = time();
        }
        $this->withSession($s);
    }

    /** Model dengan koneksi fresh (AuthFilter menutup koneksi shared). */
    private function model(string $class): object
    {
        return new $class(\Config\Database::connect('tests', false));
    }

    /** Payload POST yang sah: setor dari kas unit 1 ke rekening CV (akun 16). */
    private function setorPayload(string $token, array $ubah = []): array
    {
        return array_merge([
            'unit_id'       => '1',
            'akun_kas_id'   => '5',
            'akun_bank_id'  => '16',
            'nominal'       => '1000000',
            'tanggal'       => FinanceScopeService::periodeMulaiDate(),
            'keterangan'    => 'Setor kas harian',
            'submit_token'  => $token,
            'operation_key' => 'STR-FORGED',
        ], $ubah);
    }

    /** Payload POST penarikan dari rekening CV (akun 16) ke kas unit 1. */
    private function penarikanPayload(string $token, array $ubah = []): array
    {
        return array_merge([
            'unit_id'       => '1',
            'akun_kas_id'   => '5',
            'akun_bank_id'  => '16',
            'nominal'       => '1000000',
            'tanggal'       => FinanceScopeService::periodeMulaiDate(),
            'keterangan'    => 'Tarik kas operasional',
            'submit_token'  => $token,
            'operation_key' => 'PNK-FORGED',
        ], $ubah);
    }

    /**
     * Kirim POST dengan token CSRF yang valid.
     *
     * Route Setor/Penarik dilindungi filter `csrf`. Token dibuat lewat
     * Security service (tokenRandomize = false, jadi token == hash) alih-alih
     * meng-crawl halaman form, supaya test ini tidak ikut gagal kalau halaman
     * form sengaja menolak dirender (mis. test role non-input).
     *
     * Token harus dibuat ulang tiap POST: Security::$regenerate = true
     * commemorate hash yang sama setelah verify.
     *
     * @param array<string, mixed> $payload
     */
    private function postCsrf(string $url, array $payload): void
    {
        $security = \Config\Services::security();
        $security->generateHash();

        $this->post($url, $payload + ['csrf_test_name' => $security->getHash()])->assertRedirect();
    }

    /**
     * Pesan flash hasil aksi terakhir.
     *
     * Flash ini dibaca lewat helper session() yang sama dengan yang dipakai
     * controller. template.php menaruhnya ke toastr.success/warning, jadi isi
     * flash = teks yang benar-benar dibacakan user.
     *
     * Catatan: flash belum dibaca harus diambil di request yang sama; follow-up
     * GET akan me-reset mock session.
     */
    private function pesanTampil(): string
    {
        $gagal  = (string) session()->getFlashdata('gagal');
        $sukses = (string) session()->getFlashdata('sukses');

        return $gagal !== '' ? $gagal : $sukses;
    }

    // =================================================================
    // 1. Permission
    // =================================================================

    public function testHalamanSetorTunaiDitolakUntukRoleNonInput(): void
    {
        // Role 3 = admin cabang: boleh lihat, tidak boleh input.
        $this->sesi(self::AKUN_CABANG, '', 1, 3);

        $this->get('kas_bank/setor-tunai')->assertRedirect();
    }

    public function testSimpanSetorTunaiDitolakUntukRoleNonInput(): void
    {
        $this->sesi(self::AKUN_CABANG, 'tok-perm', 1, 3);

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-perm'));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertStringContainsString('tidak berhak', $this->pesanTampil());
    }

    // =================================================================
    // 2. Scope unit & rekening
    // =================================================================

    public function testUnitLuarCakupanUserDitolak(): void
    {
        $this->sesi(self::AKUN_CABANG, 'tok-unit-luar');
        $this->withSession(['ID_JABATAN' => 1]); // root, tapi unit 1

        // Stamping leg ke unit 3 (di luar user scope akun cabang).
        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-unit-luar', ['unit_id' => '3']));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
    }

    public function testRekeningBankLuarScopeDitolak(): void
    {
        // Akun 45 = admin root unit 2. Menyetor dari laci unit 2 (KAS 6) ke
        // rekening CV (16) itu sah. Diganti ke rekening nonaktif -> tolak.
        // Nonaktif di produksi adalah FARA (akun 4).
        $this->sesi(45, 'tok-rek', 2);

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-rek', [
            'unit_id'      => '2',
            'akun_kas_id'  => '6',
            'akun_bank_id' => '4',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertStringContainsString('tidak valid', $this->pesanTampil());
    }

    public function testSetorKeRekeningMilikUnitLainDitolak(): void
    {
        // Akun 43 = admin root unit 1. User scope-nya semua unit (root), tapi
        // rekening CV (16) sebagai TUJUAN tetap boleh untuk root lintas unit.
        // Yang ditolak: KAS dari unit lain di-stamp ke unit 1.
        $this->sesi(self::AKUN_ROOT, 'tok-kas-luar');

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-kas-luar', [
            'unit_id'     => '1',
            'akun_kas_id' => '6', // laci kas unit 2
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertStringContainsString('bukan milik unit', $this->pesanTampil());
    }

    // =================================================================
    // 3. Anti double-submit & idempotensi
    // =================================================================

    public function testDoubleSubmitTokenSamaHanyaMenulisSatuOperation(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-double');

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-double'));

        $m = $this->model(ModelTransaksiKasBank::class);
        $this->assertSame(2, $m->countAllResults(), 'Setor harus menulis 2 kaki');
        $this->assertSame(2, $m->where('sumber_tipe', KasBankSetorTarikService::SUMBER_TIPE_SETOR)
            ->countAllResults());

        // Submit kedua dengan token yang sama: token sudah dikonsumsi.
        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-double'));

        $this->assertSame(2, $this->model(ModelTransaksiKasBank::class)->countAllResults(),
            'Klik kedua tidak boleh menambah baris');
    }

    public function testSubmitTokenTidakValidDitolak(): void
    {
        $this->sesi(self::AKUN_ROOT); // tanpa token di session

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('token-palsu'));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertStringContainsString('sudah dikirim', $this->pesanTampil());
    }

    public function testOperationKeyDariPostDiabaikanDanDipakaiKeyServer(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-key');

        // operation_key dipalsukan di POST. Controller harus memakai key dari
        // session, bukan nilai kiriman user.
        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-key'));

        $m   = $this->model(ModelTransaksiKasBank::class);
        $all = $m->findAll();

        $this->assertCount(2, $all, 'Kedua kaki harus ada');

        $kunci = array_map(fn ($b) => $b->submission_key, $all);
        $this->assertNotContains('STR-FORGED', $kunci, 'Key kiriman user tidak boleh dipakai');

        // Hanya kaki pertama yang membawa key; kaki kedua NULL karena
        // submission_key punya UNIQUE global.
        $nonNull = array_values(array_filter($kunci, fn ($k) => $k !== null && $k !== ''));
        $this->assertCount(1, $nonNull, 'submission_key hanya boleh pada satu kaki');
        $this->assertStringStartsWith('STR-', $nonNull[0]);
    }

    public function testOperationKeyIdempotenUntukUnitYangBerbeda(): void
    {
        // Dua unit dengan key SAMA harus tetap dua operasi: key di-scope per
        // (jenis, unit) di session, jadi tidak bentrok.
        $this->sesi(self::AKUN_ROOT, 'tok-u1');
        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-u1'));

        $this->sesi(self::AKUN_ROOT, 'tok-u2');
        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-u2', [
            'unit_id'     => '2',
            'akun_kas_id' => '6',
        ]));

        $m = $this->model(ModelTransaksiKasBank::class);
        $this->assertSame(4, $m->countAllResults(), 'Dua unit = dua operasi terpisah');
        $this->assertSame(2, $m->where('submission_key IS NOT NULL', null, false)->countAllResults());
    }

    // =================================================================
    // 4. Cut-off & guard saldo
    // =================================================================

    public function testSetorTanggalSebelumPeriodeDitolak(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-tanggal');

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-tanggal', [
            'tanggal' => '2026-09-01',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertNotEmpty($this->pesanTampil());
    }

    public function testSetorMelebihiSaldoKasDitolak(): void
    {
        // Saldo laci unit 1 pada cut-off hanya 1.486.120.
        $this->sesi(self::AKUN_ROOT, 'tok-saldo');

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-saldo', [
            'nominal' => '9000000',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertStringContainsString('tidak cukup', $this->pesanTampil());
    }

    public function testSetorDenganLaciTanpaStatementTidakDianggapSaldoNol(): void
    {
        // Unit 50 punya laci kas (akun 11) tapi statement-nya belum diisi.
        // Angka laci harus dianggap "belum diketahui", bukan 0 -- jadi setor
        // tidak boleh lolos begitu saja hanya karena 0 < nominal.
        $this->sesi(self::AKUN_ROOT, 'tok-ho');

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-ho', [
            'unit_id'     => '50',
            'akun_kas_id' => '10',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertNotEmpty($this->pesanTampil());
    }

    // =================================================================
    // 5. Statement belum diverifikasi
    // =================================================================

    /**
     * Rekening yang WAJIB punya statement tidak boleh dipakai sebelum
     * statement-nya diverifikasi Finance -- untuk SETOR maupun TARIK.
     *
     * Test lama di sini memakai rekening bersama (bukan yang wajib statement)
     * dan affirme bahwa setor ke rekening belum verifikasi tetap boleh.
     * Setelah fixture dibenahi ke semantics produksi, akun 15 = SABRINA dan
     * `rekeningWajibStatementByBank` memang memuatnya. Jadi ekspektasi
     * lama itu sudah bertabrakan dengan policy Fase 1, dan sekarang ditegakkan.
     */
    public function testSetorKeRekeningWajibStatementBelumTerverifikasiDitolak(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-unverif');

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-unverif', [
            'unit_id'      => '4',
            'akun_kas_id'  => '8',
            'akun_bank_id' => '15',
            'nominal'      => '1000000',
        ]));

        $this->assertSame(
            $before,
            $this->model(ModelTransaksiKasBank::class)->countAllResults(),
            'Rekening wajib statement belum diverifikasi tidak boleh menambah saldo'
        );
        $this->assertStringContainsStringIgnoringCase('statement', $this->pesanTampil());
    }

    /**
     * Rekening kas Direksi (akun 1) DIKECUALIKAN dari mekanisme statement.
     *
     * Di sinilah aturan bisnis "uang masuk boleh dicatat lebih dulu, koran bank
     * menyusul" benar-benar berlaku: saldo kas Direksi memang di luar unit dan
     * tidak punya cutoff statement. Ekspektasi lama dipindah ke sini, ke kelas
     * rekening yang memang production izinkan -- bukan ke rekening operasional
     * yang wajib statement.
     */
    public function testSetorKeRekeningDireksiTanpaStatementTetapBoleh(): void
    {
        // Role 1 ada di financeHoSourceRoles [0, 1], jadi berwenang memakai
        // kas Direksi.
        $this->sesi(self::AKUN_ROOT, 'tok-direksi', 1, 1);

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-direksi', [
            'akun_bank_id' => '1',
            'nominal'      => '1000000',
        ]));

        $m = $this->model(ModelTransaksiKasBank::class);
        $this->assertSame(2, $m->countAllResults(), 'Setor ke kas Direksi tetap dicatat');

        $sukses = $this->pesanTampil();
        $this->assertStringContainsString('berhasil', $sukses);
    }

    public function testPenarikanDariSharedTanpaAlokasiUnitDitolak(): void
    {
        // Rekening 9017 (khusus test) = shared, tapi TIDAK punya baris alokasi
        // untuk unit 1. Entitlement unit 1 di sana 0, jadi penarikan harus
        // ditolak walaupun statement-nya sudah terverifikasi. Batasnya
        // entitlement, bukan saldo.
        //
        // Catatan: versi lama memakai id 17 yang TIDAK PERNAH ada di tabel
        // akun_kas_bank, jadi test ini sebenarnya ditolak karena rekeningnya
        // tidak ditemukan -- bukan karena entitlement. Sekarang rekeningnya
        // benar-benar ada, jadi yang diuji beneran entitlement.
        $this->sesi(self::AKUN_ROOT, 'tok-tanpa-alokasi');

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/penarikan-tunai/save', $this->penarikanPayload('tok-tanpa-alokasi', [
            'akun_bank_id' => '9017',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertNotEmpty($this->pesanTampil());
    }

/**
     * Yang membatasi penarikan adalah ALOKASI unit, bukan saldo rekening.
     *
     * Rekening 9018 (khusus test) berstatement VERIFIED 30jt, tapi alokasi unit 1
     * hanya 5jt. Tarik 5jt boleh; tarik 5jt + 1 ripit ditolak walau saldo fisik
     * masih 25jt. Kalau yang jadi pagar adalah saldo statement, test ini gagal.
     *
     * Versi lama memakai SABRINA (akun 15) yang statement-nya BELUM VERIFIKASI.
     * Itu tidak lagi sah dipakai: `rekeningWajibStatementByBank` memuat 15,
     * jadi penarikan dari sana ditolak di gate statement -- bukan di gate
     * entitlement yang mestinya diuji test ini.
     */
    public function testPenarikanDibatasiAlokasiBukanSaldoRekening(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-alokasi');

        $this->postCsrf('kas_bank/penarikan-tunai/save', $this->penarikanPayload('tok-alokasi', [
            'akun_bank_id' => '9018',
            'nominal'      => '5000000',
        ]));

        $this->assertSame(2, $this->model(ModelTransaksiKasBank::class)->countAllResults());

        // Di atas alokasi -> ditolak.
        $this->sesi(self::AKUN_ROOT, 'tok-alokasi2');
        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/penarikan-tunai/save', $this->penarikanPayload('tok-alokasi2', [
            'akun_bank_id' => '9018',
            'nominal'      => '5000001',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
    }

    // =================================================================
    // 6. Shared account & entitlement
    // =================================================================

    public function testPenarikanTidakBolehLebihDariEntitlementUnit(): void
    {
        // Rekening CV (16): statement 100jt, alokasi unit 1 hanya 25jt.
        // Tarik 30jt harus ditolak walau saldo rekening 100jt.
        $this->sesi(self::AKUN_ROOT, 'tok-entitle');

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/penarikan-tunai/save', $this->penarikanPayload('tok-entitle', [
            'nominal' => '30000000',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
        $this->assertNotEmpty($this->pesanTampil());
    }

    public function testPenarikanSampaiEntitlementUnitDiterima(): void
    {
        // Unit 1 punya 25jt di rekening CV. Tarik tepat 25jt -> boleh,
        // dan posisi unit 1 jadi 0.
        $this->sesi(self::AKUN_ROOT, 'tok-25jt');

        $this->postCsrf('kas_bank/penarikan-tunai/save', $this->penarikanPayload('tok-25jt', [
            'nominal' => '25000000',
        ]));

        $this->assertSame(2, $this->model(ModelTransaksiKasBank::class)->countAllResults());

        $sukses = $this->pesanTampil();
        $this->assertStringContainsString('berhasil', $sukses);
    }

    public function testPenarikanDariRekeningMilikUnitLainDitolak(): void
    {
        // User unit 1 menarik dari rekening privat ALFARIZKI (akun 3), yang di
        // produksi milik unit 3.
        $this->sesi(self::AKUN_CABANG, 'tok-privat', 1, 1);

        $before = $this->model(ModelTransaksiKasBank::class)->countAllResults();

        $this->postCsrf('kas_bank/penarikan-tunai/save', $this->penarikanPayload('tok-privat', [
            'akun_bank_id' => '3',
        ]));

        $this->assertSame($before, $this->model(ModelTransaksiKasBank::class)->countAllResults());
    }

    // =================================================================
    // 7. Pesan error
    // =================================================================

    public function testGagalTidakMembocorkanDetailTeknis(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-teknis');

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-teknis', [
            'nominal' => '0',
        ]));

        $gagal = $this->pesanTampil();
        $this->assertNotEmpty($gagal);
        $this->assertStringNotContainsString('SQLSTATE', $gagal);
        $this->assertStringNotContainsString('Stack trace', $gagal);
    }

    public function testSuksesMengandungReferensiTransfer(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-ref');

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-ref'));

        $sukses = $this->pesanTampil();
        $this->assertMatchesRegularExpression('/Referensi\s+SETOR_TUNAI-/', $sukses);
    }

    // =================================================================
    // 8. Audit: satu operation = dua kaki
    // =================================================================

    public function testSetorMenulisDuaKakiDenganTransferRefSama(): void
    {
        $this->sesi(self::AKUN_ROOT, 'tok-audit');

        $this->postCsrf('kas_bank/setor-tunai/save', $this->setorPayload('tok-audit'));

        $m  = $this->model(ModelTransaksiKasBank::class);
        $all = $m->findAll();

        $this->assertCount(2, $all);

        $ref = array_unique(array_map(fn ($b) => $b->transfer_ref, $all));
        $this->assertCount(1, $ref, 'Kedua kaki harus punya transfer_ref sama');

        $arah = array_map(fn ($b) => $b->arah, $all);
        sort($arah);
        $this->assertSame([ModeKasBank::ARAH_KELUAR, ModeKasBank::ARAH_MASUK], $arah);

        foreach ($all as $b) {
            $this->assertSame('SETOR_TUNAI', $b->sumber_tipe);
        }
    }

    // =================================================================
    // 9. Transparansi di preview
    // =================================================================

    public function testFormSetorTunaiMemuatCsrfField(): void
    {
        $this->sesi();
        $body = $this->get('kas_bank/setor-tunai')->getBody();

        $this->assertStringContainsString('name="csrf_test_name"', $body);
        $this->assertStringContainsString('name="operation_key"', $body);
        $this->assertStringContainsString('name="submit_token"', $body);
    }

    public function testFormPenarikanTunaiMemuatCsrfField(): void
    {
        $this->sesi();
        $body = $this->get('kas_bank/penarikan-tunai')->getBody();

        $this->assertStringContainsString('name="csrf_test_name"', $body);
    }

    public function testSharedAccountDiberiPenjelasanye(): void
    {
        // Rekening bersama harus ditandai jelas di form, supaya user tahu dana
        // yang disetor menjadi hak unit dia -- bukan saldo rekening miliknya.
        $this->sesi();
        $body = $this->get('kas_bank/setor-tunai')->getBody();

        $this->assertStringContainsString('Shared Account', $body);
    }

    public function testStatementBelumTerverifikasiTidakDitampilkanSebagaiSaldoNol(): void
    {
        $this->sesi();

        // Preview ke SABRINA (akun 15, unit 4) yang statement-nya BELUM VERIFIKASI.
        $security = \Config\Services::security();
        $security->generateHash();
        $body = $this->post('kas_bank/setor-tunai', [
            'unit_id'      => '4',
            'akun_kas_id'  => '8',
            'akun_bank_id' => '15',
            'nominal'      => '1000000',
            'tanggal'      => FinanceScopeService::periodeMulaiDate(),
            'keterangan'   => '',
            'csrf_test_name' => $security->getHash(),
        ])->getBody();

        $this->assertStringContainsString('BELUM DIVERIFIKASI', $body);
        $this->assertStringContainsString('belum tersedia', $body);

        // Angka placeholder 0 tidak boleh muncul sebagai saldo rekening.
        $this->assertStringNotContainsString('saldo fisik Rp 0', $body);
    }

    public function testPreviewMenolakSimpanKalauEntitlementTidakCukup(): void
    {
        // Penarikan 30jt dari rekening CV yang hanya 25jt untuk unit 1.
        $this->sesi();

        $security = \Config\Services::security();
        $security->generateHash();
        $body = $this->post('kas_bank/penarikan-tunai', [
            'unit_id'      => '1',
            'akun_kas_id'  => '5',
            'akun_bank_id' => '16',
            'nominal'      => '30000000',
            'tanggal'      => FinanceScopeService::periodeMulaiDate(),
            'keterangan'   => '',
            'csrf_test_name' => $security->getHash(),
        ])->getBody();

        $this->assertStringContainsString('belum bisa diproses', $body);
        // Tombol simpan harus mati supaya user tidak mendapat penolakan yang
        // bisa dihindari.
        $this->assertStringContainsString('disabled', $body);
        // Tidak boleh ada satu pun angka 0 yang diklaim sebagai saldo riil.
        $this->assertStringNotContainsString('saldo fisik Rp 0</div>', $body);
    }
}
