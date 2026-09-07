<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelAsetKpi extends Model
{
    protected $table = 'aset_kpi';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit',
        'asset',
        'kode_aset',
        'quantity',
        'is_active',
        'keterangan',
        'created_by',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
}