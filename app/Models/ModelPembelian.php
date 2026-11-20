<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPembelian extends Model
{
    protected $table = 'pembelian';
    protected $primaryKey = 'idpembelian';
    protected $returnType = 'object';
    protected $allowedFields = ['idpembelian', 'no_nota_supplier', 'jatuh_tempo', 'foto_nota', 'tanggal_masuk', 'sisa', 'status', 'total_transaksi', 'total_diskon', 'total_ppn', 'total_bayar', 'bayar', 'bayar_bank', 'bank_idbank', 'bayar_tunai', 'unit_idunit', 'suplier_id_suplier', 'pelanggan_id_pelanggan', 'input_by', 'frontliner'];


    public function getPembelian()
    {
        return $this->findAll();
    }

    public function insert_Pembelian($data)
    {
        if (! $this->insert($data)) {
            return false;
        }

        return (int) $this->insertID();
    }



    public function getById($id_pembelian)
    {
        return $this->where(['idpembelian' => $id_pembelian])->first();
    }

    public function getBelumLunas()
    {
        return $this->select('
                pembelian.*,
                unit.NAMA_UNIT
            ')
            ->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left')
            ->where('pembelian.status', 'Belum Lunas')
            ->findAll();
    }


    public function getBelumLunasFiltered($tanggalAwal = null, $tanggalAkhir = null, $namaUnit = null)
    {
        $builder = $this->select('
            pembelian.*,
            unit.NAMA_UNIT
        ')
            ->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left')
            ->where('pembelian.status', 'Belum Lunas');

        // Filter tanggal masuk
        if (!empty($tanggalAwal) && !empty($tanggalAkhir)) {
            $builder->where('pembelian.tanggal_masuk >=', $tanggalAwal)
                ->where('pembelian.tanggal_masuk <=', $tanggalAkhir);
        } elseif (!empty($tanggalAwal)) {
            $builder->where('pembelian.tanggal_masuk >=', $tanggalAwal);
        } elseif (!empty($tanggalAkhir)) {
            $builder->where('pembelian.tanggal_masuk <=', $tanggalAkhir);
        }

        // Filter nama unit
        if (!empty($namaUnit)) {
            $builder->where('unit.NAMA_UNIT', $namaUnit);
        }

        return $builder->findAll();
    }




    public function getAgingHutang()
    {
        $builder = $this->db->table('pembelian');
        $builder->select("
        pembelian.idpembelian,
        pembelian.no_nota_supplier,
        pembelian.tanggal_masuk,
        pembelian.jatuh_tempo,
        pembelian.total_bayar,
        pembelian.sisa,
        DATEDIFF(CURDATE(), pembelian.jatuh_tempo) AS umur_hari,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) <= 30 THEN pembelian.sisa ELSE 0 END AS `0_30_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 31 AND 60 THEN pembelian.sisa ELSE 0 END AS `31_60_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 61 AND 90 THEN pembelian.sisa ELSE 0 END AS `61_90_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) > 90 THEN pembelian.sisa ELSE 0 END AS `lebih_90_hari`,
        unit.NAMA_UNIT as nama_unit,
        akun.NAMA_AKUN as nama_akun
    ");

        $builder->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left');
        $builder->join('akun', 'akun.ID_AKUN = pembelian.input_by', 'left');

        $builder->where('pembelian.status', 'Belum Lunas');
        $builder->orderBy('pembelian.jatuh_tempo', 'ASC');

        return $builder->get()->getResult();
    }


    public function getAgingHutangfiltered($tanggal_awal = null, $tanggal_akhir = null, $nama_unit = null)
    {
        $builder = $this->db->table('pembelian');
        $builder->select("
        pembelian.idpembelian,
        pembelian.no_nota_supplier,
        pembelian.tanggal_masuk,
        pembelian.jatuh_tempo,
        pembelian.total_bayar,
        pembelian.sisa,
        DATEDIFF(CURDATE(), pembelian.jatuh_tempo) AS umur_hari,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) <= 30 THEN pembelian.sisa ELSE 0 END AS `0_30_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 31 AND 60 THEN pembelian.sisa ELSE 0 END AS `31_60_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) BETWEEN 61 AND 90 THEN pembelian.sisa ELSE 0 END AS `61_90_hari`,
        CASE WHEN DATEDIFF(CURDATE(), pembelian.jatuh_tempo) > 90 THEN pembelian.sisa ELSE 0 END AS `lebih_90_hari`,
        unit.NAMA_UNIT as nama_unit,
        akun.NAMA_AKUN as nama_akun
    ");

        $builder->join('unit', 'unit.idunit = pembelian.unit_idunit', 'left');
        $builder->join('akun', 'akun.ID_AKUN = pembelian.input_by', 'left');

        // hanya hutang yang belum lunas
        $builder->where('pembelian.status', 'Belum Lunas');

        // filter tanggal
        if (!empty($tanggal_awal) && !empty($tanggal_akhir)) {
            $builder->where("pembelian.tanggal_masuk >=", $tanggal_awal);
            $builder->where("pembelian.tanggal_masuk <=", $tanggal_akhir);
        }

        // filter unit
        if (!empty($nama_unit)) {
            $builder->where('unit.NAMA_UNIT', $nama_unit);
        }

        $builder->orderBy('pembelian.jatuh_tempo', 'ASC');

        return $builder->get()->getResult();
    }
}
