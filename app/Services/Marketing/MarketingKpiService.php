<?php

namespace App\Services\Marketing;

use App\Models\ModelMarketingLead;
use App\Services\Konten\ContentKpiService;

/**
 * KPI Digital Marketing / Kepala Divisi (jabatan 43).
 *
 * MENGULANGI BUKAN engine KPI: service ini menghitung achievement tiap komponen
 * dari DATA OPERASIONAL (marketing_lead, marketing_ads_cost, transaksi pelanggan
 * hasil lead) lalu diserahkan ke engine KPI existing via KpiCalculationService.
 *
 * Rumus (seluruh actual dihitung otomatis, bukan input manual):
 *   Conversion     = Customer Marketing / Lead Marketing × 100
 *   CPL            = Ads Cost / Paid Lead  →  achievement = Target CPL / CPL × 100
 *   Omzet Marketing= omzet penjualan + service dari customer asal lead
 *   ROAS           = Omzet Marketing / Ads Cost  →  achievement = ROAS / Target × 100
 *   Pertumbuhan    = reuse ContentKpiService::channelGrowthSummary (per channel+metric)
 *
 * Customer KPI hanya menghitung customer yang terhubung dari lead (status WON).
 * Omzet tidak pernah memakai total omzet perusahaan.
 */
class MarketingKpiService
{
    // Target default (configurable, mengikuti pola ContentKpiService).
    public const TARGET_LEAD_PER_BULAN        = 60;
    public const TARGET_CUSTOMER_PER_BULAN    = 20;
    public const TARGET_CONVERSION_PCT        = 30;
    public const TARGET_CPL                   = 250000;
    public const TARGET_OMZET_MARKETING       = 300000000;
    public const TARGET_ROAS                  = 1.5;

    public const CODE_LEAD        = 'LEAD_MARKETING';
    public const CODE_CUSTOMER    = 'CUSTOMER_MARKETING';
    public const CODE_CONVERSION  = 'CONVERSION_MARKETING';
    public const CODE_CPL         = 'CPL';
    public const CODE_OMZET       = 'OMZET_MARKETING';
    public const CODE_ROAS        = 'ROAS_MARKETING';
    public const CODE_GROWTH      = 'CHANNEL_GROWTH';

    public const COMPONENT_CODES = [
        self::CODE_LEAD,
        self::CODE_CUSTOMER,
        self::CODE_CONVERSION,
        self::CODE_CPL,
        self::CODE_OMZET,
        self::CODE_ROAS,
        self::CODE_GROWTH,
    ];

    private const BOBOT = [
        self::CODE_LEAD       => 15,
        self::CODE_CUSTOMER   => 15,
        self::CODE_CONVERSION => 15,
        self::CODE_CPL        => 10,
        self::CODE_OMZET      => 20,
        self::CODE_ROAS       => 15,
        self::CODE_GROWTH     => 10,
    ];

    private $db;
    private $leadModel;

    public function __construct()
    {
        $this->db        = \Config\Database::connect();
        $this->leadModel = new ModelMarketingLead();
    }

    // ── Data operasional ──────────────────────────────────────────

    /** Jumlah lead masuk dalam periode. SUMBER = rekap manual harian CS. */
    public function countLeads(int $month, int $year): int
    {
        return (new MarketingRekapService())->monthlyLeadTotal($month, $year);
    }

    /** Lead berbayar (Iklan) dalam periode. SUMBER = rekap manual harian CS. */
    public function paidLeads(int $month, int $year): int
    {
        return (new MarketingRekapService())->monthlyPaidTotal($month, $year);
    }

    /**
     * Customer hasil closing: detail prospek manual (non-Kommo) dengan
     * status CLOSED dalam periode (berdasarkan tanggal_won).
     * SUMBER = Detail Prospek (manual); baris Kommo dikeluarkan.
     */
    public function countCustomers(int $month, int $year): int
    {
        $m = sprintf('%04d-%02d', $year, $month);
        return (int)$this->db->query(
            "SELECT COUNT(*) c FROM marketing_lead
             WHERE status = 'CLOSED' AND kommo_lead_id IS NULL
               AND DATE_FORMAT(tanggal_won, '%Y-%m') = ?",
            [$m]
        )->getRow()->c;
    }

