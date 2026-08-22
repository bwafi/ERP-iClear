<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPenilaian extends Model
{
    protected $table            = 'penilaian';
    protected $primaryKey       = 'idpenilaian';
    protected $returnType       = 'object';
    protected $allowedFields    = [
        'aspek',
        'keterangan',
        'skor',
        'pegawai_idpegawai',
        'input_by',
        'tanggal_penilaian',
        'created_on',
        'updated_on'
    ];

    public function getPenilaian()
    {
        return $this->select('
            penilaian.*,
            akun_pegawai.NAMA_AKUN as nama_pegawai,
            akun_input.NAMA_AKUN as nama_input
        ')
        ->join('akun akun_pegawai', 'akun_pegawai.ID_AKUN = penilaian.pegawai_idpegawai', 'left')
        ->join('akun akun_input', 'akun_input.ID_AKUN = penilaian.input_by', 'left')
        ->findAll();
    }

    public function insertPenilaian($data)
    {
        return $this->insert($data);
    }


    public function getPenilaianByTanggal($tanggalAwal, $tanggalAkhir)
    {
        return $this->select('penilaian.*, akun.NAMA_AKUN')
            ->join('akun', 'akun.ID_AKUN = penilaian.pegawai_idpegawai', 'left')
            ->where('tanggal_penilaian >=', $tanggalAwal)
            ->where('tanggal_penilaian <=', $tanggalAkhir)
            ->findAll();
    }
    
public function getSkorByPegawaiAndJabatan($pegawai_idpegawai, $jabatan_idjabatan)
{
    $result = $this->select('aspek, skor')
        ->where('pegawai_idpegawai', $pegawai_idpegawai)
        ->findAll();

    $skorMap = [];

    foreach ($result as $row) {
        // Use aspek as key, value is skor
        $skorMap[$row->aspek] = $row->skor;
    }

    return $skorMap;
}

public function getJumlahByTemplatePenilaian($id_akun = null)
{
    $builder = $this->db->table('tugas')
        ->select('
            template_penilaian.idtemplate_penilaian,
            template_penilaian.aspek_penilaian,
            tugas.jumlah
        ')
        ->join('template_penilaian', 'template_penilaian.idtemplate_penilaian = tugas.template_penilaian_idtemplate_penilaian', 'left')
        ->where('tugas.status', 4); // only status = 4

    if ($id_akun) {
        $builder->where('tugas.akun_ID_AKUN', $id_akun);
    }

    return $builder->get()->getResult();
}

}