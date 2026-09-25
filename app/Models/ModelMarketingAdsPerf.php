<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Performa iklan Ads per (tanggal, campaign ± channel).
 * Dipakai oleh Laporan Digital Marketing (read-only) — sumber selain spending.
 *
 * Campaign wajib memilih Campaign Digital Marketing (campaign_id → FK
 * marketing_campaigns), bukan free text. `qualified` = Kualitas Leads
 * (jenis lead yang masuk kategori kualifikasi sesuai input Performa Ads).
 */
class ModelMarketingAdsPerf extends Model
{
    protected $table = 'marketing_ads_performance';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'period_month',
        'period_year',
        'tanggal',
        'channel_id',
        'channel_ids',
        'unit_id',
        'campaign_id',
        'amount',
        'daily_budget',
        'ppn',
        'objective',
        'reach',
        'impression',
        'klik',
        'hasil',
        'qualified',
        'note',
        'created_by',
    ];

    /**
     * Baris Ads dalam periode, dengan nama campaign (kalau campaign_id valid).
     *
     * @param int         $month
     * @param int         $year
     * @param int|null    $campaignId filter campaign (0/null = semua)
     */
    public function findByPeriod(int $month, int $year, ?int $campaignId = null): array
    {
        $q = $this->select('marketing_ads_performance.*, mc.nama AS campaign_name')
            ->join('marketing_campaigns mc', 'mc.id = marketing_ads_performance.campaign_id', 'left')
            ->where('marketing_ads_performance.period_month', $month)
            ->where('marketing_ads_performance.period_year', $year);
        if ($campaignId !== null && $campaignId > 0) {
            $q->where('marketing_ads_performance.campaign_id', $campaignId);
        }

        return $q->orderBy('marketing_ads_performance.tanggal', 'DESC')
            ->orderBy('marketing_ads_performance.id', 'DESC')
            ->findAll();
    }

    public function getByUnique(int $month, int $year, int $campaignId, ?string $tanggal, int $channelId, int $unitId)
    {
        return $this->where([
            'period_month' => $month,
            'period_year'  => $year,
            'campaign_id'  => $campaignId > 0 ? $campaignId : null,
            'tanggal'      => $tanggal !== '' ? $tanggal : null,
            'channel_id'   => $channelId > 0 ? $channelId : null,
            'unit_id'      => $unitId > 0 ? $unitId : null,
        ])->first();
    }

    /** Daftar campaign (id + nama) utk filter/select. */
    public function campaigns(int $month, int $year): array
    {
        $rows = (new ModelMarketingCampaign())->options($month, $year);

        return array_map(fn($c) => ['id' => (int)$c->id, 'nama' => $c->nama], $rows);
    }
}