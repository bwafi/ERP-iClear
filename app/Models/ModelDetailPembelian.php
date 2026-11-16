<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelDetailPembelian extends Model
{
    protected $table = 'detail_pembelian';
    protected $primaryKey = 'iddetail_pembelian';
    protected $returnType = 'object';
    protected $allowedFields = [
        'iddetail_pembelian',
        'no_batch',
        'tanggal',
        'jumlah',
        'hrg_beli',
        'diskon',
        'ppn',
        'hitung_hpp',
        'total_harga',
        'satuan_beli',
        'barang_idbarang',
        'pembelian_idpembelian',
        'unit_idunit',
        'biaya_tambahan',
        'keterangan_tambahan'
    ];

    public function getDetail()
    {
        return $this->findAll();
    }

    // public function getDetailByTanggal($tanggal_awal = null, $tanggal_akhir = null)
    // {
    //     $builder = $this->db->table($this->table);

    //     $builder->select('detail_pembelian.*, barang.nama_barang');

    //     $builder->join('barang', 'barang.idbarang = detail_pembelian.barang_idbarang');

    //     if ($tanggal_awal && $tanggal_akhir) {

    //         $builder->where('detail_pembelian.tanggal >=', $tanggal_awal);
    //         $builder->where('detail_pembelian.tanggal <=', $tanggal_akhir);
    //     }


    //     $builder->orderBy('detail_pembelian.tanggal', 'DESC');
    //     return $builder->get()->getResult();
    // }

    // public function getDetailAll()
    // {
    //     $builder = $this->db->table($this->table);

    //     $builder->select('detail_pembelian.*, barang.nama_barang');
    //     $builder->join('barang', 'barang.idbarang = detail_pembelian.barang_idbarang');

    //     $builder->orderBy('detail_pembelian.tanggal', 'DESC');

    //     return $builder->get()->getResult();
    // }

    public function getDetailAll()
    {
        return $this->db->table('detail_pembelian')
            ->select('
                detail_pembelian.*,
                detail_pembelian.tanggal,
                barang.nama_barang,
                barang.kode_barang,
                barang.imei,
                pembelian.suplier_id_suplier,
                pembelian.pelanggan_id_pelanggan,
                suplier.nama_suplier,
                unit.NAMA_UNIT')
            ->join('pembelian', 'pembelian.idpembelian = detail_pembelian.pembelian_idpembelian')
            ->join('barang', 'barang.idbarang = detail_pembelian.barang_idbarang')
            ->join('suplier', 'suplier.id_suplier =  pembelian.suplier_id_suplier', 'left')
            ->join('unit', 'unit.idunit = detail_pembelian.unit_idunit')
            ->orderBy('detail_pembelian.tanggal', 'DESC')
            ->get()
            ->getResult();
    }

    /**
     * Rincian barang untuk sekumpulan nota pembelian.
     *
     * Dipakai /daftar_tagihan supaya baris tagihan dan modal pembayaran bisa
     * menampilkan sparepart/barang per nota tanpa query per baris (N+1).
     * Hasil tetap datar; pemanggil mengelompokkannya per
     * `pembelian_idpembelian`.
     *
     * @param  int[] $idPembelian
     * @return object[]
     */
    public function getRingkasanPembelian(array $idPembelian): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $idPembelian),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        return $this->select('
                detail_pembelian.pembelian_idpembelian,
                detail_pembelian.jumlah,
                detail_pembelian.hrg_beli,
                detail_pembelian.diskon,
                detail_pembelian.total_harga,
                detail_pembelian.satuan_beli,
                barang.nama_barang,
                barang.kode_barang
            ')
            ->join('barang', 'barang.idbarang = detail_pembelian.barang_idbarang', 'left')
            ->whereIn('detail_pembelian.pembelian_idpembelian', $ids)
            ->orderBy('detail_pembelian.pembelian_idpembelian', 'ASC')
            ->orderBy('detail_pembelian.iddetail_pembelian', 'ASC')
            ->findAll();
    }

    public function insert_detail($data)
    {
        return $this->insert($data);
    }


    public function getById($id)
    {
        return $this->where(['iddetail_pembelian' => $id])->first();
    }


    public function exportfilter($tanggalAwal = null, $tanggalAkhir = null, $namaUnit = null)
    {

        $builder = $this->db->table('detail_pembelian')
            ->select('
            detail_pembelian.*,
            detail_pembelian.tanggal,
            barang.nama_barang,
            unit.NAMA_UNIT')
            ->join('pembelian', 'pembelian.idpembelian = detail_pembelian.pembelian_idpembelian')
            ->join('barang', 'barang.idbarang = detail_pembelian.barang_idbarang')
            ->join('unit', 'unit.idunit = detail_pembelian.unit_idunit');


        if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
            $builder->where('detail_pembelian.tanggal >=', $tanggalAwal)
                ->where('detail_pembelian.tanggal <=',  $tanggalAkhir . ' 23:59:59');
        }

        if (!empty($namaUnit)) {
            $builder->where('unit.NAMA_UNIT', $namaUnit);
        }

        return $builder->orderBy('detail_pembelian.tanggal', 'DESC')
            ->get()
            ->getResult();
    }
}
