<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelTutupKasir extends Model
{
    //
    protected $table = 'tutup_kasir';
    protected $primaryKey = 'idtutupkasir';
    protected $returnType = 'object';
    protected $allowedFields = [
        'akun_ID_AKUN'
    ];

    //
    public function getTutupKasir(): array
    {
        return $this->findAll();
    }

    public function getTutupKasir2()
    {
        return $this->db->table('unit')
            ->get()->getResult();
    }


    public function insert_TutupKasir($data)
    {
        return $this->insert($data);
    }


    public function getById($id, $unit)
    {
        return $this->where('idtutupkasir', $id)
                    ->where('unit', $unit)
                    ->first();
    }
}
