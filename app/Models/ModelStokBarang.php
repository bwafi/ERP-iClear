<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelStokBarang extends Model
{
    protected $table = 'stok_barang';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idbarang',
        'kode_barang',
        'nama_barang',
        'imei',
        'idkategori',
        'id_unit',
        'nama_unit',
        'stok_dasar',
        'harga',
        'sumber_stok_dasar',
        'tanggal_stok_dasar',
        'total_pembelian',
        'total_penjualan',
        'total_retur_pelanggan',
        'total_retur_supplier',
        'total_mutasi_keluar',
        'total_mutasi_masuk',
        'stok_minimum',
        'stok_akhir'
    ];

    public function getStok()
    {
        return $this->findAll();
    }

    public function getByIdBarang($id)
    {
        return $this->where(['idbarang' => $id])->first();
    }

    public function getStokMinimum()
    {
        $idUnit = session('ID_UNIT');
        return $this->where('id_unit', $idUnit)
            ->where('stok_akhir <= stok_minimum')
            ->findAll();
    }

    public function updateStokMinimum($idUnit, $idBarang, $stokMinimum)
    {
        return $this->where('id_unit', $idUnit)
            ->where('idbarang', $idBarang)
            ->set(['stok_minimum' => $stokMinimum])
            ->update();
    }

    public function getSparepart()
    {
        return $this->where('idkategori', 3)->findAll();
    }


    //untuk penjualan
    public function getAllBarang2()
    {
        $id_unit = session('ID_UNIT');

        return $this->select('
                stok_barang.*,
                barang.kode_barang,
                barang.nama_barang,
                barang.harga,
                barang.harga_beli,
                barang.imei,
                barang.jenis_hp,
                barang.internal,
                barang.warna,
                barang.status_barang AS kondisi,
                barang.status AS status_barang,
                barang.status_ppn,
                barang.input,
                kategori.nama_kategori
            ')
            ->join('barang', 'barang.idbarang = stok_barang.idbarang')
            ->join('kategori', 'kategori.id = barang.idkategori')
            ->where('barang.deleted', 0)
            ->where('kategori.delete', 0)
            ->where('stok_barang.id_unit', (int)$id_unit)
            ->where('stok_barang.stok_akhir >', 0)
            ->findAll();
    }
}