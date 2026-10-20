<?php

namespace Tests\unit;

use App\Services\Finance\ReportCalculationScope;
use PHPUnit\Framework\TestCase;

final class ReportCalculationScopeTest extends TestCase
{
    public function testValuesAreReusedOnlyInsideOneReadOnlyCalculation(): void
    {
        $calls = 0;
        $source = static function () use (&$calls) { return ++$calls; };
        ReportCalculationScope::run(static function () use ($source) {
            self::assertSame(1, ReportCalculationScope::remember('unit1:oct', $source));
            self::assertSame(1, ReportCalculationScope::remember('unit1:oct', $source));
            self::assertSame(2, ReportCalculationScope::remember('unit2:oct', $source));
        });
        self::assertSame(3, ReportCalculationScope::remember('unit1:oct', $source));
        ReportCalculationScope::run(static function () use ($source) {
            self::assertSame(4, ReportCalculationScope::remember('unit1:oct', $source));
        });
        try {
            ReportCalculationScope::run(static function () use ($source) {
                ReportCalculationScope::remember('unit1:oct', $source);
                throw new \RuntimeException('fixture');
            });
        } catch (\RuntimeException $exception) {
            self::assertSame('fixture', $exception->getMessage());
        }
        self::assertSame(6, ReportCalculationScope::remember('unit1:oct', $source));
    }
}
