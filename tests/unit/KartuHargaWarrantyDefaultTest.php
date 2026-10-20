<?php

namespace Tests\unit;

use App\Services\Service\KartuHargaWarrantyDefault;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class KartuHargaWarrantyDefaultTest extends TestCase
{
    public function testUniformWarrantyDefaultsCanBeApplied(): void
    {
        self::assertSame('bulan_1', KartuHargaWarrantyDefault::resolve(['bulan_1', 'bulan_1']));
        self::assertSame('non_garansi', KartuHargaWarrantyDefault::resolve(['non_garansi']));
    }

    public function testEmptyMissingAndMixedDefaultsAreRejected(): void
    {
        foreach ([[], [null], ['bulan_1', null], ['hari_7', 'bulan_1']] as $values) {
            try {
                KartuHargaWarrantyDefault::resolve($values);
                self::fail('Expected an invalid default to be rejected.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }

    public function testUnknownWarrantyIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        KartuHargaWarrantyDefault::resolve(['9999']);
    }
}
