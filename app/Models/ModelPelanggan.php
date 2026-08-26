<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPelanggan extends Model
{
    protected $table = 'pelanggan';
    protected $primaryKey = 'id_pelanggan';
    protected $returnType = 'object';
    protected $allowedFields = ['id_pelanggan', 'nik', 'nama', 'alamat', 'kecamatan', 'kabupaten', 'provinsi', 'kategori', 'no_hp', 'mengetahui_dari', 'deleted', 'create_on'];

    public function getPelanggan($perPage = 25, $page = 1, $search = '')
    {
        $offset = ($page - 1) * $perPage;

        $builder = $this->db->table('pelanggan');
        $builder->select('pelanggan.*');
        $builder->where('pelanggan.deleted', '0');

        if (!empty($search)) {
            $builder->groupStart()->like('pelanggan.nama', $search)
                ->orLike('pelanggan.no_hp', $search)
                ->orLike('pelanggan.alamat', $search)
                ->orLike('pelanggan.nik', $search)->groupEnd();
        }

        $builder->orderBy('pelanggan.id_pelanggan', 'DESC');

        if ($perPage > 0) {
            $builder->limit($perPage, $offset);
        }

        return $builder->get()->getResult();
    }

    public function countPelanggan($search = '')
    {
        $builder = $this->db->table('pelanggan');
        $builder->where('pelanggan.deleted', '0');

        if (!empty($search)) {
            $builder->groupStart()->like('pelanggan.nama', $search)
                ->orLike('pelanggan.no_hp', $search)
                ->orLike('pelanggan.alamat', $search)
                ->orLike('pelanggan.nik', $search)->groupEnd();
        }

        return $builder->countAllResults(false);
    }

    public function searchPelanggan($keyword, $limit = 20)
    {
        return $this->db->table('pelanggan')
            ->select('id_pelanggan, nama')
            ->where('deleted', '0')
            ->like('nama', $keyword)
            ->orderBy('nama', 'ASC')
            ->limit($limit)
            ->get()
            ->getResult();
    }

    public function insert_Pelanggan($data)
    {
        return $this->insert($data);
    }

    public function getById($id_pelanggan)
    {
        return $this->where(['id_pelanggan' => $id_pelanggan])->first();
    }

    public function getByNomor($nomor)
    {
        return $this->where(['no_hp' => $nomor])->first();
    }

    public function getPelangganWithService($per_bulan = false)
    {
        if ($per_bulan) {
            return $this->db->table('pelanggan')
                ->select("DATE_FORMAT(pelanggan.create_on, '%Y-%m') AS bulan, COUNT(DISTINCT pelanggan.id_pelanggan) AS total")
                ->join('service', 'service.pelanggan_id_pelanggan = pelanggan.id_pelanggan')
                ->where('pelanggan.deleted', '0')
                ->where('service.status_service', 4)
                ->groupBy('bulan')
                ->orderBy('bulan', 'ASC')
                ->get()
                ->getResult();
        }

        return $this->db->table('pelanggan')
            ->select('pelanggan.*, service.no_service')
            ->join('service', 'service.pelanggan_id_pelanggan = pelanggan.id_pelanggan')
            ->where('pelanggan.deleted', '0')
            ->where('service.status_service', 4)
            ->get()
            ->getResult();
    }

    public function getPelangganBaruBulanIni($per_bulan = false)
    {
        if ($per_bulan) {
            return $this->db->table('pelanggan')
                ->select("DATE_FORMAT(create_on, '%Y-%m') AS bulan, COUNT(*) AS total")
                ->where('create_on >=', date('Y-m-d', strtotime('-12 months')))
                ->groupBy('bulan')
                ->orderBy('bulan', 'ASC')
                ->get()
                ->getResult();
        }

        $oneMonthAgo = date('Y-m-d', strtotime('-1 month'));
        return $this->db->table('pelanggan')
            ->where('create_on >=', $oneMonthAgo)
            ->get()
            ->getResult();
    }
}
