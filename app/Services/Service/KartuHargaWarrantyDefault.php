<?php

namespace App\Services\Service;

use RuntimeException;

final class KartuHargaWarrantyDefault
{
    public static function resolve(array $values): string
    {
        if ($values === []) {
            throw new RuntimeException('Pilih sparepart sebelum menggunakan garansi default Kartu Harga.');
        }
        if (in_array(null, $values, true) || in_array('', $values, true)) {
            throw new RuntimeException('Salah satu item belum memiliki ketentuan garansi di Kartu Harga. Atur dulu atau pilih opsi garansi pada transaksi.');
        }

        $options = [];
        foreach ($values as $value) {
            if (! is_string($value) || ! WarrantyTerm::isValid($value)) {
                throw new RuntimeException('Ketentuan garansi default tidak valid.');
            }
            $options[] = $value;
        }
        $options = array_values(array_unique($options));
        if (count($options) !== 1) {
            throw new RuntimeException('Garansi default sparepart berbeda-beda. Pilih satu opsi garansi secara eksplisit pada transaksi.');
        }

        return $options[0];
    }
}
