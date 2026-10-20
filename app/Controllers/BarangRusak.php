<?php

namespace App\Controllers;

use App\Models\ModelBarangRusak;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelBank;

use App\Models\ModelBarang;
use App\Models\ModelPembelian;
use App\Models\ModelDetailPembelian;
use App\Models\ModelUnit;
use App\Models\ModelKategori;
use App\Models\ModelSubKategori;

class BarangRusak extends BaseController

{

    protected $AuthModel;
    protected $BankModel;

    protected $BarangRusakModel;
    protected $BarangModel;
    protected $PembelianModel;
    protected $DetailPembelianModel;
    protected $UnitModel;

    protected $KategoriModel;
    protected $SubKategoriModel;


    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->BankModel = new ModelBank();
        $this->BarangRusakModel = new ModelBarangRusak();
        $this->BarangModel = new ModelBarang();
        $this->PembelianModel = new ModelPembelian();
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->UnitModel = new ModelUnit();
        $this->KategoriModel = new ModelKategori();
        $this->SubKategoriModel = new ModelSubKategori();
    }

    public function index()
    {
        //ini perubahan
        $data =  array(
            'barang_rusak' => $this->BarangRusakModel->getBarangRusak(),
            'unit' => $this->UnitModel->getUnit(),
            'body'  => 'stok/barang_rusak',
            'kategori' => $this->KategoriModel->getKategori(),
            'sub_kategori' => $this->SubKategoriModel->getSubKategori()
        );
        return view('template', $data);
    }

    public function input()
    {

        $data = array(
            'body' => 'stok/input_barang_rusak',
            'detail_pembelian' => $this->DetailPembelianModel->getDetailAll(),
            'unit' => $this->UnitModel->getUnit()
        );


        return view('template', $data);
    }


    public function insert_barang_rusak()
    {



        $idbarang       = $this->request->getPost('idbarang');
        $idpembelian    = $this->request->getPost('idpembelian');
        $no_batch       = $this->request->getPost('no_batch');
        $kode_barang    = $this->request->getPost('kode_barang');
        $nama_barang    = $this->request->getPost('nama_barang');
        $imei           = $this->request->getPost('imei');
        $nama_suplier   = $this->request->getPost('nama_suplier');
        $tanggal        = $this->request->getPost('tanggal');
        $jumlah_total   = $this->request->getPost('jumlah_total');
        $jumlah_rusak   = $this->request->getPost('jumlah_rusak');
        $tanggal_rusak  = $this->request->getPost('tanggal_rusak');
        $keterangan     = $this->request->getPost('keterangan');


        $totalBarang = count($idbarang);


        for ($i = 0; $i < $totalBarang; $i++) {
            $data = [
                'idpembelian'    => $idpembelian[$i],
                'no_nota_sup'       => $no_batch[$i],
                'barang_idbarang'       => $idbarang[$i],
                'jumlah'   => $jumlah_rusak[$i],
                'tanggal_rusak'  => $tanggal_rusak[$i],
                'unit_idunit' => $this->request->getPost('unit_idunit'),
                'input_by' => session('ID_AKUN'),
                'keterangan'     => $keterangan[$i],
            ];


            $this->BarangRusakModel->insert($data);
        }
        session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
        return redirect()->to(base_url('/barang_rusak'));
    }
}
