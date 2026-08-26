<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelStokOpnameDraft extends Model
{
    protected $table = 'stok_opname_draft';
    protected $primaryKey = 'idstok_opname';
    protected $returnType = 'object';
    protected $allowedFields = ['idstok_opname', 'tanggal', 'hpp', 'jumlah_real', 'jumlah_komp', 'jumlah_selisih', 'satuan_terkecil', 'barang_idbarang', 'unit_idunit'];

    public function getStokOpname()
    {
        return $this->findAll();
    }

    public function getByIdBarang($id)
    {
        return $this->where(['barang_idbarang' => $id])->first();
    }


    public function insert_StokOpnameDraft($data)
    {
        return $this->insert($data);
    }


    public function getById($id)
    {
        return $this->where(['idstok_opname' => $id])->first();
    }

    public function getStokOpnameDraft()
    {
        return $this->select('
            stok_opname_draft.*, 
            barang.kode_barang, 
            barang.nama_barang, 
            barang.jenis_hp, 
            barang.warna, 
            unit.NAMA_UNIT
        ')
            ->join('barang', 'barang.idbarang = stok_opname_draft.barang_idbarang')
            ->join('unit', 'unit.idunit = stok_opname_draft.unit_idunit')
            ->orderBy('stok_opname_draft.tanggal', 'DESC')
            ->findAll();
    }

    /**
     * Server-side processing untuk DataTables (tabel Draft).
     */
    public function getStokOpnameDraftDT($limit, $offset, $search = '', $orderCol = 'stok_opname_draft.tanggal', $orderDir = 'DESC', $unitFilter = '')
    {
        $allowedOrder = ['stok_opname_draft.tanggal', 'barang.kode_barang', 'barang.nama_barang', 'unit.NAMA_UNIT'];
        if (!in_array($orderCol, $allowedOrder, true)) {
            $orderCol = 'stok_opname_draft.tanggal';
        }
        $orderDir = strtolower($orderDir) === 'asc' ? 'ASC' : 'DESC';

        $builder = $this->db->table('stok_opname_draft')
            ->select('
                stok_opname_draft.idstok_opname,
                stok_opname_draft.tanggal,
                stok_opname_draft.jumlah_real,
                stok_opname_draft.jumlah_komp,
                stok_opname_draft.jumlah_selisih,
                barang.kode_barang,
                barang.nama_barang,
                barang.jenis_hp,
                barang.warna,
                unit.NAMA_UNIT,
                stok_opname_draft.barang_idbarang,
                stok_opname_draft.unit_idunit
            ')
            ->join('barang', 'barang.idbarang = stok_opname_draft.barang_idbarang')
            ->join('unit', 'unit.idunit = stok_opname_draft.unit_idunit');

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

    public function countStokOpnameDraftDT($search = '', $unitFilter = '')
    {
        $builder = $this->db->table('stok_opname_draft')
            ->join('barang', 'barang.idbarang = stok_opname_draft.barang_idbarang')
            ->join('unit', 'unit.idunit = stok_opname_draft.unit_idunit');

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

    public function existsForToday($barang_idbarang, $unit_idunit)
    {
        return $this->where([
            'tanggal' => date('Y-m-d'),
            'barang_idbarang' => $barang_idbarang,
            'unit_idunit' => $unit_idunit
        ])->countAllResults() > 0;
    }
}
