<?php

namespace Tests\unit;

use App\Services\Finance\AkurasiLaporanCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * KPI Akurasi Laporan Keuangan — bagian MURNI (tanpa database).
 *
 * Formula (keputusan bisnis 2026-10-16, sumber = Rekonsiliasi Harian):
 *
 *     akurasi = (hari terverifikasi TANPA selisih / hari terverifikasi) × 100
 *     kontribusi = akurasi × 20 / 100
 *
 * Konvensi acuan keputusan bisnis: 7 hari cocok dari 8 hari terverifikasi →
 * 87,5 (bukan 70, karena hari yang belum diverifikasi tidak dihitung).
 *
 * @internal
 */
final class AkurasiLaporanCalculatorTest extends CIUnitTestCase
{
    private function service(): AkurasiLaporanCalculator
    {
        return new AkurasiLaporanCalculator();
    }

    /**
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function hitung(array $override = []): array
    {
        return $this->service()->assemble(array_merge([
            'unit_id'       => 1,
            'periode_mulai' => '2026-10-01',
            'periode_akhir' => '2026-10-31',
            'terekon'       => 0,
            'cocok'         => 0,
        ], $override));
    }

    // ── Acuan keputusan bisnis ────────────────────────────────────

    public function testTujuhDariDelapanHariTerverifikasiCocok(): void
    {
        // 8 hari terverifikasi, 1 hari berselisih -> 7/8 = 87,5.
        $hasil = $this->hitung(['terekon' => 8, 'cocok' => 7]);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(87.5, $hasil['score']);
        $this->assertSame(87.5, $hasil['detail']['akurasi']);
        $this->assertSame(8, $hasil['detail']['hari_terekon']);
        $this->assertSame(7, $hasil['detail']['hari_cocok']);
        $this->assertSame(1, $hasil['detail']['hari_selisih']);
        $this->assertSame(17.5, $hasil['detail']['kontribusi']);
    }

    public function testSemuaHariCocokMemberiSeratus(): void
    {
        $hasil = $this->hitung(['terekon' => 12, 'cocok' => 12]);

        $this->assertSame(100.0, $hasil['score']);
        $this->assertSame(0, $hasil['detail']['hari_selisih']);
        $this->assertSame(20.0, $hasil['detail']['kontribusi']);
    }

    public function testSatuHariSelisihDariLimaMenurunkanNilai(): void
    {
        // "Ada 1 hari selisih -> nilai berkurang": 4/5 = 80, bukan 100.
        $hasil = $this->hitung(['terekon' => 5, 'cocok' => 4]);

        $this->assertSame(80.0, $hasil['score']);
        $this->assertSame(1, $hasil['detail']['hari_selisih']);
    }

    public function testSemuaHariSelisihMemberiNol(): void
    {
        // Ada hari terverifikasi, tetapi semuanya berselisih -> 0 (bukan null).
        $hasil = $this->hitung(['terekon' => 5, 'cocok' => 0]);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(0.0, $hasil['score']);
        $this->assertSame(0.0, $hasil['detail']['kontribusi']);
    }

    // ── Belum ada hari terverifikasi ──────────────────────────────

    public function testTanpaHariTerverifikasiSkorNull(): void
    {
        // Tidak ada pembagian dengan nol; KPI belum dapat dinilai.
        $hasil = $this->hitung(['terekon' => 0, 'cocok' => 0]);

        $this->assertSame('belum_verifikasi', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertNull($hasil['detail']['akurasi']);
        $this->assertNull($hasil['detail']['kontribusi']);
    }

    public function testErrorSaatTabelTidakSiap(): void
    {
        $hasil = $this->hitung(['terekon' => 0, 'cocok' => 0, 'error' => 'no such table']);

        $this->assertSame('error', $hasil['status']);
        $this->assertNull($hasil['score']);
    }

    // ── Jaring pengaman ───────────────────────────────────────────

    public function testPembilangTidakBolehMelebihiPembagi(): void
    {
        // Data aneh: cocok > terekon -> di-clamp, tidak melebihi 100.
        $hasil = $this->hitung(['terekon' => 3, 'cocok' => 9]);

        $this->assertSame(100.0, $hasil['score']);
        $this->assertSame(3, $hasil['detail']['hari_cocok']);
        $this->assertSame(0, $hasil['detail']['hari_selisih']);
    }

    public function testNilaiNegatifDianggapNolHari(): void
    {
        $hasil = $this->hitung(['terekon' => -4, 'cocok' => -1]);

        $this->assertSame('belum_verifikasi', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertSame(0, $hasil['detail']['hari_terekon']);
    }
}
