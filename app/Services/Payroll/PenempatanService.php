<?php

namespace App\Services\Payroll;

/**
 * Tunjangan Penempatan — ditentukan OTOMATIS dari data, bukan hardcode.
 *
 * Aturan (2026-10-10):
 *   - Karyawan dianggap "di rumah" (penempatan = 1) bila akun.ALAMAT sama
 *     dengan KABUPATEN_UNIT unit penempatannya (case-insensitive, trim).
 *   - Head Office (unit 50) tidak punya kabupaten, sehingga kota rumahnya
 *     dikhususkan ke "Probolinggo" (kota HO).
 *   - Selain itu dianggap "perantau" → tunjangan penempatan Rp350.000.
 */
class PenempatanService
{
    public const TUNJANGAN_PENEMPATAN = 350000.0;

    private const HO_UNIT_ID = 50;
    private const HO_HOME    = 'probolinggo';

    public static function penempatan(?string $alamat, $unitId): int
    {
        $alamat = strtolower(trim((string) $alamat));
        if ($alamat === '') {
            return 0;
        }

        $unitId = (int) $unitId;

        if ($unitId === self::HO_UNIT_ID) {
            return $alamat === self::HO_HOME ? 1 : 0;
        }

        $home = self::kabupaten($unitId);

        return ($home !== null && $alamat === strtolower(trim($home))) ? 1 : 0;
    }

    public static function tunjanganPenempatan(?string $alamat, $unitId): float
    {
        return self::penempatan($alamat, $unitId) === 0 ? self::TUNJANGAN_PENEMPATAN : 0.0;
    }

    private static function kabupaten(int $unitId): ?string
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            $rows = \Config\Database::connect()
                ->table('unit')
                ->select('idunit, KABUPATEN_UNIT')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $cache[(int) $row['idunit']] = $row['KABUPATEN_UNIT'];
            }
        }

        return $cache[$unitId] ?? null;
    }
}