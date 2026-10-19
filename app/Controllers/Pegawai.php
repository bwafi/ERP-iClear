<?php

namespace App\Controllers;

use App\Models\ModelKategori;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelUnit;

class Pegawai extends BaseController

{
    protected $AuthModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
    }

    public function index()
    {
        $data =  array(
            'body'      => 'pegawai/list_pegawai_new',
            'unit'      =>  $this->UnitModel->getUnit(),
            'jabatan'   =>  $this->AuthModel->getJabatan(),
            'roles'     =>  $this->AuthModel->getRolesAktif(),
            'akun'      =>  $this->AuthModel->getAkunPegawai()
        );
        return view('template', $data);
    }

    function search()
    {
        $idunit = $this->request->getPost('unit_id');
        $data = $this->AuthModel->getAkun($idunit);
        return json_encode($data);
    }

    function jabatan()
    {
        $data_jabatan = [];
        $jabatan = $this->AuthModel->getJabatan();
        foreach ($jabatan as $value) {
            $roles = [];
            $q_roles = $this->AuthModel->getRolesJabatan(json_decode($value->ROLES_JABATAN));
            foreach ($q_roles as $rv) {
                $roles[] = $rv->nama_menu;
            }
            $ar = array(
                'ID_JABATAN'    => $value->ID_JABATAN,
                'NAMA_JABATAN'  => $value->NAMA_JABATAN,
                'ROLES_JABATAN' => $value->ROLES_JABATAN,
                'roles'         => $roles
            );
            $data_jabatan[] = $ar;
        }
        $data =  array(
            'body'      => 'pegawai/list_jabatan',
            'jabatan'   =>  $data_jabatan,
            'roles'     =>  $this->AuthModel->getRolesAktif(),
        );
        return view('template', $data);
    }

    public function insert_jabatan()
    {
        $nama_jabatan = $this->request->getPost('nama_jabatan');
        $roles_jabatan = $this->request->getPost('roles');

        $data = array(
            'NAMA_JABATAN' => $nama_jabatan,
            'ROLES_JABATAN' => json_encode($roles_jabatan)
        );

        $result = db_connect()->table('jabatan')->insert($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
        } else {
            session()->setFlashdata('gagal', 'Data Gagal Di Simpan');
        }
        return redirect()->to(base_url('/pegawai/jabatan'));
    }

    public function update_jabatan()
    {
        $id = $this->request->getPost('idjabatan');

        $nama_jabatan = $this->request->getPost('nama_jabatan');
        $roles_jabatan = $this->request->getPost('roles');

        $data = array(
            'NAMA_JABATAN' => $nama_jabatan,
            'ROLES_JABATAN' => json_encode($roles_jabatan)
        );

        $result = db_connect()->table('jabatan')->where('ID_JABATAN', $id)->update($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
        } else {
            session()->setFlashdata('gagal', 'Data Gagal Di Simpan');
        }
        return redirect()->to(base_url('/pegawai/jabatan'));
    }

    public function insert()
    {
        $foto_ktp = $this->request->getFile('foto_ktp');
        $foto_kk = $this->request->getFile('foto_kk');

        $nama_foto_ktp = null;
        $nama_foto_kk  = null;

        // Proses upload foto KTP
        if ($foto_ktp && $foto_ktp->isValid() && !$foto_ktp->hasMoved()) {
            $nama_foto_ktp = 'ktp_' . date('Ymd') . '_' . rand(1000, 9999) . '.' . $foto_ktp->getExtension();
            $foto_ktp->move(ROOTPATH . 'public/foto_ktp/', $nama_foto_ktp);
        }

        // Proses upload foto KK
        if ($foto_kk && $foto_kk->isValid() && !$foto_kk->hasMoved()) {
            $nama_foto_kk = 'kk_' . date('Ymd') . '_' . rand(1000, 9999) . '.' . $foto_kk->getExtension();
            $foto_kk->move(ROOTPATH . 'public/foto_kk/', $nama_foto_kk);
        }

        $data = array(
            'NOID'          => $this->request->getPost('noid'),
            'KTP'           => $this->request->getPost('no_ktp'),
            'EMAIL'         => $this->request->getPost('email'),
            'NAMA_AKUN'     => $this->request->getPost('nama'),
            'ALAMAT'        => $this->request->getPost('alamat'),
            'JENIS_KELAMIN' => $this->request->getPost('jenis_kelamin'),
            'HP'            => $this->request->getPost('hp'),
            'ROLES'         => json_encode($this->request->getPost('roles')),
            'ID_UNIT'       => $this->request->getPost('unit'),
            'ID_JABATAN'    => $this->request->getPost('jabatan'),
            'PASSWORD'      => password_hash($this->request->getPost('noid'), PASSWORD_DEFAULT, array("cost" => 10)),
            'FOTO_KTP'      => $nama_foto_ktp,
            'FOTO_KK'       => $nama_foto_kk,
            'JENIS_PEGAWAI' => $this->request->getPost('jenis_pegawai'),
            // Form lama (ter-cache) bisa tak punya field ini; absen = digaji.
            'TERIMA_GAJI'   => $this->request->getPost('terima_gaji') === '0' ? 0 : 1
        );


        $result = $this->AuthModel->insert_akun($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
        } else {
            session()->setFlashdata('gagal', 'Data Gagal Di Simpan');
        }
        return redirect()->to(base_url('/pegawai'));
    }

    public function update()
    {

        $ID_AKUN = $this->request->getPost('ID_AKUN');
        $jenis_pegawai = $this->request->getPost('jenis_pegawai');

        $foto_ktp = $this->request->getFile('foto_ktp');
        $foto_kk = $this->request->getFile('foto_kk');


        $nama_foto_ktp = $this->request->getPost('foto_ktp_lama');
        $nama_foto_kk  = $this->request->getPost('foto_kk_lama');


        if ($foto_ktp && $foto_ktp->isValid() && !$foto_ktp->hasMoved()) {
            $nama_foto_ktp = 'ktp_' . date('Ymd') . '_' . rand(1000, 9999) . '.' . $foto_ktp->getExtension();
            $foto_ktp->move(ROOTPATH . 'public/foto_ktp/', $nama_foto_ktp);
        }


        if ($foto_kk && $foto_kk->isValid() && !$foto_kk->hasMoved()) {
            $nama_foto_kk = 'kk_' . date('Ymd') . '_' . rand(1000, 9999) . '.' . $foto_kk->getExtension();
            $foto_kk->move(ROOTPATH . 'public/foto_kk/', $nama_foto_kk);
        }

        $data = array(
            'KTP'           => $this->request->getPost('no_ktp'),
            'EMAIL'         => $this->request->getPost('email'),
            'NAMA_AKUN'     => $this->request->getPost('nama'),
            'ALAMAT'        => $this->request->getPost('alamat'),
            'JENIS_KELAMIN' => $this->request->getPost('jenis_kelamin'),
            'HP'            => $this->request->getPost('hp'),
            'ROLES'         => json_encode($this->request->getPost('roles')),
            'ID_UNIT'       => $this->request->getPost('unit'),
            'ID_JABATAN'    => $this->request->getPost('jabatan'),
            'FOTO_KTP'      => $nama_foto_ktp,
            'FOTO_KK'       => $nama_foto_kk,
            'JENIS_PEGAWAI' => $jenis_pegawai,
        );
        // Field absen (mis. form ter-cache dari versi lama) tidak boleh
        // diam-diam mematikan gaji seseorang: hanya "Tidak" (0) yang mengubah.
        if ($this->request->getPost('terima_gaji') !== null) {
            $data['TERIMA_GAJI'] = $this->request->getPost('terima_gaji') ? 1 : 0;
        }
        $this->AuthModel->update($ID_AKUN, $data);
        $data2 = array(
            'STATUS_PEGAWAI' => 0
        );
        if ($jenis_pegawai == 100) {
            $THIS->AuthModel->update($ID_AKUN, $data2);
        }


        session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');

        return redirect()->to(base_url('/pegawai'));
    }


    public function delete()
    {
        $id = $this->request->getPost('ID_AKUN');
        $data = array(
            'STATUS_PEGAWAI' => '0',
            'JENIS_PEGAWAI' => 100
        );
        $result = $this->AuthModel->update($id, $data);
        if ($result) {
            session()->setFlashdata('sukses', 'Berhasil Hapus Data');
            return redirect()->to(base_url('pegawai'));
        }
    }

    public function reset_password()
    {
        $id = $this->request->getPost('ID_AKUN');
        $hashedPassword = password_hash('urbankeren', PASSWORD_BCRYPT);
        $data = array(
            'PASSWORD' => $hashedPassword
        );
        $result = $this->AuthModel->update($id, $data);
        if ($result) {
            session()->setFlashdata('sukses', 'Berhasil Update Data');
            return redirect()->to(base_url('pegawai'));
        }
    }


    //     $result = db_connect()->table('akun')->where('ID_AKUN', $id)->update($data);

    //     if ($result) {
    //         return $this->response->setJSON(['success' => true, 'message' => 'Data berhasil dihapus']);
    //     } else {
    //         return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus data']);
    //     }
    // }
}