    /** ID customer asal detail prospek manual CLOSED (tanpa batas periode). */
    public function marketingCustomerIds(): array
    {
        $rows = $this->db->query(
            "SELECT DISTINCT customer_id FROM marketing_lead
             WHERE status = 'CLOSED' AND kommo_lead_id IS NULL AND customer_id IS NOT NULL"
        )->getResult();

        return array_values(array_unique(array_map('intval', array_column($rows, 'customer_id'))));
    }

    public function adsCost(int $month, int $year): float
    {
        $row = $this->db->query(
            "SELECT COALESCE(SUM(amount),0) t FROM marketing_ads_cost WHERE period_month = ? AND period_year = ?",
            [$month, $year]
        )->getRow();

        return (float)$row->t;
    }

    /**
     * Omzet Marketing: TOTAL omzet dari detail prospek manual (non-Kommo)
     * yang berstatus CLOSED pada periode (berdasarkan tanggal_won).
     * TIDAK memakai transaksi customer; TIDAK memakai data Kommo.
     */
    public function marketingRevenue(int $month, int $year): float
    {
        $m = sprintf('%04d-%02d', $year, $month);
        $row = $this->db->query(
            "SELECT COALESCE(SUM(omset),0) t FROM marketing_lead
             WHERE status = 'CLOSED' AND kommo_lead_id IS NULL
               AND DATE_FORMAT(tanggal_won, '%Y-%m') = ?",
            [$m]
        )->getRow();

        return round((float)$row->t, 2);
    }

    /** Conversion % = customer / lead × 100 (null aman). */
    public function conversionPct(int $month, int $year): ?float
    {
        $leads = $this->countLeads($month, $year);
        if ($leads <= 0) {
            return null;
        }
        return round($this->countCustomers($month, $year) / $leads * 100, 2);
    }

    /** CPL = ads cost / paid lead (null = belum ada data). */
    public function cpl(int $month, int $year): ?float
    {
        $cost = $this->adsCost($month, $year);
        $paid = $this->paidLeads($month, $year);
        if ($paid <= 0) {
            return null;
        }
        return round($cost / $paid, 2);
    }

    /** ROAS = revenue / ads cost (null = ads cost 0 atau belum ada data). */
    public function roas(int $month, int $year): ?float
    {
        $cost  = $this->adsCost($month, $year);
        $omzet = $this->marketingRevenue($month, $year);
        if ($cost <= 0) {
            return null;
        }
        return round($omzet / $cost, 2);
    }

    // ── Achievement per komponen (engine existing) ────────────────

    public function scoreByCode(string $code, int $month, int $year): ?float
    {
        switch ($code) {
            case self::CODE_LEAD:
                $actual = $this->countLeads($month, $year);
                return self::cap100($actual / self::TARGET_LEAD_PER_BULAN * 100);

            case self::CODE_CUSTOMER:
                $actual = $this->countCustomers($month, $year);
                return self::cap100($actual / self::TARGET_CUSTOMER_PER_BULAN * 100);

            case self::CODE_CONVERSION:
                $conv = $this->conversionPct($month, $year);
                if ($conv === null) {
                    return null;
                }
                return self::cap100($conv / self::TARGET_CONVERSION_PCT * 100);

            case self::CODE_CPL:
                $cost = $this->adsCost($month, $year);
                $paid = $this->paidLeads($month, $year);
                if ($paid <= 0 || $cost <= 0) {
                    return null; // belum ada data CPL yang valid
                }
                $actualCpl = $cost / $paid;
                return self::cap100(self::TARGET_CPL / $actualCpl * 100);

            case self::CODE_OMZET:
                $actual = $this->marketingRevenue($month, $year);
                return self::cap100($actual / self::TARGET_OMZET_MARKETING * 100);

            case self::CODE_ROAS:
                $roas = $this->roas($month, $year);
                if ($roas === null) {
                    return null;
                }
                return self::cap100($roas / self::TARGET_ROAS * 100);

            case self::CODE_GROWTH:
                return (new ContentKpiService())->channelGrowthSummary($month, $year)['kpi_achievement'];
        }

        return null;
    }

