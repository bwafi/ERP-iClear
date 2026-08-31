<?php

namespace App\Services\Kpi;

/**
 * KpiScoreService — Generic KPI scoring helpers
 *
 * TWO SOURCES OF TRUTH:
 * A) Formula dari EXISTING system  (hitungKPIGaji / assetberjalan) — TIDAK DIUBAH.
 * B) Formula untuk final/configurable service — didokumentasikan per method.
 *
 * Kategori tiap method:
 *   [EXISTING]  = diambil verbatim dari hitungKPIGaji().
 *   [NEW]       = helper baru untuk final service (belum dipakai periode berjalan).
 *   [CONFIG]    = membaca dari database.
 *
 * PENTING: cap 100 HANYA digunakan pada KPI yang memang di-cap di sistem existing.
 * Jangan menerapkan cap 100 sembarangan pada KPI yang existing-nya tidak di-cap.
 */
class KpiScoreService
{
    /**
     * [EXISTING] achievement score untuk KPI yang di-cap 100.
     * Existing memakai cap 100 pada: closing, upselling, followup,
     * dan customer (context gaji) — lihat hitungKPIGaji().
     * Rumus existing: min((actual/target)*100, 100)
     *
     * CATATAN: untuk KPI yang existing-nya TIDAK di-cap
     * (mis. roas = total×100, budgeting = total×100/20), jangan pakai helper ini.
     */
    public function achievementScore(float $actual, float $target, bool $capped = true): float
    {
        if ($target <= 0) {
            return 0.0;
        }
        $score = ($actual / $target) * 100.0;
        return $capped ? min($score, 100.0) : $score;
    }

    /**
     * [EXISTING] weighted score.
     * Rumus existing (hitungKPIGaji): (nilai * bobot) / 100.
     */
    public function weightedScore(float $score, float $weight): float
    {
        return ($score / 100.0) * $weight;
    }

    /**
     * [NEW] normalize manual KPI raw_score (1-5) ke 0-100.
     * Rumus (business rule): (raw_score / max_score) * 100, capped 100.
     * HANYA untuk manual KPI (nilai evaluator). Belum dipakai periode berjalan.
     */
    public function normalizeManualScore(float $rawScore, float $maxScore = 5.0): float
    {
        if ($maxScore <= 0) {
            return 0.0;
        }
        return min(($rawScore / $maxScore) * 100.0, 100.0);
    }

    /**
     * [EXISTING] aggregate total KPI dari detail_kpi.
     * Rumus existing: sum((nilai * bobot) / 100).
     */
    public function totalWeightedScore(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += ($item['nilai'] * $item['bobot']) / 100.0;
        }
        return $total;
    }

    /**
     * [CONFIG] validasi jumlah bobot dalam satu group = 100.
     * Dipanggil di application layer SEBELUM konfigurasi diaktifkan.
     */
    public function validateWeights(array $items): array
    {
        $total = array_sum(array_column($items, 'bobot'));
        return [
            'is_valid'   => abs($total - 100) < 0.01,
            'total'      => $total,
            'difference' => abs($total - 100),
        ];
    }

    /**
     * [EXISTING] capped percentage untuk KPI rasio-as-target.
     * Sama dengan achievementScore() dengan cap; dipertahankan utk API lama.
     */
    public function cappedPercentage(float $numerator, float $denominator, float $cap = 100.0): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }
        return min(($numerator / $denominator) * 100.0, $cap);
    }

    /**
     * [EXISTING] tiered omset score — replikasi LITERAL switch-case
     * hitungKPIGaji() untuk jabatan 41 (kepala_toko) dan default.
     *
     * DISCREPANCY NOTE: kondisi exact-match `== batas2` / `== batas3`
     * dipertahankan VERBATIM dari existing (termasuk perilaku boundary-nya),
     * supaya OLD == NEW. Perbaikan boundary perlu keputusan business rule
     * terpisah dan TIDAK dilakukan di sini.
     */
    public function tieredOmsetScore(
        float $aktualOmset,
        float $batasAwal,
        float $batasKedua,
        float $batasKetiga,
        float $batasKeempat,
        float $targetOmset,
        string $mode = 'kepala_toko'
    ): float {
        switch ($mode) {
            case 'kepala_toko':
                if ($aktualOmset <= $batasAwal) return 0.0;
                if ($aktualOmset == $batasKedua) return 33.0;
                if ($aktualOmset == $batasKetiga) return 66.0;
                if ($aktualOmset >= $batasKeempat && $aktualOmset < $targetOmset) return 100.0;
                if ($aktualOmset >= $targetOmset) return 100.0;
                return (($aktualOmset - $batasAwal) / ($batasKeempat - $batasAwal)) * 100.0;

            case 'default':
            default:
                if ($aktualOmset < $batasKedua) return 0.0;
                if ($aktualOmset >= $batasKedua && $aktualOmset < $batasKetiga) return 33.0;
                if ($aktualOmset >= $batasKetiga && $aktualOmset < $batasKeempat) return 66.0;
                if ($aktualOmset >= $batasKeempat && $aktualOmset < $targetOmset) return 100.0;
                if ($aktualOmset >= $targetOmset) return 100.0;
                return (($aktualOmset - $batasAwal) / ($batasKeempat - $batasAwal)) * 100.0;
        }
    }
}

