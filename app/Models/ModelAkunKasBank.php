<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelAkunKasBank extends Model
{
    protected $table = 'akun_kas_bank';
    protected $primaryKey = 'idakun_kas_bank';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idakun_kas_bank',
        'unit_id',
        'tipe',
        'nama_akun',
        'bank_idbank',
        'no_akun_coa',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    public function getAllWithUnit(?int $unitId = null)
    {
        $builder = $this->select('akun_kas_bank.*, unit.NAMA_UNIT, bank.nama_bank, bank.norek, no_akun.nama_akun as nama_akun_coa')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->join('bank', 'bank.idbank = akun_kas_bank.bank_idbank', 'left')
            ->join('no_akun', 'no_akun.no_akun = akun_kas_bank.no_akun_coa', 'left')
            ->orderBy('akun_kas_bank.unit_id', 'ASC');

        if (!empty($unitId)) {
            $builder->where('akun_kas_bank.unit_id', (int)$unitId);
        }

        return $builder->findAll();
    }

    public function getAktifByUnit(int $unitId)
    {
        return $this->where('unit_id', $unitId)
            ->where('status', 'aktif')
            ->orderBy('tipe', 'ASC')
            ->findAll();
    }

    public function getAktifAll()
    {
        return $this->select('akun_kas_bank.*, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = akun_kas_bank.unit_id', 'left')
            ->where('akun_kas_bank.status', 'aktif')
            ->orderBy('akun_kas_bank.unit_id', 'ASC')
            ->orderBy('akun_kas_bank.tipe', 'ASC')
            ->findAll();
    }
}