<?php

namespace App\Services\Service;

use RuntimeException;

final class KartuHargaWarrantyDefault
{
    public static function resolve(array $values): int
    {
        if ($values === []) {
            throw new RuntimeException('Pilih sparepart sebelum menggunakan garansi default Kartu Harga.');
        }
        if (in_array(null, $values, true) || in_array('', $values, true)) {
            throw new RuntimeException('Salah satu item belum memiliki ketentuan garansi di Kartu Harga. Atur ketentuan atau pilih garansi manual.');
        }

        $days = [];
        foreach ($values as $value) {
            if (! is_numeric($value) || (int) $value < 0 || (int) $value > 9999) {
                throw new RuntimeException('Ketentuan garansi default tidak valid.');
            }
            $days[] = (int) $value;
        }
        $days = array_values(array_unique($days));
        if (count($days) !== 1) {
            throw new RuntimeException('Garansi default sparepart berbeda-beda. Pilih garansi service secara manual agar kebijakannya jelas.');
        }

        return $days[0];
    }
}
