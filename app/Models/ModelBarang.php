<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelBarang extends Model
{
    protected $table = 'barang';
    protected $primaryKey = 'idbarang';
    protected $returnType = 'object';
    protected $allowedFields = ['kode_barang', 'nama_barang', 'harga', 'harga_beli', 'input', 'idkategori', 'id_sub_kategori', 'imei', 'stok_minimum', 'jenis_hp', 'internal', 'warna', 'status', 'status_barang', 'deleted', 'status_ppn', 'nama_barang_id'];

    public function getBarang()
    {
        return $this->select('barang.*, kategori.nama_kategori, sub_kategori.nama_sub_kategori')
            ->join('kategori', 'kategori.id = barang.idkategori')
            ->join('sub_kategori', 'sub_kategori.id = barang.id_sub_kategori', 'left')
            ->where('barang.deleted', '0')
            ->where('kategori.delete', '0')
            ->where('barang.idkategori !=', 1)
            ->findAll();
    }

    public function semuaBarang()
    {
        return $this
            ->where('barang.deleted', 0)
            ->findAll();
    }

    public function getAllBarang()
    {
        $id_unit = session('ID_UNIT');
        return $this->select('barang.*, kategori.nama_kategori, stok_barang.stok_akhir')
            ->join('kategori', 'kategori.id = barang.idkategori')
            ->join('stok_barang', 'stok_barang.idbarang = barang.idbarang AND stok_barang.id_unit = ' . (int)$id_unit, 'left')
            ->where('barang.deleted', 0)
            ->where('kategori.delete', 0)
            ->findAll();
    }

    /**
     * Stok akhir semua barang per unit: [id_unit => [idbarang => stok]].
     *
     * Dipakai halaman mutasi untuk menampilkan stok unit pengirim yang
     * sedang dipilih, bukan hanya unit user yang login.
     */
    public function getStokPerUnit(?int $idUnit = null): array
    {
        $builder = $this->db->table('stok_barang')
            ->select('idbarang, id_unit, stok_akhir');
        if ($idUnit !== null) {
            $builder->where('id_unit', $idUnit);
        }

        $rows = $builder->get()->getResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->id_unit][(int) $r->idbarang] = (float) $r->stok_akhir;
        }

        return $map;
    }

    /**
     * Suplier terakhir per barang per unit: [id_unit => [idbarang => nama]].
     *
     * Barang tidak menyimpan suplier; sumbernya baris stok awal terbaru.
     * Satu query agregat, bukan satu query per baris produk.
     */
    public function getSuplierPerUnit(?int $idUnit = null): array
    {
        $sql = "SELECT sa.unit_idunit, sa.barang_idbarang, s.nama_suplier
             FROM stok_awal sa
             JOIN (
                 SELECT unit_idunit, barang_idbarang, MAX(idstok_awal) AS idstok_awal
                 FROM stok_awal
                 WHERE suplier_id_suplier IS NOT NULL AND suplier_id_suplier <> ''
                 GROUP BY unit_idunit, barang_idbarang
             ) terakhir ON terakhir.idstok_awal = sa.idstok_awal
             LEFT JOIN suplier s ON s.id_suplier = sa.suplier_id_suplier";
        $params = [];
        if ($idUnit !== null) {
            $sql .= ' WHERE sa.unit_idunit = ?';
            $params[] = $idUnit;
        }

        $rows = $this->db->query($sql, $params)->getResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->unit_idunit][(int) $r->barang_idbarang] = (string) ($r->nama_suplier ?? '');
        }

        return $map;
    }

    //gor push ulang

    public function getAllBarang2()
    {
        $id_unit = session('ID_UNIT');
        return $this
            ->select('barang.*, barang.status_barang as kondisi, kategori.nama_kategori, stok_barang.stok_akhir')
            ->join('kategori', 'kategori.id = barang.idkategori')
            ->join('stok_barang', 'stok_barang.idbarang = barang.idbarang AND stok_barang.id_unit = ' . (int)$id_unit, 'left')
            ->where('barang.deleted', 0)
            ->where('kategori.delete', 0)->where('stok_barang.stok_akhir >', 0)->findAll();
    }


    public function insert_Barang($data)
    {
        return $this->insert($data);
    }


    public function getById($id)
    {
        return $this
            ->where('barang.idbarang', $id)
            ->first();
    }

    public function getBykode($kodeBarang)
    {
        return $this
            ->where(['kode_barang' => $kodeBarang])
            ->first();
    }

    public function getLastBarangByKategori($idkategori, $kode_kategori)
    {
        $panjangPrefix = strlen($kode_kategori);
    
        return $this->db->table('barang')
            ->select("MAX(CAST(SUBSTRING(kode_barang, " . ($panjangPrefix + 1) . ") AS UNSIGNED)) as max_kode")
            ->where('idkategori', $idkategori)
            ->get()
            ->getRow();
    }

    public function getBarangSparepart()
    {
        return $this->select('barang.*, kategori.nama_kategori')
            ->join('kategori', 'kategori.id = barang.idkategori')
            ->where('barang.idkategori', 3)->findAll();
    }
    
    public function getBarangByJenis($jenis = null)
    {
        $builder = $this->db->table('barang');

        if (!empty($jenis)) {

            if ($jenis == 'ACC') {

                $builder->where('idkategori', 2);

            } elseif ($jenis == 'HP') {

                $builder->where('idkategori', 1);

            } else {

                $builder->like('nama_barang', $jenis);

            }
        }

        return $builder->get()->getResult();
    }
}
