<?php

namespace Tests\unit;

use App\Services\Finance\SupplierCreditJournal;
use PHPUnit\Framework\TestCase;

final class SupplierCreditJournalTest extends TestCase
{
    public function testAccountClassesRejectSwappedBalanceSheetAccounts(): void
    {
        $this->assertTrue(SupplierCreditJournal::validAccountClasses('2000000001', '1000000001', '4000000001'));
        $this->assertFalse(SupplierCreditJournal::validAccountClasses('1000000001', '2000000001', '4000000001'));
        $this->assertFalse(SupplierCreditJournal::validAccountClasses('2000000001', '1000000001', '1000000002'));
    }

    public function testApprovalWithPartialApplicationBalances(): void
    {
        $lines = SupplierCreditJournal::approvalLines(500000, 300000, 'AP', 'AR', 'RECOVERY');
        $this->assertSame(4, count($lines));
        $this->assertSame(array_sum(array_column($lines, 1)), array_sum(array_column($lines, 2)));
    }

    public function testRemainingCreditApplicationBalances(): void
    {
        $lines = SupplierCreditJournal::applicationLines(200000, 'AP', 'AR');
        $this->assertSame([['AP', 200000, 0], ['AR', 0, 200000]], $lines);
    }

    public function testOverApplicationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SupplierCreditJournal::approvalLines(200000, 300000, 'AP', 'AR', 'RECOVERY');
    }
}
