<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelSaldoAwalKasBank extends Model
{
    protected $table = 'saldo_awal_kas_bank';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'id',
        'akun_kas_bank_id',
        'tanggal',
        'saldo',
        'keterangan',
        'input_by',
        'created_at',
        'updated_at',
    ];

    public function getByAkun(int $akunId)
    {
        return $this->where('akun_kas_bank_id', $akunId)->first();
    }
}