<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * content_campaigns — master campaign marketing (periode + target konten).
 */
class ModelContentCampaign extends Model
{
    protected $table = 'content_campaigns';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'nama', 'deskripsi', 'period_month', 'period_year',
        'target_jumlah_konten', 'target_deadline', 'status', 'pic', 'created_by',
    ];

    public const STATUSES = ['draft', 'active', 'done'];

    public function untukPeriode(int $month, int $year, ?string $status = null): array
    {
        $builder = $this->where('period_month', $month)->where('period_year', $year);
        if ($status !== null) {
            $builder->where('status', $status);
        }
        return $builder->orderBy('id', 'ASC')->findAll();
    }
}