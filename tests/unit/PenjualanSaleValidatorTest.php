<?php

namespace Tests\unit;

use App\Services\Sales\PenjualanSaleValidator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Validasi total penjualan: diskon 100% (total Rp 0) sah, tetapi total 0
 * tanpa diskon penuh, diskon melebihi harga, dan pembayaran pada transaksi
 * tanpa total harus ditolak.
 *
 * @internal
 */
final class PenjualanSaleValidatorTest extends CIUnitTestCase
{
    private PenjualanSaleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PenjualanSaleValidator();
    }

    public function testDiskonPenuhDiterima(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 1.000.000', 'jumlah' => 1, 'diskon' => 'Rp 1.000.000']],
            0,
            1000000,
            0
        );

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(1000000.0, $hasil['kotor']);
        $this->assertSame(1000000.0, $hasil['diskon']);
    }

    public function testDiskonPenuhMultiItemDiterima(): void
    {
        $hasil = $this->validator->periksa(
            [
                ['harga' => 'Rp 500.000', 'jumlah' => 2, 'diskon' => 'Rp 1.000.000'],
                ['harga' => 250000, 'jumlah' => 2, 'diskon' => 'Rp 500.000'],
            ],
            0,
            1500000,
            0
        );

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(1500000.0, $hasil['kotor']);
        $this->assertSame(1500000.0, $hasil['diskon']);
    }

    public function testDiskonParsialDiterima(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 25.000']],
            75000,
            25000,
            75000
        );

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(75000.0, $hasil['kotor'] - $hasil['diskon']);
    }

    public function testDiskonMelebihiHargaDitolak(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 150.000']],
            0,
            150000,
            0
        );

        $this->assertContains(
            'Diskon produk tidak boleh melebihi harga × jumlah.',
            $hasil['errors']
        );
    }

    public function testDiskonPenjualanMelebihiNilaiProdukDitolak(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 0']],
            0,
            150000,
            0
        );

        $this->assertContains('Diskon penjualan tidak boleh melebihi nilai produk.', $hasil['errors']);
    }

    public function testTotalNolTanpaDiskonPenuhDitolak(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 0']],
            0,
            0,
            0
        );

        $this->assertContains(
            'Total penjualan nol hanya berlaku bila diskon menutup seluruh nilai produk.',
            $hasil['errors']
        );
    }

    public function testTotalNolDenganPembayaranDitolak(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 100.000']],
            0,
            100000,
            50000
        );

        $this->assertContains(
            'Transaksi tanpa total tidak memerlukan pembayaran tunai atau bank.',
            $hasil['errors']
        );
    }

    public function testTotalNegatifDitolak(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 100.000']],
            -1000,
            100000,
            0
        );

        $this->assertContains('Total penjualan tidak boleh negatif.', $hasil['errors']);
    }

    public function testTotalNolDenganPembayaranTunaiNolDiterima(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 100.000']],
            0,
            100000,
            0
        );

        $this->assertSame([], $hasil['errors']);
    }

    public function testJumlahNolDitolak(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 0, 'diskon' => 'Rp 0']],
            0,
            0,
            0
        );

        $this->assertContains('Jumlah produk minimal 1.', $hasil['errors']);
    }

    public function testHargaNolDenganProdukLainDiterima(): void
    {
        $hasil = $this->validator->periksa(
            [
                ['harga' => 0, 'jumlah' => 1, 'diskon' => 0],
                ['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 100.000'],
            ],
            0,
            100000,
            0
        );

        $this->assertSame([], $hasil['errors']);
        $this->assertSame(100000.0, $hasil['kotor']);
    }

    public function testDiskonKlienTidakSesuaiRincianDitolak(): void
    {
        $hasil = $this->validator->periksa(
            [['harga' => 'Rp 100.000', 'jumlah' => 1, 'diskon' => 'Rp 100.000']],
            0,
            0,
            0
        );

        $this->assertContains('Total diskon tidak sesuai dengan rincian produk.', $hasil['errors']);
    }

    public function testControllerMemakaiValidatorDanTidakMenolakTotalNol(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Penjualan.php');

        $this->assertStringContainsString('PenjualanSaleValidator', $source);
        $this->assertStringNotContainsString("if (\$total_penjualan <= 0) {", $source);
        $this->assertStringContainsString('$sub_total_cetak = $gross_penjualan;', $source);
    }
}
