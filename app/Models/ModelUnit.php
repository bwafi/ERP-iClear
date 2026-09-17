<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelUnit extends Model
{
    //
    protected $table = 'unit';
    protected $primaryKey = 'idunit';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idunit',
        'NAMA_UNIT',
        'NOID_UNIT',
        'JALAN_UNIT',
        'KELURAHAN_UNIT',
        'KECAMATAN_UNIT',
        'KABUPATEN_UNIT',
        'PROVINSI_UNIT',
        'LATITUDE',
        'LONGTITUDE'
    ];

    //
    public function getUnit(): array
    {
        return $this->findAll();
    }

    public function getUnit2()
    {
        return $this->db->table('unit')
            ->get()->getResult();
    }


    public function insert_Unit($data)
    {
        return $this->insert($data);
    }


    public function getById($id)
    {
        return $this->where(['idunit' => $id])->first();
    }

    /**
     * Pastikan Unit "ICLEAR Genteng" tersedia (idempoten).
     * Digunakan seeder/migration Social Media KPI dan test.
     *
     * @return int idunit unit Genteng yang aktif
     */
    public function ensureGenteng(): int
    {
        $existing = $this->select('idunit')
            ->groupStart()
            ->like('NAMA_UNIT', 'Genteng', 'both')
            ->orWhere('kode_unit', 'GNT')
            ->groupEnd()
            ->get()
            ->getFirstRow('array');

        if ($existing) {
            return (int)$existing['idunit'];
        }

        $data = [
            'idunit'        => 5,
            'NAMA_UNIT'     => 'ICLEAR Genteng',
            'kode_unit'     => 'GNT',
            'NOID_UNIT'     => '05',
            'NOTELP'        => '085183270910',
            'JALAN_UNIT'    => 'Genteng, Banyuwangi',
            'KELURAHAN_UNIT'=> 'iclear.genteng',
            'KABUPATEN_UNIT'=> 'Banyuwangi',
            'jenis'         => 'franchise',
            'RADIUS'        => '200',
        ];

        if ($this->where('idunit', 5)->countAllResults() === 0) {
            $this->insert($data);
            return 5;
        }

        unset($data['idunit']);
        $this->insert($data);
        return (int)$this->insertID();
    }
}
