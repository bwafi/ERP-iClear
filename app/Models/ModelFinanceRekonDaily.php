<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelFinanceRekonDaily extends Model
{
    protected $table = 'finance_rekon_daily';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'unit_id',
        'tanggal',
        'erp_cash_masuk',
        'actual_cash_masuk',
        'selisih_cash_masuk',
        'checked_cash_masuk',
        'erp_transfer_masuk',
        'actual_transfer_masuk',
        'selisih_transfer_masuk',
        'checked_transfer_masuk',
        'erp_kas_keluar',
        'actual_kas_keluar',
        'selisih_kas_keluar',
        'checked_kas_keluar',
        'catatan',
        'input_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function getByUnitAndDate(int $unitId, string $tanggal)
    {
        return $this->where('unit_id', $unitId)
            ->where('tanggal', $tanggal)
            ->first();
    }

    public function getByUnitAndRange(int $unitId, string $startDate, string $endDate): array
    {
        return $this->where('unit_id', $unitId)
            ->where('tanggal >=', $startDate)
            ->where('tanggal <=', $endDate)
            ->orderBy('tanggal', 'ASC')
            ->findAll();
    }

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

    public function countLengkapInRange(int $unitId, string $startDate, string $endDate): int
    {
        return $this->where('unit_id', $unitId)
            ->where('tanggal >=', $startDate)
            ->where('tanggal <=', $endDate)
            ->where('checked_cash_masuk', 1)
            ->where('checked_transfer_masuk', 1)
            ->where('checked_kas_keluar', 1)
            ->countAllResults();
    }
}
