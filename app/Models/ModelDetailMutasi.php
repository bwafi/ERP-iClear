<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelDetailMutasi extends Model
{
    protected $table = 'detail_mutasi';
    protected $primaryKey = 'iddetail_mutasi';
    protected $returnType = 'object';
    protected $allowedFields = [
        'iddetail_mutasi',
        'tanggal_kirim',
        'tanggal_terima',
        'jumlah_kirim',
        'jumlah_terima',
        'satuan',
        'hpp_barang',
        'barang_idbarang',
        'source_purchase_detail_id',
        'kirim_idunit',
        'terima_idunit',
        'mutasi_idmutasi',
        'harga_mutasi',
        'harga_beli',
        'harga_jual'
    ];

    public function getDetailMutasiStok()
    {
        return $this->findAll();
    }

    public function insert_DetailMutasiStok($data)
    {
        return $this->insert($data);
    }

    public function getById($idmutasi)
    {
        return $this->where(['iddetail_mutasi' => $idmutasi])->first();
    }


    public function getByIdMutasi($idmutasi)
    {
        return $this->where(['mutasi_idmutasi' => $idmutasi])->findAll();
    }

    public function getFullDetailMutasi()
    {
        return $this->select('
            detail_mutasi.*,
            barang.nama_barang,
            mutasi.no_nota_mutasi, mutasi.tanggal_kirim as mutasi_tanggal_kirim, mutasi.tanggal_terima as mutasi_tanggal_terima, mutasi.status,
            unit_kirim.NAMA_UNIT as nama_unit_kirim,
            unit_terima.NAMA_UNIT as nama_unit_terima
        ')
            ->join('mutasi', 'mutasi.idmutasi = detail_mutasi.mutasi_idmutasi')
            ->join('barang', 'barang.idbarang = detail_mutasi.barang_idbarang')
            ->join('unit as unit_kirim', 'unit_kirim.idunit = mutasi.kirim_idunit')
            ->join('unit as unit_terima', 'unit_terima.idunit = mutasi.terima_idunit')
            ->findAll();
    }


    public function getFullDetailMutasiByMutasiId($mutasiId)
    {
        return $this->select('
            detail_mutasi.*,
            barang.nama_barang,
            mutasi.no_nota_mutasi, 
            mutasi.tanggal_kirim as mutasi_tanggal_kirim, 
            mutasi.tanggal_terima as mutasi_tanggal_terima, 
            mutasi.status,
            unit_kirim.NAMA_UNIT as nama_unit_kirim,
            unit_terima.NAMA_UNIT as nama_unit_terima
        ')
            ->join('mutasi', 'mutasi.idmutasi = detail_mutasi.mutasi_idmutasi')
            ->join('barang', 'barang.idbarang = detail_mutasi.barang_idbarang')
            ->join('unit as unit_kirim', 'unit_kirim.idunit = mutasi.kirim_idunit')
            ->join('unit as unit_terima', 'unit_terima.idunit = mutasi.terima_idunit')
            ->where('detail_mutasi.mutasi_idmutasi', $mutasiId)
            ->findAll();
    }


    public function exportfilter($tanggalAwal = null, $tanggalAkhir = null, $unitAsal = null)
    {
        $builder = $this->select('
                detail_mutasi.*,
                barang.nama_barang,
                mutasi.no_nota_mutasi, 
                mutasi.tanggal_kirim as mutasi_tanggal_kirim, 
                mutasi.tanggal_terima as mutasi_tanggal_terima, 
                mutasi.status,
                unit_kirim.NAMA_UNIT as nama_unit_kirim,
                unit_terima.NAMA_UNIT as nama_unit_terima
            ')
            ->join('mutasi', 'mutasi.idmutasi = detail_mutasi.mutasi_idmutasi')
            ->join('barang', 'barang.idbarang = detail_mutasi.barang_idbarang')
            ->join('unit as unit_kirim', 'unit_kirim.idunit = mutasi.kirim_idunit')
            ->join('unit as unit_terima', 'unit_terima.idunit = mutasi.terima_idunit');

        if (!empty($tanggalAwal)) {
            $builder->where('mutasi.tanggal_kirim >=', $tanggalAwal);
        }

        if (!empty($tanggalAkhir)) {
            $tanggalAkhir .= ' 23:59:59';
            $builder->where('mutasi.tanggal_kirim <=', $tanggalAkhir);
        }

        if (!empty($unitAsal)) {
            $builder->where('unit_kirim.NAMA_UNIT', $unitAsal);
        }

        return $builder->findAll();
    }
}
