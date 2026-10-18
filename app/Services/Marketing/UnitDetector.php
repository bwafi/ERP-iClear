<?php

namespace App\Services\Marketing;

/**
 * Menebak unit pembebanan dari nama campaign Meta.
 *
 * Satu-satunya tempat mengubah aturan. Hasil tebakan disimpan sebagai
 * `marketing_campaigns.unit_sumber = 'auto'`; koreksi manual mengubahnya
 * menjadi 'manual' supaya sync berikutnya tidak menimpa.
 */
class UnitDetector
{
    /** Kata kunci nama campaign → idunit. */
    public const UNIT_KEYWORDS = [
        'probolinggo' => 1,
        'prob'        => 1,
        'jember'      => 2,
        'banyuwangi'  => 3,
        'bwi'         => 3,
        'pandaan'     => 4,
        'genteng'     => 5,
    ];

    /** Campaign tanpa nama cabang dibebankan ke Probolinggo. */
    public const DEFAULT_UNIT = 1;

    public static function detect(string $nama): int
    {
        $n = strtolower($nama);

        foreach (self::UNIT_KEYWORDS as $kata => $unit) {
            if (strpos($n, $kata) !== false) {
                return $unit;
            }
        }

        return self::DEFAULT_UNIT;
    }
}
