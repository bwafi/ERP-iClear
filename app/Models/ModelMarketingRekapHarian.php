<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMarketingRekapHarian extends Model
{
    protected $table = 'marketing_rekap_harian';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'tanggal',
        'unit_id',
        'total_lead_wa_dm',
        'lead_total_iklan_dashboard',
        'created_by',
    ];

    /** Satu rekap per (unit, tanggal). */
    public function getByUnitDate(int $unitId, string $tanggal): ?object
    {
        return $this->where('unit_id', $unitId)->where('tanggal', $tanggal)->first();
    }
}