<?php

namespace App\Services;

/**
 * Pemetaan area (unit) untuk akun Direktur (jabatan 2).
 *
 * Direktur di perusahaan ini adalah investor: masing-masing hanya memegang
 * sebagian cabang. Pemetaan dipusatkan di sini supaya keputusan scope-nya
 * konsisten di semua halaman, antara lain:
 *
 *   - TutupKasir::resolveUnitScope()   → dropdown unit /omset_bulanan & /asset_berjalan
 *   - PenilaianKPI::penilaian_kinerja() → daftar karyawan & "Rincian Omset Cabang"
 *
 * Direktur yang belum dipetakan TIDAK dibatasi oleh service ini (null); tiap
 * pemanggil yang memutuskan fallback-nya — /omset_bulanan memperlakukannya
 * lintas unit, sedangkan penilaian kinerja tetap mengunci ke unit sesinya.
 */
class DirekturUnitScopeService
{
    /**
     * ID_AKUN => daftar idunit yang dipegang.
     *
     * @var array<int, int[]>
     */
    public const UNIT_MAP = [
        46 => [3, 5], // Wahid Alfarizki: Banyuwangi, Genteng
        70 => [4],    // Guruh Dwi Prasetyo: Pandaan
    ];

    /**
     * Daftar idunit milik direktur, atau null bila akun tidak dipetakan
     * (berarti dibiarkan lintas unit oleh pemanggil).
     *
     * @return int[]|null
     */
    public static function unitsFor(int $idAkun): ?array
    {
        return self::UNIT_MAP[$idAkun] ?? null;
    }

    /**
     * Batasi daftar omset cabang ke unit yang diizinkan.
     *
     * @param array<int, array<string, mixed>> $cabang setiap item setidaknya punya key 'unit'
     * @param int[]|null                       $units  null = tanpa pembatasan
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filterBranches(array $cabang, ?array $units): array
    {
        if ($units === null) {
            return $cabang;
        }

        return array_values(array_filter(
            $cabang,
            static fn ($cb) => in_array((int) ($cb['unit'] ?? 0), $units, true)
        ));
    }
}
