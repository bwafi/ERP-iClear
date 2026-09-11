<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelChannelPerformance extends Model
{
    protected $table = 'channel_performance';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['channel_id', 'metric_id', 'period_month', 'period_year', 'actual', 'target_growth', 'note', 'created_by'];

    public function getByUnique(int $channelId, int $metricId, int $month, int $year)
    {
        return $this->where([
            'channel_id'   => $channelId,
            'metric_id'    => $metricId,
            'period_month' => $month,
            'period_year'  => $year,
        ])->first();
    }

    public function findByPeriod(int $month, int $year): array
    {
        return $this->where('period_month', $month)
            ->where('period_year', $year)
            ->findAll();
    }
}