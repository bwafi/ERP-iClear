<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelContentUnit extends Model
{
    protected $table = 'content_units';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['content_id', 'unit_id'];

    public function replaceForContent(int $contentId, array $unitIds)
    {
        $this->where('content_id', $contentId)->delete();

        foreach (array_unique(array_filter(array_map('intval', $unitIds))) as $unitId) {
            $this->insert(['content_id' => $contentId, 'unit_id' => $unitId]);
        }

        return true;
    }
}