<?php

namespace App\Services\Marketing;

use App\Models\ModelKpiTarget;
use App\Models\ModelMarketingCampaign;
use App\Services\Kpi\OmsetTokoCalculator;
use App\Services\Konten\MultimediaKpiService;

/**
 * KPI Digital Marketing — jabatan 43 (Kepala Divisi Digital Marketing).
 *
 * MENGULANGI BUKAN engine KPI: service menghitung achievement tiap komponen
 * dari DATA OPERASIONAL lalu diserahkan ke engine KPI existing
 * (KpiCalculationService). Bobot & target dari KONFIGURASI DB
 * (kpi_weights, kpi_targets) — bukan hardcode.
 *
 * Komponen (total bobot 100):
 *   OMZET_GLOBAL         50  Global omzet perusahaan
 *                             Target Toko (floor) = SUM(batas_awal OMSET_TOKO)
 *                             Target HO (100%)    = SUM(target_value OMSET_TOKO)
 *                             actual <= Toko → 0; >= HO → 100; else linear.
 *   LEADS_QUALITY        15  Leads (sum Hasil Performa Ads) + Kualitas
 *                             (qualified / leads), avg, cap 100.
 *   CONVERSION           10  Closing CS / Lead CS (target % di kpi_targets).
 *   CPL                  10  Budget Ads (data Performa Ads) / Datang & Closing CS
 *   CAMPAIGN_PERFORMANCE  5  Ads ber-campaign valid / total ads.
 *   REPORTING             5  Campaign selesai (done) / total campaign.
 *   IMPROVEMENT           5  Reuse MultimediaKpiService::improvementResult.
 *
 * Seluruh komponen memakai periode KPI yang sama (month/year). Data kosong
 * mengikuti pola existing: null → dikecualikan dari total (bukan 0/100 palsu);
 * kecuali LEADS dengan data nyata 0 → achievement 0 (lead dicatat 0).
 */
class DigitalMarketingKpiService
{
    public const CODES = [
        'OMZET_GLOBAL',
        'LEADS_QUALITY',
        'CONVERSION',
        'CPL',
        'CAMPAIGN_PERFORMANCE',
        'REPORTING',
        'IMPROVEMENT',
    ];

    public const BOBOT = [
        'OMZET_GLOBAL'         => 50,
        'LEADS_QUALITY'        => 15,
        'CONVERSION'           => 10,
        'CPL'                  => 10,
        'CAMPAIGN_PERFORMANCE' => 5,
        'REPORTING'            => 5,
        'IMPROVEMENT'          => 5,
    ];

    /** Cabang dengan data omzet ERP (penjualan). */
    public const SALES_UNITS = [1, 2, 3, 4];

    private $db;
    private $targetModel;

    public function __construct()
    {
        $this->db          = \Config\Database::connect();
        $this->targetModel = new ModelKpiTarget();
    }

    // ── Pure scoring (bisa diuji tanpa DB) ─────────────────────────

    public static function scoreOmzetGlobal(float $actual, float $targetToko, float $targetHo): ?float
    {
        if ($targetHo <= 0) {
            return null;
        }
        if ($actual <= $targetToko) {
            return 0.0;
        }
        if ($actual >= $targetHo) {
            return 100.0;
        }
        $range = $targetHo - $targetToko;
        if ($range <= 0) {
            return 100.0;
        }

        return round(($actual - $targetToko) / $range * 100, 4);
    }

    /**
     * LEADS_QUALITY = AVERAGE(achievement jumlah leads, achievement kualitas).
     * Absen data leads → 0 (bukan 100/error). Cap 100.
     */
    public static function leadsScore(int $actualLeads, int $qualifiedLeads, float $targetLeads): float
    {
        if ($targetLeads <= 0) {
            return 0.0;
        }
        $leadAch  = min(100.0, $actualLeads / $targetLeads * 100);
        $qualAch  = $actualLeads > 0 ? min(100.0, $qualifiedLeads / max($actualLeads, 1) * 100) : 0.0;

        return round(($leadAch + $qualAch) / 2, 4);
    }