    private static function cap100(float $v): float
    {
        return min(100.0, $v);
    }

    /**
     * Ringkasan 7 KPI utk dashboard: key, name, bobot, target label, actual
     * label, achievement. Seluruh actual dihitung otomatis.
     */
    public function monthlySummary(int $month, int $year): array
    {
        $leads    = $this->countLeads($month, $year);
        $customers = $this->countCustomers($month, $year);
        $conv     = $this->conversionPct($month, $year);
        $cost     = $this->adsCost($month, $year);
        $paid     = $this->paidLeads($month, $year);
        $cpl      = $this->cpl($month, $year);
        $omzet    = $this->marketingRevenue($month, $year);
        $roas     = $this->roas($month, $year);
        $channel  = (new ContentKpiService())->channelGrowthSummary($month, $year);

        $items = [];

        $items[] = [
            'key'   => self::CODE_LEAD,
            'name'  => 'Lead',
            'bobot' => self::BOBOT[self::CODE_LEAD],
            'target_label' => self::TARGET_LEAD_PER_BULAN . ' /bulan',
            'actual_label' => number_format($leads, 0, ',', '.') . ' lead',
            'achievement'  => $leads > 0 ? round(min(100.0, $leads / self::TARGET_LEAD_PER_BULAN * 100), 2) : null,
            'key_value'    => $leads,
        ];
        $items[] = [
            'key'   => self::CODE_CUSTOMER,
            'name'  => 'Customer',
            'bobot' => self::BOBOT[self::CODE_CUSTOMER],
            'target_label' => self::TARGET_CUSTOMER_PER_BULAN . ' /bulan',
            'actual_label' => number_format($customers, 0, ',', '.') . ' customer',
            'achievement'  => $customers > 0 ? round(min(100.0, $customers / self::TARGET_CUSTOMER_PER_BULAN * 100), 2) : null,
            'key_value'    => $customers,
        ];
        $items[] = [
            'key'   => self::CODE_CONVERSION,
            'name'  => 'Conversion',
            'bobot' => self::BOBOT[self::CODE_CONVERSION],
            'target_label' => self::TARGET_CONVERSION_PCT . '%',
            'actual_label' => $conv === null ? 'N/A' : number_format($conv, 2, ',', '.') . '%',
            'achievement'  => $conv === null ? null : round(min(100.0, $conv / self::TARGET_CONVERSION_PCT * 100), 2),
            'key_value'    => $conv,
        ];
        $items[] = [
            'key'   => self::CODE_CPL,
            'name'  => 'Cost Per Lead',
            'bobot' => self::BOBOT[self::CODE_CPL],
            'target_label' => '≤ Rp ' . number_format(self::TARGET_CPL, 0, ',', '.'),
            'actual_label' => $cpl === null ? 'N/A' : 'Rp ' . number_format($cpl, 0, ',', '.'),
            'achievement'  => ($cpl !== null && $cpl > 0) ? round(min(100.0, self::TARGET_CPL / $cpl * 100), 2) : null,
            'key_value'    => $cpl,
        ];
        $items[] = [
            'key'   => self::CODE_OMZET,
            'name'  => 'Omzet Marketing',
            'bobot' => self::BOBOT[self::CODE_OMZET],
            'target_label' => 'Rp ' . number_format(self::TARGET_OMZET_MARKETING, 0, ',', '.'),
            'actual_label' => 'Rp ' . number_format($omzet, 0, ',', '.'),
            'achievement'  => $omzet > 0 ? round(min(100.0, $omzet / self::TARGET_OMZET_MARKETING * 100), 2) : null,
            'key_value'    => $omzet,
        ];
        $items[] = [
            'key'   => self::CODE_ROAS,
            'name'  => 'ROI/ROAS',
            'bobot' => self::BOBOT[self::CODE_ROAS],
            'target_label' => number_format(self::TARGET_ROAS, 2, ',', '.') . '×',
            'actual_label' => $roas === null ? 'N/A' : number_format($roas, 2, ',', '.') . '×',
            'achievement'  => $roas === null ? null : round(min(100.0, $roas / self::TARGET_ROAS * 100), 2),
            'key_value'    => $roas,
        ];

        $costInfo = ['ads_cost' => $cost, 'paid_lead' => $paid];

        $items[] = [
            'key'   => self::CODE_GROWTH,
            'name'  => 'Pertumbuhan Channel',
            'bobot' => self::BOBOT[self::CODE_GROWTH],
            'target_label' => 'sesuai target growth',
            'actual_label' => ($channel['kpi_achievement'] ?? null) === null ? 'N/A' : 'rata2 ' . number_format($channel['kpi_achievement'], 2, ',', '.') . '%',
            'achievement'  => $channel['kpi_achievement'] ?? null,
            'key_value'    => $channel['kpi_achievement'] ?? null,
        ];

        return [
            'items'         => $items,
            'cost'          => $costInfo,
            'channel'       => $channel,
            'weightsum'     => array_sum(self::BOBOT),
        ];
    }

