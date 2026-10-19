<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelReturSuplier extends Model
{
    protected $table = 'retur_suplier';
    protected $primaryKey = 'idretur_suplier';
    protected $returnType = 'object';
    protected $allowedFields = ['idretur_suplier', 'no_retur_suplier', 'tanggal', 'jumlah', 'satuan', 'barang_idbarang', 'detail_pembelian_iddetail_pembelian', 'barang_rusak_id', 'input_by', 'unit_idunit'];

    public function getReturSuplier()
    {
        return $this->findAll();
    }

    public function insert_ReturSuplier($data)
    {
        return $this->insert($data);
    }

    public function getById($idretur_suplier)
    {
        return $this->where(['idretur_suplier' => $idretur_suplier])->first();
    }

    public function getReturPembelian()
    {
        return $this->db->table('retur_suplier')
            ->select('
                retur_suplier.*, 
                detail_pembelian.*,
                barang.nama_barang,
                unit.NAMA_UNIT')
            ->join('detail_pembelian', 'detail_pembelian.iddetail_pembelian = retur_suplier.detail_pembelian_iddetail_pembelian')
            ->join('pembelian', 'pembelian.idpembelian = detail_pembelian.pembelian_idpembelian')
            ->join('barang', 'barang.idbarang = detail_pembelian.barang_idbarang')
            ->join('unit', 'unit.idunit = retur_suplier.unit_idunit')
            ->get()
            ->getResult();
    }

    public function exportfilter($tanggalAwal = null, $tanggalAkhir = null, $namaUnit = null)
    {
        $builder = $this->db->table('retur_suplier')
            ->select('
            retur_suplier.*, 
            detail_pembelian.*,
            barang.nama_barang,
            unit.NAMA_UNIT')
            ->join('detail_pembelian', 'detail_pembelian.iddetail_pembelian = retur_suplier.detail_pembelian_iddetail_pembelian')
            ->join('pembelian', 'pembelian.idpembelian = detail_pembelian.pembelian_idpembelian')
            ->join('barang', 'barang.idbarang = detail_pembelian.barang_idbarang')
            ->join('unit', 'unit.idunit = retur_suplier.unit_idunit');


        if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
            // Tambahkan waktu maksimal agar filter akhir mencakup seluruh hari
            $tanggalAkhir .= ' 23:59:59';

            $builder->where('retur_suplier.tanggal >=', $tanggalAwal)
                ->where('retur_suplier.tanggal <=', $tanggalAkhir);
        }

        if (!empty($namaUnit)) {
            $builder->where('unit.NAMA_UNIT', $namaUnit);
        }

        return $builder->orderBy('retur_suplier.tanggal', 'DESC')->get()->getResult();
    }
}
