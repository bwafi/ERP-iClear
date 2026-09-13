<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelCustomerSatisfaction extends Model
{
    protected $table         = 'customer_satisfaction';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $createdByField = 'created_by';
    protected $returnType    = 'object';
    protected $allowedFields = [
        'tanggal',
        'id_unit',
        'jumlah_review',
        'created_by',
    ];

    /**
     * Ambil record untuk (tanggal, unit) — null bila belum ada.
     */
    public function findByTanggalUnit(string $tanggal, int $idUnit): ?object
    {
        return $this->where('tanggal', $tanggal)
            ->where('id_unit', $idUnit)
            ->first();
    }

    /**
     * Upsert jumlah review per (tanggal, unit).
     */
    public function upsert(string $tanggal, int $idUnit, int $jumlahReview, int $createdBy): object
    {
        $exist = $this->findByTanggalUnit($tanggal, $idUnit);
        $data  = [
            'tanggal'        => $tanggal,
            'id_unit'        => $idUnit,
            'jumlah_review'  => $jumlahReview,
            'created_by'     => $createdBy,
        ];

        if ($exist) {
            $this->update((int)$exist->id, $data);
            return $this->find((int)$exist->id);
        }

        $this->insert($data);
        return $this->find((int)$this->getInsertID());
    }

    /**
     * Record dalam rentang tanggal untuk daftar unit.
     */
    public function findForPeriod(array $unitIds, string $start, string $end): array
    {
        if (empty($unitIds)) {
            return [];
        }
        return $this->whereIn('id_unit', $unitIds)
            ->where('tanggal >=', $start)
            ->where('tanggal <=', $end)
            ->orderBy('tanggal', 'ASC')
            ->findAll();
    }
}