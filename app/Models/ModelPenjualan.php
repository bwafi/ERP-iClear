<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPenjualan extends Model
{
    protected $table = 'penjualan';
    protected $primaryKey = 'idpenjualan';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idpenjualan',
        'kode_invoice',
        'tanggal',
        'keterangan',
        'total_penjualan',
        'diskon',
        'harus_dibayar',
        'waktu_penjualan',
        'bayar',
        'bayar_tunai',
        'bayar_bank',
        'bank_idbank',
        'id_pelanggan',
        'service_idservice',
        'total_ppn',
        'created_on',
        'input_by',
        'sales_by',
        'unit_idunit'
    ];



    public function getPenjualan()
    {
        return $this->findAll();
    }

    public function getByIdPelanggan($id_pelanggan)
    {
        return $this->select('penjualan.*, unit.NAMA_UNIT, pelanggan.nama as nama_pelanggan, akun.NAMA_AKUN as nama_sales')
            ->join('unit', 'unit.idunit = penjualan.unit_idunit', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = penjualan.id_pelanggan', 'left')
            ->join('akun', 'akun.ID_AKUN = penjualan.sales_by', 'left')
            ->where('penjualan.id_pelanggan', $id_pelanggan)
            ->orderBy('penjualan.tanggal', 'DESC')
            ->findAll();
    }






    public function insert_Penjualan($data)
    {
        return $this->insert($data);
    }

    public function getById($idpenjulan)
    {
        return $this->where(['idpenjulan' => $idpenjulan])->first();
    }


    public function getByKodeInvoice($kode_invoice)
    {
        return $this->where(['kode_invoice' => $kode_invoice])->first();
    }

    public function getPendapatan($unit_id = null, $per_bulan = false)
    {
        if ($per_bulan) {
            $this->select("DATE_FORMAT(created_at, '%Y-%m') AS bulan, SUM(total_penjualan) AS total")
                ->groupBy("bulan")
                ->orderBy("bulan", "ASC");

            if ($unit_id) {
                $this->where('unit_idunit', $unit_id);
            }

            return $this->findAll();
        }

        $this->selectSum('total_penjualan');

        if ($unit_id) {
            $this->where('unit_idunit', $unit_id);
        }

        return $this->first()->total_penjualan ?? 0;
    }

    public function getOmsetBySales($sales_by, $bulan)
    {
        return $this->selectSum('total_penjualan')
            ->where('sales_by', $sales_by)
            ->like('tanggal', $bulan) // YYYY-MM
            ->first()
            ->total_penjualan ?? 0;
    }
}
