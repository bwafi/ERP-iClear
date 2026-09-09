<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPublicationPerformance extends Model
{
    protected $table = 'publication_performance';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['publication_id', 'metric_id', 'period_month', 'period_year', 'target', 'actual', 'achievement'];

    public function getByUnique(int $publicationId, int $metricId, int $month, int $year)
    {
        return $this->where([
            'publication_id' => $publicationId,
            'metric_id'      => $metricId,
            'period_month'   => $month,
            'period_year'    => $year,
        ])->first();
    }
}