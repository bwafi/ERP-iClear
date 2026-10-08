<?php

namespace Tests;

use App\Models\ModelAlokasiSaldoKasBank;
use App\Models\ModelAkunKasBank;
use App\Models\ModelTransaksiKasBank;
use App\Services\Finance\BankRekeningValidator;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KasBankSetorTarikService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Regression test fondasi Kas/Bank Finance.
 *
 * Yang diuji di sini adalah definisi saldo yang dipakai semua laporan:
 *
 *     saldo_fisik   = statement(cut-off) + net movement (>= 1 Okt)
 *     posisi_unit   = opening allocation + net movement unit
 *     LEGACY        = statement - SUM(opening allocation)
 *
 * Bugs yang paling mahal dicegah di sini adalah yang TERLIHAT BENAR:
 * saldo total yang konsisten sendiri tapi salah orangnya.
 */
class KasBankCutoffFoundationTest extends CIUnitTestCase
{
    protected $db;
    protected KasBankCutoffService $cutoff;
    protected KasBankSetorTarikService $pindah;

    /**
     * Semua tanggal RELATIF terhadap cut-off KasBank supaya tidak basi saat
     * config digeser:
     *
     *   tglCutoff     = Finance::$kasBankCutoffDate (tanggal statement/opening)
     *   tglMulai      = Finance::$kasBankPeriodeMulaiDate (hari pertama ledger)
     *   h1..h4        = hari ke-1..ke-4 periode
     *   setelahCutoff = sesudah tanggal cut-off (statement di luar baseline)
     *   legacy        = jauh sebelum cut-off (transaksi legacy)
     */
    private string $tglCutoff;
    private string $tglMulai;
    private string $h1;
    private string $h2;
    private string $h4;
    private string $setelahCutoff;
    private string $legacy;

    // Rekening yang dipakai test:
    //   1 = Bank Bersama (BNI-001, shared, statement VERIFIED 100jt)
    //   2 = KAS Unit 1
    //   3 = Bank Privat Unit 2 (non-shared, statement VERIFIED 20jt)
    //   9 = Rekening Finance/HO (IRA-001, shared tapi tanpa alokasi)
    //   15 = Bank yang statement-nya BELUM diverifikasi

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        $this->tglCutoff     = FinanceScopeService::kasBankCutoffDate();
        $this->tglMulai      = FinanceScopeService::kasBankPeriodeMulaiDate();
        $this->h1            = date('Y-m-d', strtotime($this->tglMulai . ' +1 day'));
        $this->h2            = date('Y-m-d', strtotime($this->tglMulai . ' +2 day'));
        $this->h4            = date('Y-m-d', strtotime($this->tglMulai . ' +4 day'));
        $this->setelahCutoff = date('Y-m-d', strtotime($this->tglCutoff . ' +8 day'));
        $this->legacy        = date('Y-m-d', strtotime($this->tglCutoff . ' -30 day'));

        $this->schema();
        $this->seed();

        $_SESSION['ID_AKUN']      = 43;
        $_SESSION['ID_UNIT']      = 1;
        $_SESSION['ID_JABATAN']   = 1;

