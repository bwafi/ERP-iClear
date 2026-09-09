<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelChannelMetric extends Model
{
    protected $table = 'channel_metric';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['channel_id', 'code', 'name', 'is_kpi', 'target_growth', 'is_active'];

    public function byChannel(int $channelId): array
    {
        return $this->where('channel_id', $channelId)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}