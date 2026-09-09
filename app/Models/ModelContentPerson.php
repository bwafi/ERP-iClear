<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelContentPerson extends Model
{
    protected $table = 'content_people';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['content_id', 'akun_id', 'role'];

    /**
     * Ganti seluruh relasi orang pada content.
     *
     * @param int   $contentId
     * @param array $talentIds   array ID akun berperan TALENT
     * @param array $creativeIds array ID akun berperan CREATIVE
     */
    public function replaceForContent(int $contentId, array $talentIds, array $creativeIds)
    {
        $this->where('content_id', $contentId)->delete();

        $rows = [];
        foreach (array_unique(array_filter(array_map('intval', $talentIds))) as $id) {
            $rows[] = ['content_id' => $contentId, 'akun_id' => $id, 'role' => 'TALENT'];
        }
        foreach (array_unique(array_filter(array_map('intval', $creativeIds))) as $id) {
            $rows[] = ['content_id' => $contentId, 'akun_id' => $id, 'role' => 'CREATIVE'];
        }

        if (!empty($rows)) {
            $this->insertBatch($rows, true, 50);
        }

        return true;
    }
}