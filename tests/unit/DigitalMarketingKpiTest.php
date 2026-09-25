<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\Marketing\DigitalMarketingKpiService;
use App\Services\Konten\MultimediaKpiService;

/**
 * Unit test rumus KPI Digital Marketing (jabatan 43) — murni (tanpa DB).
 *
 * Rumus di DigitalMarketingKpiService dipisah ke static pure method agar bisa
 * diuji lintas environment SQL. Berikut verifikasi boundary requirement:
 *   - Omzet Global: threshold Target Toko (floor) → Target HO (100%).
 *   - Leads & Kualitas: AVERAGE(achievement jumlah leads, ratio qualified),
 *     cap 100, data absent = 0 (bukan 100). Biasanya lead dicatat 0.
 *   - Conversion: target % di kpi_targets (default 30), lead 0 → null.
 *   - CPL: achievement target/actual cap 100 (lebih rendah lebih baik),
 *     average Datang & Closing; tanpa dividen ke nol → null.
 *   - Campaign Performance = ads ber-campaign valid / total ads; Reporting =
 *     campaign Selesai (done) / total campaign. Ratio cap 100; total 0 → null.
 *   - Bobot: total 100.
 *   - Improvement: mekanisme Multimedia (di-reuse, bukan formula sendiri).
 */
final class DigitalMarketingKpiTest extends CIUnitTestCase
{
    // ── Omzet Global ───────────────────────────────────────────────

    public function testOmzetGlobalBelowTokoTargetIsZero(): void
    {
        $this->assertSame(0.0, DigitalMarketingKpiService::scoreOmzetGlobal(90_000_000, 100_000_000, 200_000_000));
        $this->assertSame(0.0, DigitalMarketingKpiService::scoreOmzetGlobal(0, 100_000_000, 200_000_000));
    }

    public function testOmzetGlobalAtOrAboveTargetHoIsHundred(): void
    {
        $this->assertSame(100.0, DigitalMarketingKpiService::scoreOmzetGlobal(200_000_000, 100_000_000, 200_000_000));
        $this->assertSame(100.0, DigitalMarketingKpiService::scoreOmzetGlobal(250_000_000, 100_000_000, 200_000_000));
    }

    public function testOmzetGlobalLinearBetweenTokoAndTargetHo(): void
    {
        // 150jt dari range 100–200jt → 50%.
        $this->assertSame(50.0, DigitalMarketingKpiService::scoreOmzetGlobal(150_000_000, 100_000_000, 200_000_000));
        // 150jt dari range 120–160jt → 75%.
        $this->assertSame(75.0, DigitalMarketingKpiService::scoreOmzetGlobal(150_000_000, 120_000_000, 160_000_000));
    }

    public function testOmzetGlobalInvalidTargetReturnsNull(): void
    {
        $this->assertNull(DigitalMarketingKpiService::scoreOmzetGlobal(100_000_000, 10_000_000, 0.0));
    }

    // ── Leads & Kualitas Leads ─────────────────────────────────────

    public function testLeadsAbsentIsZeroNotHundred(): void
    {
        $this->assertSame(0.0, DigitalMarketingKpiService::leadsScore(0, 0, 3000));
    }

    public function testLeadsFullTargetWithAllQualifiedIsHundred(): void
    {
        $this->assertSame(100.0, DigitalMarketingKpiService::leadsScore(3000, 3000, 3000));
    }

    public function testLeadsUnmetTargetYetFullQuality(): void
    {
        // Volume 2000/3000 → 66.67; kualitas 100% → avg 83.33.
        $this->assertSame(83.3333, DigitalMarketingKpiService::leadsScore(2000, 2000, 3000));
    }

    public function testLeadsQualityZeroWhenNoLeadsQualified(): void
    {
        $this->assertSame(25.0, DigitalMarketingKpiService::leadsScore(1500, 0, 3000));
    }

    public function testLeadsOverTargetAndQualityCappedAtHundred(): void
    {
        // Volume 4500/3000 → 100 (cap); kualitas 3000/4500 → 66.67 → avg 83.33.
        $this->assertSame(83.3333, DigitalMarketingKpiService::leadsScore(4500, 3000, 3000));
        // Volume & kualitas penuh → 100.
        $this->assertSame(100.0, DigitalMarketingKpiService::leadsScore(4500, 4500, 3000));
    }

    // ── Conversion ─────────────────────────────────────────────────

    public function testConversionNoLeadIsNull(): void
    {
        $this->assertNull(DigitalMarketingKpiService::conversionScore(0, 5, 30));
    }

