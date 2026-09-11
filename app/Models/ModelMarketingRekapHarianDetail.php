<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMarketingRekapHarianDetail extends Model
{
    protected $table = 'marketing_rekap_harian_detail';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    /** CASCADE via FK — detail terhapus otomatis saat rekap dihapus. */
    protected $allowedFields = [
        'rekap_id',
        'platform',
        'non_iklan',
        'iklan',
        'total',
        'prospek',
        'datang',
        'rate',
    ];

    public function getByRekapId(int $rekapId): array
    {
        return $this->where('rekap_id', $rekapId)->orderBy('id', 'ASC')->findAll();
    }
}