<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelKartuStok extends Model
{
    protected $table = 'stok_barang';
    protected $primaryKey = 'idbarang';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idbarang',
        'idretur_pelanggan',
        'kode_barang',
        'id_unit',
        'nama_unit',
        'stok_dasar',
        'sumber_stok_dasar',
        'tanggal_stok_dasar',
        'total_pembelian',
        'total_penjualan',
        'total_retur_pelanggan',
        'total_retur_suplier',
        'stok_akhir'
    ];

    public function getKartuStok()
    {
        return $this->findAll();
    }

    /**
     * Server-side processing untuk DataTables (tabel Draft / Kartu Stok).
     */
    public function getKartuStokDT($limit, $offset, $search = '', $orderCol = 'kode_barang', $orderDir = 'ASC', $unitFilter = '')
    {
        $allowedOrder = ['kode_barang', 'nama_barang', 'nama_unit', 'stok_akhir'];
        if (!in_array($orderCol, $allowedOrder, true)) {
            $orderCol = 'kode_barang';
        }
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $builder = $this->db->table('stok_barang')
            ->select('idbarang, id_unit, kode_barang, nama_barang, nama_unit, stok_akhir');

        if ($search !== '') {
            $builder->groupStart()
                ->like('kode_barang', $search)
                ->orLike('nama_barang', $search)
                ->orLike('nama_unit', $search)
                ->groupEnd();
        }

        if ($unitFilter !== '') {
            $builder->where('nama_unit', $unitFilter);
        }

        $builder->orderBy($orderCol, $orderDir)
            ->limit($limit, $offset);

        return $builder->get()->getResult();
    }

    public function countKartuStokDT($search = '', $unitFilter = '')
    {
        $builder = $this->db->table('stok_barang');

        if ($search !== '') {
            $builder->groupStart()
                ->like('kode_barang', $search)
                ->orLike('nama_barang', $search)
                ->orLike('nama_unit', $search)
                ->groupEnd();
        }

        if ($unitFilter !== '') {
            $builder->where('nama_unit', $unitFilter);
        }

        return $builder->countAllResults(false);
    }



    public function insert_getKartuStok($data)
    {
        return $this->insert($data);
    }

    public function getById($id)
    {
        return $this->where(['idbarang' => $id])->first();
    }


    public function getKartuStokWithKategori()
    {
        return $this->select('stok_barang.*, barang.status_ppn, kategori.nama_kategori')
            ->join('barang', 'barang.kode_barang = stok_barang.kode_barang', 'left')
            ->join('kategori', 'kategori.id = barang.idkategori', 'left')
            ->where('barang.deleted', '0')
            ->where('kategori.delete', '0')
            ->findAll();
    }

    public function getKartuStokTerlaris()
    {
        return $this->select('stok_barang.*, barang.status_ppn, kategori.nama_kategori')
            ->join('barang', 'barang.kode_barang = stok_barang.kode_barang', 'left')
            ->join('kategori', 'kategori.id = barang.idkategori', 'left')
            ->where('barang.deleted', '0')
            ->where('kategori.delete', '0')
            ->orderBy('stok_barang.total_penjualan', 'DESC')
            ->limit(10)
            ->findAll();
    }



    // public function exportfilter($tanggalAwal = null, $tanggalAkhir = null, $namaUnit = null, $statusPpn = null)
    // {
    //     $builder = $this->select('stok_barang.*, barang.imei ,barang.status_ppn, kategori.nama_kategori')
    //         ->join('barang', 'barang.kode_barang = stok_barang.kode_barang', 'left')
    //         ->join('kategori', 'kategori.id = barang.idkategori', 'left')
    //         ->where('barang.deleted', '0')
    //         ->where('kategori.delete', '0');

    //     // Filter tanggal stok_dasar
    //     if (!empty($tanggalAwal)) {
    //         $tanggalAwal = date('Y-m-d', strtotime($tanggalAwal));
    //         $builder->where('tanggal_stok_dasar >=', $tanggalAwal);
    //     }

    //     if (!empty($tanggalAkhir)) {
    //         $tanggalAkhir = date('Y-m-d', strtotime($tanggalAkhir));
    //         $builder->where('tanggal_stok_dasar <=', $tanggalAkhir);
    //     }

    //     // Filter nama_unit
    //     if (!empty($namaUnit)) {
    //         $builder->where('stok_barang.id_unit', $namaUnit);
    //     }

    //     // Filter status_ppn
    //     if ($statusPpn === 'PPN') {
    //         $builder->where('barang.status_ppn', '1');
    //     } elseif ($statusPpn === 'Non PPN') {
    //         $builder->where('barang.status_ppn', '0');
    //     }

    //     return $builder->get()->getResult();
    // }

    public function exportfilter($namaUnit = null, $statusPpn = null)
{
    $builder = $this->select('stok_barang.*, barang.imei, barang.status_ppn, kategori.nama_kategori')
        ->join('barang', 'barang.kode_barang = stok_barang.kode_barang', 'left')
        ->join('kategori', 'kategori.id = barang.idkategori', 'left')
        ->where('barang.deleted', '0')
        ->where('kategori.delete', '0');

    // Filter nama_unit
    if (!empty($namaUnit)) {
        $builder->where('stok_barang.id_unit', $namaUnit);
    }

    // Filter status_ppn
    if ($statusPpn === 'PPN') {
        $builder->where('barang.status_ppn', '1');
    } elseif ($statusPpn === 'Non PPN') {
        $builder->where('barang.status_ppn', '0');
    }

    return $builder->get()->getResult();
}


    public function getMinTanggalStok()
    {
        return $this->db->table('stok_barang')
            ->selectMin('tanggal_stok_dasar')
            ->get()
            ->getRow()
            ->tanggal_stok_dasar;
    }
}