<?php

namespace Tests;

use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\TutupKasirClosing;
use App\Services\Finance\TutupKasirSaldoAwal;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * Saldo awal TRANSFER dari rekening BANK BERSAMA yang dialokasikan per unit.
 *
 * SKENARIO (angka produksi yang sedang diperiksa):
 *
 *   Cut-off        2026-10-07   statement akun 12 = Rp20.000.000
 *   Periode mulai  2026-10-08
 *   Akun 12        BANK shared, is_finance_ho = 0, unit_id NULL
 *   Alokasi        Unit 1 = Rp10.000.000, Unit 2 = Rp10.000.000
 *   Movement       service Unit 2, status 4, 2026-10-08, transfer Rp400.000
 *   Tutup kasir    TIDAK ADA baris sama sekali (hari pertama periode)
 *   Bank non-shared milik Unit 1 & 2 TIDAK ADA (itu sebabnya fallback ini ada)
 *
 * Yang dijaga di sini:
 *
 *   1. Saldo awal TRANSFER per unit = ALOKASINYA (Rp10.000.000), bukan saldo
 *      fisik rekening (Rp20.000.000) dan bukan saldo berjalan unit itu
 *      (Rp10.400.000). Dua angka terakhir boleh muncul di laporan posisi,
 *      tidak boleh pernah jadi saldo awal.
 *   2. Saldo awal dan saldo berjalan terpisah:
 *        awal   = alokasi saja
 *        berjalan = alokasi + movement periode
 *      `saldoAwalTransfer()` tidak boleh memanggil `netMovement()`.
 *   3. Resolver mengikuti rantai yang sama dengan `rekeningDefaultUnit()`,
 *      dan alokasi bernilai 0 bukan saldo awal.
 *   4. Urutan sumber tidak berubah: carry-forward closing > rekening BANK
 *      milik unit > alokasi rekening bersama > belum ditetapkan.
 *   5. Alokasi hanya DIBACA, tidak pernah DITULIS oleh Tutup Kasir.
 *
 * Bug yang dicegah:
 *   - Sebelumnya unit tanpa BANK sendiri selalu jatuh ke
 *     `SUMBER_BELUM`, padahal dananya sudah benar-benar ada sebagai baris
 *     alokasi. Halaman Tutup Kasir berhenti total di unit-unit itu.
 *   - Solusi yang salah adalah memakai saldo fisik Rp20.000.000 sebagai
 *     saldo awal SEMUA unit: dua unit akan mengklaim seluruh rekening dan
 *     posisi unit tidak akan pernah cocok dengan saldo fisik.
 *   - Solusi yang salah kedua adalah memakai posisi berjalan
 *     (alokasi + movement): movement hari itu jadi bagian saldo AWAL, lalu
 *     dihitung lagi sebagai pendapatan hari itu — double count.
 */
class TutupKasirSharedAllocationTest extends CIUnitTestCase
{
    protected $db;
    protected TutupKasirSaldoAwal $saldoAwal;
    protected TutupKasirClosing $closing;
    protected KasBankCutoffService $cutoff;

    private const AKUN_SHARED = 12; // BANK bersama Unit 1 & 2
    private const AKUN_BANK3  = 20; // BANK non-shared milik Unit 3
    private const AKUN_KAS1   = 30; // KAS Unit 1
    private const AKUN_KAS2   = 31; // KAS Unit 2

    private const UNIT1    = 1;
    private const UNIT2    = 2;
    private const UNIT3    = 3;
    private const UNIT_LUAR = 50;

    private const ALOKASI    = 10000000;
    private const FISIK_AWAL = 20000000;
    private const BANK3_AWAL = 5000000;
    private const TRANSFER   = 400000;
    private const OPENING    = 500000;

    /** Tanggal closing pada skenario. Dipisah supaya test tidak ikut bergeser. */
    private const TANGGAL = '2026-10-08';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        // Fail-closed: test ini menghapus dan membuat ulang tabel, jadi hanya
        // boleh jalan terhadap `erp_finance_test`.
        if ($this->db->getDatabase() !== 'erp_finance_test') {
            $this->fail('Test hanya boleh jalan pada database erp_finance_test, bukan: '
                . $this->db->getDatabase());
        }

