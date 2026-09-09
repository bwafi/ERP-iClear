<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPlatform extends Model
{
    protected $table = 'platforms';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['code', 'name', 'is_active'];

    public function optionsActive(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }
}