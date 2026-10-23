<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelBarangRusakClaim extends Model
{
    protected $table = 'barang_rusak_claim';
    protected $primaryKey = 'idbarang_rusak_claim';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idbarang_rusak_claim',
        'service_idservice',
        'barang_idbarang',
        'jumlah',
        'keterangan',
        'unit_idunit',
        'input_by',
        'created_at',
    ];

    public function getAll()
    {
        return $this->select('
                barang_rusak_claim.*,
                barang.kode_barang,
                barang.nama_barang,
                service.no_service,
                pelanggan.nama AS nama_pelanggan,
                unit.NAMA_UNIT AS nama_unit,
                akun.NAMA_AKUN AS nama_input
            ')
            ->join('barang', 'barang.idbarang = barang_rusak_claim.barang_idbarang', 'left')
            ->join('service', 'service.idservice = barang_rusak_claim.service_idservice', 'left')
            ->join('pelanggan', 'pelanggan.id_pelanggan = service.pelanggan_id_pelanggan', 'left')
            ->join('unit', 'unit.idunit = barang_rusak_claim.unit_idunit', 'left')
            ->join('akun', 'akun.ID_AKUN = barang_rusak_claim.input_by', 'left')
            ->orderBy('barang_rusak_claim.idbarang_rusak_claim', 'DESC')
            ->findAll();
    }
}
