<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMarketingAdsCost extends Model
{
    protected $table = 'marketing_ads_cost';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['period_month', 'period_year', 'tanggal', 'channel_id', 'campaign', 'amount', 'note', 'created_by'];

    public function findByPeriod(int $month, int $year): array
    {
        return $this->where('period_month', $month)
            ->where('period_year', $year)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function getByUnique(int $month, int $year, int $channelId, string $campaign)
    {
        return $this->where([
            'period_month' => $month,
            'period_year'  => $year,
            'channel_id'   => $channelId > 0 ? $channelId : null,
            'campaign'     => $campaign,
        ])->first();
    }

    public function sumByPeriod(int $month, int $year): float
    {
        $row = $this->where('period_month', $month)
            ->where('period_year', $year)
            ->selectSum('amount', 'total')
            ->first();

        return (float)($row->total ?? 0);
    }
}