        $this->saldoAwal = new TutupKasirSaldoAwal($this->db);
        $this->closing   = new TutupKasirClosing($this->db);
        $this->cutoff    = new KasBankCutoffService();

        $this->schema();
        $this->seed();
    }

    private function q(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            $this->fail('Query gagal: ' . $this->db->error()['message']);
        }
    }

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
        $this->q('ALTER TABLE transaksi_kas_bank
                ADD UNIQUE KEY uniq_tkb_submission (submission_key)');
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

        // Legacy: tetap dibuat karena masih dibaca sebagai cerminan, tapi
        // TIDAK pernah jadi sumber saldo awal.
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
        $cutoff = FinanceScopeService::kasBankCutoffDate();

        // Alias: `self::KONSTANTA` tidak bisa diinterpolasi di dalam string
        // double quote, jadi nilainya ditarik ke variabel dulu.
        $shared = self::AKUN_SHARED;
        $bank3  = self::AKUN_BANK3;
        $u1     = self::UNIT1;
        $u2     = self::UNIT2;
        $u3     = self::UNIT3;

        $this->q("INSERT INTO unit (idunit, NAMA_UNIT) VALUES
            (1,'Probolinggo'), (2,'Jember'), (3,'Malang'), (50,'Head Office')");
        $this->q("INSERT INTO akun (ID_AKUN, ID_UNIT, ID_JABATAN, NAMA_AKUN, ROLES) VALUES
            (43, 1, 1, 'Admin Root', '[]')");
        $this->q("INSERT INTO bank (idbank, nama_bank, norek, atas_nama) VALUES
            ('BCA-0393','Bank BCA','0393778773','ICLEAR DIGITAL SOLUSI')");

        // Unit 1 & 2 TIDAK punya BANK non-shared: itu persis kasus yang
        // diperbaiki. Unit 3 punya, untuk membuktikan jalur lama tidak berubah.
        $this->q("INSERT INTO akun_kas_bank
                (idakun_kas_bank, unit_id, tipe, nama_akun, bank_idbank, no_akun_coa,
                 status, is_shared, is_finance_ho) VALUES
            ({$shared}, NULL, 'BANK', 'BCA 0393778773 (ICLEAR)',
                'BCA-0393', NULL, 'aktif', 1, 0),
            ({$bank3}, {$u3}, 'BANK', 'BCA Malang',
                'BCA-0393', NULL, 'aktif', 0, 0),
            (" . self::AKUN_KAS1 . ", {$u1}, 'KAS', 'Kas Probolinggo',
                NULL, '1010101000', 'aktif', 0, 0),
            (" . self::AKUN_KAS2 . ", {$u2}, 'KAS', 'Kas Jember',
                NULL, '1010101000', 'aktif', 0, 0)");

        // Statement cut-off. Sengaja BELUM_VERIFIKASI: Tutup Kasir memang
        // tidak menjadikan verifikasi sebagai syarat membaca baseline.
        $this->q("INSERT INTO saldo_awal_kas_bank
                (akun_kas_bank_id, tanggal, saldo, keterangan, status) VALUES
            ({$shared}, '{$cutoff}', " . self::FISIK_AWAL . ", 'Koran cut-off', 'BELUM_VERIFIKASI'),
            ({$bank3}, '{$cutoff}', " . self::BANK3_AWAL . ", 'Koran cut-off', 'BELUM_VERIFIKASI')");

        // Dua unit, satu rekening, masing-masing setengah.
        $this->q("INSERT INTO alokasi_saldo_kas_bank
                (akun_kas_bank_id, unit_id, nominal, keterangan, created_at) VALUES
            ({$shared}, {$u1}, " . self::ALOKASI . ", 'Unit 1', '" . date('Y-m-d H:i:s') . "'),
            ({$shared}, {$u2}, " . self::ALOKASI . ", 'Unit 2', '" . date('Y-m-d H:i:s') . "')");

        // Baseline KAS kedua unit supaya `hitung()` bisa lolos gerbang.
        foreach ([self::AKUN_KAS1 => self::UNIT1, self::AKUN_KAS2 => self::UNIT2] as $akun => $unit) {
            $this->q("INSERT INTO opening_kas
                    (akun_kas_bank_id, unit_id, tanggal, opening, real_cash, selisih,
                     status, input_by, created_at, updated_at)
                VALUES ({$akun}, {$unit}, '{$cutoff}', " . self::OPENING . ', '
                . self::OPENING . ", 0, 'TERVERIFIKASI', 1, '" . date('Y-m-d H:i:s') . "',
                '" . date('Y-m-d H:i:s') . "')");
        }

        // Movement periode: transfer masuk Unit 2 pada hari ke-1 periode.
        $this->q("INSERT INTO service
                (unit_idunit, tanggal_selesai, status_service, harus_dibayar, bayar_tunai, keterangan)
            VALUES ({$u2}, '" . self::TANGGAL . "', 4, " . self::TRANSFER . ",
                0, 'transfer shared-allocation')");
    }

    // =================================================================
    // 0. SKENARIO
    // =================================================================

    public function testSkenarioMemakaiCutOffDanPeriodeAktual(): void
    {
        $this->assertSame(
            '2026-10-07',
            FinanceScopeService::kasBankCutoffDate(),
            'Config cut-off KasBank berubah. Seluruh angka skenario di test ini ikut bergeser.'
        );
        $this->assertSame('2026-10-08', FinanceScopeService::kasBankPeriodeMulaiDate());
        $this->assertSame(
            FinanceScopeService::kasBankPeriodeMulaiDate(),
            date('Y-m-d', strtotime(FinanceScopeService::kasBankCutoffDate() . ' +1 day')),
            'Periode harus mulai tepat sehari setelah cut-off'
        );
    }

    // =================================================================
    // 1 & 2. SALDO AWAL = ALOKASI, BUKAN SALDO BERJALAN
    // =================================================================

    public function testSaldoAwalTransferUnit1AdalahAlokasinya(): void
    {
        $h = $this->saldoAwal->saldoAwalTransfer(self::UNIT1, self::TANGGAL);

        $this->assertTrue($h['ada'], $h['pesan']);
        $this->assertSame(self::ALOKASI, (int) $h['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_ALOKASI, $h['sumber']);
    }

    public function testSaldoAwalTransferUnit2AdalahAlokasinyaBukanSaldoBerjalan(): void
    {
        $h = $this->saldoAwal->saldoAwalTransfer(self::UNIT2, self::TANGGAL);

        $this->assertTrue($h['ada'], $h['pesan']);
        $this->assertSame(
            self::ALOKASI,
            (int) $h['nilai'],
            'Saldo awal Unit 2 harus alokasi Rp10.000.000, bukan alokasi + movement Rp400.000'
        );

        // Guard eksplisit terhadap dua angka yang salah.
        $this->assertNotSame(
            self::ALOKASI + self::TRANSFER,
            (int) $h['nilai'],
            'Saldo awal tidak boleh memuat movement hari berjalan (double count)'
        );
        $this->assertNotSame(
            self::FISIK_AWAL,
            (int) $h['nilai'],
            'Saldo awal satu unit tidak boleh berupa saldo fisik seluruh rekening'
        );
    }

    public function testSaldoAwalTidakMembacaNetMovement(): void
    {
        $sebelum = $this->saldoAwal->saldoAwalTransfer(self::UNIT2, self::TANGGAL);

        // Tambah movement: posisi unit naik, saldo awal TIDAK.
        $this->q("INSERT INTO service
                (unit_idunit, tanggal_selesai, status_service, harus_dibayar, bayar_tunai, keterangan)
            VALUES (" . self::UNIT2 . ", '" . self::TANGGAL . "', 4, 777000, 0, 'tambahan')");

        $sesudah = $this->saldoAwal->saldoAwalTransfer(self::UNIT2, self::TANGGAL);

        $this->assertSame((int) $sebelum['nilai'], (int) $sesudah['nilai'],
            'Saldo awal harus tetap alokasi berapa pun movement harian');
        $this->assertSame(
            self::ALOKASI + self::TRANSFER + 777000,
            $this->cutoff->posisiUnit(self::AKUN_SHARED, self::UNIT2, self::TANGGAL),
            'Posisi unit memang harus bergerak — itu bedanya dengan saldo awal'
        );
    }

    // =================================================================
    // 3. POSISI UNIT & SALDO FISIK — PASANGAN YANG HARUS COCOK
    // =================================================================

    public function testPosisiUnit1HanyaAlokasinya(): void
    {
        $this->assertSame(
            self::ALOKASI,
            $this->cutoff->posisiUnit(self::AKUN_SHARED, self::UNIT1, self::TANGGAL)
        );
    }

    public function testPosisiUnit2AlokasiDitambahMovement(): void
    {
        $this->assertSame(
            self::ALOKASI + self::TRANSFER,
            $this->cutoff->posisiUnit(self::AKUN_SHARED, self::UNIT2, self::TANGGAL)
        );
    }

    public function testTotalPosisiUnitSamaDenganSaldoFisik(): void
    {
        $this->assertSame(
            self::FISIK_AWAL + self::TRANSFER,
            $this->cutoff->totalPosisiUnit(self::AKUN_SHARED)
        );
        $this->assertSame(
            $this->cutoff->saldoFisik(self::AKUN_SHARED, self::TANGGAL),
            $this->cutoff->totalPosisiUnit(self::AKUN_SHARED),
            'Sebaran unit harus habis terbagi: tidak ada saldo yang hilang atau dobel'
        );
    }

    public function testSaldoFisikPadaCutoffBelumMemuatMovementPeriode(): void
    {
        $this->assertSame(self::FISIK_AWAL, $this->cutoff->saldoFisik(self::AKUN_SHARED, '2026-10-07'));
    }

    public function testSaldoFisikSetelahPeriodeMulaiMemuatMovement(): void
    {
        $this->assertSame(
            self::FISIK_AWAL + self::TRANSFER,
            $this->cutoff->saldoFisik(self::AKUN_SHARED, self::TANGGAL)
        );
    }

    public function testEntitledUnitIdsHanyaUnitYangPunyaAlokasi(): void
    {
        $this->assertEqualsCanonicalizing(
            [self::UNIT1, self::UNIT2],
            $this->cutoff->entitledUnitIds(self::AKUN_SHARED)
        );
    }

    public function testUnitTanpaHakAtasRekeningBerposisiNol(): void
    {
        $this->assertFalse($this->cutoff->entitled(self::AKUN_SHARED, self::UNIT_LUAR));
        $this->assertSame(0, $this->cutoff->posisiUnit(self::AKUN_SHARED, self::UNIT_LUAR, self::TANGGAL));
    }

    public function testLegacyUnassignedNolKarenaAlokasiHabisMembagiStatement(): void
    {
        $this->assertSame(0, $this->cutoff->legacyUnassigned(self::AKUN_SHARED));

        $inv = $this->cutoff->cekInvariant(self::AKUN_SHARED);
        $this->assertSame(0, (int) $inv['legacy_unassigned']);
        $this->assertSame(0, (int) $inv['selisih'], 'Invariant saldo fisik vs sebaran unit harus utuh');
    }

    // =================================================================
    // 4. RESOLVER — RANTAI SUMBER
    // =================================================================

    public function testUnitTanpaAlokasiTidakDiberiSaldoAwal(): void
    {
        $h = $this->saldoAwal->saldoAwalTransfer(self::UNIT_LUAR, self::TANGGAL);

        $this->assertFalse($h['ada']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_BELUM, $h['sumber']);
        $this->assertNull($this->saldoAwal->akunBankUnitTerAlokasi(self::UNIT_LUAR));
    }

    public function testAlokasiNolBukanSaldoAwal(): void
    {
        $this->q("UPDATE alokasi_saldo_kas_bank SET nominal = 0
                WHERE akun_kas_bank_id = " . self::AKUN_SHARED . ' AND unit_id = ' . self::UNIT1);

        $this->assertNull(
            $this->saldoAwal->akunBankUnitTerAlokasi(self::UNIT1),
            'Baris alokasi bernilai 0 = hak terpetakan tapi saldo belum dialokasikan'
        );

        $h = $this->saldoAwal->saldoAwalTransfer(self::UNIT1, self::TANGGAL);
        $this->assertFalse($h['ada']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_BELUM, $h['sumber']);
    }

    public function testRekeningBankMilikUnitTetapPrioritasSebelumAlokasi(): void
    {
        // Unit 3 juga punya alokasi di rekening bersama. Rekening miliknya
        // sendiri harus tetap menang.
        $this->q("INSERT INTO alokasi_saldo_kas_bank
                (akun_kas_bank_id, unit_id, nominal, keterangan, created_at) VALUES
            (" . self::AKUN_SHARED . ', ' . self::UNIT3 . ", 3000000, 'Unit 3', '" . date('Y-m-d H:i:s') . "')");

        $this->assertSame(
            self::AKUN_BANK3,
            $this->saldoAwal->akunBankUnit(self::UNIT3),
            'Rekening BANK milik unit harus dipilih lebih dulu'
        );
        $this->assertNull(
            $this->saldoAwal->akunBankUnitTerAlokasi(self::UNIT3),
            'Resolver alokasi hanya dipakai kalau unit TIDAK punya rekening sendiri'
        );

        $h = $this->saldoAwal->saldoAwalTransfer(self::UNIT3, self::TANGGAL);
        $this->assertTrue($h['ada'], $h['pesan']);
        $this->assertSame(self::BANK3_AWAL, (int) $h['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_OPENING, $h['sumber']);
    }

    public function testCarryForwardClosingTetapPrioritasPertama(): void
    {
        // Tanggal SEBELUM cut-off KasBank (2026-10-07): closing ini tetap sah
        // jadi carry-forward. Closing tepat pada cut-off sengaja tidak dipakai
        // sebagai fixture di sini — lihat
        // testClosingPadaJendelaResetKasBankDitolak().
        $u1 = self::UNIT1;
        $this->q("INSERT INTO tutup_kasir
                (tanggal, unit, awal_cash, akhir_cash, awal_transfer, akhir_transfer,
                 pendapatan_cash, pendapatan_transfer, pengeluaran_cash, pengeluaran_transfer,
                 cash_laci, status, akun_ID_AKUN, created_at, updated_at)
            VALUES ('2026-10-06', {$u1}, 500000, 600000,
                " . self::ALOKASI . ", 9999999,
                0, 0, 0, 0, 600000, 'selesai', 43, '" . date('Y-m-d H:i:s') . "',
                '" . date('Y-m-d H:i:s') . "')");

        $h = $this->saldoAwal->saldoAwalTransfer(self::UNIT1, self::TANGGAL);

        $this->assertTrue($h['ada'], $h['pesan']);
        $this->assertSame(9999999, (int) $h['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_CLOSING, $h['sumber'],
            'Carry-forward closing harus tetap menang di atas alokasi rekening bersama');
    }

    /**
     * Closing pada jendela reset KasBank (tepat tanggal cut-off) DITOLAK.
     *
     * `akhir_transfer` tanggal cut-off dihitung dengan ledger LAMA, jadi tidak
     * sebanding dengan baseline periode baru. Sumber yang sah adalah alokasi
     * unit atas rekening bersama pada cut-off.
     *
     * Dua closing dipasang sekaligus: satu pada cut-off (2026-10-07) dan satu
     * lebih lama (2026-10-06). Kalau penolakan diimplementasikan sebagai
     * `WHERE` di query, baris 6 Okt akan terpilih dan angka legacy tetap bocor.
     * Post-filter atas baris terakhir membuat keduanya gugur dan sumber jatuh
     * ke alokasi.
     */
    public function testClosingPadaJendelaResetKasBankDitolak(): void
    {
        $u1 = self::UNIT1;
        $this->q("INSERT INTO tutup_kasir
                (tanggal, unit, awal_cash, akhir_cash, awal_transfer, akhir_transfer,
                 pendapatan_cash, pendapatan_transfer, pengeluaran_cash, pengeluaran_transfer,
                 cash_laci, status, akun_ID_AKUN, created_at, updated_at)
            VALUES
            ('2026-10-06', {$u1}, 500000, 600000, " . self::ALOKASI . ", 8888888,
                0, 0, 0, 0, 600000, 'selesai', 43, '" . date('Y-m-d H:i:s') . "',
                '" . date('Y-m-d H:i:s') . "'),
            ('2026-10-07', {$u1}, 600000, 700000, " . self::ALOKASI . ", 9999999,
                0, 0, 0, 0, 700000, 'selesai', 43, '" . date('Y-m-d H:i:s') . "',
                '" . date('Y-m-d H:i:s') . "')");

        $h = $this->saldoAwal->saldoAwalTransfer(self::UNIT1, self::TANGGAL);

        $this->assertTrue($h['ada'], $h['pesan']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_ALOKASI, $h['sumber'],
            'Closing pada cut-off gugur; sumbernya alokasi unit, bukan closing');
        $this->assertSame(self::ALOKASI, (int) $h['nilai']);
        $this->assertNotSame(9999999, (int) $h['nilai'],
            'Angka closing tanggal cut-off tidak boleh bocor');
        $this->assertNotSame(8888888, (int) $h['nilai'],
            'Penolakan bukan WHERE: baris 6 Okt tidak boleh terpilih menggantikan 7 Okt');
    }

    /**
     * Sisi KAS punya gate yang sama — satu titik di `closingTerakhirFor()`.
     */
    public function testClosingKasPadaJendelaResetDitolakDanJatuhKeBaseline(): void
    {
        $u1 = self::UNIT1;
        $this->q("INSERT INTO tutup_kasir
                (tanggal, unit, awal_cash, akhir_cash, awal_transfer, akhir_transfer,
                 pendapatan_cash, pendapatan_transfer, pengeluaran_cash, pengeluaran_transfer,
                 cash_laci, status, akun_ID_AKUN, created_at, updated_at)
            VALUES ('2026-10-07', {$u1}, 500000, 7777777,
                " . self::ALOKASI . ", " . self::ALOKASI . ",
                0, 0, 0, 0, 7777777, 'selesai', 43, '" . date('Y-m-d H:i:s') . "',
                '" . date('Y-m-d H:i:s') . "')");

        $h = $this->saldoAwal->saldoAwalKas(self::UNIT1, self::TANGGAL);

        $this->assertSame(TutupKasirSaldoAwal::SUMBER_OPENING, $h['sumber'],
            'Closing cut-off ditolak; sisi KAS jatuh ke baseline Opening KAS');
        $this->assertSame(self::OPENING, (int) $h['nilai']);
        $this->assertNotSame(7777777, (int) $h['nilai']);
    }

    public function testAlokasiTidakDitulisOlehAlurTutupKasir(): void
    {
        $baca = function (): array {
            return $this->db->table('alokasi_saldo_kas_bank')
                ->select('id, akun_kas_bank_id, unit_id, nominal, keterangan, input_by, created_at, updated_at')
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();
        };

        $sebelum = $baca();
        $this->assertCount(2, $sebelum);

        $this->saldoAwal->saldoAwalTransfer(self::UNIT1, self::TANGGAL);
        $this->saldoAwal->saldoAwalTransfer(self::UNIT2, self::TANGGAL);
        $this->cutoff->posisiUnit(self::AKUN_SHARED, self::UNIT1, self::TANGGAL);
        $this->cutoff->posisiUnit(self::AKUN_SHARED, self::UNIT2, self::TANGGAL);
        $this->cutoff->saldoFisik(self::AKUN_SHARED, self::TANGGAL);

        $this->assertSame($sebelum, $baca(), 'Alokasi saldo bersama hanya boleh dibaca, tidak ditulis');
    }

    // =================================================================
    // 5. WIRING — controller memakai service yang sama
    // =================================================================

    public function testHitungMenyampaikanSaldoAwalTransferAlokasi(): void
    {
        $h = $this->closing->hitung(self::UNIT2, self::TANGGAL);

        $this->assertTrue($h['siap'], $h['alasan']);
        $this->assertTrue($h['saldo_awal_tf']['ada'], $h['saldo_awal_tf']['pesan']);
        $this->assertSame(self::ALOKASI, (int) $h['saldo_awal_tf']['nilai']);
        $this->assertSame(TutupKasirSaldoAwal::SUMBER_ALOKASI, $h['saldo_awal_tf']['sumber']);

        $this->assertSame(self::TRANSFER, (int) $h['transfer_masuk']);
        $this->assertSame(self::ALOKASI + self::TRANSFER, (int) $h['angka']['akhir_transfer'],
            'Akhir transfer = saldo awal + movement; keduanya tidak boleh saling menimpa');
        $this->assertSame(self::ALOKASI, (int) $h['angka']['awal_transfer']);
    }

    public function testControllerIndexMemanggilHitungDanTutupMemanggilSimpan(): void
    {
        $src = (string) file_get_contents(APPPATH . 'Controllers/TutupKasir.php');

        // index(): SATU sumber angka.
        $this->assertStringContainsString('->hitung($unit, $today)', $src,
            'TutupKasir::index() wajib memakai TutupKasirClosing::hitung()');
        $this->assertStringContainsString("'saldo_awal_tf'", $src);
        $this->assertStringContainsString("'tutup_bisa_disimpan'", $src);
        $this->assertStringContainsString("'sudah_ditutup'", $src);
        $this->assertStringContainsString("'transfer_internal'", $src);

        // tutup(): hanya meneruskan cash_laci ke simpan().
        $this->assertStringContainsString('->simpan(', $src,
            'TutupKasir::tutup() wajib memakai TutupKasirClosing::simpan()');
        $this->assertStringContainsString("getPost('cash_laci')", $src);

        // Tidak ada lagi input angka dari form dan tidak ada lagi tulis manual.
        $this->assertStringNotContainsString("\$this->request->getPost('awal_cash')", $src);
        $this->assertStringNotContainsString("\$this->request->getPost('akhir_cash')", $src);
        $this->assertStringNotContainsString("table('kas_masuk')->insert", $src,
            'tutup() tidak boleh lagi menulis baris kas_masuk kas awal');
        $this->assertStringNotContainsString("table('tutup_kasir')->insert", $src,
            'tutup() tidak boleh lagi insert tutup_kasir manual di luar simpan()');
        $this->assertStringNotContainsString('Tutup kasir unit ini hari ini sudah dilakukan', $src,
            'Early return render kedua sudah dihapus; view punya $sudah_ditutup');

        // Flash key harus yang benar-benar dirender template.
        $this->assertStringContainsString("->with('sukses'", $src);
        $this->assertStringContainsString("->with('gagal'", $src);
        $this->assertStringNotContainsString("->with('success'", $src);
    }

    public function testSimpanMenulisAlokasiSebagaiSaldoAwalTransfer(): void
    {
        $hariIni = date('Y-m-d');

        // Gerbang simpan() hanya menerima tanggal hari ini, jadi movement
        // dipindah ke hari ini supaya tidak bergantung pada tanggal skenario.
        $this->q('DELETE FROM service');
        $this->q("INSERT INTO service
                (unit_idunit, tanggal_selesai, status_service, harus_dibayar, bayar_tunai, keterangan)
            VALUES (" . self::UNIT2 . ", '{$hariIni}', 4, " . self::TRANSFER . ", 0, 'hari ini')");

        $hasil = $this->closing->simpan(self::UNIT2, $hariIni, self::OPENING, 43);

        $this->assertTrue($hasil['ok'], $hasil['alasan']);
        $this->assertSame('tersimpan', $hasil['kode']);

        $baris = $this->db->table('tutup_kasir')
            ->where('unit', self::UNIT2)
            ->where('tanggal', $hariIni)
            ->get()->getRow();

        $this->assertNotNull($baris, 'Closing harus tercatat');
        $this->assertSame(self::ALOKASI, (int) $baris->awal_transfer,
            'Saldo awal transfer yang tersimpan adalah alokasi unit, bukan saldo fisik');
        $this->assertSame(self::ALOKASI + self::TRANSFER, (int) $baris->akhir_transfer);

        // Sisi KAS ikut terhitung dari baseline, bukan dari angka form.
        $this->assertSame(self::OPENING, (int) $baris->awal_cash);
        $this->assertSame(self::OPENING, (int) $baris->akhir_cash);
        $this->assertSame(self::TRANSFER, (int) $baris->pendapatan_transfer);
        $this->assertSame(self::OPENING, (int) $baris->cash_laci);
    }
}
