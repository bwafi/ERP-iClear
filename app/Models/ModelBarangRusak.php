<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelBarangRusak extends Model
{
    protected $table = 'barang_rusak';
    protected $primaryKey = 'idbarang_rusak';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idbarang_rusak',
        'idpembelian',
        'no_nota_sup',
        'barang_idbarang',
        'jumlah',
        'tanggal_rusak',
        'unit_idunit',
        'input_by',
        'created_at',
        'keterangan',
        'claim_id'
    ];

    public function getBarangRusak()
    {
        return $this->select('
            barang_rusak.*, 
            barang.kode_barang, 
            barang.nama_barang, 
            barang.imei,
            barang.idkategori,
            barang.id_sub_kategori,
            unit.NAMA_UNIT AS nama_unit,
            akun.NAMA_AKUN AS nama_input,
            kategori.nama_kategori,
            sub_kategori.nama_sub_kategori
        ')
            ->join('barang', 'barang.idbarang = barang_rusak.barang_idbarang', 'left')
            ->join('unit', 'unit.idunit = barang_rusak.unit_idunit', 'left')
            ->join('akun', 'akun.ID_AKUN = barang_rusak.input_by', 'left')
            ->join('kategori', 'kategori.idkategori = barang.idkategori', 'left')
            ->join('sub_kategori', 'sub_kategori.id = barang.id_sub_kategori', 'left')
            ->orderBy('barang_rusak.idbarang_rusak', 'DESC')
            ->findAll();
    }





    public function getById($idbarang_rusak)
    {
        return $this->select('
                barang_rusak.*, 
                barang.kode_barang, 
                barang.nama_barang, 
                barang.imei
            ')
            ->join('barang', 'barang.idbarang = barang_rusak.barang_idbarang', 'left')
            ->where('barang_rusak.idbarang_rusak', $idbarang_rusak)
            ->first();
    }
}
