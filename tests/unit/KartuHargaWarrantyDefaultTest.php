<?php

namespace Tests\unit;

use App\Services\Service\KartuHargaWarrantyDefault;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KartuHargaWarrantyDefaultTest extends TestCase
{
    public function testUniformWarrantyDefaultsCanBeApplied(): void
    {
        self::assertSame(30, KartuHargaWarrantyDefault::resolve([30, '30', 30]));
        self::assertSame(0, KartuHargaWarrantyDefault::resolve([0, '0']));
    }

    public function testEmptyMissingAndMixedDefaultsAreRejected(): void
    {
        foreach ([[], [null], [30, null], [7, 30]] as $values) {
            try {
                KartuHargaWarrantyDefault::resolve($values);
                self::fail('Expected an invalid default to be rejected.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }

    public function testOutOfRangeWarrantyIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        KartuHargaWarrantyDefault::resolve([10000]);
    }
}
