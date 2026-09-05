<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelAsset extends Model
{
    protected $table = 'asset';
    protected $primaryKey = 'idasset';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idasset',
        'unit',
        'asset_code',
        'asset',
        'tanggal_perolehan',
        'nilai_perolehan',
        'penyusutan_bulanan',
        'jangka_waktu',
        'nilai_sekarang',
        'kondisi',
        'keterangan',
        'deleted',
        'idkategori_asset'
    ];


    public function getAsset()
    {
        // Subquery riwayat_asset per asset
        $subQuery = $this->db->table('riwayat_asset AS ra1')
            ->select('ra1.asset_idasset, ra1.nilai_riwayat')
            ->join(
                '(SELECT asset_idasset, MAX(tanggal_penyusutan) AS max_tanggal 
              FROM riwayat_asset 
              GROUP BY asset_idasset) AS ra2',
                'ra1.asset_idasset = ra2.asset_idasset AND ra1.tanggal_penyusutan = ra2.max_tanggal'
            );

        return $this->select('asset.*, ra.nilai_riwayat AS nilai_sekarang, ka.kategori_asset AS nama_kategori_asset')
            ->join('(' . $subQuery->getCompiledSelect() . ') AS ra', 'ra.asset_idasset = asset.idasset', 'left')
            ->join('kategori_asset AS ka', 'ka.idkategori_asset = asset.idkategori_asset', 'left')
            ->where('asset.deleted', 0)
            ->findAll();
    }

    public function getAssetLama()
    {
        return $this->select('asset.*, ka.kategori_asset AS nama_kategori_asset')
            ->join('kategori_asset AS ka', 'ka.idkategori_asset = asset.idkategori_asset', 'left')
            ->where('asset.deleted', 1)
            ->findAll();
    }




    public function getById($id)
    {
        return $this->where('idasset', $id)->where('deleted', 0)->first();
    }


    public function insert_Asset($data)
    {
        return $this->insert($data);
    }
}
