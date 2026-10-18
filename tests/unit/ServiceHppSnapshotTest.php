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
}
