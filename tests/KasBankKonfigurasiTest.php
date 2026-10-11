<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use App\Controllers\KasBank;
use App\Services\Finance\FinanceScopeService;
use App\Services\Finance\KasBankCutoffService;

/**
 * Warning konfigurasi KasBank ("rekening belum punya baseline") — aturan baru:
 *
 * 1. Baseline yang ADA tapi berstatus BELUM_VERIFIKASI TIDAK dianggap belum
 *    lengkap: modul KasBank memakai baseline + alokasi tanpa workflow
 *    verifikasi, yang penting barisnya ADA pada tanggal cut-off.
 * 2. Baseline bank harus benar-benar ada PERSIS pada kasBankCutoffDate().
 *    Baris statement lama (mis. 2026-10-05) bukan pengganti baseline —
 *    rekening seperti itu tetap dilaporkan.
 * 3. Kewajiban opening KAS hanya untuk laci unit OPERASIONAL
 *    (unit.is_operational = 1). Head Office (unit.is_operational = 0) dan
 *    rekening Finance/HO (akun.is_finance_ho = 1) tidak diwajibkan.
 *    Penanda operasional TIDAK membebaskan rekening bank: bank tetap
 *    diperiksa sesuai aturan rekeningnya (is_finance_ho, kepemilikan, dsb.).
 *
 * Test ini DROP/CREATE tabel sendiri, jadi hanya boleh jalan terhadap
 * `erp_finance_test` (prefix DBPrefix = '', lihat phpunit.erp_finance.xml).
 * Dijalankan via: vendor/bin/phpunit -c phpunit.erp_finance.xml tests/KasBankKonfigurasiTest.php
 */
class KasBankKonfigurasiTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        if ($this->db->getDatabase() !== 'erp_finance_test') {
            $this->fail('Test hanya boleh jalan pada database erp_finance_test, bukan: '
                . $this->db->getDatabase());
        }

        $this->schema();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->q('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'opening_kas', 'saldo_awal_kas_bank', 'alokasi_saldo_kas_bank',
            'akun_kas_bank', 'bank', 'unit',
        ] as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }
        $this->q('SET FOREIGN_KEY_CHECKS = 1');

        parent::tearDown();
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

        foreach (['unit', 'bank', 'akun_kas_bank', 'saldo_awal_kas_bank', 'alokasi_saldo_kas_bank', 'opening_kas'] as $t) {
            $this->q('DROP TABLE IF EXISTS ' . $t);
        }

        // unit.is_operational mengikuti migration AddIsOperationalToUnit.
        $this->q('CREATE TABLE unit (
                idunit INTEGER PRIMARY KEY AUTO_INCREMENT,
                NAMA_UNIT TEXT NULL,
                kode_unit TEXT NULL,
                is_operational TINYINT(1) NOT NULL DEFAULT 1)');
        $this->q('CREATE TABLE bank (
                idbank VARCHAR(20) PRIMARY KEY, jenis_bank TEXT NULL,
                nama_bank TEXT NULL, norek TEXT NULL, atas_nama TEXT NULL)');
        $this->q('CREATE TABLE akun_kas_bank (
                idakun_kas_bank INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL, tipe TEXT NULL, nama_akun TEXT NULL,
                bank_idbank TEXT NULL, no_akun_coa TEXT NULL, status TEXT NULL,
                is_shared TINYINT(1) DEFAULT 0, is_finance_ho TINYINT(1) DEFAULT 0)');
        $this->q('CREATE TABLE saldo_awal_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, tanggal TEXT NULL, saldo REAL NULL,
                keterangan TEXT NULL, status TEXT DEFAULT \'BELUM_VERIFIKASI\',
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE alokasi_saldo_kas_bank (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, nominal REAL NULL,
                keterangan TEXT NULL, input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE TABLE opening_kas (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                akun_kas_bank_id INT NULL, unit_id INT NULL, tanggal TEXT NULL,
                opening BIGINT DEFAULT 0 NULL, keterangan VARCHAR(255) NULL,
                input_by INT NULL, created_at TEXT NULL, updated_at TEXT NULL)');
        $this->q('CREATE UNIQUE INDEX uniq_opening_kas_akun_tanggal
                ON opening_kas (akun_kas_bank_id, tanggal)');

        $this->q('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function seed(): void
    {
        $cutoff = FinanceScopeService::kasBankCutoffDate();

        // Unit 1 & 2 operasional; unit 50 = Head Office non-operasional.
        $this->q("INSERT INTO unit (idunit, NAMA_UNIT, kode_unit, is_operational)
                  VALUES (1,'Probolinggo','PRO',1), (2,'Jember','JBR',1), (50,'Head Office','HO',0)");

        // Tiap unit operasional punya KAS aktif agar cek "unit tanpa KAS"
        // tidak ikut nyala. Akun 3 = Kas Head Office (unit 50): TANPA opening.
        // Akun 7 = rekening BANK milik unit non-operasional (bukan Finance/HO):
        // tetap harus diperiksa — penanda operasional tidak membebaskan bank.
        $this->q("INSERT INTO akun_kas_bank (idakun_kas_bank, unit_id, tipe, nama_akun, no_akun_coa, status, is_shared, is_finance_ho)
                  VALUES
                  (1, 1, 'KAS',  'Kas ICLEAR Probolinggo', 'A', 'aktif', 0, 0),
                  (2, 2, 'KAS',  'Kas ICLEAR Jember',      'A', 'aktif', 0, 0),
                  (3, 50,'KAS',  'Kas Head Office',        'A', 'aktif', 0, 0),
                  (4, 1, 'BANK', 'Bank BCA Perorangan',   'A', 'aktif', 0, 0),
                  (5, 2, 'BANK', 'Bank BNI Genteng',      'A', 'aktif', 0, 0),
                  (6, NULL,'BANK','Bank BCA Finance HO',  'A', 'aktif', 1, 1),
                  (7, 50,'BANK', 'Bank Milik HO',          'A', 'aktif', 0, 0)");

        // Opening KAS: akun 1 punya baseline PERSIS cut-off; akun 2 & HO tidak.
        $this->q("INSERT INTO opening_kas (akun_kas_bank_id, unit_id, tanggal, opening, keterangan)
                  VALUES (1, 1, '$cutoff', 1500000, 'Baseline Probolinggo')");

        // Statement bank:
        //   - akun 4: baseline PERSIS cut-off tapi BELUM_VERIFIKASI (bukan blocker).
        //   - akun 5: hanya baris lama 2026-10-05 -> baseline cut-off BELUM ADA
        //     (blocker, fallback tertagih adalah 2026-10-05).
        //   - akun 6 & 7: tanpa baseline sama sekali.
        $this->q("INSERT INTO saldo_awal_kas_bank (akun_kas_bank_id, tanggal, saldo, status, keterangan)
                  VALUES
                  (4, '$cutoff', 25000000, 'BELUM_VERIFIKASI', 'statement BCA'),
                  (5, '2026-10-05', 5000000, 'SUDAH', 'statement BNI lama')");

        // Akun 6 shared -> wajib punya alokasi supaya cek "shared tanpa
        // alokasi" tidak ikut nyala.
        $this->q("INSERT INTO alokasi_saldo_kas_bank (akun_kas_bank_id, unit_id, nominal, keterangan)
                  VALUES (6, 1, 20000000, 'hak unit BCA HO')");
    }

    /** @return array<int, array{level:string, judul:string, detail:array<int,string>, aksi:string}> */
    private function diagnostik(): array
    {
        $method = new \ReflectionMethod(KasBank::class, 'diagnostikKonfigurasi');
        $method->setAccessible(true);

        return $method->invoke(new KasBank());
    }

    /** Ambil warning baseline dari output diagnostik, [] kalau tidak ada. */
    private function baselineWarning(array $out): array
    {
        foreach ($out as $item) {
            if (($item['level'] ?? '') === 'warning'
                && strpos((string) $item['judul'], 'belum punya baseline') !== false) {
                return $item;
            }
        }

        return [];
    }

    /**
     * Laci kas unit OPERASIONAL tanpa opening KAS wajib dilaporkan; laci yang
     * sudah punya opening pada cut-off tidak.
     */
    public function testKasUnitOperasionalWajibPunyaOpening(): void
    {
        $cutoff = FinanceScopeService::kasBankCutoffDate();

        $out  = $this->diagnostik();
        $warn = $this->baselineWarning($out);

        $this->assertNotEmpty($warn, 'ada rekening yang benar-benar belum punya baseline, warning harus muncul');

        $detail = implode("\n", $warn['detail']);
        $this->assertStringContainsString(
            'Kas ICLEAR Jember (#2) — belum ada opening KAS pada ' . $cutoff,
            $detail,
            'KAS unit operasional tanpa opening tetap dilaporkan'
        );
        $this->assertStringNotContainsString('Kas ICLEAR Probolinggo', $detail, 'KAS dengan opening cut-off tidak dilaporkan');
    }

    /**
     * Kas Head Office (unit non-operasional, is_operational = 0) tidak
     * diwajibkan punya opening KAS.
     */
    public function testKasHeadOfficeNonOperasionalTidakWajibOpening(): void
    {
        $out  = $this->diagnostik();
        $warn = $this->baselineWarning($out);

        $this->assertNotEmpty($warn);

        $detail = implode("\n", $warn['detail']);
        $this->assertStringNotContainsString('Kas Head Office', $detail, 'Kas Head Office tidak boleh diwajibkan punya opening');
        $this->assertStringNotContainsString('(#3)', $detail);
    }

    /**
     * Rekening bank tetap diperiksa sesuai aturan rekeningnya, tidak peduli
     * status operasional unit pemiliknya:
     *   - bank milik unit non-operasional (akun 7) tetap wajib baseline;
     *   - rekening Finance/HO (is_finance_ho = 1, akun 6) tidak wajib;
     *   - baseline bank BELUM_VERIFIKASI (akun 4) bukan blocker;
     *   - bank dengan hanya baris lama (akun 5) tetap dilaporkan disertai
     *     tanggal fallback-nya.
     */
    public function testRekeningBankTetapDiperiksaSesuaiAturanRekening(): void
    {
        $cutoff = FinanceScopeService::kasBankCutoffDate();

        $out  = $this->diagnostik();
        $warn = $this->baselineWarning($out);

        $this->assertNotEmpty($warn);

        $detail = implode("\n", $warn['detail']);
        $this->assertStringContainsString('Bank Milik HO (#7)', $detail, 'bank milik unit non-operasional tetap harus punya baseline');
        $this->assertStringContainsString('belum ada baris statement pada ' . $cutoff, $detail);

        $this->assertStringNotContainsString('Bank BCA Finance HO', $detail, 'rekening Finance/HO (is_finance_ho = 1) tidak wajib');
        $this->assertStringNotContainsString('Bank BCA Perorangan', $detail, 'baseline BELUM_VERIFIKASI tidak dianggap belum lengkap');
    }

    /** Jumlah dan judul warning baseline hanya berisi blocker yang sebenarnya. */
    public function testDiagnostikWarningJumlahDanJudul(): void
    {
        $cutoff = FinanceScopeService::kasBankCutoffDate();

        $out  = $this->diagnostik();
        $warn = $this->baselineWarning($out);

        $this->assertSame(
            '3 rekening belum punya baseline untuk ' . $cutoff,
            $warn['judul']
        );
        $this->assertCount(3, $warn['detail']);
    }

    /**
     * Level service: baseline hanya dianggap ADA kalau barisnya PERSIS pada
     * tanggal cut-off, dan tiap baris membawa penanda operasional unit pemilik.
     */
    public function testServiceMenandaiBaselineDanUnitOperasional(): void
    {
        $service = new KasBankCutoffService();
        $rows    = $service->diagnostikBaseline();
        $byAkun  = [];

        foreach ($rows as $r) {
            $byAkun[(int) $r['akun_id']] = $r;
        }

        // Laci operasional unit 1 & 2, + kas HO unit 50.
        $this->assertTrue($byAkun[1]['unit_is_operational']);
        $this->assertTrue($byAkun[2]['unit_is_operational']);
        $this->assertFalse($byAkun[3]['unit_is_operational'], 'unit 50 = Head Office non-operasional');

        // Akun 7 (bank milik unit non-operasional) ikut membawa penandanya,
        // tapi itu tidak membebaskan bank — kebijakan filter ada di controller.
        $this->assertFalse($byAkun[7]['unit_is_operational']);
        $this->assertFalse($byAkun[7]['is_finance_ho']);

        // Akun 6 (Finance/HO) unit NULL -> dianggap operasional (penanda unit
        // hanya bicara soal laci kas unit), dikecualikan via is_finance_ho.
        $this->assertTrue($byAkun[6]['unit_is_operational']);
        $this->assertTrue($byAkun[6]['is_finance_ho']);

        // Baseline hanya PERSIS tanggal cut-off. BELUM_VERIFIKASI tetap
        // baseline-ada (akun 4); baris lama bukan baseline (akun 5).
        $this->assertTrue($byAkun[1]['baseline_ada']);
        $this->assertTrue($byAkun[4]['baseline_ada']);
        $this->assertFalse($byAkun[4]['baseline_terverifikasi']);
        $this->assertFalse($byAkun[5]['baseline_ada']);
        $this->assertSame('2026-10-05', $byAkun[5]['tertagih']);
        $this->assertNull($byAkun[7]['tertagih']);
        $this->assertFalse($byAkun[2]['baseline_ada']);
        $this->assertFalse($byAkun[3]['baseline_ada']);
        $this->assertFalse($byAkun[6]['baseline_ada']);
    }
}