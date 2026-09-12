<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Performa iklan Ads per (tanggal, campaign) ± channel.
 * Dipakai oleh Laporan Digital Marketing (read-only) — sumberselain spending.
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
        'campaign',
        'amount',
        'daily_budget',
        'ppn',
        'objective',
        'reach',
        'impression',
        'klik',
        'hasil',
        'note',
        'created_by',
    ];

    public function findByPeriod(int $month, int $year, ?string $campaign = null): array
    {
        $q = $this->where('period_month', $month)->where('period_year', $year);
        if ($campaign !== null && $campaign !== '') {
            $q->where('campaign', $campaign);
        }

        return $q->orderBy('tanggal', 'DESC')->orderBy('id', 'DESC')->findAll();
    }

    public function getByUnique(int $month, int $year, string $campaign, ?string $tanggal, int $channelId, int $unitId)
    {
        return $this->where([
            'period_month' => $month,
            'period_year'  => $year,
            'campaign'     => $campaign,
            'tanggal'      => $tanggal !== '' ? $tanggal : null,
            'channel_id'   => $channelId > 0 ? $channelId : null,
            'unit_id'      => $unitId > 0 ? $unitId : null,
        ])->first();
    }

    public function campaigns(int $month, int $year): array
    {
        $rows = $this->select('campaign')
            ->distinct()
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('campaign !=', '')
            ->orderBy('campaign', 'ASC')
            ->findAll();

        return array_map(fn($r) => $r->campaign, $rows);
    }
}