    public function testConversionFormula(): void
    {
        // 15 closing dari 100 lead = 15% vs target 30% → 50%.
        $this->assertSame(50.0, DigitalMarketingKpiService::conversionScore(100, 15, 30));
        // 45/150 = 30% → 100%.
        $this->assertSame(100.0, DigitalMarketingKpiService::conversionScore(150, 45, 30));
        // di atas target → cap 100.
        $this->assertSame(100.0, DigitalMarketingKpiService::conversionScore(100, 40, 30));
        // 0 closing → 0 (data lead ada).
        $this->assertSame(0.0, DigitalMarketingKpiService::conversionScore(100, 0, 30));
    }

    // ── CPL ────────────────────────────────────────────────────────

    public function testCplNoBudgetOrDivisionsIsNull(): void
    {
        $this->assertNull(DigitalMarketingKpiService::cplScore(null, null, 250000));
    }

    public function testCplFixture(): void
    {
        // Budget 500.000.000 / 2.000 datang = 250.000 → ideal 100%.
        $this->assertSame(100.0, DigitalMarketingKpiService::cplScore(250_000, null, 250_000));
        $this->assertSame(100.0, DigitalMarketingKpiService::cplScore(null, 250_000, 250_000));
    }

    public function testCplLowerIsBetter(): void
    {
        // 200.000 (lebih murah dari 250.000) → 125% cap 100.
        $this->assertSame(100.0, DigitalMarketingKpiService::cplScore(200_000, null, 250_000));
        // 500.000 (lebih mahal) → 50%.
        $this->assertSame(50.0, DigitalMarketingKpiService::cplScore(500_000, null, 250_000));
    }

    public function testCplAveragesDatangAndClosingAchievements(): void
    {
        // datang 100%, closing 50% → rata-rata 75%.
        $this->assertSame(75.0, DigitalMarketingKpiService::cplScore(250_000, 500_000, 250_000));
    }

    // ── Campaign Performance ───────────────────────────────────────

    public function testCampaignPerformanceNoAdsIsNull(): void
    {
        $this->assertNull(DigitalMarketingKpiService::campaignPerformanceScore(0, 0));
    }

    public function testCampaignPerformanceRatioCapped(): void
    {
        $this->assertSame(80.0, DigitalMarketingKpiService::campaignPerformanceScore(10, 8));
        $this->assertSame(100.0, DigitalMarketingKpiService::campaignPerformanceScore(10, 10));
        // Total ads 0 → null (guard), div-by-zero dicegah.
        $this->assertNull(DigitalMarketingKpiService::campaignPerformanceScore(0, 1));
    }

    // ── Reporting ──────────────────────────────────────────────────

    public function testReportingNoCampaignIsNull(): void
    {
        $this->assertNull(DigitalMarketingKpiService::reportingScore(0, 0));
    }

    public function testReportingDoneVsTotalCampaign(): void
    {
        $this->assertSame(100.0, DigitalMarketingKpiService::reportingScore(3, 3));
        $this->assertSame(66.6667, DigitalMarketingKpiService::reportingScore(3, 2));
        $this->assertSame(0.0, DigitalMarketingKpiService::reportingScore(3, 0));
    }

    // ── Bobot & struktur ───────────────────────────────────────────

    public function testWeightsSumExactlyHundred(): void
    {
        $this->assertSame(100.0, (float)array_sum(DigitalMarketingKpiService::BOBOT));
        $this->assertSame(7, count(DigitalMarketingKpiService::CODES));
        $this->assertCount(count(DigitalMarketingKpiService::BOBOT), DigitalMarketingKpiService::CODES);
    }

    public function testOmzetGlobalWeightIsHalf(): void
    {
        $this->assertEquals(50.0, DigitalMarketingKpiService::BOBOT['OMZET_GLOBAL']);
    }

    public function testImprovementReusesMultimediaMechanism(): void
    {
        // Improvement jabatan 43 = reuse mechanism Multimedia (bukan duplikasi rumus).
        $this->assertTrue(method_exists(MultimediaKpiService::class, 'improvementResult'));
        $this->assertSame(1, MultimediaKpiService::TARGET_IMPROVEMENT);
    }

    // ── Sanity angka requirement user ──────────────────────────────

    public function testRequirementFixtureLeadsTargetThreeThousandAndCplTwoHundredFiftyThousand(): void
    {
        // Dalam requirement: target leads 3.000, CPL 250.000 — untuk conversion 30%.
        // Diverifikasi lewat example calls (nilai dipakai default di service).
        // 2500 lead dari target 3000 + kualitas 100% → (83.33 + 100)/2 = 91.67.
        $this->assertSame(91.6667, DigitalMarketingKpiService::leadsScore(2500, 2500, 3000));
        $this->assertSame(100.0, DigitalMarketingKpiService::cplScore(250_000, 250_000, 250_000));
        $this->assertSame(100.0, DigitalMarketingKpiService::conversionScore(100, 30, 30));
    }
}