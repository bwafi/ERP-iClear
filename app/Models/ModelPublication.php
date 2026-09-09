<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPublication extends Model
{
    protected $table = 'publications';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['content_id', 'unit_id', 'platform_id', 'link', 'status', 'published_at', 'created_by'];

    public function getById($id)
    {
        return $this->select('
                publications.*,
                unit.NAMA_UNIT,
                platforms.name AS platform_name,
                platforms.code AS platform_code
            ')
            ->join('unit', 'unit.idunit = publications.unit_id', 'left')
            ->join('platforms', 'platforms.id = publications.platform_id', 'left')
            ->where('publications.id', $id)
            ->first();
    }

    public function performances(int $publicationId): array
    {
        return $this->db->table('publication_performance')
            ->select('publication_performance.*, performance_metrics.name AS metric_name')
            ->join('performance_metrics', 'performance_metrics.id = publication_performance.metric_id', 'left')
            ->where('publication_performance.publication_id', $publicationId)
            ->orderBy('publication_performance.period_year', 'ASC')
            ->orderBy('publication_performance.period_month', 'ASC')
            ->get()
            ->getResult();
    }
}