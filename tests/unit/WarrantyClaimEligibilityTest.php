<?php

namespace Tests\unit;

use App\Services\Service\WarrantyClaimEligibility;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class WarrantyClaimEligibilityTest extends TestCase
{
    private function service(int $status = 4, int $days = 30, string $completedAt = '2026-10-14 10:00:00'): object
    {
        return (object) [
            'status_service' => $status,
            'garansi_hari' => $days,
            'tanggal_selesai' => $completedAt,
        ];
    }

    private function today(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('Asia/Jakarta'));
    }

    public function testLastWarrantyDayIsEligible(): void
    {
        $this->assertTrue(WarrantyClaimEligibility::allows($this->service(), $this->today('2026-11-13')));
        $this->assertFalse(WarrantyClaimEligibility::allows($this->service(), $this->today('2026-11-14')));
    }

    public function testUnfinishedOrWarrantyFreeServiceCannotClaim(): void
    {
        $this->assertFalse(WarrantyClaimEligibility::allows($this->service(2), $this->today('2026-10-18')));
        $this->assertFalse(WarrantyClaimEligibility::allows($this->service(4, 0), $this->today('2026-10-18')));
    }

    public function testMissingOrInvalidCompletionDateCannotClaim(): void
    {
        $this->assertFalse(WarrantyClaimEligibility::allows($this->service(4, 30, '0000-00-00'), $this->today('2026-10-18')));
        $this->assertFalse(WarrantyClaimEligibility::allows($this->service(4, 30, '2026-02-30'), $this->today('2026-10-18')));
    }
}
