<?php

namespace App\Controllers;

use App\Models\ModelTutupKasir;
use CodeIgniter\Controller;

class Unit extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();
        $data = $db->table('unit')
                        ->orderBy('idunit','ASC')
                        ->get()
                        ->getResult();
        
        return view('template', [
                'data'             => $data,
                'body'             => 'datamaster/unit'
            ]);
    }

    public function insert_unit()
    {
        $db = \Config\Database::connect();
        $db->table('unit')->insert([
            'NAMA_UNIT'      => $this->request->getPost('nama_unit'),
            'NOTELP'         => $this->request->getPost('notelp'),
            'JALAN_UNIT'     => $this->request->getPost('alamat_unit'),
            'KELURAHAN_UNIT' => $this->request->getPost('kelurahan_unit'),
            'jenis'          => $this->request->getPost('kepemilikan'),
            'tanggungan'     => $this->request->getPost('tanggungan'),
        ]);

        session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
        return redirect()->to(base_url('/unit'));
    }

    public function update_unit()
    {
        $db = \Config\Database::connect();
        $id = $this->request->getPost('id_unit');

        $db->table('unit')
                ->where('idunit',$id)
                ->update([
                    'NAMA_UNIT'      => $this->request->getPost('nama_unit'),
                    'NOTELP'         => $this->request->getPost('notelp'),
                    'JALAN_UNIT'     => $this->request->getPost('alamat_unit'),
                    'KELURAHAN_UNIT' => $this->request->getPost('kelurahan_unit'),
                    'jenis'          => $this->request->getPost('kepemilikan'),
                    'tanggungan'     => $this->request->getPost('tanggungan'),
                ]);

        session()->setFlashdata('sukses', 'Data Berhasil Di Ubah');
        return redirect()->to(base_url('/unit'));
    }

    public function delete_unit()
    {
        $db = \Config\Database::connect();
        
        $db->table('unit')
                ->where('idunit',$this->request->getPost('id_unit'))
                ->delete();

        session()->setFlashdata('sukses', 'Data Berhasil Di Hapus');
        return redirect()->to(base_url('/unit'));
    }
}