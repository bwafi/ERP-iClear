<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelStokOpnamePeriode extends Model
{
    protected $table = 'stok_opname_periode';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'unit_idunit',
        'tanggal',
        'status',
        'jumlah_komp',
        'jumlah_real',
        'jumlah_selisih',
        'total_barang',
        'terisi_barang',
        'mulai_by',
        'finalisasi_by',
        'tanggal_finalisasi',
        'created_at',
        'updated_at',
    ];

    public function getByUnitTanggal(int $unit, string $tanggal)
    {
        return $this->where(['unit_idunit' => $unit, 'tanggal' => $tanggal])->first();
    }

    public function getByUnit(int $unit, int $limit = 60)
    {
        return $this->where('unit_idunit', $unit)
            ->orderBy('tanggal', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function listAllRecent(int $limit = 200)
    {
        return $this->orderBy('tanggal', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}