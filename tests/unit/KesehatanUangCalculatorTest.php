<?php

namespace Tests\unit;

use App\Services\Finance\KesehatanUangCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * KPI Kesehatan Uang per Cabang — bagian MURNI (tanpa database).
 *
 * Memastikan formula inti tidak bergeser (keputusan bisnis 2026-10-15:
 * ARUS KAS bulan itu, bukan saldo rekening):
 *
 *     rasio        = uang masuk bulan itu / uang keluar bulan itu x 100
 *     skor kategori= min((rasio / 120) x 100, 100)
 *     kontribusi   = skor kategori x 30 / 100
 *
 * uang masuk  = penjualan + service (tunai + transfer)
 * uang keluar = HPP + kas keluar operasional + payroll
 *
 * Contoh acuan keputusan bisnis: rasio 60% -> skor kategori 50 -> kontribusi
 * 15 poin dari bobot 30%.
 *
 * @internal
 */
final class KesehatanUangCalculatorTest extends CIUnitTestCase
{
    private function service(): KesehatanUangCalculator
    {
        return new KesehatanUangCalculator();
    }

    /**
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function hitung(array $override = []): array
    {
        $masuk = array_merge(['tunai' => 0, 'transfer' => 0], $override['masuk'] ?? []);
        $masuk['total'] = $masuk['tunai'] + $masuk['transfer'];

        return $this->service()->assemble([
            'unit_id'              => 1,
            'periode_mulai'        => '2026-10-01',
            'periode_akhir'        => '2026-10-31',
            'masuk'                => $masuk,
            'hpp'                  => $override['hpp'] ?? 0,
            'kas_keluar'           => $override['kas_keluar'] ?? 0,
            'payroll'              => $override['payroll'] ?? 0,
            'kas_keluar_excluded'  => $override['kas_keluar_excluded'] ?? ['hpp' => 0, 'sdm' => 0],
        ]);
    }

    // ── Rasio di bawah target ─────────────────────────────────────

    public function testRasioDiBawahTargetSkorProporsional(): void
    {
        // Uang keluar 1.000.000; uang masuk 600.000 -> rasio 60%.
        // skor = 60/120*100 = 50; kontribusi = 50*30/100 = 15.
        $hasil = $this->hitung([
            'masuk'      => ['tunai' => 200000, 'transfer' => 400000],
            'hpp'        => 400000,
            'kas_keluar' => 400000,
            'payroll'    => 200000,
        ]);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(60.0, $hasil['detail']['rasio']);
        $this->assertSame(50.0, $hasil['score']);
        $this->assertSame(15.0, $hasil['detail']['kontribusi']);
        $this->assertSame(600000, $hasil['detail']['total_masuk']);
        $this->assertSame(1000000, $hasil['detail']['total_keluar']);
        $this->assertSame(120.0, $hasil['detail']['target_rasio']);
    }

    public function testKontribusiMemakaiBobotTigaPuluhPersen(): void
    {
        // Rasio 36% -> skor 30 -> kontribusi 9.
        $hasil = $this->hitung([
            'masuk' => ['tunai' => 360000],
            'hpp'   => 1000000,
        ]);

        $this->assertSame(36.0, $hasil['detail']['rasio']);
        $this->assertSame(30.0, $hasil['score']);
        $this->assertSame(9.0, $hasil['detail']['kontribusi']);
    }

    // ── Tepat 120% ────────────────────────────────────────────────

    public function testRasioTepatSeratusDuaPuluhPersenSkorPenuh(): void
    {
        $hasil = $this->hitung([
            'masuk'      => ['tunai' => 700000, 'transfer' => 500000],
            'hpp'        => 500000,
            'kas_keluar' => 300000,
            'payroll'    => 200000,
        ]);

        $this->assertSame(120.0, $hasil['detail']['rasio']);
        $this->assertSame(100.0, $hasil['score']);
        $this->assertSame(30.0, $hasil['detail']['kontribusi']);
    }

    // ── Di atas target ────────────────────────────────────────────

    public function testRasioDiAtasTargetTetapMaksimalSeratus(): void
    {
        $hasil = $this->hitung([
            'masuk'      => ['tunai' => 3000000],
            'hpp'        => 500000,
            'kas_keluar' => 300000,
            'payroll'    => 200000,
        ]);

        $this->assertSame(300.0, $hasil['detail']['rasio']);
        $this->assertSame(100.0, $hasil['score']);
    }

    // ── Uang masuk nol / negatif ──────────────────────────────────

    public function testUangMasukNolMemberiSkorNol(): void
    {
        // Tidak ada uang masuk: rasio 0%, skor 0 (bukan null / bukan lolos).
        $hasil = $this->hitung([
            'hpp'     => 1000000,
        ]);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(0.0, $hasil['detail']['rasio']);
        $this->assertSame(0.0, $hasil['score']);
        $this->assertSame(0.0, $hasil['detail']['kontribusi']);
    }

    public function testUangMasukNegatifMemberiSkorNolBukanNegatif(): void
    {
        $hasil = $this->hitung([
            'masuk' => ['tunai' => -50000],
            'hpp'   => 1000000,
        ]);

        $this->assertSame(-5.0, $hasil['detail']['rasio']);
        $this->assertSame(0.0, $hasil['score']);
        $this->assertSame(0.0, $hasil['detail']['kontribusi']);
    }

    // ── Uang keluar nol / negatif ─────────────────────────────────

    public function testUangKeluarNolTidakDibagiNol(): void
    {
        $hasil = $this->hitung([
            'masuk' => ['tunai' => 500000],
        ]);

        $this->assertSame('kebutuhan_nol', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertNull($hasil['detail']['rasio']);
    }

    public function testUangKeluarNegatifTidakDibagiNol(): void
    {
        $hasil = $this->hitung([
            'masuk' => ['tunai' => 500000],
            'hpp'   => -100,
        ]);

        $this->assertSame('kebutuhan_nol', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertNull($hasil['detail']['rasio']);
    }

    // ── Helper statis ─────────────────────────────────────────────

    public function testSkorKategoriStatis(): void
    {
        $this->assertSame(50.0, KesehatanUangCalculator::skorKategori(60.0, 120.0));
        $this->assertSame(100.0, KesehatanUangCalculator::skorKategori(120.0, 120.0));
        $this->assertSame(100.0, KesehatanUangCalculator::skorKategori(240.0, 120.0));
        $this->assertSame(0.0, KesehatanUangCalculator::skorKategori(0.0, 120.0));
        $this->assertSame(0.0, KesehatanUangCalculator::skorKategori(-10.0, 120.0));
        $this->assertNull(KesehatanUangCalculator::skorKategori(null, 120.0));
        $this->assertNull(KesehatanUangCalculator::skorKategori(60.0, 0.0));
    }
}
