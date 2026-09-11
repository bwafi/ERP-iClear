<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelAuth extends Model
{
    protected $table = 'akun';
    protected $primaryKey = 'ID_AKUN';
    protected $returnType = 'object';
    protected $allowedFields = ['ID_AKUN', 'ID_UNIT', 'ID_PANGKAT', 'ID_JABATAN', 'ID_GEDUNG', 'NOID', 'KTP', 'EMAIL', 'PASSWORD', 'ROLES', 'IS_DOKTER', 'NAMA_AKUN', 'ALAMAT', 'JENIS_KELAMIN', 'TELEPON', 'HP', 'AGAMA', 'STATUS_PEGAWAI', 'FOTO_KTP', 'FOTO_KK', 'JENIS_PEGAWAI'];

    function get_allakun()
    {
        $db = db_connect()->table($this->table);
        $db->join('pangkat', 'pangkat.ID_PANGKAT=akun.ID_PANGKAT');
        $db->join('gedung', 'gedung.ID_GEDUNG=akun.ID_GEDUNG');
        $db->join('jabatan', 'jabatan.ID_JABATAN=akun.ID_JABATAN');
        if (session()->get('ID_GEDUNG') != 0) {
            $db->where('akun.ID_GEDUNG', session()->get('ID_GEDUNG'));
        }
        $db->orderBy('akun.ID_GEDUNG', 'ASC');
        return $db->get();
    }

    public function getAkunPegawai()
    {
        return $this->select('akun.*, jabatan.NAMA_JABATAN, unit.NAMA_UNIT')
            ->join('jabatan', 'jabatan.ID_JABATAN = akun.ID_JABATAN', 'left')
            ->join('unit', 'unit.idunit = akun.ID_UNIT', 'left')
            ->findAll();
    }



    public function getAkunFrontliner($id_jabatan = 37)
    {
        return $this->where('ID_JABATAN', $id_jabatan)->findAll();
    }

    /** CS (jabatan 42) & Kepala Divisi (43) untuk dropdown PIC lead. */
    public function getCsKadiv()
    {
        return $this->whereIn('ID_JABATAN', [42, 43])
            ->where('STATUS_PEGAWAI', 1)
            ->orderBy('NAMA_AKUN', 'ASC')
            ->findAll();
    }


    public function cekUsername($username)
    {
        $db = db_connect()->table($this->table)
            ->where("NOID", $username)
            ->orWhere("KTP", $username)
            ->orWhere("EMAIL", $username);
        return $db->get();
    }

    public function get_unit($id)
    {
        $db = db_connect()->table("unit")->where("idunit", $id);
        return $db->get()->getRowArray();
    }

    public function getJabatan()
    {
        $db = db_connect()->table("jabatan");
        return $db->get()->getResultObject();
    }

    public function get_nama_jabatan($id)
    {
        $db = db_connect()->table("jabatan")->where("ID_JABATAN", $id);
        return $db->get()->getRowArray();
    }

    public function insert_akun($data)
    {
        return $this->db->table($this->table)->insert($data);
    }

    public function getById($id)
    {
        return $this->where(['ID_AKUN' => $id])->first();
    }

    function getRolesAktif()
    {
        return $this->db->table('menu')
            // ->where(array("url !=" => null))
            ->get()->getResultObject();
    }

    function getRolesJabatan($roles)
    {
        return $this->db->table('menu')->whereIn('idmenu', $roles)->get()->getResultObject();
    }

    function getAkun($idunit = null)
    {
        $query = $this->db->table('akun');
        if ($idunit !== null && $idunit !== '') {
            $query->where(array("ID_UNIT" => $idunit));
        }
        $query->where(array("STATUS_PEGAWAI" => 1));
        $query->join('unit', 'unit.idunit=akun.ID_UNIT');
        $query->join('jabatan', 'jabatan.ID_JABATAN=akun.ID_JABATAN');
        return $query->get()->getResultObject();
    }

    function updatePass($id, $data)
    {
        return $this->db->table($this->table)->where('NOID', $id)->update($data);
    }


    public function getdataakun()
    {
        return $this->db->table($this->table)
            ->select('akun.*, jabatan.NAMA_JABATAN')
            ->join('jabatan', 'jabatan.ID_JABATAN = akun.ID_JABATAN', 'left')
            ->get()
            ->getResult();
    }

    public function getLatLongByAkun($idAkun)
    {
        return $this->select('unit.LATITUDE, unit.LONGTITUDE')
            ->join('unit', 'unit.idunit = akun.ID_UNIT')
            ->where('akun.ID_AKUN', $idAkun)
            ->first();
    }



    public function getByIdWithJabatan($id)
    {
        // Join ke tabel jabatan
        $result = $this->select('akun.*, jabatan.NAMA_JABATAN, jabatan.ROLES_JABATAN')
            ->join('jabatan', 'jabatan.ID_JABATAN = akun.ID_JABATAN', 'left')
            ->where('akun.ID_AKUN', $id)
            ->first();

        if (!$result) {
            return null; // kalau data akun tidak ditemukan
        }

        // Default
        $result->apakah_service = 'service_tidak';

        // Pastikan roles_jabatan ada dan dalam format JSON
        if (!empty($result->ROLES_JABATAN)) {
            $roles = json_decode($result->ROLES_JABATAN, true);

            // Jika 72 ada di dalam array roles_jabatan
            if (is_array($roles) && in_array("72", $roles)) {
                $result->apakah_service = 'service_oke';
            }
        }

        return $result;
    }
}
