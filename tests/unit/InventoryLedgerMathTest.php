<?php

namespace Tests\unit;

use App\Services\Inventory\InventoryLedgerMath;
use PHPUnit\Framework\TestCase;

final class InventoryLedgerMathTest extends TestCase
{
    public function testIncomingRecalculatesMovingAverageAndOutgoingUsesCurrentValue(): void
    {
        $first = InventoryLedgerMath::incoming(0, 0, 2, 100);
        $second = InventoryLedgerMath::incoming($first['balance_quantity'], $first['balance_value'], 2, 200);
        $outgoing = InventoryLedgerMath::outgoing($second['balance_quantity'], $second['balance_value'], 3);

        $this->assertSame(4, $second['balance_quantity']);
        $this->assertSame(600, $second['balance_value']);
        $this->assertSame(-450, $outgoing['value']);
        $this->assertSame(1, $outgoing['balance_quantity']);
        $this->assertSame(150, $outgoing['balance_value']);
    }

    public function testFinalOutgoingClearsRoundingRemainder(): void
    {
        $first = InventoryLedgerMath::outgoing(3, 100, 1);
        $last = InventoryLedgerMath::outgoing($first['balance_quantity'], $first['balance_value'], 2);

        $this->assertSame(-33, $first['value']);
        $this->assertSame(-67, $last['value']);
        $this->assertSame(0, $last['balance_value']);
    }

    public function testIncomingTransferCarriesExactValue(): void
    {
        $outgoing = InventoryLedgerMath::outgoing(3, 100, 1);
        $incoming = InventoryLedgerMath::incomingValue(2, 200, 1, -$outgoing['value']);

        $this->assertSame(-33, $outgoing['value']);
        $this->assertSame(33, $incoming['value']);
        $this->assertSame(233, $incoming['balance_value']);
    }

    public function testPurchaseKeepsExactNetAmountWhenPerUnitCostIsRounded(): void
    {
        $purchase = InventoryLedgerMath::incomingValue(0, 0, 3, 100);
        $sale = InventoryLedgerMath::outgoing($purchase['balance_quantity'], $purchase['balance_value'], 1);

        $this->assertSame(100, $purchase['balance_value']);
        $this->assertSame(-33, $sale['value']);
        $this->assertSame(67, $sale['balance_value']);
    }

    public function testReversalUsesOriginalQuantityAndValue(): void
    {
        $sale = InventoryLedgerMath::outgoing(4, 600, 2);
        $reversed = InventoryLedgerMath::reverse($sale['balance_quantity'], $sale['balance_value'],
            $sale['quantity'], $sale['value']);

        $this->assertSame(4, $reversed['balance_quantity']);
        $this->assertSame(600, $reversed['balance_value']);
    }

    public function testReversingConsumedReceiptIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InventoryLedgerMath::reverse(1, 100, 2, 200);
    }

    public function testOversellingIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InventoryLedgerMath::outgoing(1, 100, 2);
    }
}
