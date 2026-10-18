<?php

namespace Tests\unit;

use App\Services\Finance\SupplierCreditAllocator;
use PHPUnit\Framework\TestCase;

final class SupplierCreditAllocatorTest extends TestCase
{
    public function testAllocatesAcrossInvoicesWithoutCashPayment(): void
    {
        $this->assertSame(
            [11 => 500000, 12 => 200000],
            SupplierCreditAllocator::plan([11 => 500000, 12 => 300000], 700000)
        );
    }

    public function testKeepsUnusedInvoiceBalance(): void
    {
        $this->assertSame(
            [11 => 200000],
            SupplierCreditAllocator::plan([11 => 500000, 12 => 300000], 200000)
        );
    }

    public function testRejectsCreditAboveOutstandingDebt(): void
    {
        $this->expectException(\RuntimeException::class);
        SupplierCreditAllocator::plan([11 => 500000], 600000);
    }
}