    /** Conversion % = closing / lead × 100; lead 0 → null (tanpa div-by-zero). */
    public static function conversionScore(int $leadCs, int $closingCs, float $targetPct): ?float
    {
        if ($leadCs <= 0 || $targetPct <= 0) {
            return null;
        }
        $conv = $closingCs / $leadCs * 100;

        return round(min(100.0, $conv / $targetPct * 100), 4);
    }

    /**
     * CPL: achievement masing-masing (target / actual, cap 100 — CPL lebih
     * rendah = lebih efisien = skor lebih tinggi), lalu rata-rata yang tersedia.
     * Keduanya null → null.
     */
    public static function cplScore(?float $cplDatang, ?float $cplClosing, float $targetCpl): ?float
    {
        if ($targetCpl <= 0) {
            return null;
        }
        $sum   = 0.0;
        $count = 0;
        foreach ([$cplDatang, $cplClosing] as $cpl) {
            if ($cpl === null || $cpl <= 0) {
                continue;
            }
            $sum   += min(100.0, $targetCpl / $cpl * 100);
            $count++;
        }
        if ($count <= 0) {
            return null;
        }

        return round($sum / $count, 4);
    }

    public static function campaignPerformanceScore(int $totalAds, int $withCampaign): ?float
    {
        if ($totalAds <= 0) {
            return null;
        }

        return round(min(100.0, $withCampaign / $totalAds * 100), 4);
    }

    public static function reportingScore(int $totalCampaign, int $done): ?float
    {
        if ($totalCampaign <= 0) {
            return null;
        }

        return round(min(100.0, $done / $totalCampaign * 100), 4);
    }

    // ── Data operasional ───────────────────────────────────────────

    /** Total biaya iklan (Spending + PPN) dalam periode — Performa Ads. */
    public function adsBudget(int $month, int $year): float
    {
        $row = $this->db->query(
            "SELECT COALESCE(SUM(COALESCE(amount,0) + COALESCE(amount,0) * COALESCE(ppn,0) / 100), 0) AS t
             FROM marketing_ads_performance
             WHERE period_month = ? AND period_year = ?",
            [$month, $year]
        )->getRow();

        return round((float)$row->t, 2);
    }

    /** Leads dari Performa Ads (Hasil) dalam periode. */
    public function adsLeads(int $month, int $year): int
    {
        $row = $this->db->query(
            "SELECT COALESCE(SUM(hasil), 0) AS t
             FROM marketing_ads_performance
             WHERE period_month = ? AND period_year = ?",
            [$month, $year]
        )->getRow();

        return (int)$row->t;
    }

    /** Kualitas Leads (qualified) dari Performa Ads dalam periode. */
    public function adsQualified(int $month, int $year): int
    {
        $row = $this->db->query(
            "SELECT COALESCE(SUM(qualified), 0) AS t
             FROM marketing_ads_performance
             WHERE period_month = ? AND period_year = ?",
            [$month, $year]
        )->getRow();

        return (int)$row->t;
    }

    /** Lead CS (rekap harian manual) dalam periode. */
    public function leadCs(int $month, int $year): int
    {
        return (new MarketingRekapService())->monthlyLeadTotal($month, $year);
    }

    /** Datang CS (rekap harian manual) dalam periode. */
    public function datangCs(int $month, int $year): int
    {
        return (new MarketingRekapService())->monthlyDatangTotal($month, $year);
    }

    /** Closing CS (detail prospek manual CLOSING) dalam periode. */
    public function closingCs(int $month, int $year): int
    {
        return (new MarketingKpiService())->countCustomers($month, $year);
    }

