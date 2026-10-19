<?php

namespace Tests\unit;

use App\Services\Finance\JournalReportFilters;
use PHPUnit\Framework\TestCase;

final class JournalReportFiltersTest extends TestCase
{
    public function testKeepsOnlySupportedJournalFiltersForExport(): void
    {
        self::assertSame([
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-18',
            'filterUnit' => '4',
            'no_akun' => '1030100000',
            'tabel_referensi' => 'pembelian',
            'id_referensi' => '150',
        ], JournalReportFilters::fromArray([
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-18',
            'filterUnit' => '4',
            'no_akun' => '1030100000',
            'tabel_referensi' => 'pembelian',
            'id_referensi' => '150',
            'nama_unit' => 'not part of the filter contract',
        ]));
    }

    public function testSourceDrilldownKeepsDateAndUsesTheSourceUnitAndReference(): void
    {
        self::assertSame([
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-18',
            'filterUnit' => 4,
            'tabel_referensi' => 'pembelian',
            'id_referensi' => '150',
        ], JournalReportFilters::forSource([
            'startDate' => '2026-10-01',
            'endDate' => '2026-10-18',
            'filterUnit' => '2',
            'no_akun' => '1030100000',
        ], 4, ' pembelian ', 150));
    }

    public function testSourceDrilldownRetainsSelectedUnitWhenSourceHasNoUnit(): void
    {
        self::assertSame([
            'filterUnit' => '2',
            'tabel_referensi' => 'refund',
            'id_referensi' => '0',
        ], JournalReportFilters::forSource(['filterUnit' => '2'], null, 'refund', 0));
    }
}
