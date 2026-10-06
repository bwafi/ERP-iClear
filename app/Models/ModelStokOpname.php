<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelStokOpname extends Model
{
    protected $table = 'stok_opname';
    protected $primaryKey = 'idstok_opname';
    protected $returnType = 'object';
    protected $allowedFields = ['idstok_opname', 'tanggal', 'hpp', 'jumlah_real', 'jumlah_komp', 'jumlah_selisih', 'satuan_terkecil', 'barang_idbarang', 'unit_idunit', 'periode_id', 'is_reverted'];

    /**
     * Baris yang sudah di-reopen (is_reverted = 1) dikeluarkan dari pembacaan
     * normal. Nilai lamanya tetap ada di tabel sebagai riwayat, tapi tidak ikut
     * dihitung lagi — termasuk tidak ikut menjumlahkan stok, karena view
     * stok_barang juga memfilternya.
     */
    private function aktif()
    {
        return $this->where('stok_opname.is_reverted', 0);
    }

    public function getStokOpname()
    {
        return $this->aktif()->findAll();
    }

    public function getByIdBarang($id)
    {
        return $this->where(['barang_idbarang' => $id, 'is_reverted' => 0])->first();
    }


    public function insert_StokOpnameFix($data)
    {
        return $this->insert($data);
    }


    public function getById($id)
    {
        return $this->where(['idstok_opname' => $id])->first();
    }

    public function getStokOpnameAll()
    {
        return $this->select('
            stok_opname.*, 
            barang.kode_barang, 
            barang.nama_barang, 
            barang.jenis_hp,  
            barang.warna, 
            unit.NAMA_UNIT
        ')
            ->join('barang', 'barang.idbarang = stok_opname.barang_idbarang')
            ->join('unit', 'unit.idunit = stok_opname.unit_idunit')
            ->where('stok_opname.is_reverted', 0)
            ->orderBy('stok_opname.tanggal', 'DESC')
            ->findAll();
    }

    /**
     * Server-side processing untuk DataTables (tabel Fixed).
     */
    public function getStokOpnameAllDT($limit, $offset, $search = '', $orderCol = 'stok_opname.tanggal', $orderDir = 'DESC', $unitFilter = '')
    {
        $allowedOrder = ['stok_opname.tanggal', 'barang.kode_barang', 'barang.nama_barang', 'unit.NAMA_UNIT'];
        if (!in_array($orderCol, $allowedOrder, true)) {
            $orderCol = 'stok_opname.tanggal';
        }
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';

        $builder = $this->db->table('stok_opname')
            ->select('
                stok_opname.idstok_opname,
                stok_opname.tanggal,
                stok_opname.jumlah_real,
                stok_opname.jumlah_komp,
                stok_opname.jumlah_selisih,
                barang.kode_barang,
                barang.nama_barang,
                barang.jenis_hp,
                barang.warna,
                unit.NAMA_UNIT,
                stok_opname.barang_idbarang,
                stok_opname.unit_idunit
            ')
            ->join('barang', 'barang.idbarang = stok_opname.barang_idbarang')
            ->join('unit', 'unit.idunit = stok_opname.unit_idunit')
            ->where('stok_opname.is_reverted', 0);

        if ($search !== '') {
            $builder->groupStart()
                ->like('barang.kode_barang', $search)
                ->orLike('barang.nama_barang', $search)
                ->orLike('unit.NAMA_UNIT', $search)
                ->groupEnd();
        }

        if ($unitFilter !== '') {
            $builder->where('unit.NAMA_UNIT', $unitFilter);
        }

        $builder->orderBy($orderCol, $orderDir)
            ->limit($limit, $offset);

        return $builder->get()->getResult();
    }

    public function countStokOpnameAllDT($search = '', $unitFilter = '')
    {
        $builder = $this->db->table('stok_opname')
            ->join('barang', 'barang.idbarang = stok_opname.barang_idbarang')
            ->join('unit', 'unit.idunit = stok_opname.unit_idunit')
            ->where('stok_opname.is_reverted', 0);

        if ($search !== '') {
            $builder->groupStart()
                ->like('barang.kode_barang', $search)
                ->orLike('barang.nama_barang', $search)
                ->orLike('unit.NAMA_UNIT', $search)
                ->groupEnd();
        }

        if ($unitFilter !== '') {
            $builder->where('unit.NAMA_UNIT', $unitFilter);
        }

        return $builder->countAllResults(false);
    }


    public function exportfilter($tanggalAwal = null, $tanggalAkhir = null, $namaUnit = null, $termasukReverted = false)
    {
        $builder = $this->select('
            stok_opname.*, 
            barang.kode_barang, 
            barang.nama_barang, 
            barang.jenis_hp,  
            barang.warna, 
            unit.NAMA_UNIT
        ')
            ->join('barang', 'barang.idbarang = stok_opname.barang_idbarang')
            ->join('unit', 'unit.idunit = stok_opname.unit_idunit');

        // Revisi yang sudah di-reopen tidak dihitung sebagai hasil opname yang
        // berlaku, kecuali pemanggil memang meminta melihat riwayatnya.
        if (! $termasukReverted) {
            $builder->where('stok_opname.is_reverted', 0);
        }


        if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {

            $tanggalAkhir .= ' 23:59:59';
            $builder->where('stok_opname.tanggal >=', $tanggalAwal)
                ->where('stok_opname.tanggal <=', $tanggalAkhir);
        }

        if (!empty($namaUnit)) {
            $builder->where('unit.NAMA_UNIT', $namaUnit);
        }

        return $builder->orderBy('stok_opname.tanggal', 'DESC')->findAll();
    }

    public function existsForToday($barang_idbarang, $unit_idunit)
    {
        return $this->where([
            'tanggal' => date('Y-m-d'),
            'barang_idbarang' => $barang_idbarang,
            'unit_idunit' => $unit_idunit
        ])->countAllResults() > 0;
    }
}
