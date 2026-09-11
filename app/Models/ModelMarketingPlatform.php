<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMarketingPlatform extends Model
{
    protected $table = 'marketing_platform';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['name', 'urutan', 'is_active'];

    public function active(): array
    {
        return $this->where('is_active', 1)->orderBy('urutan', 'ASC')->orderBy('id', 'ASC')->findAll();
    }
}