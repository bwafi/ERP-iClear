<?php

namespace Tests\unit;

use App\Services\Service\ServiceHppSnapshot;
use PHPUnit\Framework\TestCase;

final class ServiceHppSnapshotTest extends TestCase
{
    public function testExistingItemKeepsOriginalHpp(): void
    {
        $existingItem = (object) ['hpp_penjualan' => 219120];

        $this->assertSame(219120, ServiceHppSnapshot::resolve($existingItem, 230000));
    }

    public function testExistingZeroOrUnknownHppIsNotReplacedSilently(): void
    {
        $this->assertSame(0, ServiceHppSnapshot::resolve((object) ['hpp_penjualan' => 0], 230000));
        $this->assertNull(ServiceHppSnapshot::resolve((object) ['hpp_penjualan' => null], 230000));
    }

    public function testNewItemUsesCurrentHpp(): void
    {
        $this->assertSame(230000, ServiceHppSnapshot::resolve(null, 230000));
    }

    public function testWarrantyReplacementKeepsItsOwnHistoricalHpp(): void
    {
        $existingItem = (object) ['jumlah_tambahan_garansi' => 1, 'hpp_penjualan_garansi' => 230000];

        $this->assertSame(230000, ServiceHppSnapshot::resolveWarrantyReplacement($existingItem, 1, 250000));
        $this->assertSame(250000, ServiceHppSnapshot::resolveWarrantyReplacement(null, 1, 250000));
        $this->assertSame(0, ServiceHppSnapshot::resolveWarrantyReplacement(null, 0, 250000));
    }

    public function testWarrantyReplacementQuantityChangeIsRejected(): void
    {
        $existingItem = (object) ['jumlah_tambahan_garansi' => 1, 'hpp_penjualan_garansi' => 230000];

        $this->expectException(\InvalidArgumentException::class);
        ServiceHppSnapshot::resolveWarrantyReplacement($existingItem, 2, 250000);
    }

    public function testSecondClaimUsesNewHppWithoutChangingFirstClaim(): void
    {
        $firstClaim = (object) ['jumlah' => 1, 'hpp_satuan' => 230000];

        $this->assertSame(230000, ServiceHppSnapshot::resolveWarrantyClaimIssue($firstClaim, 1, 250000));
        $this->assertSame(250000, ServiceHppSnapshot::resolveWarrantyClaimIssue(null, 1, 250000));
        $this->assertSame(230000, $firstClaim->hpp_satuan);
    }

    public function testRecordedClaimCannotBeRemovedOnFormResubmit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ServiceHppSnapshot::resolveWarrantyClaimIssue(
            (object) ['jumlah' => 1, 'hpp_satuan' => 230000], 0, 250000
        );
    }
}
