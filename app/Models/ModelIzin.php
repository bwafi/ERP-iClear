<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelIzin extends Model
{
    protected $table = 'izin';
    protected $primaryKey = 'idizin';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idizin',
        'akun_idakun',
        'alasan',
        'status',
        'tanggal_mulai',
        'uuid_atasan',
        'pegawai_uuid',
        'tanggal_akhir',
        'file',
        'balasan',
        'jenis_perizinan_idjenis_perizinan'
    ];


    public function getAll()
    {
        return $this->findAll();
    }


    public function getById($id)
    {
        return $this->where(['idizin' => $id])->first();
    }

    public function insertIzin($data)
    {
        return $this->insert($data);
    }

    // Ambil data izin berdasarkan pegawai
    public function getByPegawai($uuid)
    {
        return $this->where(['pegawai_uuid' => $uuid])->findAll();
    }

    // Ambil data izin berdasarkan status
    public function getByStatus($status)
    {
        return $this->where(['status' => $status])->findAll();
    }

    // Ambil data izin beserta jenis perizinannya
    public function getWithJenisPerizinan()
    {
        return $this->select('izin.*, jenis_perizinan.jenis_izin')
            ->join('jenis_perizinan', 'jenis_perizinan.idjenis_perizinan = izin.jenis_perizinan_idjenis_perizinan')
            ->findAll();
    }

    // Ambil data izin pegawai tertentu dengan jenis perizinan
    public function getByPegawaiWithJenis($uuid)
    {
        return $this->select('izin.*, jenis_perizinan.jenis_izin')
            ->join('jenis_perizinan', 'jenis_perizinan.idjenis_perizinan = izin.jenis_perizinan_idjenis_perizinan')
            ->where('pegawai_uuid', $uuid)
            ->findAll();
    }

    public function getByAkun(int $akunId)
    {
        return $this->select('izin.*, jenis_perizinan.jenis_izin')
            ->join('jenis_perizinan', 'jenis_perizinan.idjenis_perizinan = izin.jenis_perizinan_idjenis_perizinan', 'left')
            ->where('akun_idakun', $akunId)
            ->orderBy('idizin', 'DESC')
            ->findAll();
    }

    public function getWithAkun()
    {
        return $this->select('izin.*, akun.NAMA_AKUN, akun.ID_UNIT, unit.NAMA_UNIT, jenis_perizinan.jenis_izin')
            ->join('akun', 'akun.ID_AKUN = izin.akun_idakun', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->join('jenis_perizinan', 'jenis_perizinan.idjenis_perizinan = izin.jenis_perizinan_idjenis_perizinan', 'left')
            ->orderBy('izin.status', 'ASC')
            ->orderBy('izin.idizin', 'DESC')
            ->findAll();
    }
}