    // ── Data chart (ApexCharts) ───────────────────────────────────

    /**
     * Jumlah detail prospek manual per status dalam periode (donut chart).
     * Hanya baris manual (non-Kommo).
     */
    public function leadsByStatus(int $month, int $year): array
    {
        $m    = sprintf('%04d-%02d', $year, $month);
        $rows = $this->db->query(
            "SELECT status, COUNT(*) c FROM marketing_lead
             WHERE kommo_lead_id IS NULL
               AND DATE_FORMAT(tanggal, '%Y-%m') = ?
             GROUP BY status",
            [$m]
        )->getResult();

        $result = [
            'PROSPEK' => 0,
            'BOOKING' => 0,
            'DATANG'  => 0,
            'CLOSED'  => 0,
            'BATAL'   => 0,
        ];
        foreach ($rows as $r) {
            if (isset($result[$r->status])) {
                $result[$r->status] = (int)$r->c;
            }
        }

        return $result;
    }

    /** Total biaya iklan per channel dalam periode (untuk bar chart). */
    public function adsCostByChannel(int $month, int $year): array
    {
        $rows = $this->db->query(
            "SELECT COALESCE(ch.name, 'Umum') channel_name, COALESCE(SUM(ad.amount),0) total
             FROM marketing_ads_cost ad
             LEFT JOIN channel ch ON ch.id = ad.channel_id
             WHERE ad.period_month = ? AND ad.period_year = ?
             GROUP BY ad.channel_id, ch.name
             ORDER BY total DESC",
            [$month, $year]
        )->getResult();

        return array_map(
            fn($r) => ['channel' => $r->channel_name, 'amount' => (float)$r->total],
            $rows
        );
    }

    /**
     * Deret tren beberapa bulan terakhir (termasuk periode terpilih):
     * leads masuk, won, biaya iklan, omzet marketing.
     */
    public function trendSeries(int $month, int $year, int $months = 6): array
    {
        $months = max(2, min(12, $months));
        $time   = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $labels = [];
        $leads  = [];
        $won    = [];
        $ads    = [];
        $omzet  = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $p       = (clone $time)->modify("-{$i} month");
            $m       = (int)$p->format('n');
            $y       = (int)$p->format('Y');
            $labels[] = $p->format('M y');
            $leads[]  = $this->countLeads($m, $y);
            $won[]    = $this->countCustomers($m, $y);
            $ads[]    = round($this->adsCost($m, $y));
            $omzet[]  = round($this->marketingRevenue($m, $y));
        }

        return [
            'labels' => $labels,
            'leads'  => $leads,
            'won'    => $won,
            'ads'    => $ads,
            'omzet'  => $omzet,
        ];
    }
}