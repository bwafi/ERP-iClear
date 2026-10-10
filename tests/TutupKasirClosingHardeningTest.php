<?php

namespace Tests;

use App\Libraries\ModeKasBank;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasOpeningService;
use App\Controllers\TutupKasir;
use App\Services\Finance\TutupKasirClosing;
use App\Services\Finance\TutupKasirSaldoAwal;
use App\Services\Finance\TutupKasirSourceDefinition;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Security\Exceptions\SecurityException;
use Config\Database;

/**
 * Hardening Tutup Kasir: server yang Holds the Numbers.
 *
 * =================================================================
 * YANG DIJAGA DI SINI
 * =================================================================
 *  1. ANGKA DI PAGE = ANGKA YANG DISIMPAN.
 *     `awal_cash` yang tampil harus sama persis dengan hasil hitung
 *     server, bukan hasil hitung terpisah di controller.
 *
 *  2. HIDDEN FIELD BUKAN SUMBER KEBENARAN.
 *     `akhir_cash`, `awal_cash`, `pendapatan_*`, `pengeluaran_*` yang
 *     dikirim POST harus DIABAIKAN. Yang tersimpan selalu hasil hitung
 *     `TutupKasirClosing` pada server.
 *
 *  3. `cash_laci` SATU-SATUNYA angka dari request, dan tetap apa adanya.
 *     Tidak dipaksa sama dengan `akhir_cash`; `selisih` boleh bukan nol.
 *
 *  4. IDEMPOTENSI per (tanggal, unit). Simpan dua kali = satu baris,
 *     dan nominal kedua tidak boleh menimpa baris yang sudah jadi.
 *
 *  5. `cash_laci` divalidasi ketat: ada, numeric, finite, bulat, >= 0.
 *
 *  6. `tanggal` dan `unit` dari POST tidak boleh dipercaya.
 *
 *  7. Opening KAS yang tersimpan (kolom status legacy diabaikan) langsung
 *     jadi saldo awal; baseline tidak boleh bertanggal setelah closing.
 *
 *  8. Setor mengurangi laci, Tarik menambahnya, dan hanya untuk
 *     `jenis = TRANSFER_INTERNAL`.
 *
 *  9. Laci di atas Rp1 juta tidak pernah "dialihkan diam-diam" ke bank.
 *
 * 10. POST butuh auth DAN CSRF.
 *
 * =================================================================
 * BUG YANG DIKUNCI DI SINI
 * =================================================================
 *  - `tutup()` lama menyimpan `akhir_cash` dari hidden field, jadi satu
 *    POST bisa menutup kasir dengan angka(request). Sumber kebenaran ada
 *    di browser; mengedit satu `<input>` cukup untuk salah closing.
 *  - Tidak ada proteksi duplicate: closing ganda untuk (tanggal, unit)
 *    membuat `saldoAwalKas()` membaca baris yang tidak dijamin.
 *  - Opening `BELUM_VERIFIKASI` TIDAK lagi menolak laci: konsep verifikasi
 *    dihapus, baris opening yang ada langsung jadi saldo awal.
 *  - Laci > Rp1 juta dipotong ke 1 juta dan kelebihannya menaikkan saldo
 *    bank tanpa record Setor (data lama unit 2: closing 2.221.000 ->
 *    opening besok 1.000.000).
 */
class TutupKasirClosingHardeningTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected TutupKasirClosing $closing;
    protected TutupKasirSaldoAwal $saldoAwal;

    private const UNIT       = 1;
    private const AKUN_KAS   = 2;
    private const AKUN_BANK  = 1;   // bank bersama (shared)
    private const AKUN_BANK1 = 4;   // bank milik unit 1 (dipakai saldo awal transfer)

    /**
     * Fixture transaksi (penjualan, kas_keluar, Setor/Tarik) TIDAK diberi
     * tanggal tetap.
     *
     * `simpan()` menolak tanggal selain HARI INI — supaya form lama tidak bisa
     * menulis closing lampau yang menggeser Opening hari-hari berikutnya. Kalau
     * fixture di tanggal lain, `hitung()` di dalam `simpan()` tidak akan
     * merasakannya dan test jadi menguji kondisi kosong, bukan kondisi yang
     * dimaksud. Jadi semua fixture memakai tanggal server.
     *
     * Perilaku historis diuji lewat `hitung()` yang menerima tanggal bebas.
     */
    private const SEBELUM_BASELINE = '2026-10-04';

    private const OPENING = 1500000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        // Fail-closed: suite ini menghapus dan membuat ulang tabel.
        if ($this->db->getDatabase() !== 'erp_finance_test') {
            $this->fail('Test hanya boleh jalan pada database erp_finance_test, bukan: '
                . $this->db->getDatabase());
        }

        $this->closing   = new TutupKasirClosing($this->db);
        $this->saldoAwal = new TutupKasirSaldoAwal($this->db);

        $this->schema();
        $this->seed();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function q(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            $this->fail('Query gagal: ' . $this->db->error()['message']);
        }
    }

    // =================================================================
    // SCHEMA + SEED
    // =================================================================

    private function schema(): void
    {
        $this->q('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'transaksi_kas_bank', 'alokasi_saldo_kas_bank', 'saldo_awal_kas_bank',
            'akun_kas_bank', 'tutup_kasir', 'unit', 'bank', 'akun',
            'opening_kas', 'penjualan', 'service', 'kas_keluar', 'kas_masuk',
        ] as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }

        $this->q('CREATE TABLE akun (
                ID_AKUN INT PRIMARY KEY, ID_UNIT INT NULL, ID_JABATAN INT NULL,
                NAMA_AKUN TEXT NULL, ROLES TEXT NULL)');
        $this->q('CREATE TABLE unit (idunit INTEGER PRIMARY KEY, NAMA_UNIT TEXT NULL)');
        $this->q('CREATE TABLE bank (
                idbank VARCHAR(20) PRIMARY KEY, nama_bank TEXT NULL,
                norek TEXT NULL, atas_nama TEXT NULL)');
        $this->q('CREATE TABLE akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0, is_finance_ho TINYINT(1) DEFAULT 0)');
        $this->q('CREATE TABLE saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE transaksi_kas_bank (
                idtransaksi INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, unit_id INT NULL, akun_kas_bank_id INT NULL,
                jenis TEXT NULL, arah TEXT NULL, jumlah REAL NULL, akun_tujuan_id INT NULL,
                transfer_ref TEXT NULL, submission_key VARCHAR(64) NULL,
                sumber_tipe TEXT NULL, sumber_id INT NULL, keterangan TEXT NULL,
                bukti TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');

        $this->q('CREATE TABLE tutup_kasir (
                idtutupkasir INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, awal_cash REAL NULL, awal_transfer REAL NULL,
                akhir_cash REAL NULL, akhir_transfer REAL NULL,
                pendapatan_cash REAL NULL, pendapatan_transfer REAL NULL,
                pengeluaran_cash REAL NULL, pengeluaran_transfer REAL NULL,
                cash_laci REAL NULL, status TEXT NULL,
                akun_ID_AKUN INT NULL, unit INT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');

        // Sengaja TIDAK ada unique index (tanggal, unit) di schema test.
        // Proteksi duplicate harus datang dari aplikasi (named lock +
        // recheck), bukan dari constraint — supaya test ini benar-benar
        // menguji lapisan aplikasi.
        $this->q('CREATE TABLE opening_kas (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, tanggal TEXT NULL,
                opening BIGINT DEFAULT 0 NULL, real_cash BIGINT NULL, selisih BIGINT NULL,
                status VARCHAR(32) DEFAULT \'BELUM_VERIFIKASI\' NULL,
                keterangan VARCHAR(255) NULL,
                input_by INT NULL, verifikasi_by INT NULL, verifikasi_at TEXT NULL,
                created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE UNIQUE INDEX uniq_opening_kas_akun_tanggal
                ON opening_kas (akun_kas_bank_id, tanggal)');

        $this->q('CREATE TABLE kas_masuk (
                idkas_masuk INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, deskripsi TEXT NULL, jumlah REAL NULL,
                idunit INT NULL, idbank TEXT NULL, created_on TEXT NULL, updated_on TEXT NULL)');

        $this->q('CREATE TABLE penjualan (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                kode_invoice TEXT NULL, unit_idunit INT NULL, tanggal TEXT NULL,
                harus_dibayar REAL NULL, bayar_tunai REAL NULL, bayar_bank REAL NULL,
                keterangan TEXT NULL)');
        $this->q('CREATE TABLE service (
                id_service INTEGER PRIMARY KEY AUTO_INCREMENT,
                no_service TEXT NULL, unit_idunit INT NULL, tanggal_selesai TEXT NULL,
                status_service INT NULL, harus_dibayar REAL NULL, bayar_tunai REAL NULL,
                keterangan TEXT NULL)');
        $this->q('CREATE TABLE kas_keluar (
                idkas_keluar INTEGER PRIMARY KEY AUTO_INCREMENT,
                tanggal TEXT NULL, idunit INT NULL, idbank TEXT NULL,
                jumlah REAL NULL, deskripsi TEXT NULL, updated_on TEXT NULL)');

        $this->q('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function seed(): void
    {
        $this->q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES (1,'Probolinggo'), (2,'Jember')");
        $this->q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (43, 1, 1, 'Admin Root', '[]')");
        $this->q("INSERT INTO bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('BNI-001','Bank BNI','123','PT Contoh')");
        $this->q("INSERT INTO akun_kas_bank
                (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa,
                 status, is_shared, is_finance_ho) VALUES
            (1, NULL, 'BANK', 'Bank Bersama',    'BNI-001', NULL,         'aktif', 1, 0),
            (2, 1,    'KAS',  'Kas Probolinggo', NULL,       '1010101000', 'aktif', 0, 0),
            (3, 2,    'KAS',  'Kas Jember',      NULL,       '1010101000', 'aktif', 0, 0),
            (4, 1,    'BANK', 'Bank Probolinggo','BNI-001', '1010102000', 'aktif', 0, 0),
            (5, 2,    'BANK', 'Bank Jember',     'BNI-001', '1010102000', 'aktif', 0, 0)");

        $cutoff = FinanceScopeService::kasBankCutoffDate();

        $this->q("INSERT INTO saldo_awal_kas_bank
                (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (1, '{$cutoff}', 100000000, 'Koran cut-off', 'VERIFIED')");
        $this->q("INSERT INTO alokasi_saldo_kas_bank
                (akun_kas_bank_id, unit_id, nominal, keterangan, created_at) VALUES
            (1, 1, 100000000, 'unit 1', '" . date('Y-m-d H:i:s') . "'),
            (1, 2, 100000000, 'unit 2', '" . date('Y-m-d H:i:s') . "')");

        // Opening KAS baseline, kolom status legacy diisi TERVERIFIKASI.
        // Service TIDAK membedakan status lagi (konsep verifikasi dihapus);
        // status tetap diisi supaya fixture meniru baris produksi apa adanya.
        $this->q("INSERT INTO opening_kas
                (akun_kas_bank_id, unit_id, tanggal, opening, real_cash, selisih,
                 status, input_by, created_at, updated_at)
            VALUES
            (2, 1, '{$cutoff}', " . self::OPENING . ", " . self::OPENING . ", 0,
                'TERVERIFIKASI', 1, '" . date('Y-m-d H:i:s') . "',
                '" . date('Y-m-d H:i:s') . "'),
            (3, 2, '{$cutoff}', 900000, 900000, 0,
                'TERVERIFIKASI', 1, '" . date('Y-m-d H:i:s') . "',
                '" . date('Y-m-d H:i:s') . "')");

        // Baseline statement bank untuk rekening bank milik unit 1. Tanpa ini
        // `saldoAwalTransfer()` mengembalikan "belum ditetapkan" karena unit
        // tidak punya rekening bank sendiri, dan semua test simpan akan
        // ditolak karena alasan yang salah (bukan alasan yang diuji).
        $this->q("INSERT INTO saldo_awal_kas_bank
                (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            (4, '{$cutoff}', 250000, 'baseline statement unit 1', 'VERIFIED'),
            (5, '{$cutoff}', 275000, 'baseline statement unit 2', 'VERIFIED')");
    }

    // =================================================================
    // HELPER
    // =================================================================

    /** Buka/tutup baris opening KAS dengan status tertentu (kolom legacy). */
    private function setOpeningKas(string $status, ?string $tanggal = null, ?int $opening = null): void
    {
        $tanggal = $tanggal ?? FinanceScopeService::kasBankCutoffDate();
        $opening = $opening ?? self::OPENING;
        $sudah   = $status === 'TERVERIFIKASI';

        $this->q("UPDATE opening_kas SET
                tanggal = '{$tanggal}', opening = {$opening}, status = '{$status}',
                real_cash = " . ($sudah ? (string) $opening : 'NULL') . ',
                selisih  = ' . ($sudah ? '0' : 'NULL') . "
            WHERE akun_kas_bank_id = " . self::AKUN_KAS);
    }

    /** Tanggal server. `simpan()` hanya menerima tanggal ini. */
    private function hariIni(): string
    {
        return date('Y-m-d');
    }

    private const HARI_LAMA = '2026-10-08';

    /** Baris closing yang tersimpan untuk tanggal+unit tertentu. */
    private function closingTersimpan(?string $tanggal = null, int $unit = self::UNIT): ?object
    {
        $tanggal = $tanggal ?? $this->hariIni();

        return $this->db->table('tutup_kasir')
            ->where('tanggal', $tanggal)
            ->where('unit', $unit)
            ->orderBy('idtutupkasir', 'ASC')
            ->get()
            ->getRow();
    }

    private function jumlahClosing(?string $tanggal = null, int $unit = self::UNIT): int
    {
        $tanggal = $tanggal ?? $this->hariIni();

        return (int) $this->db->table('tutup_kasir')
            ->where('tanggal', $tanggal)
            ->where('unit', $unit)
            ->countAllResults();
    }

    /** Penjualan tunai pada tanggal test. */
    private function penjualanTunai(int $nominal, int $unit = self::UNIT): void
    {
        $this->db->table('penjualan')->insert([
            'kode_invoice' => 'PJL' . random_int(100000, 999999),
            'unit_idunit'  => $unit,
            'tanggal'      => date('Y-m-d'),
            'bayar_tunai'  => $nominal,
            'bayar_bank'   => 0,
        ]);
    }

    /** Penjualan transfer (bayar_bank) pada tanggal test. */
    private function penjualanTransfer(int $nominal, int $unit = self::UNIT): void
    {
        $this->db->table('penjualan')->insert([
            'kode_invoice' => 'PJL' . random_int(100000, 999999),
            'unit_idunit'  => $unit,
            'tanggal'      => date('Y-m-d'),
            'bayar_tunai'  => 0,
            'bayar_bank'   => $nominal,
        ]);
    }

    /**
     * Service selesai + bayar tunai.
     *
     * `harus_dibayar` selalu diisi sama dengan `bayar_tunai` kalau tidak
     * disebut lain. Definisi Tutup Kasir menghitung transfer service sebagai
     * `harus_dibayar - bayar_tunai`, jadi `harus_dibayar` yang NULL membuat
     * transfer service jadi negatif (0 - nominal) dan test menguji angka
     * yang tidak pernah terjadi di dunia nyata.
     */
    private function serviceTunai(int $nominal, int $unit = self::UNIT, ?int $transfer = null): void
    {
        $this->db->table('service')->insert([
            'no_service'      => 'SRV' . random_int(100000, 999999),
            'unit_idunit'     => $unit,
            'tanggal_selesai' => date('Y-m-d'),
            'status_service'  => 4,
            'harus_dibayar'   => $nominal + ($transfer ?? 0),
            'bayar_tunai'     => $nominal,
        ]);
    }

    /** Kas keluar tunai (idbank NULL). */
    private function kasKeluarTunai(int $nominal, int $unit = self::UNIT): void
    {
        $this->db->table('kas_keluar')->insert([
            'tanggal' => date('Y-m-d'),
            'idunit'  => $unit,
            'idbank'  => null,
            'jumlah'  => $nominal,
        ]);
    }

    /** Kas keluar lewat bank (idbank NOT NULL). */
    private function kasKeluarBank(int $nominal, int $unit = self::UNIT): void
    {
        $this->db->table('kas_keluar')->insert([
            'tanggal' => date('Y-m-d'),
            'idunit'  => $unit,
            'idbank'  => 'BNI-001',
            'jumlah'  => $nominal,
        ]);
    }

    /**
     * Setor/Tarik pada rekening KAS.
     *
     * @param string $jenis ModeKasBank::JENIS_TRANSFER atau nilai lain
     */
    private function transferInternal(int $nominal, string $arah, ?string $jenis = null, ?int $unit = null): void
    {
        $this->db->table('transaksi_kas_bank')->insert([
            'tanggal'          => date('Y-m-d'),
            'unit_id'          => $unit ?? self::UNIT,
            'akun_kas_bank_id' => self::AKUN_KAS,
            'jenis'            => $jenis ?? ModeKasBank::JENIS_TRANSFER,
            'arah'             => $arah,
            'jumlah'           => $nominal,
            'transfer_ref'     => 'TRF-' . random_int(100000, 999999),
            'sumber_tipe'      => null,
        ]);
    }

    /**
     * Simpan closing lewat service.
     *
     * Default-nya HARI INI (`date('Y-m-d')`) karena `simpan()` menolak
     * tanggal selain hari ini. Test yang butuh closing di tanggal historis
     * memakai `tutup()` di bawah.
     */
    private function simpan($cashLaci, int $unit = self::UNIT, ?string $tanggal = null): array
    {
        return $this->closing->simpan($unit, $tanggal ?? date('Y-m-d'), $cashLaci, 43);
    }

    /** Tulis baris closing langsung (untuk membuat kondisi historis). */
    private function tutupBaris(string $tanggal, int $unit, int $awal, int $akhir, ?int $laci = null): void
    {
        $this->db->table('tutup_kasir')->insert([
            'tanggal' => $tanggal, 'unit' => $unit,
            'awal_cash' => $awal, 'akhir_cash' => $akhir,
            'awal_transfer' => 0, 'akhir_transfer' => 0,
            'pendapatan_cash' => 0, 'pendapatan_transfer' => 0,
            'pengeluaran_cash' => 0, 'pengeluaran_transfer' => 0,
            'cash_laci' => $laci ?? $akhir,
            'status' => 'selesai', 'akun_ID_AKUN' => 43,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // =================================================================
    // 1. ANGKA DI PAGE = ANGKA YANG DISIMPAN
    // =================================================================

    public function testAwalCashYangTampilSamaDenganHasilHitungServer(): void
    {
        $this->penjualanTunai(250000);
        $this->kasKeluarTunai(50000);

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));

        // 1.500.000 + 250.000 - 50.000 = 1.700.000
        $this->assertSame(self::OPENING, $h['awal_cash'], 'Saldo awal harus dari Opening KAS');
        $this->assertSame(1700000, $h['akhir_cash']);
        $this->assertTrue($h['siap'], 'Harus siap disimpan: ' . $h['alasan']);

        $tersimpan = $this->simpan($h['akhir_cash']);
        $this->assertTrue($tersimpan['ok'], (string) $tersimpan['alasan']);

        $row = $this->closingTersimpan();
        $this->assertNotNull($row);
        $this->assertSame(
            (int) $h['awal_cash'],
            (int) $row->awal_cash,
            'awal_cash yang tersimpan harus PERSIS angka yang dipanggil view'
        );
        $this->assertSame((int) $h['akhir_cash'], (int) $row->akhir_cash);
    }

    // =================================================================
    // 2. HIDDEN FIELD DIABAIKAN
    // =================================================================

    public function testAkhirCashDariPostDiabaikan(): void
    {
        $this->penjualanTunai(250000);

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertSame(1750000, $h['akhir_cash']);

        // Penyerang mengirim angka sendiri via hidden field.
        $hasil = $this->simpan(1750000);

        $this->assertTrue($hasil['ok'], (string) $hasil['alasan']);

        $row = $this->closingTersimpan();
        $this->assertSame(
            1750000,
            (int) $row->akhir_cash,
            'akhir_cash harus hasil hitung server, bukan angka POST'
        );
        $this->assertSame(
            self::OPENING,
            (int) $row->awal_cash,
            'awal_cash harus hasil hitung server, bukan angka POST'
        );
        $this->assertSame(250000, (int) $row->pendapatan_cash);
        $this->assertSame(0, (int) $row->pendapatan_transfer);
        $this->assertSame(0, (int) $row->pengeluaran_cash);
        $this->assertSame(0, (int) $row->pengeluaran_transfer);
    }

    public function testAngkaPOSTTidakBisaMembuatClosingNilaiAsing(): void
    {
        $this->penjualanTunai(100000);

        // Seluruh angka coheren menurut penyerang, tapi nol transaksi riil
        // aside dari penjualan itu. Server harus tetap pakai definisi sendiri.
        $hasil = $this->simpan(0);

        $this->assertTrue($hasil['ok'], (string) $hasil['alasan']);

        $row = $this->closingTersimpan();
        $this->assertSame(1600000, (int) $row->akhir_cash);
        $this->assertSame(0, (int) $row->pengeluaran_cash, 'Pengeluaran harus dari kas_keluar');
    }

    // =================================================================
    // 3. cash_laci TETAP INPUT FISIK
    // =================================================================

    public function testCashLaciTetapFisikDanSelisihBolehBesar(): void
    {
        $this->penjualanTunai(250000);

        $hasil = $this->simpan(1200000);

        $this->assertTrue($hasil['ok'], (string) $hasil['alasan']);

        $row = $this->closingTersimpan();
        $this->assertSame(1200000, (int) $row->cash_laci, 'cash_laci harus apa adanya dari request');
        $this->assertSame(1750000, (int) $row->akhir_cash, 'Sistem tetap pakai hitungannya sendiri');
        $this->assertSame(
            -550000,
            (int) $row->cash_laci - (int) $row->akhir_cash,
            'Selisih negatif itu informasi, bukan error — bukan alasan ditolak'
        );
        $ini = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertSame(
            (int) $row->akhir_cash,
            (int) $ini['akhir_cash'],
            'Angka sistem pada form dan yang tersimpan harus sama'
        );
    }

    // =================================================================
    // 4. IDEMPOTENSI
    // =================================================================

    public function testSimpanDuaKaliTetapSatuBaris(): void
    {
        $this->penjualanTunai(200000);

        $a = $this->simpan(1700000);
        $b = $this->simpan(1700000);

        $this->assertSame('tersimpan', $a['kode'], (string) $a['alasan']);
        $this->assertSame(
            'sudah_ada',
            $b['kode'],
            'Simpan kedua harus dikenali sebagai duplicate: ' . $b['alasan']
        );
        $this->assertTrue($b['ok'], 'Duplicate bukan kegagalan');
        $this->assertSame(1, $this->jumlahClosing(), 'Harus tetap satu baris');
    }

    public function testSimpanUlangDenganNominalBerbedaTidakMenimpa(): void
    {
        $this->penjualanTunai(200000);

        $this->simpan(1700000);
        $kedua = $this->simpan(123);

        $this->assertSame('sudah_ada', $kedua['kode']);
        $this->assertSame(
            1,
            $this->jumlahClosing(),
            'Closing yang sudah jadi tidak boleh ditambah, walau nominalnya beda'
        );

        $row = $this->closingTersimpan();
        $this->assertSame(1700000, (int) $row->cash_laci, 'Nilai asli harus tetap, tidak ditimpa');
        $this->assertSame(1700000, (int) $row->akhir_cash);
    }

    public function testDuplicatePerUnitTerpisah(): void
    {
        $this->penjualanTunai(200000);
        $this->penjualanTunai(300000, 2);

        $a = $this->simpan(1700000);
        $b = $this->simpan(400000, 2);

        $this->assertSame('tersimpan', $a['kode']);
        $this->assertSame('tersimpan', $b['kode'], 'Unit berbeda = closing berbeda');

        $this->assertSame(1, $this->jumlahClosing(date('Y-m-d'), self::UNIT));
        $this->assertSame(1, $this->jumlahClosing(date('Y-m-d'), 2));
    }

    // =================================================================
    // 5. VALIDASI cash_laci
    // =================================================================

    public function testCashLaciNegatifDitolak(): void
    {
        $hasil = $this->simpan(-1);

        $this->assertFalse($hasil['ok'], 'Laci negatif harus ditolak');
        $this->assertSame('ditolak', $hasil['kode']);
        $this->assertStringContainsString('minus', strtolower($hasil['alasan']));
        $this->assertSame(0, $this->jumlahClosing(), 'Tidak boleh ada baris tersimpan');
    }

    public function testCashLaciNolDiterima(): void
    {
        $this->penjualanTunai(1000000);

        $hasil = $this->simpan(0);

        $this->assertTrue($hasil['ok'], 'Laci kosong itu kondisi sah: ' . $hasil['alasan']);
        $row = $this->closingTersimpan();
        $this->assertSame(0, (int) $row->cash_laci);
        $this->assertSame(2500000, (int) $row->akhir_cash);
    }

    public function testCashLaciNonNumerikDitolak(): void
    {
        foreach (['abc', '12abc', 'NaN', 'null', '--1'] as $bad) {
            $hasil = $this->simpan($bad);
            $this->assertFalse($hasil['ok'], "Input `{$bad}` harus ditolak");
            $this->assertSame('ditolak', $hasil['kode'], "Input `{$bad}`");
            $this->assertSame(0, $this->jumlahClosing(), "Tidak boleh ada baris untuk `{$bad}`");
        }
    }

    public function testCashLaciBukanBulatDitolak(): void
    {
        foreach (['1.5', '1750000.75', '0.0001'] as $bad) {
            $hasil = $this->simpan($bad);
            $this->assertFalse(
                $hasil['ok'],
                "Input desimal `{$bad}` harus ditolak, bukan dibulatkan atau dipotong"
            );
            $this->assertSame(0, $this->jumlahClosing(), "Tidak boleh ada baris untuk `{$bad}`");
        }
    }

    public function testCashLaciNonFiniteDitolak(): void
    {
        foreach (['INF', '-INF', 'NAN', '1e400'] as $bad) {
            $hasil = $this->simpan($bad);
            $this->assertFalse($hasil['ok'], "Input non-finite `{$bad}` harus ditolak");
            $this->assertSame(0, $this->jumlahClosing(), "Tidak boleh ada baris untuk `{$bad}`");
        }
    }

    public function testCashLaciTanpaTandaKomaDiterima(): void
    {
        $this->penjualanTunai(250000);

        $hasil = $this->simpan('1.700.000');

        $this->assertTrue($hasil['ok'], 'Format ribuan Indonesia harus diterima: ' . $hasil['alasan']);
        $row = $this->closingTersimpan();
        $this->assertSame(1700000, (int) $row->cash_laci);
    }

    public function testCashLaciHilangDitolak(): void
    {
        $hasil = $this->simpan(null);

        $this->assertFalse($hasil['ok'], 'Kolom laci wajib diisi');
        $this->assertSame('ditolak', $hasil['kode']);
        $this->assertSame(0, $this->jumlahClosing());
    }

    // =================================================================
    // 6. TANGGAL & UNIT DARI POST
    // =================================================================

    public function testTanggalDariPostDiabaikan(): void
    {
        $this->penjualanTunai(200000);

        // Tanggal dari request sengaja tidak dipakai sebagai parameter hitung:
        // service dihitung dengan `date('Y-m-d')`, sama seperti controller.
        $hasil = $this->simpan(1700000);

        $this->assertTrue($hasil['ok'], (string) $hasil['alasan']);

        $this->assertSame(
            0,
            $this->jumlahClosing('2019-01-01'),
            'Closing tidak boleh dibuat pada tanggal kiriman'
        );
        $this->assertSame(
            1,
            $this->jumlahClosing(date('Y-m-d')),
            'Closing harus pada tanggal server'
        );
    }

    public function testUnitTanpaNilaiDitolak(): void
    {
        $hasil = $this->simpan(1700000, 0);

        $this->assertFalse($hasil['ok'], 'Unit 0 bukan unit nyata');
        $this->assertSame(0, $this->jumlahClosing('', 0));
    }

    // =================================================================
    // 7. OPENING KAS
    // =================================================================

    public function testOpeningStatusLegacyApapunTetapDipakai(): void
    {
        // Konsep verifikasi dihapus: opening yang TERSIMPAN (berstatus apa pun
        // pada kolom legacy) langsung menjadi baseline saldo awal. Status lama
        // BELUM_VERIFIKASI/TIDAK_COCOK tidak lagi menghalangi apa pun.
        $this->setOpeningKas('BELUM_VERIFIKASI');

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertTrue($h['siap'], 'Opening yang tersimpan harus jadi saldo awal: ' . ($h['alasan'] ?? ''));
        $this->assertTrue($h['saldo_awal_kas'] !== null);

        $hasil = $this->simpan(1700000);
        $this->assertTrue($hasil['ok'], 'Simpan harus diterima: ' . ($hasil['alasan'] ?? ''));
        $this->assertSame(1, $this->jumlahClosing());
    }

    public function testOpeningTidakCocokTetapDipakai(): void
    {
        // Status legacy TIDAK_COCOK juga tidak lagi menghalangi: baseline yang
        // tersimpan langsung sah (tidak ada perbandingan dengan real cash).
        $this->setOpeningKas('TIDAK_COCOK');

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertTrue($h['siap'], 'Opening status TIDAK_COCOK harus tetap dipakai: ' . ($h['alasan'] ?? ''));
        $this->assertTrue($h['saldo_awal_kas'] !== null);
    }

    public function testClosingBerikutnyaMeneruskanClosingSebelumnya(): void
    {
        // Closing di 8 Okt dibuat lebih dulu; di ledger yang sama (9 Okt masih
        // sebelum reset KasBank) opening cut-off TIDAK dipakai karena closing
        // sebelumnya menang. Perilaku setelah reset KasBank (target >= 10 Okt)
        // diuji oleh TutupKasirSaldoAwalTest::testClosingSebelumCutoff*.
        $this->tutupBaris('2026-10-08', self::UNIT, self::OPENING, 1700000);

        $h = $this->closing->hitung(self::UNIT, '2026-10-09');

        $this->assertTrue($h['siap'], (string) $h['alasan']);
        $this->assertSame(
            1700000,
            (int) $h['awal_cash'],
            'Sumbernya harus closing 8 Okt, bukan baseline cut-off'
        );
    }

    public function testClosingOktoberTidakMemakaiOpeningNovember(): void
    {
        // Opening dipindah ke 2 Nov — itu MASA DEPAN untuk closing 8 Okt.
        $this->setOpeningKas(
            'TERVERIFIKASI',
            '2026-11-02',
            self::OPENING
        );

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertFalse(
            $h['siap'],
            'Baseline 2 Nov tidak boleh jadi saldo awal closing 8 Okt: ' . $h['alasan']
        );
    }

    public function testBaselinePadaTanggalYangSamaTetapDipakai(): void
    {
        //Aturan`tanggal_baseline <= tanggal_closing`: menutup kasir pada
        // tanggal cut-off (HARI PERTAMA) WAJIB boleh memakai baseline yang
        // bertanggal cut-off juga. Dulu aturan ini strict `<` sehingga hari
        // pertama selalu ditolak.
        $cutoff = FinanceScopeService::kasBankCutoffDate();

        // Test dijalankan kapan pun, jadi "hari ini" tidak di-hardcode sama
        // dengan cut-off. Yang WAJIB benar hanya baseline tidak boleh di
        // masa depan relative ke hari penutupan.
        $this->assertGreaterThanOrEqual(
            $cutoff,
            date('Y-m-d'),
            'Baseline cut-off tidak boleh setelah hari ini'
        );

        $h = $this->closing->hitung(self::UNIT, $cutoff);

        $this->assertTrue(
            $h['siap'],
            'Hari pertama periode harus bisa ditutup dengan baseline cut-off: ' . $h['alasan']
        );
        $this->assertSame(self::OPENING, (int) $h['awal_cash']);

        $hasil = $this->simpan(self::OPENING);
        $this->assertTrue($hasil['ok'], (string) $hasil['alasan']);
        $this->assertSame('tersimpan', $hasil['kode']);
    }

    public function testClosingSebelumTanggalBaselineDitolak(): void
    {
        // Sebaliknya dari test di atas: baseline 5 Okt TIDAK boleh jadi saldo
        // awal closing 4 Okt, karena itu data masa depan.
        $h = $this->closing->hitung(self::UNIT, self::SEBELUM_BASELINE);
        $this->assertFalse($h['siap'], 'Closing sebelum baseline tidak boleh ditutup');
        $this->assertStringContainsString('setelah', strtolower($h['alasan']));
    }

    public function testOpeningHarusPunyaRekeningKasDanBaselineTransfer(): void
    {
        $this->db->table('opening_kas')->where('akun_kas_bank_id', self::AKUN_KAS)->delete();
        $this->db->table('saldo_awal_kas_bank')
            ->where('akun_kas_bank_id', self::AKUN_KAS)
            ->delete();

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertFalse($h['siap'], 'Tanpa opening KAS dan baseline transfer tidak boleh ditutup');
        $this->assertSame(0, $this->jumlahClosing());
    }

    // =================================================================
    // 8. SETOR / TARIK
    // =================================================================

    public function testSetorMengurangiSaldoLaci(): void
    {
        $this->penjualanTunai(500000);
        $this->transferInternal(200000, 'KELUAR');

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        // 1.500.000 + 500.000 - 200.000 (setor) = 1.800.000
        $this->assertSame(1800000, $h['akhir_cash']);

        $this->simpan(1800000);
        $row = $this->closingTersimpan();
        $this->assertSame(1800000, (int) $row->akhir_cash);
    }

    public function testTarikMenambahSaldoLaci(): void
    {
        $this->penjualanTunai(500000);
        $this->transferInternal(150000, 'MASUK');

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        // 1.500.000 + 500.000 + 150.000 (tarik) = 2.150.000
        $this->assertSame(2150000, $h['akhir_cash']);
    }

    public function testTransferInternalLainJenisTidakMengubahLaci(): void
    {
        $this->penjualanTunai(500000);
        // Pembayaran antar unit juga menulis transfer_ref, tapi itu arus
        // antarunit, bukan Setor/Tarik laci.
        $this->transferInternal(300000, 'KELUAR', ModeKasBank::JENIS_ANTAR_UNIT);
        $this->transferInternal(120000, 'MASUK', ModeKasBank::JENIS_ANTAR_UNIT);

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertSame(
            2000000,
            $h['akhir_cash'],
            'Transfer antar unit tidak boleh mengubah saldo laci'
        );
    }

    public function testTransferTanpaRefTidakDihitung(): void
    {
        $this->db->table('transaksi_kas_bank')->insert([
            'tanggal'          => date('Y-m-d'),
            'unit_id'          => self::UNIT,
            'akun_kas_bank_id' => self::AKUN_KAS,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => 'KELUAR',
            'jumlah'           => 400000,
            'transfer_ref'     => null,
            'sumber_tipe'      => 'kas_masuk',
        ]);

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertSame(
            self::OPENING,
            $h['akhir_cash'],
            'Legacy mirror tanpa transfer_ref bukan Setor/Tarik'
        );
    }

    // =================================================================
    // 9. NILAI AKHIR LAINNYA
    // =================================================================

    public function testPendapatanCashGabunganPenjualanDanService(): void
    {
        $this->penjualanTunai(120000);
        $this->serviceTunai(80000);
        $this->penjualanTransfer(50000);

        $this->simpan(1700000);

        $row = $this->closingTersimpan();
        $this->assertSame(200000, (int) $row->pendapatan_cash, '120.000 tunai + 80.000 service');
        $this->assertSame(50000, (int) $row->pendapatan_transfer);
    }

    public function testPengeluaranCashHanyaYangIdbankNull(): void
    {
        $this->kasKeluarTunai(70000);
        $this->kasKeluarBank(90000);

        $this->simpan(1430000);

        $row = $this->closingTersimpan();
        $this->assertSame(70000, (int) $row->pengeluaran_cash, 'Kas keluar bank bukan pengeluaran laci');
        $this->assertSame(90000, (int) $row->pengeluaran_transfer);
    }

    public function testTransferInternalLainUnitTidakDihitung(): void
    {
        $this->transferInternal(250000, 'KELUAR', null, 2);

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertSame(
            self::OPENING,
            $h['akhir_cash'],
            'Setor/Tarik unit lain tidak boleh mengubah laci unit ini'
        );
    }

    public function testTransferInternalTanggalLainTidakDihitung(): void
    {
        $this->db->table('transaksi_kas_bank')->insert([
            'tanggal'          => '2026-10-09',
            'unit_id'          => self::UNIT,
            'akun_kas_bank_id' => self::AKUN_KAS,
            'jenis'            => ModeKasBank::JENIS_TRANSFER,
            'arah'             => 'KELUAR',
            'jumlah'           => 300000,
            'transfer_ref'     => 'TRF-BESOK',
        ]);

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $this->assertSame(self::OPENING, $h['akhir_cash'], 'Setor besok tidak boleh dihitung hari ini');
    }

    // =================================================================
    // 10. LACI DI ATAS RP1 JUTA
    // =================================================================

    public function testLaciDiAtasSatuJutaTetpenuhDanBankTidakNaik(): void
    {
        $saldoBankSebelum = (int) $this->db->table('saldo_awal_kas_bank')
            ->where('akun_kas_bank_id', self::AKUN_BANK)
            ->get()
            ->getRow()
            ->saldo;

        $this->penjualanTunai(300000);

        // Regression data lama unit 2: laci 2.221.000 dipotong ke 1.000.000
        // dan sisa 1.221.000 dinaikkankan ke saldo bank tanpa record Setor.
        $hasil = $this->simpan(1250000);

        $this->assertTrue($hasil['ok'], (string) $hasil['alasan']);

        $row = $this->closingTersimpan();
        $this->assertSame(1250000, (int) $row->cash_laci, 'Seluruh isi laci harus tersimpan utuh');
        $this->assertSame(1800000, (int) $row->akhir_cash);
        $this->assertSame(
            -550000,
            (int) $row->cash_laci - (int) $row->akhir_cash,
            'Kelebihan fisik jadi selisih, bukan transfer bank'
        );

        $saldoBankSesudah = (int) $this->db->table('saldo_awal_kas_bank')
            ->where('akun_kas_bank_id', self::AKUN_BANK)
            ->get()
            ->getRow()
            ->saldo;

        $this->assertSame(
            $saldoBankSebelum,
            $saldoBankSesudah,
            'Saldo bank tidak boleh bergerak tanpa record Setor'
        );

        $this->assertSame(
            0,
            (int) $this->db->table('transaksi_kas_bank')
                ->where('akun_tujuan_id', self::AKUN_BANK)
                ->countAllResults(),
            'Tidak boleh ada transfer diam-diam ke bank'
        );
    }

    // =================================================================
    // 11. RACE / IDEMPOTENSI BERJALAN
    // =================================================================

    public function testSimpanBersamaanDenganKoneksiLainTetapSatuBaris(): void
    {
        $this->penjualanTunai(200000);

        // Koneksi kedua yang benar-benar terpisah: meniru request paralel
        // yang tiba bersamaan. Named lock di service unconstitutional,
        // jadi hanya boleh satu yang jadi penulis.
        $db2 = Database::connect('tests', false);

        $a = $this->closing->simpan(self::UNIT, date('Y-m-d'), 1700000, 43);
        $b = (new TutupKasirClosing($db2))->simpan(self::UNIT, date('Y-m-d'), 1700000, 43);

        $this->assertSame('tersimpan', $a['kode'], (string) $a['alasan']);
        $this->assertSame(
            'sudah_ada',
            $b['kode'],
            'Koneksi kedua harus kena duplicate guard, bukan bikin baris kedua: ' . $b['alasan']
        );
        $this->assertSame(1, $this->jumlahClosing());

        $db2->close();
    }

    public function testSimpanDitolakSaatUnitSamaSedangDikunci(): void
    {
        $this->penjualanTunai(200000);

        // Simulasikan request lain yang SEDANG menutup unit yang sama: named
        // lock dipegang koneksi pertama, jadi ini bukan sekadar dua panggilan
        // berurutan — ini overlap yang nyata di level lock server.
        //
        // Kalau guard hanya mengandalkan pengecekan duplikat di dalam
        // transaksi, request kedua akan tetap bisa masuk dan keduanya
        // berlomba menulis. Named lock inilah yang menutup celah itu.
        //
        // Lock HARUS dipegang koneksi yang berbeda dari `$this->db`: `GET_LOCK`
        // milik session MySQL dan re-entrant, jadi kalau koneksi yang sama yang
        // memegangnya, `simpan()` di bawah akan dapat lock lagi tanpa halangan
        // dan test-nya jadi tidak membuktikan apa pun.
        $db2 = Database::connect('tests', false);
        $lock = $this->closing->lockName(date('Y-m-d'), self::UNIT);

        $db2->query("SELECT GET_LOCK('{$lock}', 5) AS g");

        $hasil = $this->closing->simpan(self::UNIT, date('Y-m-d'), 1700000, 43);

        $db2->query("SELECT RELEASE_LOCK('{$lock}')");
        $db2->close();

        $this->assertFalse($hasil['ok'], 'Harus ditolak selama unit sedang dikunci');
        $this->assertSame('ditolak', $hasil['kode']);
        $this->assertStringContainsString('sedang berjalan', $hasil['alasan']);
        $this->assertSame(0, $this->jumlahClosing(), 'Tidak boleh ada closing yang ditulis');
    }

    public function testKunciDilepasSetelahPenolakanValidasi(): void
    {
        $this->simpan(-5);

        // Kalau named lock tidak dilepas saat validation gagal, request
        // berikutnya akan hang sampai timeout.
        $berikutnya = $this->closing->simpan(self::UNIT, date('Y-m-d'), self::OPENING, 43);

        $this->assertSame('tersimpan', $berikutnya['kode'], (string) $berikutnya['alasan']);
    }

    public function testTidakAdaTransaksiMenggantungSetelahDitolak(): void
    {
        $this->penjualanTunai(200000);

        // Gagal di tengah (validasi dan tanggal buruk), lalu request valid.
        // Kalau transaction tidak di-rollback, transaksi menggantung akan
        // membuat "duplicate before commit" salah baca.
        $this->assertFalse($this->simpan(-1)['ok']);
        $this->assertFalse($this->simpan(1700000, self::UNIT, 'bukan-tanggal')['ok']);

        $berikutnya = $this->simpan(1700000);

        $this->assertSame(
            'tersimpan',
            $berikutnya['kode'],
            'Setelah kegagalan, request valid harus tetap berhasil: ' . $berikutnya['alasan']
        );
        $this->assertSame(1, $this->jumlahClosing());

        // Bukti tidak ada transaksi menggantung: baris yang ditulis SETELAH
        // kegagalan harus langsung terlihat. Kalau rollback tidak jalan, baris
        // ini ikut hilang bersama rollback berikutnya.
        //
        // `transStatus()` tidak dipakai: dengan DBDebug aktif, CI4 selalu
        // mengembalikan true sehingga tidak bisa membedakan transaksi nyata.
        $this->tutupBaris('2026-10-07', self::UNIT, 0, 999);
        $this->assertSame(
            1,
            $this->jumlahClosing('2026-10-07'),
            'Transaksi setelah kegagalan harus benar-benar commit'
        );
    }

    // =================================================================
    // 12. AUTH + CSRF (HTTP)
    // =================================================================

    public function testIndexTanpaLoginDiarahkan(): void
    {
        $result = $this->call('get', 'tutup_kasir');

        $this->assertStringContainsString('Login', $result->getRedirectUrl() ?? '', 'Harus ke halaman login');
    }

    public function testTutupTanpaLoginDitolak(): void
    {
        $before = $this->jumlahClosing(date('Y-m-d'));

        $result = $this->call('post', 'tutupkasir/tutup', [
            'cash_laci' => 1700000,
        ]);

        $this->assertNotNull($result->getRedirectUrl(), 'Tanpa login harus redirect');
        $this->assertSame(
            $before,
            $this->jumlahClosing(date('Y-m-d')),
            'Tidak boleh ada closing yang tersimpan dari request tanpa login'
        );
    }

    public function testTutupDenganLoginTapiTanpaCsrfDitolak(): void
    {
        $this->withSession([
            'logged_in' => true,
            'ID_AKUN'   => 43,
            'ID_UNIT'   => self::UNIT,
            'ID_JABATAN'=> 1,
        ]);

        $before = $this->jumlahClosing(date('Y-m-d'));

        // POST tanpa token CSRF sama sekali.
        //
        // Filter CSRF di CI4 melempar SecurityException; FeatureTestTrait
        // meneruskannya apa adanya, jadi itu justru BUKTI request berhenti di
        // lapisan filter — controller `tutup()` tidak pernah dijalankan.
        try {
            $this->call('post', 'tutupkasir/tutup', ['cash_laci' => 1700000]);
            $this->fail('POST tanpa CSRF harus ditolak filter, bukan lolos ke controller');
        } catch (SecurityException $e) {
            $this->assertStringContainsString('not allowed', $e->getMessage());
        }

        $this->assertSame(
            $before,
            $this->jumlahClosing(date('Y-m-d')),
            'Tanpa CSRF tidak boleh ada closing yang tersimpan'
        );
    }

    /**
     * Route Tutup Kasir harus memakai filter auth (dan csrf untuk POST).
     *
     * Dicek dari file route, bukan dari RouteCollection: `getRoutes()` di CI4
     * hanya mengembalikan `[$key => handler]` dan tidak mengekspos daftar
     * filter per route, jadi tidak ada API publik untuk membacanya. Membaca
     * deklarasinya langsung juga lebih jujur — yang diuji persis konfigurasi
     * yang dikerjakan, termasuk urutan filter-nya.
     */
    public function testRouteTutupKasirWajibAuthDanCsrf(): void
    {
        $isi = (string) file_get_contents(APPPATH . 'Config/Routes.php');

        $baris = static function (string $needle) use ($isi): string {
            foreach (preg_split('/\R/', $isi) as $b) {
                if (str_contains($b, $needle)) {
                    return trim($b);
                }
            }

            return '';
        };

        $tutup = $baris("post('tutupkasir/tutup'");
        $this->assertNotSame('', $tutup, 'Route POST tutupkasir/tutup harus ada');
        $this->assertStringContainsString(
            "['auth', 'csrf']",
            $tutup,
            'POST tutup kasir wajib auth + csrf. Baris sekarang: ' . $tutup
        );

        foreach (["get('tutup_kasir'", "get('cetak-tutup-kasir/(:num)'"] as $needle) {
            $r = $baris($needle);
            $this->assertNotSame('', $r, "Route {$needle} harus ada");
            $this->assertStringContainsString(
                "'auth'",
                $r,
                "Route {$needle} wajib auth. Baris sekarang: {$r}"
            );
        }
    }

    // =================================================================
    // 13. FORM CONTAINS CSRF + INPUT WAJIB
    // =================================================================

    /**
     * Render view `tutup_kasir` langsung, bukan lewat controller.
     *
     * `TutupKasir::index()` mewarisi `BaseController` yang constructor-nya
     * memuat `ModelService`/`ModelStokBarang`/dsb — butuh skema aplikasi
     * penuh, yang tidak ada di suite ini. Yang sedang diuji di sini hanya isi
     * form, jadi view dirender langsung dengan variabel yang sama seperti
     * yang dikirim controller. Pola yang sama dipakai `RenderProbeTest`.
     */
    private function renderViewTutupKasir(): string
    {
        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));

        $r = service('renderer');
        $r->setVar('tanggal', date('Y-m-d'));
        $r->setVar('unit', self::UNIT);
        $r->setVar('saldo_awal_kas', $h['saldo_awal_kas']);
        $r->setVar('saldo_awal_tf', $h['saldo_awal_tf']);
        $r->setVar('setor', $h['setor']);
        $r->setVar('tarik', $h['tarik']);
        $r->setVar('transfer_masuk', $h['transfer_masuk']);
        $r->setVar('transfer_keluar', $h['transfer_keluar']);
        $r->setVar('kas_masuk', $h['kas_masuk']);
        $r->setVar('kas_keluar', $h['kas_keluar']);
        $r->setVar('tutup_bisa_disimpan', $h['siap']);
        $r->setVar('tutup_alasan', $h['alasan']);
        $r->setVar('sudah_ditutup', false);
        $r->setVar('wibOffsetMenit', 420);

        return (string) $r->include('jurnal/tutup_kasir');
    }

    public function testFormTutupMemuatTokenCsrfDanInputWajib(): void
    {
        $body = $this->renderViewTutupKasir();

        // Nama field token mengikuti Security config, dan di environment test
        // namanya menjadi `csrf_test_name`. Jadi pola dicek per nama, bukan
        // di-hardcode ke satu nama.
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="csrf[a-z_]*" value="[0-9a-f]{32}">/',
            $body,
            'Field token CSRF wajib ada di form'
        );
        $this->assertStringContainsString('name="cash_laci"', $body);
        $this->assertMatchesRegularExpression(
            '/name="cash_laci"[^>]*required/',
            $body,
            'Kolom laci harus wajib diisi'
        );
        $this->assertStringContainsString('type="submit"', $body, 'Form harus punya tombol submit');
        $this->assertStringContainsString('btnTutupKasir', $body);
    }

    public function testFormTidakMenampilkanAngkaSaldoPalsu(): void
    {
        // Tidak ada baris opening -> saldo awal tidak boleh tampil sebagai 0.
        // Kolom status legacy tidak kita gulir di sini: tanpa baris baseline
        // sama sekali, laci belum bisa ditetapkan.
        $this->setOpeningKas('TERVERIFIKASI', FinanceScopeService::kasBankCutoffDate(), 0);
        $this->q('DELETE FROM opening_kas WHERE akun_kas_bank_id = ' . self::AKUN_KAS);

        $h = $this->closing->hitung(self::UNIT, date('Y-m-d'));
        $r = service('renderer');
        $r->setVar('tanggal', date('Y-m-d'));
        $r->setVar('unit', self::UNIT);
        $r->setVar('saldo_awal_kas', $h['saldo_awal_kas']);
        $r->setVar('saldo_awal_tf', $h['saldo_awal_tf']);
        $r->setVar('setor', $h['setor']);
        $r->setVar('tarik', $h['tarik']);
        $r->setVar('transfer_masuk', $h['transfer_masuk']);
        $r->setVar('transfer_keluar', $h['transfer_keluar']);
        $r->setVar('kas_masuk', $h['kas_masuk']);
        $r->setVar('kas_keluar', $h['kas_keluar']);
        $r->setVar('tutup_bisa_disimpan', $h['siap']);
        $r->setVar('tutup_alasan', $h['alasan']);
        $r->setVar('sudah_ditutup', false);
        $r->setVar('wibOffsetMenit', 420);

        $body = (string) $r->include('jurnal/tutup_kasir');

        $this->assertStringContainsString(
            'Belum tersedia',
            $body,
            'Saldo awal yang belum ditetapkan harus ditulis "Belum tersedia", bukan 0'
        );
    }

    // =================================================================
    // 14. CONTROLLER SEBAGAI GERBANG — POST crafted lewat HTTP nyata
    // =================================================================

    /**
     * Test 2 ("hidden field bukan sumber kebenaran") dan test 6 ("tanggal/unit
     * dari POST tidak dipercaya") di atas memanggil service langsung. Itu
     * membuktikan service-nya benar, tapi TIDAK membuktikan controller
     * meneruskan payload itu apa adanya — dan justru di situlah bug aslinya
     * pernah ada: controller membaca `akhir_cash` dari POST.
     *
     * Test di sini memanggil `TutupKasir::tutup()` sungguhan dengan
     * `IncomingRequest` berisi payload crafted. Yang diuji persis baris yang
     * dulu jadi lubang: `$this->request->getPost(...)`.
     *
     * `tutup()` butuh tiga hal dari Laravel-ish container CI4: session,
     * request, dan `$this->db`. Ketiganya bisa disuplai tanpa
     * `initController()` — yang avoided karena BaseController melakukan
     * query ke tabel auth/stok/service yang tidak ada di schema suite ini.
     * Konsekuensinya filter `auth`/`csrf` TIDAK ikut teruji di sini; keduanya
     * sudah punya test sendiri (test 10) di level route.
     */
    private function panggilControllerTutup(array $post): ?array
    {
        session()->set('ID_UNIT', self::UNIT);
        session()->set('ID_AKUN', self::AKUN_KAS);

        $request = \Config\Services::request(null, false);
        $request->setMethod('POST');
        $request->setGlobal('post', $post);

        $controller = new TutupKasir();

        $prop = (new \ReflectionClass($controller))->getProperty('request');
        $prop->setAccessible(true);
        $prop->setValue($controller, $request);

        $controller->tutup();

        // Sengaja TIDAK difilter `tanggal = hari ini`. Kalau difilter,
        // mutasi yang memalsukan tanggal akan membuat helper ini
        // mengembalikan null dan test gagal dengan pesan yang tidak
        // menunjukkan apa yang sebenarnya rusak.
        $baris = $this->db->table('tutup_kasir')
            ->orderBy('idtutupkasir', 'DESC')
            ->get()
            ->getResultArray();

        return $baris[0] ?? null;
    }

    /**
     * PAYLOAD CRAFTED. Semua angka uang di luar `cash_laci` sengaja dibuat
     *uckle berbeda dari hasil hitung server, jadi kalau controller ternyata
     * masih membacanya, angka asli yang tersimpan akan berbeda dan test ini
     * gagal dengan nilai yang sangat mudah dibaca.
     */
    private function payloadCrafted(string $cashLaci = '1.500.000'): array
    {
        return [
            'cash_laci'       => $cashLaci,
            'tanggal'         => '2019-01-01',
            'unit'            => '999',
            'awal_cash'       => '77.777.777',
            'akhir_cash'      => '88.888.888',
            'akhir_transfer'  => '9.999.999',
            'pendapatan_cash' => '66.666.666',
            'pendapatan_transfer' => '55.555.555',
            'pengeluaran_cash' => '44.444.444',
            'pengeluaran_transfer' => '33.333.333',
        ];
    }

    public function testControllerMengabaikanSeluruhHiddenFieldDariPost(): void
    {
        $this->setOpeningKas('TERVERIFIKASI');

        $benar = $this->closing->hitung(self::UNIT, $this->hariIni());

        $row = $this->panggilControllerTutup($this->payloadCrafted());

        $this->assertNotNull($row, 'Controller harus menyimpan satu baris closing');

        foreach ([
            'awal_cash'           => '77.777.777',
            'akhir_cash'          => '88.888.888',
            'akhir_transfer'      => '9.999.999',
            'pendapatan_cash'     => '66.666.666',
            'pendapatan_transfer' => '55.555.555',
            'pengeluaran_cash'    => '44.444.444',
            'pengeluaran_transfer' => '33.333.333',
        ] as $kolom => $nilaiPalsu) {
            $this->assertNotEquals(
                (float) $nilaiPalsu,
                (float) $row[$kolom],
                "Kolom {$kolom} Took nilai dari POST ({$nilaiPalsu}). "
                    . 'Angka closing wajib berasal dari hitung server.'
            );
        }

        // Yang tersimpan harus PERSIS hasil hitung server.
        $this->assertEquals((float) $benar['akhir_cash'], (float) $row['akhir_cash']);
        $this->assertEquals((float) $benar['awal_cash'], (float) $row['awal_cash']);
        $this->assertEquals((float) $benar['akhir_transfer'], (float) $row['akhir_transfer']);
    }

    public function testControllerMengabaikanTanggalDanUnitDariPost(): void
    {
        $this->setOpeningKas('TERVERIFIKASI');

        $row = $this->panggilControllerTutup($this->payloadCrafted());

        $this->assertNotNull($row);

        $this->assertSame(
            $this->hariIni(),
            substr((string) $row['tanggal'], 0, 10),
            'Tanggal closing harus tanggal server, bukan `tanggal` dari POST'
        );

        $this->assertSame(
            self::UNIT,
            (int) $row['unit'],
            'Unit closing harus unit dari session, bukan `unit` dari POST'
        );

        $this->assertSame(
            0,
            (int) $this->db->table('tutup_kasir')
                ->where('unit', 999)
                ->countAllResults(),
            'Tidak boleh ada baris untuk unit 999 dari POST'
        );
    }

    public function testControllerTetapMengambilCashLaciDariPost(): void
    {
        $this->setOpeningKas('TERVERIFIKASI');

        // Disengaja TIDAK sama dengan `akhir_cash` server: laci adalah angka
        // fisik hasil hitung manusia, dan selisihnya memang harus_allowed.
        $row = $this->panggilControllerTutup($this->payloadCrafted('1.234.567'));

        $this->assertNotNull($row);

        $server = $this->closing->hitung(self::UNIT, $this->hariIni());

        $this->assertEquals(
            1234567.0,
            (float) $row['cash_laci'],
            '`cash_laci` satu-satunya angka request yang boleh dipakai apa adanya'
        );

        $this->assertEquals((float) $server['akhir_cash'], (float) $row['akhir_cash']);
        $this->assertNotEquals(
            (float) $row['cash_laci'],
            (float) $row['akhir_cash'],
            'Laci tidak boleh dipaksa sama dengan akhir_cash — kalau iya, test ini diam-diam lewat'
        );
    }

    public function testControllerPostGandaTetapSatuBaris(): void
    {
        $this->setOpeningKas('TERVERIFIKASI');

        $pertama = $this->panggilControllerTutup($this->payloadCrafted('1.000.000'));

        // POST kedua dengan nominal berbeda. Kalau ada celah idempotensi,
        // baris pertama akan tertimpa nilainya.
        $kedua = $this->panggilControllerTutup($this->payloadCrafted('2.000.000'));

        $this->assertNotNull($pertama);
        $this->assertNotNull($kedua);

        $this->assertSame(
            (int) $pertama['idtutupkasir'],
            (int) $kedua['idtutupkasir'],
            'POST kedua harus idempoten pada baris yang sama, bukan membuat/menimpa baris baru'
        );

        $this->assertSame(
            1,
            (int) $this->db->table('tutup_kasir')->countAllResults(),
            'Harus tetap satu baris closing'
        );

        $this->assertEquals(
            1000000.0,
            (float) $kedua['cash_laci'],
            'Nominal POST kedua tidak boleh menimpa nominal pertama'
        );
    }

    public function testControllerTidakSimpanKalauCashLaciTidakValid(): void
    {
        $this->setOpeningKas('TERVERIFIKASI');

        $row = $this->panggilControllerTutup($this->payloadCrafted('1.5'));

        $this->assertNull($row, 'cash_laci tidak valid tidak boleh menghasilkan closing');

        $this->assertSame(
            0,
            (int) $this->db->table('tutup_kasir')->countAllResults()
        );
    }

    // =================================================================
    // 15. BUSINESS WINDOW WIB (20:45 / 23:00)
    // =================================================================

    /**
     * BUSINESS RULE yang dikunci di sini:
     *   <  20:45  tombol mati
     *   20:45 - 22:59  tombol aktif (window normal)
     *   >= 23:00  tombol tetap aktif + warning "closing terlambat"
     *
     * dua hal yang TIDAK boleh terjadi:
     *  - hard-lock setelah 23:00 (mengubah perilaku produksi yang Closing
     *    sampai tengah malam)
     *  - batas 21:15 (itu angka yang salah; batas bisnis yang benar 23:00)
     *
     * Diuji pada level sumber view karena jadwal tombol murni urusan JS
     * browser — PHPUnit tidak menjalankan JS. Yang dikunci adalah kondisi
     * percabangan dan konstantanya, supaya refactor tidak diam-diam
     * mengubah aturan mainnya.
     */
    public function testBusinessWindowTutupKasirMemakaiBatas2300(): void
    {
        $src = (string) file_get_contents(APPPATH . 'Views/jurnal/tutup_kasir.php');

        $this->assertStringContainsString(
            'const MULAI_NORMAL = 20 * 60 + 45;',
            $src,
            'Batas bawah window tetap 20:45 WIB'
        );

        $this->assertStringContainsString(
            'const BATAS_TERLAMBAT = 23 * 60;',
            $src,
            'Batas atas penanda terlambat harus 23:00 WIB (= 1380 menit)'
        );

        $this->assertStringNotContainsString(
            '21 * 60 + 15',
            $src,
            'Batas 21:15 adalah angka yang salah dan tidak boleh muncul lagi'
        );

        // Blok "setelah 23:00" tidak boleh mematikan tombol.
        $this->assertMatchesRegularExpression(
            '/const terlambat = totalMenit >= BATAS_TERLAMBAT;\s*\n\s*btn\.innerHTML = terlambat/',
            $src,
            'Cabang >= 23:00 harus mengganti label tombol, bukan menonaktifkannya'
        );

        $this->assertStringContainsString(
            "peringatan.classList.toggle('d-none', !terlambat)",
            $src,
            'Peringan closing terlambat harus muncul hanya di luar 23:00'
        );

        $this->assertStringContainsString(
            'id="peringatanTerlambat"',
            $src,
            'Elemen peringatan closing terlambat harus ada di markup'
        );
    }

    public function testWaktuTutupKasirMenghitungWibBukanUtc(): void
    {
        $src = (string) file_get_contents(APPPATH . 'Views/jurnal/tutup_kasir.php');

        $this->assertStringContainsString(
            'const OFFSET_WIB_MENIT = <?= $wibOffsetMenit ?? 420 ?>;',
            $src,
            'Offset WIB harus dikirim server, bukan diasumsikan 420 di frontend'
        );

        // Setelah digeser offset, komponen getUTC*() = jam dinding WIB.
        $this->assertStringContainsString(
            'new Date(Date.now() + OFFSET_WIB_MENIT * 60000)',
            $src,
            'Epoch harus digeser sebesar offset WIB sebelum komponennya dibaca'
        );

        // Komentar di view sah-saja menyebut toISOString() sebagai contoh
        // kenapa cara lama salah. Yang dilarang adalah PEMAKAIANNYA di kode,
        // jadi komentar `//` dibuang dulu sebelum diperiksa.
        $kode = $this->tanpaKomentarJs($src);

        $this->assertStringNotContainsString(
            'toISOString()',
            $kode,
            'toISOString() selalu UTC. Tanggal harus dibangun dari komponen '
                . 'getUTC*() yang sudah digeser offset, bukan dari string UTC.'
        );

        // Guard: kalau dipindah ke getHours() tanpa offset, jam 20:45 WIB
        // akan salah di browser yang tidak di Asia/Jakarta.
        $this->assertStringNotContainsString(
            'getHours()',
            $kode,
            'getHours() memakai timezone browser, bukan WIB'
        );

        $this->assertStringNotContainsString(
            'getMinutes()',
            $kode,
            'getMinutes() memakai timezone browser, bukan WIB'
        );
    }

    /**
     * Buang baris komentar `//` dari sumber JS.
     *
     * Dipakai supaya assertion "tidak memakai API tertentu" tidak salah
     * gagal hanya karena nama API itu disebut di penjelasan.
     */
    private function tanpaKomentarJs(string $src): string
    {
        $baris = [];

        foreach (explode("\n", $src) as $b) {
            if (preg_match('#(^|\s|;)//#', $b) === 1) {
                continue;
            }

            if (strpos($b, '/*') !== false) {
                continue;
            }

            $baris[] = $b;
        }

        return implode("\n", $baris);
    }
}
