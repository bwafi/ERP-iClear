<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelFinanceOmsetDaily extends Model
{
    protected $table = 'finance_omzet_daily';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit_id',
        'tanggal',
        'omzet_erp',
        'omzet_sheet',
        'selisih',
        'is_match',
        'input_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getByUnitAndRange(int $unitId, string $startDate, string $endDate): array
    {
        return $this->where('unit_id', $unitId)
            ->where('tanggal >=', $startDate)
            ->where('tanggal <=', $endDate)
            ->orderBy('tanggal', 'ASC')
            ->findAll();
    }

    /**
     * Satu baris per (unit, tanggal). Update jika sudah ada.
     */
    public function upsert(array $data): bool
    {
        $existing = $this->where('unit_id', $data['unit_id'])
            ->where('tanggal', $data['tanggal'])
            ->first();

        if ($existing) {
            $data['id'] = $existing->id;
            return $this->save($data);
        }

        return (bool) $this->insert($data);
    }
}