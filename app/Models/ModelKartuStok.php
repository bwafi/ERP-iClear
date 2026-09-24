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
     * Builder dasar Kartu Stok (stok_barang + barang + kategori), hanya data aktif.
     */
    private function kartuStokBuilder()
    {
        return $this->db->table('stok_barang')
            ->select('
                stok_barang.idbarang AS idbarang,
                stok_barang.id_unit,
                stok_barang.kode_barang,
                stok_barang.nama_barang,
                stok_barang.imei,
                stok_barang.nama_unit,
                stok_barang.harga AS harga,
                stok_barang.stok_awal,
                stok_barang.stok_akhir,
                stok_barang.total_pembelian,
                stok_barang.total_penjualan,
                stok_barang.total_retur_pelanggan,
                stok_barang.total_retur_supplier,
                stok_barang.total_mutasi_masuk,
                stok_barang.total_mutasi_keluar,
                barang.harga_beli,
                barang.jenis_hp,
                barang.warna,
                barang.status_ppn,
                kategori.nama_kategori
            ')
            ->join('barang', 'barang.kode_barang = stok_barang.kode_barang', 'left')
            ->join('kategori', 'kategori.id = barang.idkategori', 'left')
            ->where('barang.deleted', '0')
            ->where('kategori.delete', '0');
    }

    private function applyKartuStokFilters($builder, $search = '', $unit = '', $ppn = '')
    {
        if ($search !== '') {
            $builder->groupStart()
                ->like('stok_barang.kode_barang', $search)
                ->orLike('stok_barang.nama_barang', $search)
                ->orLike('stok_barang.imei', $search)
                ->orLike('stok_barang.nama_unit', $search)
                ->orLike('kategori.nama_kategori', $search)
                ->groupEnd();
        }

        if ($unit !== '') {
            $builder->where('stok_barang.id_unit', $unit);
        }

        if ($ppn === 'PPN') {
            $builder->where('barang.status_ppn', '1');
        } elseif ($ppn === 'Non PPN') {
            $builder->where('barang.status_ppn', '0');
        }

        return $builder;
    }

    /**
     * Server-side processing DataTables Kartu Stok.
     */
    public function getKartuStokDT($limit, $offset, $search = '', $orderCol = 'stok_barang.kode_barang', $orderDir = 'ASC', $unit = '', $ppn = '')
    {
        $allowedOrder = [
            'stok_barang.kode_barang',
            'stok_barang.nama_barang',
            'stok_barang.nama_unit',
            'kategori.nama_kategori',
            'barang.status_ppn',
            'stok_barang.stok_akhir',
        ];
        if (!in_array($orderCol, $allowedOrder, true)) {
            $orderCol = 'stok_barang.kode_barang';
        }
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $builder = $this->applyKartuStokFilters($this->kartuStokBuilder(), $search, $unit, $ppn);

        return $builder->orderBy($orderCol, $orderDir)
            ->limit($limit, $offset)
            ->get()
            ->getResult();
    }

    /**
     * Total keseluruhan (tanpa filter tambahan, hanya data aktif).
     */
    public function countKartuStokAll()
    {
        return $this->kartuStokBuilder()->countAllResults(false);
    }

    /**
     * Total data setelah filter search/unit/ppn.
     */
    public function countKartuStokDT($search = '', $unit = '', $ppn = '')
    {
        return $this->applyKartuStokFilters($this->kartuStokBuilder(), $search, $unit, $ppn)->countAllResults(false);
    }

    /**
     * Ringkasan untuk kartu statistik halaman.
     */
    public function getSummaryKartuStok()
    {
        return $this->db->table('stok_barang')
            ->select('
                COUNT(*) AS total_barang,
                COUNT(DISTINCT stok_barang.id_unit) AS total_unit,
                COALESCE(SUM(stok_barang.stok_akhir), 0) AS total_stok,
                COALESCE(SUM(stok_barang.stok_akhir * barang.harga_beli), 0) AS nilai_stok
            ')
            ->join('barang', 'barang.kode_barang = stok_barang.kode_barang', 'left')
            ->join('kategori', 'kategori.id = barang.idkategori', 'left')
            ->where('barang.deleted', '0')
            ->where('kategori.delete', '0')
            ->get()
            ->getRow();
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