    /** Valid ads count vs valid-campaign ads dalam periode (Campaign Performance). */
    public function campaignCoverage(int $month, int $year): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(
                    CASE
                        WHEN a.campaign_id IS NOT NULL
                         AND EXISTS(SELECT 1 FROM marketing_campaigns mc WHERE mc.id = a.campaign_id)
                        THEN 1 ELSE 0
                    END
                ), 0) AS valid
             FROM marketing_ads_performance a
             WHERE a.period_month = ? AND a.period_year = ?",
            [$month, $year]
        )->getRow();

        return ['total' => (int)$row->total, 'valid' => (int)$row->valid];
    }

    /** Reporting: % campaign selesai (done) terhadap total campaign pada periode. */
    public function reportingCoverage(int $month, int $year): array
    {
        $db = $this->db;
        $total = $db->table('marketing_campaigns')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->countAllResults();
        $done = $db->table('marketing_campaigns')
            ->where('status', ModelMarketingCampaign::STATUS_DONE)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->countAllResults();

        return ['total' => (int)$total, 'done' => (int)$done];
    }

    // ── Target ─────────────────────────────────────────────────────

    /** Target komponen divisi (unit HO 50 / unit employee / fallback unit NULL). */
    public function divTarget(string $code, int $unitId, string $context, ?string $date): ?float
    {
        $comp = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();
        if (!$comp) {
            return null;
        }
        $target = $this->targetModel->getTargetByKpiAndUnit((int)$comp->id, $unitId, $context, $date);
        if (!$target) {
            $target = $this->targetModel->getTargetByKpiAndUnit((int)$comp->id, 50, $context, $date);
        }
        if (!$target) {
            $target = $this->db->query(
                "SELECT target_value FROM kpi_targets
                 WHERE kpi_component_id = ? AND unit_id IS NULL
                   AND context IN (?, 'default')
                   AND effective_from <= ?
                   AND (effective_to IS NULL OR effective_to >= ?)
                 ORDER BY (context = ?) DESC, effective_from DESC LIMIT 1",
                [(int)$comp->id, $context, $date ?? date('Y-m-d'), $date ?? date('Y-m-d'), $context]
            )->getRow();
        }
        if (!$target) {
            return null;
        }

        return (float)$target->target_value;
    }

    /**
     * Target omzet global: Target Toko = SUM(batas_awal), Target HO = SUM(target_value)
     * dari kpi_targets OMSET_TOKO per cabang (sumber mekanisme target existing).
     *
     * @return array{toko:float, ho:float}
     */
    public function omzetTargets(string $context, ?string $date): array
    {
        $comp = $this->db->table('kpi_components')->where('code', 'OMSET_TOKO')->get()->getRow();
        $toko = 0.0;
        $ho   = 0.0;
        foreach (self::SALES_UNITS as $unit) {
            $target = $comp
                ? $this->targetModel->getTargetByKpiAndUnit((int)$comp->id, $unit, $context, $date)
                : null;
            if (!$target) {
                continue;
            }
            $toko += (float)($target->batas_awal ?? 0);
            $ho   += (float)$target->target_value;
        }

        return ['toko' => round($toko, 2), 'ho' => round($ho, 2)];
    }

    // ── Achievement per komponen (engine existing) ─────────────────

    /**
     * Data lengkap satu komponen utk engine KPI.
     *
     * @return array{achievement:?float, target:?float, actual:?float,
     *              shortfall:?float, source:string}
     */
    public function componentData(string $code, int $employeeId, int $unitId, int $month, int $year, string $context, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        switch ($code) {
            case 'OMZET_GLOBAL':
                $omset   = new OmsetTokoCalculator();
                $actual  = 0.0;
                foreach (self::SALES_UNITS as $unit) {
                    $actual += (float)$omset->calculate(0, $unit, $month, $year);
                }
                $targets = $this->omzetTargets($context, $date);
                return [
                    'achievement' => self::scoreOmzetGlobal((float)$actual, $targets['toko'], $targets['ho']),
                    'target'      => $targets['ho'],
                    'actual'      => round($actual, 2),
                    'shortfall'   => round(max($targets['ho'] - $actual, 0), 2),
                    'source'      => 'Omzet Global ERP (penjualan)',
                ];

            case 'LEADS_QUALITY':
                $leads     = $this->adsLeads($month, $year);
                $qualified = $this->adsQualified($month, $year);
                $target    = $this->divTarget('LEADS_QUALITY', $unitId, $context, $date) ?? 3000;
                return [
                    'achievement' => self::leadsScore($leads, $qualified, $target),
                    'target'      => $target,
                    'actual'      => $leads,
                    'shortfall'   => round(max($target - $leads, 0), 2),
                    'source'      => 'Input Performa Ads → Hasil → Leads',
                ];

            case 'CONVERSION':
                $leadCs   = $this->leadCs($month, $year);
                $closing  = $this->closingCs($month, $year);
                $target   = $this->divTarget('CONVERSION', $unitId, $context, $date) ?? 30;
                $actualPct = $leadCs > 0 ? round($closing / $leadCs * 100, 2) : null;
                return [
                    'achievement' => self::conversionScore($leadCs, $closing, $target),
                    'target'      => $target,
                    'actual'      => $actualPct,
                    'shortfall'   => $actualPct === null ? null : round(max($target - $actualPct, 0), 2),
                    'source'      => 'Data CS (rekap harian → closing prospek)',
                ];

            case 'CPL':
                $budget   = $this->adsBudget($month, $year);
                $datang   = $this->datangCs($month, $year);
                $closing  = $this->closingCs($month, $year);
                $target   = $this->divTarget('CPL', $unitId, $context, $date) ?? 250000;
                $cplDatang  = $datang > 0  ? round($budget / $datang, 2) : null;
                $cplClosing = $closing > 0 ? round($budget / $closing, 2) : null;
                $combined = null;
                $count = 0;
                foreach ([$cplDatang, $cplClosing] as $cpl) {
                    if ($cpl !== null) {
                        $combined = ($combined ?? 0.0) + $cpl;
                        $count++;
                    }
                }
                return [
                    'achievement' => self::cplScore($cplDatang, $cplClosing, $target),
                    'target'      => $target,
                    'actual'      => $count > 0 ? round($combined / $count, 2) : null,
                    'shortfall'   => null,
                    'source'      => 'Budget Performa Ads / Datang & Closing CS',
                ];

            case 'CAMPAIGN_PERFORMANCE':
                $cv = $this->campaignCoverage($month, $year);
                $pct = $cv['total'] > 0 ? round($cv['valid'] / $cv['total'] * 100, 2) : null;
                return [
                    'achievement' => self::campaignPerformanceScore($cv['total'], $cv['valid']),
                    'target'      => 100,
                    'actual'      => $pct,
                    'shortfall'   => $pct === null ? null : round(max(100 - $pct, 0), 2),
                    'source'      => 'Performa Ads → Campaign',
                ];

            case 'REPORTING':
                $rc = $this->reportingCoverage($month, $year);
                $pct = $rc['total'] > 0 ? round($rc['done'] / $rc['total'] * 100, 2) : null;
                return [
                    'achievement' => self::reportingScore($rc['total'], $rc['done']),
                    'target'      => 100,
                    'actual'      => $pct,
                    'shortfall'   => $pct === null ? null : round(max(100 - $pct, 0), 2),
                    'source'      => 'Campaign selesai / total campaign',
                ];

            case 'IMPROVEMENT':
                $res = (new MultimediaKpiService())->improvementResult($employeeId, $month, $year);
                return [
                    'achievement' => $res['achievement'],
                    'target'      => (float)MultimediaKpiService::TARGET_IMPROVEMENT,
                    'actual'      => (float)$res['approved'],
                    'shortfall'   => round(max(MultimediaKpiService::TARGET_IMPROVEMENT - $res['approved'], 0), 2),
                    'source'      => 'Mechanism Improvement Multimedia',
                ];
        }

        return ['achievement' => null, 'target' => null, 'actual' => null, 'shortfall' => null, 'source' => ''];
    }

    // ── Ringkasan dashboard ────────────────────────────────────────

    /**
     * Ringkasan 7 KPI div. utk dashboard Marketing. Struktur output mengikuti
     * MarketingKpiService::monthlySummary (items + cost + channel + weightsum).
     */
    public function monthlySummary(int $month, int $year, ?int $employeeId = null, int $unitId = 50): array
    {
        $context = 'default';
        $codeToData = [];
        foreach (self::CODES as $code) {
            $codeToData[$code] = $this->componentData($code, $employeeId ?? 0, $unitId, $month, $year, $context);
        }

        $labels = [
            'OMZET_GLOBAL'         => ['Omzet Global Perusahaan', fn($d) => 'Rp ' . number_format((float)$d['actual'], 0, ',', '.')],
            'LEADS_QUALITY'        => ['Leads & Kualitas Leads', fn($d) => number_format((float)$d['actual'], 0, ',', '.') . ' leads'],
            'CONVERSION'           => ['Conversion', fn($d) => $d['actual'] === null ? 'N/A' : number_format((float)$d['actual'], 2, ',', '.') . '%'],
            'CPL'                  => ['Cost Per Lead', fn($d) => $d['actual'] === null ? 'N/A' : 'Rp ' . number_format((float)$d['actual'], 0, ',', '.')],
            'CAMPAIGN_PERFORMANCE' => ['Campaign Performance', fn($d) => $d['actual'] === null ? 'N/A' : number_format((float)$d['actual'], 2, ',', '.') . '%'],
            'REPORTING'            => ['Reporting', fn($d) => $d['actual'] === null ? 'N/A' : number_format((float)$d['actual'], 2, ',', '.') . '%'],
            'IMPROVEMENT'          => ['Improvement', fn($d) => number_format((float)$d['actual'], 0, ',', '.') . ' disetujui'],
        ];

        $items = [];
        foreach (self::CODES as $code) {
            $d     = $codeToData[$code];
            $label = $labels[$code];
            $items[] = [
                'key'           => $code,
                'name'          => $label[0],
                'bobot'         => self::BOBOT[$code],
                'target_label'  => $this->targetLabel($code, $d['target']),
                'actual_label'  => $label[1]($d),
                'achievement'   => $d['achievement'] === null ? null : round((float)$d['achievement'], 2),
                'key_value'     => $d['actual'],
            ];
        }

        return [
            'items'     => $items,
            'cost'      => ['ads_cost' => $this->adsBudget($month, $year), 'paid_lead' => $this->adsLeads($month, $year)],
            'channel'   => ['rows' => [], 'kpi_achievement' => null, 'kpi_metrics' => 0],
            'weightsum' => array_sum(self::BOBOT),
        ];
    }

    private function targetLabel(string $code, $target): string
    {
        if ($target === null || $target === '') {
            return 'sesuai target';
        }
        switch ($code) {
            case 'OMZET_GLOBAL':
                return 'Rp ' . number_format((float)$target, 0, ',', '.');
            case 'LEADS_QUALITY':
                return number_format((float)$target, 0, ',', '.') . ' leads/bulan';
            case 'CONVERSION':
                return number_format((float)$target, 2, ',', '.') . '%';
            case 'CPL':
                return '≤ Rp ' . number_format((float)$target, 0, ',', '.');
            case 'CAMPAIGN_PERFORMANCE':
            case 'REPORTING':
                return '100%';
            case 'IMPROVEMENT':
                return number_format((float)$target, 0, ',', '.') . ' improvement/bulan';
        }

        return 'sesuai target';
    }
}