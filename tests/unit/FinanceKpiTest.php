<?php

use App\Services\Finance\FinanceKpiService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Finance;

/**
 * Unit test KPI Finance (jabatan 0) — murni (tanpa DB).
 *
 * Finance di HO mengelola semua cabang, sehingga achievement tiap KPI =
 * RATA-RATA skor antar-cabang dari scorecard Dashboard Finance. Bobot harus
 * persis sama dengan Config\Finance::$kpiWeights (keputusan bisnis).
 *
 * @internal
 */
final class FinanceKpiTest extends CIUnitTestCase
{
    // ── Pemetaan kode komponen → kode config ───────────────────────

    public function testConfigCodeMembuangPrefixFin(): void
    {
        $this->assertSame('kesehatan_uang', FinanceKpiService::configCode('FIN_KESEHATAN_UANG'));
        $this->assertSame('hutang_piutang', FinanceKpiService::configCode('FIN_HUTANG_PIUTANG'));
        $this->assertSame('cash_flow',      FinanceKpiService::configCode('FIN_CASH_FLOW'));
        $this->assertSame('akurasi',        FinanceKpiService::configCode('FIN_AKURASI'));
    }

    public function testSemuaKodeKomponenPunyaBobotDiConfigFinance(): void
    {
        $weights = (new Finance())->kpiWeights;

        foreach (FinanceKpiService::CODES as $code) {
            $configCode = FinanceKpiService::configCode($code);
            $this->assertArrayHasKey(
                $configCode,
                $weights,
                "Kode {$code} tidak punya padanan di Config\\Finance::\$kpiWeights."
            );
        }
    }

    public function testBobotConfigFinanceTotalSeratus(): void
    {
        $this->assertSame(100, (int) array_sum((new Finance())->kpiWeights));
    }

    public function testImprovementDihapusDanBobotnyaKePayroll(): void
    {
        $finance = new Finance();

        // KPI "Improvement Finance" dihapus 2026-10-16; bobotnya (5) pindah ke
        // Payroll (5 -> 10).
        $this->assertArrayNotHasKey('improvement', $finance->kpiWeights);
        $this->assertArrayNotHasKey('improvement', $finance->kpiLabels);
        $this->assertNotContains('improvement', $finance->manualKpiCodes);
        $this->assertNotContains('FIN_IMPROVEMENT', FinanceKpiService::CODES);

        $this->assertSame(10, $finance->kpiWeights['payroll']);
    }

    // ── Agregasi rata-rata antar-cabang ────────────────────────────

    public function testAverageMenghitungRataRataLintasCabang(): void
    {
        $rowsByUnit = [
            1 => ['cash_flow' => ['score' => 100.0]],
            2 => ['cash_flow' => ['score' => 80.0]],
            3 => ['cash_flow' => ['score' => 60.0]],
        ];

        // (100 + 80 + 60) / 3 = 80.
        $this->assertSame(80.0, FinanceKpiService::averageScore($rowsByUnit, 'cash_flow'));
    }

    public function testAverageMengabaikanSkorNull(): void
    {
        $rowsByUnit = [
            1 => ['akurasi' => ['score' => 90.0]],
            2 => ['akurasi' => ['score' => null]],
            3 => ['akurasi' => ['score' => 70.0]],
        ];

        // null (belum ada data) tidak dianggap 0: (90 + 70) / 2 = 80.
        $this->assertSame(80.0, FinanceKpiService::averageScore($rowsByUnit, 'akurasi'));
    }

    public function testAverageSemuaNullMengembalikanNull(): void
    {
        $rowsByUnit = [
            1 => ['payroll' => ['score' => null]],
            2 => [],
        ];

        $this->assertNull(
            FinanceKpiService::averageScore($rowsByUnit, 'payroll'),
            'Tanpa data di semua cabang → null (dikecualikan dari total, bukan 0).'
        );
    }

    public function testAverageNolNyataTetapNol(): void
    {
        // Skor 0 yang benar-benar ada (mis. rekonsiliasi belum diisi) harus
        // ikut dirata-rata sebagai 0, bukan dianggap "tanpa data".
        $rowsByUnit = [
            1 => ['rekonsiliasi' => ['score' => 0.0]],
            2 => ['rekonsiliasi' => ['score' => 100.0]],
        ];

        $this->assertSame(50.0, FinanceKpiService::averageScore($rowsByUnit, 'rekonsiliasi'));
    }
}
