<?php

namespace Tests;

use App\Models\ModelFinanceRekonDaily;
use App\Services\Finance\AkurasiLaporanCalculator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/**
 * KPI Akurasi Laporan Keuangan — sisi DATABASE (sumber Rekonsiliasi Harian).
 *
 * Keputusan bisnis 2026-10-16:
 *   akurasi = (hari terverifikasi TANPA selisih / hari terverifikasi) × 100
 *
 * Fokus pengujian (bagian yang tidak bisa diuji murni):
 *   - hanya hari lengkap + status_proses = verified yang ikut dihitung;
 *   - draft/submitted/need_revision tidak menyeret sekaligus tidak membantu;
 *   - satu hari berselisih menurunkan nilai;
 *   - baris di luar bulan evaluasi diabaikan.
 *
 * @internal
 */
final class AkurasiLaporanCalculatorTest extends CIUnitTestCase
{
    protected $db;

    private int $bulan;
    private int $tahun;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::connect();

        // Pakai BULAN LALU supaya seluruh tanggal pasti sudah lewat (calculator
        // memotong rentang di hari ini, jadi tidak ada risiko tanggal futur).
        $mulai       = date('Y-m-01', strtotime('-1 month'));
        $this->bulan = (int) date('n', strtotime($mulai));
        $this->tahun = (int) date('Y', strtotime($mulai));
        $this->base  = $mulai;

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
     * Sisipkan satu baris rekonsiliasi.
     *
     * $selisih = selisih transfer (0 = semua cocok). $status default verified.
     */
    private function seed(int $unitId, string $tanggal, int $selisih = 0, string $status = 'verified'): void
    {
        $cash = 700000 + $selisih;
        $this->q("INSERT INTO db_finance_rekon_daily
            (unit_id, tanggal, actual_cash_masuk, actual_transfer_masuk, actual_kas_keluar,
             selisih_cash_masuk, selisih_transfer_masuk, selisih_kas_keluar, status_proses)
            VALUES ({$unitId}, '{$tanggal}', {$cash}, 300000, 500000, 0, {$selisih}, 0, '{$status}')");
    }

    private function calc(): AkurasiLaporanCalculator
    {
        return new AkurasiLaporanCalculator();
    }

    // ── Acuan: 7 dari 8 hari terverifikasi cocok ──────────────────

    public function testTujuhDariDelapanHariTerverifikasiCocok(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->seed(1, $this->tgl($i), 0);
        }
        $this->seed(1, $this->tgl(7), 25000); // 1 hari berselisih

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(8, $hasil['detail']['hari_terekon']);
        $this->assertSame(7, $hasil['detail']['hari_cocok']);
        $this->assertSame(1, $hasil['detail']['hari_selisih']);
        $this->assertSame(87.5, $hasil['score']);
    }

    // ── Hari belum terverifikasi tidak dihitung ───────────────────

    public function testHariBelumVerifikasiTidakIkutPembagiMaupunPembilang(): void
    {
        // 3 hari terverifikasi cocok.
        $this->seed(1, $this->tgl(0), 0);
        $this->seed(1, $this->tgl(1), 0);
        $this->seed(1, $this->tgl(2), 0);
        // 2 hari terisi tetapi belum/tanpa verifikasi, satunya bahkan berselisih:
        // TIDAK boleh menyeret nilai.
        $this->seed(1, $this->tgl(3), 0, 'submitted');
        $this->seed(1, $this->tgl(4), 999999, 'draft');
        $this->seed(1, $this->tgl(5), 0, 'need_revision');

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(3, $hasil['detail']['hari_terekon']);
        $this->assertSame(3, $hasil['detail']['hari_cocok']);
        $this->assertSame(100.0, $hasil['score']);
    }

    public function testTanpaHariTerverifikasiSkorNull(): void
    {
        $this->seed(1, $this->tgl(0), 0, 'submitted');
        $this->seed(1, $this->tgl(1), 0, 'draft');

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame('belum_verifikasi', $hasil['status']);
        $this->assertNull($hasil['score']);
    }

    // ── Hari tidak lengkap tidak dihitung ─────────────────────────

    public function testHariBelumLengkapTidakDihitung(): void
    {
        // actual_kas_keluar NULL -> belum lengkap walau status verified.
        $this->q("INSERT INTO db_finance_rekon_daily
            (unit_id, tanggal, actual_cash_masuk, actual_transfer_masuk, actual_kas_keluar,
             selisih_cash_masuk, selisih_transfer_masuk, selisih_kas_keluar, status_proses)
            VALUES (1, '{$this->tgl(0)}', 700000, 300000, NULL, 0, 0, 0, 'verified')");

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame('belum_verifikasi', $hasil['status']);
        $this->assertNull($hasil['score']);
    }

    // ── Isolasi unit & rentang tanggal ────────────────────────────

    public function testBarisUnitLainDanDiLuarBulanDiabaikan(): void
    {
        $this->seed(1, $this->tgl(0), 0);
        $this->seed(2, $this->tgl(1), 0);              // unit lain
        $this->seed(1, date('Y-m-d', strtotime($this->base . ' -1 day')), 0);  // bulan sebelumnya
        $this->seed(1, date('Y-m-d', strtotime($this->base . ' +2 month')), 0); // bulan berikutnya

        $hasil = $this->calc()->calculate(1, $this->bulan, $this->tahun);

        $this->assertSame(1, $hasil['detail']['hari_terekon']);
        $this->assertSame(100.0, $hasil['score']);
    }

    // ── Model: dua hitungan berbeda ───────────────────────────────

    public function testModelMenghitungPembagiDanPembilangTerpisah(): void
    {
        $this->seed(1, $this->tgl(0), 0);
        $this->seed(1, $this->tgl(1), 0);
        $this->seed(1, $this->tgl(2), 15000);

        $model    = new ModelFinanceRekonDaily();
        $dari     = $this->tgl(0);
        $sampai   = $this->tgl(30);

        $this->assertSame(3, $model->countLengkapVerifiedInRange(1, $dari, $sampai));
        $this->assertSame(2, $model->countLengkapVerifiedCocokInRange(1, $dari, $sampai));
    }
}
