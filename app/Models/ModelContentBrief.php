<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * content_briefs — brief/requirement per content (1:1).
 */
class ModelContentBrief extends Model
{
    protected $table = 'content_briefs';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'content_id', 'isi_brief', 'requirement', 'approved_by', 'approved_at', 'created_by',
    ];

    public function forContent(int $contentId)
    {
        return $this->where('content_id', $contentId)->first();
    }

    public function upsertForContent(
        int $contentId,
        ?string $isiBrief,
        ?string $requirement,
        ?int $approvedBy = null
    ): bool {
        $isiBrief    = $isiBrief !== null ? trim((string)$isiBrief) : null;
        $requirement = $requirement !== null ? trim((string)$requirement) : null;

        $row = $this->forContent($contentId);
        $now = $isiBrief !== '' || $requirement !== '' ? date('Y-m-d H:i:s') : null;

        if ($row) {
            if ($isiBrief === '' && $requirement === '') {
                $this->where('content_id', $contentId)->delete();
                return true;
            }
            $data = ['isi_brief' => $isiBrief !== '' ? $isiBrief : null, 'requirement' => $requirement !== '' ? $requirement : null];
            if ($approvedBy !== null && empty($row->approved_at)) {
                $data['approved_by'] = $approvedBy;
                $data['approved_at'] = $now;
            }
            return $this->update($row->id, $data);
        }

        if ($isiBrief === '' && $requirement === '') {
            return true;
        }

        return $this->insert([
            'content_id'  => $contentId,
            'isi_brief'   => $isiBrief !== '' ? $isiBrief : null,
            'requirement' => $requirement !== '' ? $requirement : null,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedBy ? $now : null,
        ]) !== false;
    }
}