        $this->cutoff = new KasBankCutoffService();
        $this->pindah = new KasBankSetorTarikService();
    }

    private function q(string $sql): void
    {
        $this->db->query($sql);
    }

    private function schema(): void
    {
        foreach ([
            'db_transaksi_kas_bank', 'db_alokasi_saldo_kas_bank', 'db_saldo_awal_kas_bank',
            'db_akun_kas_bank', 'db_tutup_kasir', 'db_unit', 'db_bank', 'db_akun',
        ] as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }

        // Wajib ada: guard user-scope di KasBankSetorTarikService membaca
        // FinanceScopeService::scopeInfo(), yang mengambil role dari db_akun
        // (bukan dari session). Tanpa tabel ini, penentuan user scope tidak
        // mungkin dilakukan dan Setor/Tarik tidak akan pernah lolos.
        $this->q('CREATE TABLE db_akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $this->q('CREATE TABLE db_unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $this->q('CREATE TABLE db_bank (idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL, norek TEXT NULL, atas_nama TEXT NULL)');
        $this->q('CREATE TABLE db_akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0, is_finance_ho TINYINT(1) DEFAULT 0)');
        $this->q('CREATE TABLE db_saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE db_transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL,
                sumber_tipe TEXT NULL, sumber_id INT NULL, keterangan TEXT NULL,
                bukti TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE db_alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE db_tutup_kasir (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit INT NULL, tanggal TEXT NULL, akhir_cash REAL NULL)');

        // UNIQUE global pada submission_key: ini yang membuat idempotensi
        // mungkin tanpa tabel operation terpisah.
        $this->q('ALTER TABLE db_transaksi_kas_bank ADD UNIQUE KEY uniq_tkb_submission (submission_key)');
    }

    private function seed(): void
    {
        $this->q("INSERT INTO db_unit (idunit, NAMA_UNIT) VALUES
            (1,'Probolinggo'), (2,'Jember'), (3,'Banyuwangi'), (4,'Pandaan'),
            (5,'Genteng'), (50,'Head Office')");

        // Login untuk guard user-scope. ID_JABATAN 1 = Admin Root, yang ada di
        // FinanceScopeService::inputRoles() sehingga user scope-nya lintas unit.
        $this->q("INSERT INTO db_akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (43, 1, 1, 'Admin Root', '[]')");

        $this->q("INSERT INTO db_bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('BNI-001','Bank BNI','123','PT Contoh'),
            ('IRA-001','Bank BCA','0391943558','PT Contoh Finance'),
            ('1','Bank Lama','999','PT Contoh'),
            ('2','Bank Lama 2','888','PT Contoh')");

        $this->q("INSERT INTO db_akun_kas_bank
                (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, status, is_shared, is_finance_ho) VALUES
            (1,  NULL, 'BANK', 'Bank Bersama',    'BNI-001', 'aktif', 1, 0),
            (2,  1,    'KAS',  'Kas Probolinggo', NULL,       'aktif', 0, 0),
            (3,  2,    'BANK', 'Bank Privat Jember','1',       'aktif', 0, 0),
            (4,  2,    'KAS',  'Kas Jember',       NULL,       'aktif', 0, 0),
            (5,  5,    'KAS',  'Kas Genteng',     NULL,       'aktif', 0, 0),
            (9,  NULL, 'BANK', 'Rekening Finance/HO','IRA-001','aktif', 1, 1),
            (15, NULL, 'BANK', 'Belum Terverifikasi','2',    'aktif', 1, 0)");

        // Statement cut-off yang SUDAH diverifikasi Finance.
        $this->q("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (1, '{$this->tglCutoff}', 100000000, 'Koran 30 Sep', 'VERIFIED'),
            (3, '{$this->tglCutoff}',  20000000, 'Koran 30 Sep', 'VERIFIED'),
            (9, '{$this->tglCutoff}',  30000000, 'Koran 30 Sep', 'VERIFIED')");

        // Rekening 15 sengaja placeholder: saldo 0 dan BELUM diverifikasi.
        $this->q("INSERT INTO db_saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (15, '{$this->tglCutoff}', 0, 'Placeholder, koran belum masuk', 'BELUM_VERIFIKASI')");

        // Opening allocation: dari 100jt di rekening bersama, hanya 40jt yang
        // sudah diketauhui kepemilikannya. 60jt jadi LEGACY.
        $this->q("INSERT INTO db_alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan) VALUES
            (1, 1, 25000000, 'Probolinggo'),
            (1, 2, 15000000, 'Jember'),
            (3, 2, 20000000, 'Jember')");

        // Closing kas cut-off. Unit 50 (HO) sengaja TIDAK punya closing.
        $this->q("INSERT INTO db_tutup_kasir (unit, tanggal, akhir_cash) VALUES
            (1, '{$this->tglCutoff}', 1486120), (2, '{$this->tglCutoff}', 1115000),
            (3, '{$this->tglCutoff}', 1917000), (4, '{$this->tglCutoff}', 4000),
            (5, '{$this->tglCutoff}', 855500)");

        // Closing telat: angka 31 Okt TIDAK boleh dipakai untuk baseline 30 Sep.
        $this->q("INSERT INTO db_tutup_kasir (unit, tanggal, akhir_cash) VALUES
            (1, '{$this->tglMulai}', 7777777)");
    }

    // =================================================================
    // 1. Cut-off kas
    // =================================================================

    public function testSaldoKasCutoffHanyaAmbilClosingPersisPadaTanggalCutoff(): void
    {
        $baris = KasBankCutoffService::querySaldoKasCutoff();

        $byUnit = [];
        foreach ($baris as $b) {
            $byUnit[(int) $b['unit_id']] = (int) $b['saldo'];
        }

        $this->assertSame(1486120, $byUnit[1], 'Harus 1.486.120 dari 30 Sep, bukan 7.777.777 dari 1 Okt');
        $this->assertSame(1115000, $byUnit[2]);
        $this->assertArrayNotHasKey(50, $byUnit, 'Unit tanpa closing tidak boleh muncul sebagai 0');
        $this->assertCount(5, $baris);
    }

    // =================================================================
    // 2. Unit tanpa closing
    // =================================================================

    public function testUnitTanpaClosingCutoffTermasukHeadOffice(): void
    {
        $tanpa = KasBankCutoffService::unitTanpaClosingCutoff();

        $this->assertContains(50, $tanpa, 'Unit 50 wajib dilaporkan sebagai perlu konfirmasi, bukan 0');
        $this->assertNotContains(1, $tanpa);
    }

    // =================================================================
    // 3. Statement reference
    // =================================================================

    public function testStatementEfektifDipilihPadaTanggalTerakhirSebelumSampai(): void
    {
        // Statement kedua, SESUDAH cut-off, tidak boleh menggantikan baseline.
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 1,
            'tanggal'          => $this->setelahCutoff,
            'saldo'            => 777777777,
            'keterangan'       => 'Koran 15 Okt',
            'status'           => 'VERIFIED',
        ]);

        $this->assertSame(100000000, $this->cutoff->saldoStatement(1),
            'Saldo aktif harus memakai statement 30 Sep, bukan statement 15 Okt');
    }

    public function testHanyaSatuStatementYangDijumlahkanBukanSemua(): void
    {
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 1,
            'tanggal'          => $this->setelahCutoff,
            'saldo'            => 777777777,
            'status'           => 'VERIFIED',
        ]);

        // Kalau statement dijumlahkan semua, hasilnya 877.777.777.
        $this->assertSame(100000000, $this->cutoff->saldoFisik(1));
    }

    // =================================================================
    // 4. Batas bawah movement
    // =================================================================

    public function testCutOffKasBankTerpisahDariCutOffFinanceGlobal(): void
    {
        // Cut-off & periode KasBank punya config SENDIRI. Cut-off Finance
        // global tidak boleh ikut tergeser (dipakai TutupKasir, Hutang-Piutang,
        // dan KPI Cash Flow).
        $this->assertSame($this->tglCutoff, FinanceScopeService::kasBankCutoffDate());
        $this->assertSame($this->tglMulai, FinanceScopeService::kasBankPeriodeMulaiDate());
        $this->assertSame(
            date('Y-m-d', strtotime($this->tglCutoff . ' +1 day')),
            $this->tglMulai,
            'Periode ledger KasBank harus cut-off + 1 hari'
        );
        $this->assertNotSame(
            FinanceScopeService::cutoffDate(),
            FinanceScopeService::kasBankCutoffDate(),
            'Cut-off KasBank tidak boleh menimpa cut-off Finance global'
        );
        $this->assertNotSame(
            FinanceScopeService::periodeMulaiDate(),
            FinanceScopeService::kasBankPeriodeMulaiDate(),
            'Periode KasBank tidak boleh menimpa periode Finance global'
        );
    }

    public function testMutasiSebelumPeriodeMulaiDiabaikanDanSesudahnyaDihitung(): void
    {
        $m = new ModelTransaksiKasBank();
        // Tanggal cut-off = tanggal statement, BUKAN mutasi.
        $m->insert(['tanggal' => $this->tglCutoff, 'unit_id' => 1, 'akun_kas_bank_id' => 1,
            'jenis' => 'PEMASUKAN', 'arah' => 'MASUK', 'jumlah' => 999]);
        // Hari pertama periode operasional KasBank.
        $m->insert(['tanggal' => $this->tglMulai, 'unit_id' => 1, 'akun_kas_bank_id' => 1,
            'jenis' => 'PEMASUKAN', 'arah' => 'MASUK', 'jumlah' => 5000]);
        $m->insert(['tanggal' => $this->h4, 'unit_id' => 1, 'akun_kas_bank_id' => 1,
            'jenis' => 'PENGELUARAN', 'arah' => 'KELUAR', 'jumlah' => 2000]);

        // 100.000.000 + 5.000 - 2.000. Angka 999 pada tanggal cut-off TIDAK masuk.
        $this->assertSame(100003000, $this->cutoff->saldoFisik(1));
    }

    // =================================================================
    // 5. LEGACY residual
    // =================================================================

    public function testLegacyAdalahStatementKurangTotalOpeningAllocation(): void
    {
        $this->assertSame(40000000, $this->cutoff->totalOpeningAllocation(1));
        // 100jt - 25jt - 15jt = 60jt
        $this->assertSame(60000000, $this->cutoff->legacyUnassigned(1));
        // Non-shared milik unit 2: dialokasikan penuh, jadi LEGACY 0.
        $this->assertSame(0, $this->cutoff->legacyUnassigned(3));
    }

    public function testLegacyTidakJadiSaldoUnitTanpaEntitlement(): void
    {
        // Unit 5 tidak punya alokasi di rekening bersama. Saldo fisik 100jt
        // dan LEGACY 60jt keduanya BUKAN haknya.
        $this->assertFalse($this->cutoff->entitled(1, 5));
        $this->assertSame(0, $this->cutoff->posisiUnit(1, 5));

        // Rekening Finance/HO/shared: secara bentuk mirip rekening bersama,
        // tapi tidak punya baris alokasi -> tidak ada yang entitled.
        $this->assertFalse($this->cutoff->entitled(9, 1));
        $this->assertSame(0, $this->cutoff->posisiUnit(9, 1));
    }

    // =================================================================
    // 6. Invariant
    // =================================================================

    public function testInvariantRekeningSeimbangSetelahAdaMutasi(): void
    {
        $m = new ModelTransaksiKasBank();
        $m->insert(['tanggal' => $this->h2, 'unit_id' => 2, 'akun_kas_bank_id' => 1,
            'jenis' => 'PEMASUKAN', 'arah' => 'MASUK', 'jumlah' => 3000000]);
        $m->insert(['tanggal' => $this->h4, 'unit_id' => 1, 'akun_kas_bank_id' => 1,
            'jenis' => 'PENGELUARAN', 'arah' => 'KELUAR', 'jumlah' => 1500000]);

        $cek = $this->cutoff->cekInvariant(1);
        $this->assertSame(
            KasBankCutoffService::INV_SEIMBANG,
            $cek['status'],
            'Invariant harus seimbang; selisih=' . $cek['selisih']
        );
        $this->assertSame(101500000, $cek['saldo_fisik']);
        // 60jt legacy tidak berubah: mutasi selalu punya unit pemilik.
        $this->assertSame(60000000, $cek['legacy_unassigned']);
    }

    // =================================================================
    // 7-8. Setor tunai
    // =================================================================

    public function testSetorTunaiMemindahkanPosisiTanpaMengubahOpeningAllocation(): void
    {
        // KAS Unit 1 perlu statement dulu supaya saldo fisiknya ada.
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 2, 'tanggal' => $this->tglCutoff,
            'saldo' => 5000000, 'status' => 'VERIFIED',
        ]);

        $r = $this->pindah->setorTunai(1, 2, 1, 1000000, $this->h1, 'sub-setor-1');

        $this->assertTrue($r['ok'], $r['alasan']);
        $this->assertSame('inserted', $r['status']);

        $this->assertSame(4000000, $this->cutoff->saldoFisik(2), 'KAS berkurang 1jt');
        $this->assertSame(101000000, $this->cutoff->saldoFisik(1), 'Bank bertambah 1jt');

        // Opening allocation TIDAK ikut bergerak.
        $this->assertSame(40000000, $this->cutoff->totalOpeningAllocation(1));

        // Posisi unit membesar karena movement, bukan karena tabel alokasi.
        $this->assertSame(26000000, $this->cutoff->posisiUnit(1, 1));
        $this->assertSame(60000000, $this->cutoff->legacyUnassigned(1), 'LEGACY tidak tersentuh setor');
    }

    public function testSetorDitolakKalauSaldoKasTidakCukup(): void
    {
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 2, 'tanggal' => $this->tglCutoff,
            'saldo' => 100000, 'status' => 'VERIFIED',
        ]);

        $r = $this->pindah->setorTunai(1, 2, 1, 999999, $this->h1, 'sub-kurang');

        $this->assertFalse($r['ok']);
        $this->assertSame('failed', $r['status']);
        $this->assertSame(0, $this->cutoff->netMovement(1));
    }

    // =================================================================
    // 9-10. Penarikan tunai
    // =================================================================

    public function testPenarikanBerhasilKetikaPosisiUnitCukup(): void
    {
        // Posisi Unit 2 di rekening bersama = alokasi 15jt.
        $r = $this->pindah->tarikTunai(2, 4, 1, 5000000, $this->h2, 'sub-tarik-1');

        $this->assertTrue($r['ok'], $r['alasan']);
        $this->assertSame(95000000, $this->cutoff->saldoFisik(1));
        $this->assertSame(10000000, $this->cutoff->posisiUnit(1, 2));
    }

    public function testPenarikanDitolakKetikaPosisiUnitNolWalauSaldoFisikCukup(): void
    {
        // Ini inti dari model: saldo fisik 100jt, tapi Unit 5 punya posisi 0.
        // Dulu ceknya per saldo fisik rekening, jadi penarikan ini lolos dan
        // Unit 5 mengambil uang yang bukan haknya.
        $this->assertGreaterThan(0, $this->cutoff->saldoFisik(1));

        $r = $this->pindah->tarikTunai(5, 5, 1, 1000000, $this->h2, 'sub-curang');

        $this->assertFalse($r['ok']);
        $this->assertSame('failed', $r['status']);
        $this->assertStringContainsString('tidak punya hak', $r['alasan']);
        $this->assertSame(0, $this->cutoff->netMovement(1), 'Tidak boleh ada mutasi sama sekali');
        $this->assertSame(100000000, $this->cutoff->saldoFisik(1));
    }

    public function testPenarikanDitolakKetikaPosisiUnitKurang(): void
    {
        $r = $this->pindah->tarikTunai(2, 4, 1, 25000000, $this->h2, 'sub-kurang-2');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('hanya Rp15.000.000', $r['alasan']);
        $this->assertSame(0, $this->cutoff->netMovement(1));
    }

    // =================================================================
    // 11. Atomicitas
    // =================================================================

    public function testSetorGagalTotalTidakMeninggalkanMutasiSeparuh(): void
    {
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 2, 'tanggal' => $this->tglCutoff,
            'saldo' => 5000000, 'status' => 'VERIFIED',
        ]);

        // submission_key bentrok dengan key yang sudah dipakai leg pertama pada
        // insert sebelumnya -> leg kedua harus rollback, bukan nyisetengah.
        $this->db->table('transaksi_kas_bank')->insert([
            'tanggal' => $this->legacy, 'unit_id' => 1, 'akun_kas_bank_id' => 1,
            'jenis' => 'TRANSFER_INTERNAL', 'arah' => 'MASUK', 'jumlah' => 1,
            'submission_key' => 'sub-dobel',
        ]);
        $sebelumKas = $this->cutoff->saldoFisik(2);

        $r = $this->pindah->setorTunai(1, 2, 1, 1000000, $this->h1, 'sub-dobel');

        $this->assertTrue($r['ok']);
        $this->assertSame('skipped', $r['status'], 'Idempoten: key sama tidak boleh menulis lagi');
        $this->assertSame($sebelumKas, $this->cutoff->saldoFisik(2));
        $this->assertSame(
            1,
            $this->db->table('transaksi_kas_bank')->where('submission_key', 'sub-dobel')->countAllResults()
        );
    }

    // =================================================================
    // 12. Idempotensi
    // =================================================================

    public function testSubmissionKeyGandaTidakMenduplikasiMutasi(): void
    {
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 2, 'tanggal' => $this->tglCutoff,
            'saldo' => 5000000, 'status' => 'VERIFIED',
        ]);

        $a = $this->pindah->setorTunai(1, 2, 1, 1000000, $this->h1, 'sub-idem');
        $b = $this->pindah->setorTunai(1, 2, 1, 1000000, $this->h1, 'sub-idem');

        $this->assertSame('inserted', $a['status']);
        $this->assertSame('skipped', $b['status']);

        // Hanya 1 leg KAS + 1 leg BANK, saldo berubah sekali.
        $this->assertSame(2, $this->db->table('transaksi_kas_bank')->countAllResults());
        $this->assertSame(4000000, $this->cutoff->saldoFisik(2));
        $this->assertSame(101000000, $this->cutoff->saldoFisik(1));
    }

    public function testMutasiTerdahuluPadaTanggalAdaYangSudahTerverifikasi(): void
    {
        $this->db->table('saldo_awal_kas_bank')->insert([
            'akun_kas_bank_id' => 2, 'tanggal' => $this->tglCutoff,
            'saldo' => 5000000, 'status' => 'VERIFIED',
        ]);

        $r = $this->pindah->setorTunai(1, 2, 1, 1000000, $this->legacy, 'sub-legacy');

        $this->assertFalse($r['ok'], 'Transaksi sebelum periode operasional baru harus ditolak');
        $this->assertStringContainsString($this->tglMulai, $r['alasan']);
        $this->assertSame(0, $this->db->table('transaksi_kas_bank')->countAllResults());
    }

    // =================================================================
    // 13. Validasi idbank
    // =================================================================

    public function testIdbankTidakTerdaftarDitolak(): void
    {
        $v = new BankRekeningValidator();
        $r = $v->validate('TIDAK-ADA');

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('tidak terdaftar', $r['alasan']);
    }

    public function testIdbankTidakDiCastKeInteger(): void
    {
        $v = new BankRekeningValidator();

        // 'BNI-001' kalau di-cast int jadi 0, dan cek "0 = kosong" akan
        // lolos diam-diam. Ini harus tetap gagal.
        $r = $v->validate('BNI-001');
        $this->assertTrue($r['ok']);
        $this->assertSame('BNI-001', $r['idbank'], 'idbank harus dikembalikan apa adanya');
        $this->assertSame(1, (int) $r['akun']->idakun_kas_bank);

        // idbank non-numerik yang tidak dikenal harus ditolak, bukan jadi 0.
        $r2 = $v->validate('BCA-XYZ');
        $this->assertFalse($r2['ok']);
    }

    public function testIdbankSudahDipetakanTapiBelumAktifDitolak(): void
    {
        $this->db->table('akun_kas_bank')->update(['status' => 'nonaktif'], ['idakun_kas_bank' => 1]);

        $r = (new BankRekeningValidator())->validate('BNI-001');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('nonaktif', $r['alasan']);
    }

    public function testIdbankKosongDiterima(): void
    {
        $v = new BankRekeningValidator();

        $this->assertTrue($v->validate(null)['ok'], 'Transaksi tunai boleh tanpa rekening');
        $this->assertTrue($v->validate('')['ok']);
        $this->assertFalse($v->validate(null, false)['ok'], 'Kalau wajib diisi, harus ditolak');
    }

    // =================================================================
    // 14. Guard opening allocation
    // =================================================================

    public function testAlokasiDitolakSaatTotalMelebihiStatement(): void
    {
        // Sisa statement rekening 1 = 100jt - 40jt = 60jt.
        $cek = $this->cutoff->cekOpeningAllocation(1, 60000001);
        $this->assertFalse($cek['ok']);
        $this->assertSame(60000000, $cek['statement'] - 40000000);

        // Tepat sama dengan sisa masih boleh: LEGACY boleh 0.
        $this->assertTrue($this->cutoff->cekOpeningAllocation(1, 60000000)['ok']);
    }

    public function testAlokasiDitolakKalauStatementBelumTerverifikasi(): void
    {
        // Rekening 15 saldonya 0 dan belum diverifikasi Finance.
        $this->assertFalse($this->cutoff->statementVerified(15));

        $cek = $this->cutoff->cekOpeningAllocation(15, 1);
        $this->assertFalse($cek['ok'], 'Tidak boleh alokasi terhadap placeholder');
    }

    // =================================================================
    // 15. Tidak ada opening transaction
    // =================================================================

    public function testTidakAdaOpeningTransactionBankDiLedger(): void
    {
        // Statement adalah BASELINE, bukan transaksi. Kalau suatu saat ada baris
        // ledger bertanggal pada/sebelum cut-off, itu berarti ada yang menulis
        // opening transaction dan statement akan terhitung dua kali.
        $ada = $this->db->table('transaksi_kas_bank')
            ->where('tanggal <=', FinanceScopeService::kasBankCutoffDate())
            ->where('akun_kas_bank_id', 1)
            ->countAllResults();

        $this->assertSame(0, $ada, 'Tidak boleh ada baris ledger pada periode statement');

        // Dan model yang dipakai juga tidak boleh menjadikannya mutasi.
        $this->assertSame(0, $this->cutoff->netMovement(1));
    }
}