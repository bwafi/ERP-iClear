<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelStokAwal extends Model
{
    protected $table = 'stok_awal';
    protected $primaryKey = 'idstok_awal';
    protected $returnType = 'object';
    protected $allowedFields = ['idstok_awal', 'tanggal', 'jumlah', 'barang_idbarang', 'harga_beli', 'satuan_terkecil', 'unit_idunit', 'suplier_id_suplier', 'pelanggan_id_pelanggan', 'input_by'];

    public function getStok()
    {
        return $this->findAll();
    }

    public function getByIdBarang($id)
    {
        return $this->where(['barang_idbarang' => $id])->first();
    }


    public function insert_Stok($data)
    {
        return $this->insert($data);
    }


    public function getById($id)
    {
        return $this->where(['idstok_awal' => $id])->first();
    }

    public function getAllStok()
    {
        return $this->db->table('stok_awal')
            ->select('
                stok_awal.*,
                barang.nama_barang,
                barang.status_ppn,
                barang.imei,
                kategori.nama_kategori,
                unit.NAMA_UNIT,
                suplier.nama_suplier,
                pelanggan.nama as nama_pelanggan
            ')
            ->join('barang', 'barang.idbarang = stok_awal.barang_idbarang', 'left')
            ->join('kategori', 'kategori.id = barang.idkategori', 'left')
            ->join('unit', 'unit.idunit = stok_awal.unit_idunit', 'left')
            ->join('suplier', 'suplier.id_suplier = stok_awal.suplier_id_suplier', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = stok_awal.pelanggan_id_pelanggan', 'left')
            ->where('barang.deleted', '0')
            ->where('kategori.delete', '0')
            ->get()
            ->getResult();
    }
}
