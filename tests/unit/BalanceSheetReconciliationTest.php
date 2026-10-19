<?php

namespace Tests\unit;

use App\Services\Finance\BalanceSheetReconciliation;
use PHPUnit\Framework\TestCase;

final class BalanceSheetReconciliationTest extends TestCase
{
    public function testBalancedJournalSeparatesCurrentProfit(): void
    {
        $summary = BalanceSheetReconciliation::summarize([
            ['no_akun' => '1010101000', 'debet' => 1000, 'kredit' => 0],
            ['no_akun' => '2010101000', 'debet' => 0, 'kredit' => 300],
            ['no_akun' => '3010101000', 'debet' => 0, 'kredit' => 500],
            ['no_akun' => '4010101000', 'debet' => 0, 'kredit' => 300],
            ['no_akun' => '5010101000', 'debet' => 100, 'kredit' => 0],
        ]);

        $this->assertSame(1000, $summary['aset']);
        $this->assertSame(300, $summary['kewajiban']);
        $this->assertSame(500, $summary['ekuitas']);
        $this->assertSame(200, $summary['laba_berjalan']);
        $this->assertSame(0, $summary['selisih']);
        $this->assertSame(0, $summary['selisih_jurnal']);
    }

    public function testUnclassifiedAccountsRemainVisible(): void
    {
        $summary = BalanceSheetReconciliation::summarize([
            ['no_akun' => '8010101000', 'debet' => 0, 'kredit' => 100],
        ]);

        $this->assertSame(['8010101000'], $summary['akun_tidak_terklasifikasi']);
        $this->assertSame(-100, $summary['selisih_jurnal']);
    }
}
