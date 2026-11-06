<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelPresensi extends Model
{
    protected $table = 'presensi';
    protected $primaryKey = 'idpresensi';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idpresensi',
        'waktu_masuk',
        'waktu_pulang',
        'jam_jadwal_masuk',
        'jam_jadwal_pulang',
        'jam_toleransi',
        'status_absensi',
        'lat',
        'long',
        'ip',
        'foto',
        'foto_pulang',
        'idjadwal_masuk',
        'akun_idakun',
        'unit_idunit',
        'created_at',
        'updated_at',
        'jarak',
        'status_kehadiran',
        'keterangan',
        'sesi',
        'sumber',
    ];

    /** @return list<object> */
    public function getHariIniByAkun(int $akunId, string $tanggal): array
    {
        return $this->where('akun_idakun', $akunId)
            ->where('DATE(waktu_masuk)', $tanggal)
            ->findAll();
    }

    public function getByAkunTanggalSesi(int $akunId, string $tanggal, string $sesi)
    {
        return $this->where('akun_idakun', $akunId)
            ->where('DATE(waktu_masuk)', $tanggal)
            ->where('sesi', $sesi)
            ->first();
    }

    public function getAll()
    {
        return $this->select('presensi.*, 
                          jadwal_masuk.nama_jadwal,
                          jadwal_masuk.toleransi,
                          jadwal_masuk.jam_masuk as jmmasuk, 
                          akun.NAMA_AKUN, 
                          akun.ID_UNIT, 
                          akun.ID_JABATAN,
                          unit.NAMA_UNIT,
                          unit.LATITUDE, 
                          unit.LONGTITUDE')
            ->join('jadwal_masuk', 'jadwal_masuk.idjadwal_masuk = presensi.idjadwal_masuk', 'left')
            ->join('akun', 'akun.ID_AKUN = presensi.akun_idakun', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->findAll();
    }





    public function getPresensiHariIni($akunId, $tanggalHariIni)
    {
        return $this->select('presensi.*, 
                          jadwal_masuk.nama_jadwal, 
                          akun.NAMA_AKUN, 
                          akun.ID_UNIT, 
                          unit.NAMA_UNIT,
                          unit.LATITUDE, 
                          unit.LONGTITUDE')
            ->join('jadwal_masuk', 'jadwal_masuk.idjadwal_masuk = presensi.idjadwal_masuk', 'left')
            ->join('akun', 'akun.ID_AKUN = presensi.akun_idakun', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->where('presensi.akun_idakun', $akunId)
            ->where('DATE(presensi.waktu_masuk)', $tanggalHariIni)
            ->findAll();
    }



    public function filterexport($tanggalAwal, $tanggalAkhir, array $unitIds = [])
    {
        $tanggalAkhir .= ' 23:59:59';
        //tambahan
        $builder = $this->select('presensi.*,
                              jadwal_masuk.nama_jadwal,
                              akun.NAMA_AKUN,
                              akun.ID_UNIT,
                              unit.NAMA_UNIT,
                              unit.LATITUDE,
                              unit.LONGTITUDE')
            ->join('jadwal_masuk', 'jadwal_masuk.idjadwal_masuk = presensi.idjadwal_masuk', 'left')
            ->join('akun', 'akun.ID_AKUN = presensi.akun_idakun', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->where('presensi.created_at >=', $tanggalAwal)
            ->where('presensi.created_at <=', $tanggalAkhir);

        if ($unitIds !== []) {
            $builder->whereIn('akun.ID_UNIT', $unitIds);
        }

        return $builder->findAll();
    }




    public function getById($id)
    {
        return $this->where(['idpresensi' => $id])->first();
    }

    public function getByIdAkun($id)
    {
        return $this->select('presensi.*, 
                              jadwal_masuk.nama_jadwal, 
                              jadwal_masuk.toleransi,
                          jadwal_masuk.jam_masuk as jmmasuk, 
                              akun.NAMA_AKUN, 
                              akun.ID_UNIT, 
                              unit.NAMA_UNIT,
                              unit.LATITUDE, 
                              unit.LONGTITUDE')
            ->join('jadwal_masuk', 'jadwal_masuk.idjadwal_masuk = presensi.idjadwal_masuk', 'left')
            ->join('akun', 'akun.ID_AKUN = presensi.akun_idakun', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->where('presensi.akun_idakun', $id)
            ->findAll();
    }



    public function insertPresensi($data)
    {
        return $this->insert($data);
    }

    public function getByAkun($akun_id)
    {
        return $this->where(['akun_idakun' => $akun_id])->findAll();
    }

    public function getUnconfirmed()
    {
        return $this->where(['status_absensi' => 0])->findAll();
    }

    public function getConfirmed()
    {
        return $this->where(['status_absensi' => 1])->findAll();
    }

    //     public function countAbsensiPerBulan($akunId, $tanggalPenilaian)
    // {
    //     // Convert given date into start and end of month
    //     $startDate = date('Y-m-01 00:00:00', strtotime($tanggalPenilaian));
    //     $endDate   = date('Y-m-t 23:59:59', strtotime($tanggalPenilaian));

    //     return $this->selectCount('idpresensi', 'total_absensi')
    //         ->where('akun_idakun', $akunId)
    //         ->where('waktu_masuk >=', $startDate)
    //         ->where('waktu_masuk <=', $endDate)
    //         ->first();
    // }



    //     public function countOnTimeAbsensiPerBulan($akunId, $startDate, $endDate)
    //     {
    //         return $this->selectCount('idpresensi', 'total_ontime')
    //             ->where('akun_idakun', $akunId)
    //             ->where('waktu_masuk >=', $startDate)
    //             ->where('waktu_masuk <=', $endDate)
    //             ->where('waktu_masuk <= ADDTIME(jam_jadwal_masuk, jam_toleransi)')
    //             ->first();
    //     }

    public function countAbsensiPerBulan($akunId, $startDate, $endDate)
    {
        return $this->selectCount('idpresensi', 'total_absensi')
            ->where('akun_idakun', $akunId)
            ->where('waktu_masuk >=', $startDate)
            ->where('waktu_masuk <=', $endDate)
            ->first();
    }

    public function countOnTimeAbsensiPerBulan($akunId, $startDate, $endDate)
    {
        return $this->selectCount('idpresensi', 'total_ontime')
            ->where('akun_idakun', $akunId)
            ->where('waktu_masuk >=', $startDate)
            ->where('waktu_masuk <=', $endDate)
            ->where('waktu_masuk <= ADDTIME(jam_jadwal_masuk, jam_toleransi)')
            ->first();
    }

    public function countGrooming($akunId, $startDate, $endDate)
    {
        return $this->selectCount('idpresensi', 'total_grooming')
            ->where('akun_idakun', $akunId)
            ->where('waktu_masuk >=', $startDate)
            ->where('waktu_masuk <=', $endDate)
            ->where('status_absensi', 1)
            ->first();
    }
}
