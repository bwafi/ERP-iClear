<?php

namespace Tests\unit;

use App\Services\Finance\WarrantyExpenseJournal;
use PHPUnit\Framework\TestCase;

final class WarrantyExpenseJournalTest extends TestCase
{
    public function testBalancedExpenseAndInventoryLines(): void
    {
        $lines = WarrantyExpenseJournal::lines([
            ['no_akun' => '5010101000', 'debet_kredit' => 'debet'],
            ['no_akun' => '1010101000', 'debet_kredit' => 'kredit'],
        ], 230000);

        $this->assertSame([
            ['5010101000', 230000, 0],
            ['1010101000', 0, 230000],
        ], $lines);
        $this->assertSame(0, array_sum(array_column($lines, 1)) - array_sum(array_column($lines, 2)));
    }

    public function testMissingOrIncorrectMappingFailsClosed(): void
    {
        foreach ([
            [],
            [['no_akun' => '5010101000', 'debet_kredit' => 'debet']],
            [
                ['no_akun' => '4010101000', 'debet_kredit' => 'debet'],
                ['no_akun' => '1010101000', 'debet_kredit' => 'kredit'],
            ],
        ] as $template) {
            try {
                WarrantyExpenseJournal::lines($template, 230000);
                $this->fail('Template tidak valid seharusnya ditolak.');
            } catch (\InvalidArgumentException $error) {
                $this->assertNotSame('', $error->getMessage());
            }
        }
    }

    public function testZeroHppCannotCreateStockIssue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WarrantyExpenseJournal::lines([
            ['no_akun' => '5010101000', 'debet_kredit' => 'debet'],
            ['no_akun' => '1010101000', 'debet_kredit' => 'kredit'],
        ], 0);
    }
}
