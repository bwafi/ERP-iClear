<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

class TerbilangHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('terbilang');
    }

    public function testNol(): void
    {
        $this->assertSame('Nol Rupiah', terbilang_rupiah(0));
    }

    public function testRatusan(): void
    {
        $this->assertSame('Sembilan Ratus Dua Puluh Tujuh Rupiah', terbilang_rupiah(927));
    }

    public function testSeribuBukanSatuRibu(): void
    {
        $this->assertSame('Seribu Rupiah', terbilang_rupiah(1000));
    }

    public function testJutaan(): void
    {
        $this->assertSame('Satu Juta Rupiah', terbilang_rupiah(1000000));
    }

    public function testMiliaran(): void
    {
        $this->assertSame('Dua Miliar Rupiah', terbilang_rupiah(2000000000));
    }

    public function testNegatif(): void
    {
        $this->assertSame('Minus Lima Ribu Rupiah', terbilang_rupiah(-5000));
    }

    public function testAngkaInvoiceContoh(): void
    {
        $this->assertSame(
            'Empat Puluh Satu Juta Tiga Ratus Empat Belas Ribu Sembilan Ratus Dua Puluh Tujuh Rupiah',
            terbilang_rupiah(41314927)
        );
    }

    public function testGajiCabangContoh(): void
    {
        $this->assertSame(
            'Sepuluh Juta Dua Ratus Dua Puluh Tujuh Ribu Lima Ratus Empat Puluh Dua Rupiah',
            terbilang_rupiah(10227542)
        );
    }
}
