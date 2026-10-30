<?php

namespace Tests\unit;

use App\Models\ModelAkunKasBank;
use App\Services\Finance\KasBankCutoffService;
use App\Services\Finance\KesehatanUangCalculator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * KPI Kesehatan Uang per Cabang — bagian MURNI (tanpa database).
 *
 * Memastikan formula inti tidak bergeser (keputusan bisnis 2026-10-30:
 * SALDO kas + bank, bukan uang diterima):
 *
 *     rasio        = saldo kas + bank / biaya bulan itu x 100
 *     skor kategori= min((rasio / 120) x 100, 100)
 *     kontribusi   = skor kategori x 30 / 100
 *
 * saldo       = posisi unit di rekening KAS + BANK
 * biaya       = kas keluar operasional + payroll (HPP tidak dihitung)
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
        return $this->service()->assemble([
            'unit_id'              => 1,
            'periode_mulai'        => '2026-10-01',
            'periode_akhir'        => '2026-10-31',
            'tanggal_saldo'        => '2026-10-31',
            'saldo_tersedia'       => $override['saldo_tersedia'] ?? true,
            'saldo'                => ['total' => (int) ($override['saldo'] ?? 0)],
            'kas_keluar'           => $override['kas_keluar'] ?? 0,
            'payroll'              => $override['payroll'] ?? 0,
            'kas_keluar_excluded'  => $override['kas_keluar_excluded'] ?? ['hpp' => 0, 'sdm' => 0],
        ]);
    }

    // ── Rasio di bawah target ─────────────────────────────────────

    public function testRasioDiBawahTargetSkorProporsional(): void
    {
        // Biaya 1.000.000; saldo 600.000 -> rasio 60%.
        // skor = 60/120*100 = 50; kontribusi = 50*30/100 = 15.
        $hasil = $this->hitung([
            'saldo'      => 600000,
            'kas_keluar' => 600000,
            'payroll'    => 400000,
        ]);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(60.0, $hasil['detail']['rasio']);
        $this->assertSame(50.0, $hasil['score']);
        $this->assertSame(15.0, $hasil['detail']['kontribusi']);
        $this->assertSame(600000, $hasil['detail']['total_saldo']);
        $this->assertSame(1000000, $hasil['detail']['total_keluar']);
        $this->assertSame(120.0, $hasil['detail']['target_rasio']);
    }

    public function testKontribusiMemakaiBobotTigaPuluhPersen(): void
    {
        // Rasio 36% -> skor 30 -> kontribusi 9.
        $hasil = $this->hitung([
            'saldo'      => 360000,
            'kas_keluar' => 1000000,
        ]);

        $this->assertSame(36.0, $hasil['detail']['rasio']);
        $this->assertSame(30.0, $hasil['score']);
        $this->assertSame(9.0, $hasil['detail']['kontribusi']);
    }

    // ── Tepat 120% ────────────────────────────────────────────────

    public function testRasioTepatSeratusDuaPuluhPersenSkorPenuh(): void
    {
        $hasil = $this->hitung([
            'saldo'      => 1200000,
            'kas_keluar' => 800000,
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
            'saldo'      => 3000000,
            'kas_keluar' => 800000,
            'payroll'    => 200000,
        ]);

        $this->assertSame(300.0, $hasil['detail']['rasio']);
        $this->assertSame(100.0, $hasil['score']);
    }

    // ── Saldo nol / negatif ──────────────────────────────────────

    public function testSaldoNolMemberiSkorNol(): void
    {
        // Tidak ada saldo: rasio 0%, skor 0 (bukan null / bukan lolos).
        $hasil = $this->hitung([
            'kas_keluar' => 1000000,
        ]);

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame(0.0, $hasil['detail']['rasio']);
        $this->assertSame(0.0, $hasil['score']);
        $this->assertSame(0.0, $hasil['detail']['kontribusi']);
    }

    public function testSaldoNegatifMemberiSkorNolBukanNegatif(): void
    {
        $hasil = $this->hitung([
            'saldo'      => -50000,
            'kas_keluar' => 1000000,
        ]);

        $this->assertSame(-5.0, $hasil['detail']['rasio']);
        $this->assertSame(0.0, $hasil['score']);
        $this->assertSame(0.0, $hasil['detail']['kontribusi']);
    }

    // ── Uang keluar nol / negatif ─────────────────────────────────

    public function testSaldoBelumTersediaTidakMemberiSkor(): void
    {
        // Periode sebelum ledger Kas & Bank dimulai: jangan pakai angka
        // statement lama sebagai saldo bulan itu.
        $hasil = $this->hitung([
            'saldo_tersedia' => false,
            'saldo'          => 500000,
            'kas_keluar'     => 1000000,
        ]);

        $this->assertSame('saldo_belum_tersedia', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertNull($hasil['detail']['rasio']);
    }

    public function testUangKeluarNolTidakDibagiNol(): void
    {
        $hasil = $this->hitung([
            'saldo' => 500000,
        ]);

        $this->assertSame('kebutuhan_nol', $hasil['status']);
        $this->assertNull($hasil['score']);
        $this->assertNull($hasil['detail']['rasio']);
    }

    public function testHppTidakDihitungSebagaiBiaya(): void
    {
        // HPP penjualan bukan komponen Kesehatan Uang (keputusan bisnis
        // 2026-10-30): hanya kas keluar operasional + payroll.
        $hasil = $this->hitung([
            'saldo'      => 1000000,
            'hpp'        => 5000000,
            'kas_keluar' => 500000,
            'payroll'    => 500000,
        ]);

        $this->assertSame(1000000, $hasil['detail']['total_keluar']);
        $this->assertSame(100.0, $hasil['detail']['rasio']);
        $this->assertArrayNotHasKey('hpp', $hasil['detail']);
    }

    public function testUangKeluarNegatifTidakDibagiNol(): void
    {
        $hasil = $this->hitung([
            'saldo'      => 500000,
            'kas_keluar' => -100,
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

    // ── Saldo kas + bank per unit ─────────────────────────────────

    public function testSaldoKasBankMenjumlahkanPosisiTunaiDanBank(): void
    {
        $kasBank = $this->createMock(KasBankCutoffService::class);
        $akun    = $this->createMock(ModelAkunKasBank::class);

        $akun->method('getDalamScopeUnit')->with([1])->willReturn([
            (object) ['idakun_kas_bank' => 2, 'tipe' => 'KAS'],
            (object) ['idakun_kas_bank' => 1, 'tipe' => 'BANK'],
        ]);

        $kasBank->method('posisiUnit')->willReturnMap([
            [2, 1, '2026-10-31', 1500000],
            [1, 1, '2026-10-31', 8500000],
        ]);

        $calc = new KesehatanUangCalculator(null, $kasBank, $akun);

        $this->assertSame(
            ['tunai' => 1500000, 'bank' => 8500000, 'total' => 10000000],
            $calc->saldoKasBank(1, '2026-10-31')
        );
    }
}
