<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelDetailPenjualan extends Model
{
    protected $table = 'detail_penjualan';
    protected $primaryKey = 'iddetail_penjualan';
    protected $returnType = 'object';
    protected $allowedFields = [
        'iddetail_penjualan',
        'jumlah',
        'barang_idbarang',
        'harga_penjualan',
        'sub_total',
        'penjualan_idpenjualan',
        'hpp_penjualan',
        'satuan_jual',
        'diskon_penjualan',
        'unit_idunit'
    ];

    public function getDetail()
    {
        return $this->findAll();
    }

    public function insert_detail($data)
    {
        return $this->insert($data);
    }

    public function getById($id)
    {
        return $this->where(['iddetail_penjualan' => $id])->first();
    }

    public function updateDetail($data, $id)
    {
        return $this->where('iddetail_penjualan', $id)->set($data)->update();
    }

    public function deleteDetail($id)
    {
        return $this->where('iddetail_penjualan', $id)->delete();
    }



    public function getDetailPenjualan()
    {
        return $this->db->table('detail_penjualan')
            ->select('
                detail_penjualan.*,
                penjualan.kode_invoice,
                penjualan.tanggal,
                penjualan.id_pelanggan,
                penjualan.total_ppn,
                barang.nama_barang,
                barang.imei,
                unit.NAMA_UNIT')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang')
            ->join('unit', 'unit.idunit = detail_penjualan.unit_idunit')
            ->orderBy('penjualan.tanggal', 'DESC')
            ->get()
            ->getResult();
    }


    public function exportfilter($tanggalAwal = null, $tanggalAkhir = null, $namaUnit = null)
    {
        $builder = $this->db->table('detail_penjualan')
            ->select('
            detail_penjualan.*,
            penjualan.kode_invoice,
            penjualan.tanggal,
            barang.nama_barang,
            unit.NAMA_UNIT')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang')
            ->join('unit', 'unit.idunit = detail_penjualan.unit_idunit');


        if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
            $builder->where('penjualan.tanggal >=', $tanggalAwal)
                ->where('penjualan.tanggal <=', $tanggalAkhir . ' 23:59:59');
        }


        if (!empty($namaUnit)) {
            $builder->where('unit.NAMA_UNIT', $namaUnit);
        }

        return $builder->orderBy('penjualan.tanggal', 'DESC')
            ->get()
            ->getResult();
    }

    public function getDetailPenjualanByInvoice($kode_invoice)
    {
        return $this->db->table('detail_penjualan')
            ->select('
            detail_penjualan.*,
            penjualan.kode_invoice,
            penjualan.tanggal,
            barang.nama_barang,
            unit.NAMA_UNIT')
            ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
            ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang')
            ->join('unit', 'unit.idunit = detail_penjualan.unit_idunit')
            ->where('penjualan.kode_invoice', $kode_invoice)
            ->orderBy('penjualan.tanggal', 'DESC')
            ->get()
            ->getResult();
    }

    public function countByCategory($categoryId = 2, $startDate = null, $endDate = null)
{
    $builder = $this->db->table('detail_penjualan')
        ->select('COUNT(detail_penjualan.iddetail_penjualan) as total_category')
        ->join('penjualan', 'penjualan.idpenjualan = detail_penjualan.penjualan_idpenjualan')
        ->join('barang', 'barang.idbarang = detail_penjualan.barang_idbarang')
        ->where('barang.idkategori', $categoryId);

    if (!empty($startDate) && !empty($endDate)) {
        $builder->where('DATE(penjualan.tanggal) >=', $startDate)
                ->where('DATE(penjualan.tanggal) <=', $endDate);
    }

    return $builder->get()->getRow();
}

}