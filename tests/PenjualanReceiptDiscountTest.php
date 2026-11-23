<?php

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Struk penjualan diskon 100%: Subtotal tetap nilai kotor, diskon mengurangi
 * penuh, dan Total yang tercetak harus Rp 0 (sama dengan reprint riwayat).
 *
 * @internal
 */
final class PenjualanReceiptDiscountTest extends CIUnitTestCase
{
    private function render(): string
    {
        return view('cetak/cetak_penjualan_thermal', [
            'produk' => [
                [
                    'nama' => 'iPhone 11',
                    'jumlah' => 1,
                    'harga' => 'Rp 1.000.000',
                    'diskon' => 'Rp 1.000.000',
                    'ppn' => 0,
                    'imei' => '123456789',
                    'status_barang' => 0,
                ],
            ],
            'tanggal' => '2026-11-23 10:00:00',
            'kasir' => 'Kasir Satu',
            'customer' => 'Pelanggan Umum',
            'no_invoice' => 'SLL1202611230001',
            'total_ppn' => 0,
            'diskon' => 1000000,
            'sub_total' => 1000000,
            'total' => 0,
            'bayar' => 0,
            'kembalian' => 0,
            'dataunit' => (object) [
                'NAMA_UNIT' => 'Toko Uji',
                'JALAN_UNIT' => 'Jalan Uji',
                'KABUPATEN_UNIT' => 'Sidoarjo',
                'NOTELP_KOMPLAIN' => '0812',
            ],
        ]);
    }

    public function testTotalStrukDiskonPenuhNol(): void
    {
        $html = $this->render();

        preg_match('/<table class="invoice-total">(.*?)<\/table>/s', $html, $cocok);

        $this->assertNotEmpty($cocok, 'Tabel total struk tidak ditemukan.');
        $this->assertStringContainsString('1.000.000', $cocok[1], 'Subtotal harus nilai kotor.');
        $this->assertStringContainsString('-1.000.000', $cocok[1], 'Total diskon harus penuh.');
        $this->assertStringContainsString('<strong>0</strong>', $cocok[1], 'Total bersih harus Rp 0.');
    }
}
