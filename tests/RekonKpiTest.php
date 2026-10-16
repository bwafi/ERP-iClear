<?php

namespace Tests;

use App\Services\Finance\RekonDailyCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * KPI Rekonsiliasi — sisi DATABASE.
 *
 * Keputusan bisnis 2026-10-16:
 *   rekon = (hari LENGKAP / jumlah hari dalam bulan) × 100
 *
 * - Pembilang = hari yang ketiga actual_* terisi; status approval (draft /
 *   submitted / verified) TIDAK lagi disyaratkan — ini mengukur kelengkapan,
 *   bukan kebenaran/approval (itu KPI Akurasi).
 * - Pembagi = SELURUH hari dalam bulan (tidak dipotong di hari ini), jadi
 *   setiap hari yang tidak direkonsiliasi menurunkan skor.
 *
 * @internal
 */
final class RekonKpiTest extends CIUnitTestCase
{
    protected $db;

    private int $bulan;
    private int $tahun;
    private string $base;
    private int $hariBulan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        // Bulan lalu: seluruh tanggal pasti sudah lewat.
        $mulai        = date('Y-m-01', strtotime('-1 month'));
        $this->bulan  = (int) date('n', strtotime($mulai));
        $this->tahun  = (int) date('Y', strtotime($mulai));
        $this->base   = $mulai;
        $this->hariBulan = (int) date('t', strtotime($mulai));

        $this->schema();
    }

    protected function tearDown(): void
    {
        $this->q('DROP TABLE IF EXISTS db_finance_rekon_daily');
        parent::tearDown();
    }

    private function q(string $sql): void
    {
        $this->db->query($sql);
    }

    private function schema(): void
    {
        $this->q('DROP TABLE IF EXISTS db_finance_rekon_daily');
        $this->q('CREATE TABLE db_finance_rekon_daily (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                unit_id INT NULL,
                tanggal TEXT NULL,
                actual_cash_masuk REAL NULL,
                actual_transfer_masuk REAL NULL,
                actual_kas_keluar REAL NULL,
                selisih_cash_masuk REAL NULL,
                selisih_transfer_masuk REAL NULL,
                selisih_kas_keluar REAL NULL,
                status_proses TEXT NULL)');
    }

    /** Tanggal ke-$n (0-based) dalam bulan evaluasi. */
    private function tgl(int $n): string
    {
        return date('Y-m-d', strtotime($this->base . " +{$n} day"));
    }

    /**
     * Sisipkan satu baris rekonsiliasi lengkap.
     *
     * $lengkap = false -> actual_kas_keluar NULL (belum lengkap).
     */
    private function seed(int $unitId, string $tanggal, string $status = 'verified', bool $lengkap = true, int $selisih = 0): void
    {
        $kasKeluar = $lengkap ? 500000 : null;
        $selisihKas = $lengkap ? 0 : null;

        $this->q("INSERT INTO db_finance_rekon_daily
            (unit_id, tanggal, actual_cash_masuk, actual_transfer_masuk, actual_kas_keluar,
             selisih_cash_masuk, selisih_transfer_masuk, selisih_kas_keluar, status_proses)
            VALUES ({$unitId}, '{$tanggal}', 700000, 300000, {$this->sqlNull($kasKeluar)}, 0, {$selisih}, {$this->sqlNull($selisihKas)}, '{$status}')");
    }

    private function sqlNull($value): string
    {
        return $value === null ? 'NULL' : (string) $value;
    }

    private function calc(): RekonDailyCalculator
    {
        return new RekonDailyCalculator();
    }

    // ── Pembilang: kelengkapan, tanpa syarat verified ──────────────

    public function testPembilangMenghitungLengkapTanpaSyaratVerified(): void
    {
        $this->seed(1, $this->tgl(0), 'verified');

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame($this->hariBulan, $hasil['detail']['hari_bulan']);
        $this->assertSame(1, $hasil['detail']['hari_lengkap']);
        $this->assertSame(1, $hasil['detail']['hari_lengkap_verified']);
        $this->assertSame(round((1 / $this->hariBulan) * 100, 2), $hasil['score']);
    }

    public function testDraftDanSubmittedIkutDihitung(): void
    {
        $this->seed(1, $this->tgl(0), 'draft');
        $this->seed(1, $this->tgl(1), 'submitted');
        $this->seed(1, $this->tgl(2), 'verified');

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(3, $hasil['detail']['hari_lengkap']);
        $this->assertSame(1, $hasil['detail']['hari_lengkap_verified']);
        $this->assertSame(round((3 / $this->hariBulan) * 100, 2), $hasil['score']);
    }

    // ── Pembagi: seluruh hari dalam bulan ─────────────────────────

    public function testHariKosongMenurunkanSkor(): void
    {
        // Semua hari lengkap KECUALI satu -> skor di bawah 100.
        for ($i = 1; $i < $this->hariBulan; $i++) {
            $this->seed(1, $this->tgl($i), 'verified');
        }

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $expected = round((($this->hariBulan - 1) / $this->hariBulan) * 100, 2);
        $this->assertSame($this->hariBulan, $hasil['detail']['hari_bulan']);
        $this->assertSame($this->hariBulan - 1, $hasil['detail']['hari_lengkap']);
        $this->assertSame($expected, $hasil['score']);
        $this->assertLessThan(100.0, $hasil['score']);
    }

    public function testSemuaHariLengkapSeratus(): void
    {
        for ($i = 0; $i < $this->hariBulan; $i++) {
            $this->seed(1, $this->tgl($i), 'verified');
        }

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(100.0, $hasil['score']);
    }

    public function testTidakAdaRekonsiliasiNol(): void
    {
        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(0.0, $hasil['score']);
    }

    // ── Selisih tidak menurunkan skor ─────────────────────────────

    public function testSelisihTidakMenurunkanSkor(): void
    {
        for ($i = 0; $i < $this->hariBulan; $i++) {
            $this->seed(1, $this->tgl($i), 'verified', true, 25000);
        }

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(100.0, $hasil['score']);
    }

    // ── Hari belum lengkap tidak dihitung ─────────────────────────

    public function testHariBelumLengkapTidakDihitung(): void
    {
        $this->seed(1, $this->tgl(0), 'verified', false);

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(0, $hasil['detail']['hari_lengkap']);
        $this->assertSame(0.0, $hasil['score']);
    }

    // ── Isolasi unit & rentang tanggal ────────────────────────────

    public function testBarisUnitLainDanDiLuarBulanDiabaikan(): void
    {
        $this->seed(1, $this->tgl(0), 'verified');
        $this->seed(2, $this->tgl(1), 'verified');                              // unit lain
        $this->seed(1, date('Y-m-d', strtotime($this->base . ' -1 day')));      // bulan sebelumnya
        $this->seed(1, date('Y-m-d', strtotime($this->base . ' +2 month')));    // bulan berikutnya

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(1, $hasil['detail']['hari_lengkap']);
        $this->assertSame(round((1 / $this->hariBulan) * 100, 2), $hasil['score']);
    }
}
