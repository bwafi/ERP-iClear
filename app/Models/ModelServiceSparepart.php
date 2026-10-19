<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelServiceSparepart extends Model
{
    protected $table = 'service_sparepart';
    protected $primaryKey = 'idservice_sparepart';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idservice_sparepart',
        'jumlah',
        'harga_penjualan',
        'harga_penjualan_garansi',
        'sub_total',
        'sub_total_garansi',
        'hpp_penjualan',
        'satuan_jual',
        'diskon_penjualan',
        'diskon_penjualan_garansi',
        'service_idservice',
        'barang_idbarang',
        'unit_idunit',
        'jumlah_tambahan_garansi',
        'hpp_penjualan_garansi',
        'source_purchase_detail_id'
    ];

    public function getSerModelServiceSparepart()
    {
        return $this->findAll();
    }

    public function getSerModelServiceSparepartByServiceId($idservice)
    {
        return $this->db->table('service_sparepart')
            ->select('service_sparepart.*, barang.kode_barang, barang.nama_barang, service.garansi_hari, stok_barang.stok_akhir, purchase.no_nota_supplier AS source_nota_supplier, supplier.nama_suplier AS source_nama_suplier')
            ->join('barang', 'barang.idbarang = service_sparepart.barang_idbarang')
            ->join('service', 'service.idservice = service_sparepart.service_idservice')
            ->join('detail_pembelian source_detail', 'source_detail.iddetail_pembelian = service_sparepart.source_purchase_detail_id', 'left')
            ->join('pembelian purchase', 'purchase.idpembelian = source_detail.pembelian_idpembelian', 'left')
            ->join('suplier supplier', 'supplier.id_suplier = purchase.suplier_id_suplier', 'left')
            ->join('stok_barang', 'stok_barang.idbarang = service_sparepart.barang_idbarang AND stok_barang.id_unit = service_sparepart.unit_idunit', 'left')
            ->where('service_sparepart.service_idservice', $idservice)
            ->get()->getResult();
    }

    public function getGaransiHariByServiceId($idservice)
    {
        return $this->db->table('service')
            ->select('garansi_hari')
            ->where('idservice', $idservice)
            ->get()
            ->getRow();
    }

    public function insert_SerModelServiceSparepart($data)
    {
        return $this->insert($data);
    }

    public function updateByServiceAndBarang($serviceId, $barangId, $data)
    {
        return $this->where([
            'service_idservice' => $serviceId,
            'barang_idbarang' => $barangId
        ])->set($data)->update();
    }

    public function deleteByServiceAndBarang($serviceId, $barangId)
    {
        return $this->where([
            'service_idservice' => $serviceId,
            'barang_idbarang' => $barangId
        ])->delete();
    }
    public function getByServiceId($idservice)
    {
        return $this->where('service_idservice', $idservice)->findAll();
    }
}
