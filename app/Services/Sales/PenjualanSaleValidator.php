<?php

namespace App\Services\Sales;

/**
 * Validasi total penjualan dari rincian produk.
 *
 * Halaman /penjualan mengirim total setelah diskon (termasuk PPN). Total 0
 * adalah transaksi sah bila diskon menutup seluruh nilai produk, jadi guard
 * lama `total <= 0` tidak boleh dipakai lagi. Service ini memastikan total 0
 * hanya terjadi karena diskon penuh, bukan karena keranjang kosong, harga 0,
 * atau nominal yang dimanipulasi.
 *
 * Service ini tidak menyentuh database.
 */
final class PenjualanSaleValidator
{
    /**
     * @param array<int, array{harga?: mixed, jumlah?: mixed, diskon?: mixed}> $produk
     *
     * @return array{kotor: float, diskon: float, errors: array<int, string>}
     */
    public function periksa(array $produk, float $total, float $diskon, float $dibayar): array
    {
        $rincian = $this->hitung($produk);
        $errors = $rincian['errors'];
        $kotor = $rincian['kotor'];
        $diskonRincian = $rincian['diskon'];

        if ($kotor <= 0 && $errors === []) {
            $errors[] = 'Nilai produk harus lebih besar dari nol.';
        }

        if ($diskon < 0) {
            $errors[] = 'Diskon penjualan tidak boleh negatif.';
        } elseif ($diskon > $kotor) {
            $errors[] = 'Diskon penjualan tidak boleh melebihi nilai produk.';
        }

        if (abs($diskon - $diskonRincian) > 0.5) {
            $errors[] = 'Total diskon tidak sesuai dengan rincian produk.';
        }

        if ($total < 0) {
            $errors[] = 'Total penjualan tidak boleh negatif.';
        }

        if ($total <= 0 && $diskon < $kotor) {
            $errors[] = 'Total penjualan nol hanya berlaku bila diskon menutup seluruh nilai produk.';
        }

        if ($total <= 0 && $dibayar > 0) {
            $errors[] = 'Transaksi tanpa total tidak memerlukan pembayaran tunai atau bank.';
        }

        return [
            'kotor' => $kotor,
            'diskon' => $diskonRincian,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<int, array{harga?: mixed, jumlah?: mixed, diskon?: mixed}> $produk
     *
     * @return array{kotor: float, diskon: float, errors: array<int, string>}
     */
    public function hitung(array $produk): array
    {
        $kotor = 0.0;
        $diskon = 0.0;
        $errors = [];

        foreach ($produk as $item) {
            if (! is_array($item)) {
                $errors[] = 'Rincian produk tidak valid.';
                continue;
            }

            $harga = $this->uang($item['harga'] ?? 0);
            $jumlah = (int) ($item['jumlah'] ?? 0);
            $potongan = $this->uang($item['diskon'] ?? 0);

            if ($harga < 0) {
                $errors[] = 'Harga produk tidak boleh negatif.';
                continue;
            }

            if ($jumlah < 1) {
                $errors[] = 'Jumlah produk minimal 1.';
                continue;
            }

            $subtotal = $harga * $jumlah;

            if ($potongan < 0) {
                $errors[] = 'Diskon produk tidak boleh negatif.';
                continue;
            }

            if ($potongan > $subtotal) {
                $errors[] = 'Diskon produk tidak boleh melebihi harga × jumlah.';
                continue;
            }

            $kotor += $subtotal;
            $diskon += $potongan;
        }

        return [
            'kotor' => $kotor,
            'diskon' => $diskon,
            'errors' => $errors,
        ];
    }

    /**
     * Baca nominal rupiah dari input form: "Rp 1.000.000", "1000000", dst.
     */
    private function uang($raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        return (float) str_replace(['Rp', '.', ' '], '', (string) $raw);
    }
}
