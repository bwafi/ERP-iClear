<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelMarketingLead extends Model
{
    protected $table = 'marketing_lead';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $allowedFields = [
        'tanggal', 'nama', 'no_hp', 'source_id', 'ads_organic', 'price', 'cs', 'cabang', 'status',
        'customer_id', 'tanggal_won', 'created_by',
        'kommo_lead_id', 'kommo_account_id', 'kommo_pipeline_id',
        'kommo_status_id', 'kommo_updated_at', 'kommo_deleted_at',
    ];

    public function findByPeriod(int $month, int $year, ?string $status = null, ?int $limit = null, ?int $offset = 0): array
    {
        $builder = $this->periodWhere($month, $year, $status)
            ->orderBy('tanggal', 'DESC')
            ->orderBy('id', 'DESC');
        // Limit/offset lewat findAll() (bukan builder->limit) karena findAll
        // justru MENIMPA limit bawaan query builder.
        if ($limit !== null && $limit > 0) {
            return $builder->findAll($limit, max(0, $offset));
        }
        return $builder->findAll();
    }

    public function countByPeriod(int $month, int $year, ?string $status = null): int
    {
        return $this->periodWhere($month, $year, $status)->countAllResults();
    }

    private function periodWhere(int $month, int $year, ?string $status = null)
    {
        $where = "DATE_FORMAT(tanggal, '%Y-%m') = '" . sprintf('%04d-%02d', $year, $month) . "'";
        // Lead yang dihapus di Kommo (soft-delete) tidak ikut daftar aktif.
        $where .= ' AND (kommo_deleted_at IS NULL)';
        if ($status !== null) {
            $where .= " AND status = '" . $status . "'";
        }
        return $this->where($where, null, false);
    }

    /** Cari berdasarkan kommo_lead_id (primary external identifier). */
    public function findByKommoId(int $kommoLeadId): ?object
    {
        return $this->where('kommo_lead_id', $kommoLeadId)->first();
    }
}