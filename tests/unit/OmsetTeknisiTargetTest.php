<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\Kpi\KpiCalculationService;

/**
 * Target OMSET_TEKNISI otomatis = target OMSET_TOKO unit ÷ 2 (mulai 2026-11).
 */
final class OmsetTeknisiTargetTest extends CIUnitTestCase
{
    public function testTargetIsHalfOfStoreTarget(): void
    {
        $this->assertSame(25_000_000.0, KpiCalculationService::teknisiTargetFromToko(50_000_000, 17_500_000));
        $this->assertSame(37_500_000.0, KpiCalculationService::teknisiTargetFromToko(75_000_000, 17_500_000));
    }

    public function testMissingOrInvalidStoreTargetFallsBack(): void
    {
        $this->assertSame(17_500_000.0, KpiCalculationService::teknisiTargetFromToko(null, 17_500_000));
        $this->assertSame(17_500_000.0, KpiCalculationService::teknisiTargetFromToko(0, 17_500_000));
        $this->assertSame(17_500_000.0, KpiCalculationService::teknisiTargetFromToko(-1, 17_500_000));
    }
}
