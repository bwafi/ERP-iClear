<?php

namespace App\Services\Pembelian;

use InvalidArgumentException;

/**
 * Normalisasi dan perhitungan baris pembelian.
 *
 * Aturan yang dipegang di sini:
 *  - PPN 11% dihitung dari (harga_beli x jumlah - diskon).
 *  - Biaya tambahan menambah nilai persediaan/HPP dan total baris, tetapi
 *    TIDAK menjadi dasar PPN.
 *  - Total header selalu hasil hitung server, bukan nilai POST.
 *
 * Nilai uang dibulatkan ke rupiah penuh karena kolom pembelian/detail/jurnal
 * memakai integer.
 */
final class PembelianCalculator
{
    public const PPN_RATE = 0.11;

    /**
     * @param mixed $produkData nilai mentah POST `produk`
     *
     * @return array{
     *     baris: list<array<string,mixed>>,
     *     total: int,
     *     diskon: int,
     *     ppn: int
     * }
     *
     * @throws InvalidArgumentException
     */
    public static function normalisasi($produkData): array
    {
        if (! is_array($produkData) || $produkData === []) {
            throw new InvalidArgumentException('Pembelian wajib memiliki minimal satu produk.');
        }

        $baris = [];
        $total = 0;
        $totalDiskon = 0;
        $totalPpn = 0;

        foreach ($produkData as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Format produk pembelian tidak valid.');
            }

            $id = self::angkaBulatPositif($item['id'] ?? null);
            if ($id === null) {
                throw new InvalidArgumentException('Produk pembelian tidak valid.');
            }

            $jumlah = self::angkaBulatPositif($item['jumlah'] ?? null);
            if ($jumlah === null) {
                throw new InvalidArgumentException('Jumlah produk harus bilangan bulat lebih dari 0.');
            }

            $hargaBeli = self::angkaPositif($item['harga_beli'] ?? null);
            if ($hargaBeli === null) {
                throw new InvalidArgumentException('Harga beli harus bilangan bulat lebih dari 0.');
            }

            $diskon = self::angkaNolAtauPositif($item['diskon'] ?? 0);
            if ($diskon === null) {
                throw new InvalidArgumentException('Diskon produk tidak valid.');
            }

            $biaya = self::angkaNolAtauPositif($item['biaya_tambahan'] ?? 0);
            if ($biaya === null) {
                throw new InvalidArgumentException('Biaya tambahan produk tidak valid.');
            }

            $subtotal = $hargaBeli * $jumlah;
            if ($diskon > $subtotal) {
                throw new InvalidArgumentException('Diskon tidak boleh melebihi subtotal produk.');
            }

            $dasarPpn = $subtotal - $diskon;
            $ppn = ! empty($item['ppn']) ? (int) round($dasarPpn * self::PPN_RATE) : 0;
            $dasarPersediaan = $dasarPpn + $biaya;
            $totalBaris = $dasarPersediaan + $ppn;

            $baris[] = [
                'id'               => $id,
                'jumlah'           => $jumlah,
                'harga_beli'       => $hargaBeli,
                'diskon'           => $diskon,
                'biaya_tambahan'   => $biaya,
                'harga_jual'       => self::angkaNolAtauPositif($item['harga'] ?? 0) ?? 0,
                'keterangan'       => trim((string) ($item['keterangan'] ?? '')),
                'ppn_dicentang'    => ! empty($item['ppn']),
                'subtotal'         => $subtotal,
                'dasar_ppn'        => $dasarPpn,
                'dasar_persediaan' => $dasarPersediaan,
                'ppn'              => $ppn,
                'total'            => $totalBaris,
            ];

            $total += $totalBaris;
            $totalDiskon += $diskon;
            $totalPpn += $ppn;
        }

        if ($total <= 0) {
            throw new InvalidArgumentException('Total pembelian harus lebih dari 0.');
        }

        return [
            'baris'  => $baris,
            'total'  => $total,
            'diskon' => $totalDiskon,
            'ppn'    => $totalPpn,
        ];
    }

    public static function angkaPositif($value): ?int
    {
        $angka = self::parseRupiah($value);

        return $angka !== null && $angka > 0 ? $angka : null;
    }

    /**
     * Bilangan bulat murni tanpa pemisah ribuan. Dipakai untuk id dan qty:
     * `1.5` harus ditolak, bukan dibaca sebagai 15.
     */
    public static function angkaBulatPositif($value): ?int
    {
        $angka = self::parseBulat($value);

        return $angka !== null && $angka > 0 ? $angka : null;
    }

    public static function angkaNolAtauPositif($value): ?int
    {
        $angka = self::parseRupiah($value);

        return $angka !== null && $angka >= 0 ? $angka : null;
    }

    private static function parseBulat($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value) || floor($value) !== $value) {
                return null;
            }

            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $bersih = trim($value);
        if ($bersih === '' || ! ctype_digit($bersih)) {
            return null;
        }

        return (int) $bersih;
    }

    /**
     * Membaca nominal rupiah dari POST. Hanya angka bulat yang diterima;
     * tanda minus, desimal, atau teks lain ditolak supaya qty/harga tidak
     * berubah arti diam-diam.
     */
    public static function parseRupiah($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (! is_finite($value) || floor($value) !== $value) {
                return null;
            }

            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $bersih = str_replace(['Rp', 'rp', 'RP', ' ', '.'], '', trim($value));
        if ($bersih === '' || ! ctype_digit($bersih)) {
            return null;
        }

        return (int) $bersih;
    }
}
