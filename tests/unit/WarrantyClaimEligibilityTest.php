<?php

namespace Tests\unit;

use App\Services\Service\WarrantyClaimEligibility;
use App\Services\Service\WarrantyTerm;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class WarrantyClaimEligibilityTest extends TestCase
{
    private function service(int $status = 4, ?string $option = 'bulan_1', string $start = '2026-01-31', ?string $end = null): object
    {
        return (object) [
            'status_service' => $status,
            'garansi_opsi_snapshot' => $option,
            'garansi_mulai' => $start,
            'garansi_akhir' => $end,
            'garansi_hari' => 30,
            'tanggal_selesai' => $start . ' 10:00:00',
        ];
    }

    private function today(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('Asia/Jakarta'));
    }

    public function testCalendarMonthExpiryIsInclusive(): void
    {
        self::assertTrue(WarrantyClaimEligibility::allows($this->service(), $this->today('2026-02-28')));
        self::assertFalse(WarrantyClaimEligibility::allows($this->service(), $this->today('2026-03-01')));
    }

    public function testCalendarYearHandlesLeapDay(): void
    {
        $service = $this->service(4, 'tahun_1', '2024-02-29');
        self::assertTrue(WarrantyClaimEligibility::allows($service, $this->today('2025-02-28')));
        self::assertFalse(WarrantyClaimEligibility::allows($service, $this->today('2025-03-01')));
    }

    public function testEveryConfiguredDurationHasCorrectCalendarExpiry(): void
    {
        $expected = [
            'hari_7' => '2026-02-07',
            'bulan_1' => '2026-02-28',
            'bulan_6' => '2026-07-31',
            'tahun_1' => '2027-01-31',
            'tahun_2' => '2028-01-31',
        ];
        foreach ($expected as $option => $end) {
            $service = $this->service(4, $option, '2026-01-31');
            self::assertTrue(WarrantyClaimEligibility::allows($service, $this->today($end)), $option);
            self::assertFalse(WarrantyClaimEligibility::allows($service, $this->today((new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d'))), $option);
        }
    }

    public function testNonWarrantyUnsetAndInvalidLegacyAreNotClaimable(): void
    {
        foreach (['non_garansi', WarrantyTerm::UNSET, 'old_unknown'] as $option) {
            self::assertFalse(WarrantyClaimEligibility::allows($this->service(4, $option), $this->today('2026-02-01')));
        }
    }

    public function testLifetimeHasNoExpiryButRequiresStartAndCompletedService(): void
    {
        self::assertTrue(WarrantyClaimEligibility::allows($this->service(4, 'selamanya'), $this->today('2035-01-01')));
        self::assertFalse(WarrantyClaimEligibility::allows($this->service(2, 'selamanya'), $this->today('2026-02-01')));
        self::assertFalse(WarrantyClaimEligibility::allows($this->service(4, 'selamanya', ''), $this->today('2026-02-01')));
        self::assertFalse(WarrantyClaimEligibility::allows($this->service(4, 'selamanya', '2026-03-01'), $this->today('2026-02-28')));
    }

    public function testOldDaySnapshotsRemainEligibleWithoutBeingConverted(): void
    {
        $service = (object) ['status_service' => 4, 'garansi_opsi_snapshot' => null, 'garansi_hari' => 30, 'tanggal_selesai' => '2026-01-01'];
        self::assertTrue(WarrantyClaimEligibility::allows($service, $this->today('2026-01-31')));
        self::assertFalse(WarrantyClaimEligibility::allows($service, $this->today('2026-02-01')));
    }
}
