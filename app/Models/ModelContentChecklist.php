<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelContentChecklist extends Model
{
    protected $table = 'content_checklists';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = ['content_id', 'item_id', 'is_checked', 'checked_by', 'checked_at'];

    /**
     * Pastikan semua item checklist aktif tersedia untuk content.
     */
    public function ensureItemsForContent(int $contentId, array $itemIds)
    {
        $existing = array_map(
            'intval',
            $this->where('content_id', $contentId)->findColumn('item_id') ?? []
        );

        $rows = [];
        foreach (array_map('intval', $itemIds) as $itemId) {
            if (!in_array($itemId, $existing, true)) {
                $rows[] = [
                    'content_id' => $contentId,
                    'item_id'    => $itemId,
                    'is_checked' => 0,
                ];
            }
        }

        if (!empty($rows)) {
            $this->insertBatch($rows, true, 50);
        }

        return true;
    }

    public function toggle(int $contentId, int $itemId, int $checkerId): array
    {
        $row = $this->where('content_id', $contentId)->where('item_id', $itemId)->first();

        if (!$row) {
            $this->insert([
                'content_id' => $contentId,
                'item_id'    => $itemId,
                'is_checked' => 1,
                'checked_by' => $checkerId,
                'checked_at' => date('Y-m-d H:i:s'),
            ]);

            return ['ok' => true, 'checked' => true];
        }

        $isChecked = $row->is_checked ? 0 : 1;

        $this->update($row->id, [
            'is_checked' => $isChecked,
            'checked_by' => $isChecked ? $checkerId : null,
            'checked_at' => $isChecked ? date('Y-m-d H:i:s') : null,
        ]);

        return ['ok' => true, 'checked' => (bool)$isChecked];
    }

    /**
     * Simpan seluruh checklist dalam satu request.
     *
     * @param array $itemIds    daftar ID item aktif (sumber kebenaran)
     * @param array $checkedIds daftar item yang dicentang (dari form)
     */
    public function syncForContent(int $contentId, array $itemIds, array $checkedIds, int $actorId): bool
    {
        $itemIds    = array_values(array_unique(array_map('intval', $itemIds)));
        $checkedIds = array_map('intval', $checkedIds);

        $rows = $this->where('content_id', $contentId)->findAll();
        $rowsByItem = [];
        foreach ($rows as $r) {
            $rowsByItem[(int)$r->item_id] = $r;
        }

        $now = date('Y-m-d H:i:s');

        foreach ($itemIds as $itemId) {
            $checked = in_array($itemId, $checkedIds, true) ? 1 : 0;

            if (isset($rowsByItem[$itemId])) {
                $row = $rowsByItem[$itemId];
                if ((int)$row->is_checked !== $checked) {
                    $this->update($row->id, [
                        'is_checked' => $checked,
                        'checked_by' => $checked ? $actorId : null,
                        'checked_at' => $checked ? $now : null,
                    ]);
                }
            } else {
                $this->insert([
                    'content_id' => $contentId,
                    'item_id'    => $itemId,
                    'is_checked' => $checked,
                    'checked_by' => $checked ? $actorId : null,
                    'checked_at' => $checked ? $now : null,
                ]);
            }
        }

        return true;
    }

    /**
     * Ringkasan konsistensi brand untuk sekumpulan content (periode+scope).
     */
    public function brandSummary(array $contentIds): array
    {
        if (empty($contentIds)) {
            return ['total_items' => 0, 'checked_items' => 0];
        }

        $ids = implode(',', array_map('intval', $contentIds));

        $row = $this->db->query(
            "SELECT COUNT(*) AS total_items, COALESCE(SUM(is_checked), 0) AS checked_items
             FROM content_checklists
             WHERE content_id IN ($ids)"
        )->getRow();

        return [
            'total_items'   => (int)$row->total_items,
            'checked_items' => (int)$row->checked_items,
        ];
    }
}