<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelAuditAsetItem extends Model
{
    protected $table = 'audit_aset_item';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'periode_id',
        'aset_kpi_id',
        'quantity_ditemukan',
        'kondisi',
        'perawatan',
        'keterangan',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Ambil seluruh item sebuah periode, keyed by aset_kpi_id.
     *
     * @return array<int, object>
     */
    public function itemsByPeriode(int $periodeId): array
    {
        $rows = $this->where('periode_id', $periodeId)->findAll();
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row->aset_kpi_id] = $row;
        }
        return $map;
    }

    /**
     * Upsert satu item audit per aset dalam periode.
     */
    public function upsertItem(int $periodeId, int $asetKpiId, array $data): bool
    {
        $existing = $this->where('periode_id', $periodeId)
            ->where('aset_kpi_id', $asetKpiId)
            ->first();

        $payload = array_merge($data, [
            'periode_id'  => $periodeId,
            'aset_kpi_id' => $asetKpiId,
        ]);

        if ($existing) {
            $payload['id'] = $existing->id;
            return $this->save($payload);
        }

        return $this->insert($payload) !== false;
    }
}