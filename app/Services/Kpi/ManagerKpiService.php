<?php

namespace App\Services\Kpi;

/**
 * ManagerKpiService — KPI Manager (jabatan 34), 7 komponen perusahaan.
 *
 *   OMZET_GLOBAL           50  rumus sama Kepala Divisi (43)
 *   FIN_KESEHATAN_UANG     15  nilai Finance apa adanya
 *   TARGET_CABANG          10  versi semua cabang
 *   EFEKTIVITAS_ORGANISASI 10  rata-rata KPI SPV (40)
 *   SDM_LEADERSHIP          5  rata-rata KPI bawahan (40/43/45/0)
 *   IMPROVEMENT_PERUSAHAAN  5  input manual bulanan
 *   REPORTING_GOVERNANCE    5  input manual bulanan
 *
 * Aturan angka (desain 2026-10-18):
 *   - Anggota tanpa nilai dihitung 0 (pembagi = semua anggota aktif).
 *   - SDM & Leadership dirata-rata PER JABATAN dulu, baru antar jabatan.
 *   - Tiap skor kontributor di-cap 100 sebelum dirata-rata.
 */
class ManagerKpiService
{
    /** 7 komponen KPI Manager. */
    public const CODES = [
        'OMZET_GLOBAL',
        'FIN_KESEHATAN_UANG',
        'TARGET_CABANG',
        'EFEKTIVITAS_ORGANISASI',
        'SDM_LEADERSHIP',
        'IMPROVEMENT_PERUSAHAAN',
        'REPORTING_GOVERNANCE',
    ];

    /**
     * Rata-rata skor dengan SEMUA anggota sebagai pembagi (anggota tanpa
     * nilai = 0). Tiap skor di-cap 100. null bila tidak ada anggota.
     *
     * @param array<int,float|null> $scores
     */
    public static function averageOverMembers(array $scores, int $memberCount): ?float
    {
        if ($memberCount <= 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($scores as $s) {
            if ($s === null) {
                continue;
            }
            $sum += min((float) $s, 100.0);
        }

        return round($sum / $memberCount, 4);
    }

    /**
     * Rata-rata antar-jabatan (per-jabatan dulu). null = jabatan tanpa
     * anggota → dilewati. Semua null → null.
     *
     * @param array<int,float|null> $roleAverages
     */
    public static function averageAcrossRoles(array $roleAverages): ?float
    {
        $sum = 0.0;
        $n   = 0;
        foreach ($roleAverages as $v) {
            if ($v === null) {
                continue;
            }
            $sum += (float) $v;
            $n++;
        }

        return $n > 0 ? round($sum / $n, 4) : null;
    }
}
