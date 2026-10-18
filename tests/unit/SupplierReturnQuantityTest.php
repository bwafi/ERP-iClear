<?php

namespace Tests\unit;

use App\Services\Inventory\SupplierReturnQuantity;
use PHPUnit\Framework\TestCase;

final class SupplierReturnQuantityTest extends TestCase
{
    public function testAllowsRemainingPurchasedQuantity(): void
    {
        $this->assertTrue(SupplierReturnQuantity::allows(5, 2, 3));
    }

    public function testRejectsCumulativeOverReturn(): void
    {
        $this->assertFalse(SupplierReturnQuantity::allows(5, 2, 4));
        $this->assertFalse(SupplierReturnQuantity::allows(5, 5, 1));
    }

    public function testRejectsNonPositiveQuantities(): void
    {
        $this->assertFalse(SupplierReturnQuantity::allows(5, 0, 0));
        $this->assertFalse(SupplierReturnQuantity::allows(0, 0, 1));
        $this->assertFalse(SupplierReturnQuantity::allows(5, -1, 1));
    }
}
