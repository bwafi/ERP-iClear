<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelBrandChecklistItem extends Model
{
    protected $table = 'brand_checklist_items';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['code', 'name', 'is_active'];

    public function optionsActive(): array
    {
        return $this->where('is_active', 1)->orderBy('id', 'ASC')->findAll();
    }
}