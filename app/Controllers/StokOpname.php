<?php

namespace App\Controllers;

use App\Models\ModelStokAwal;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKartuStok;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\ModelStokOpname;
use App\Models\ModelStokOpnameDraft;
use App\Controllers\StokAwal;
use App\Models\ModelBarang;
use App\Models\ModelStokBarang;
use App\Models\ModelHppBarang;
use App\Models\ModelUnit;



class StokOpname extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $StokOpnameModel;
    protected $StokOpnameDraftModel;
    protected $StokAwalModel;
    protected $BarangModel;
    protected $StokBarangModel;
    protected $HppBarangModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->StokOpnameModel = new ModelStokOpname();
        $this->StokOpnameDraftModel = new ModelStokOpnameDraft();
        $this->StokAwalModel = new ModelStokAwal();
        $this->BarangModel = new ModelBarang();
        $this->StokBarangModel = new ModelStokBarang();
        $this->HppBarangModel = new ModelHppBarang();
        $this->UnitModel = new ModelUnit();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'stok' => $this->KartuStokModel->getKartuStok(),
            'stokopname' => $this->StokOpnameDraftModel->getStokOpnameDraft(),
            'stokopnamedraft' => $this->StokOpnameDraftModel->getStokOpname(),
            'unit' => $this->UnitModel->getUnit(),
            'body'  => 'stok/stok_opname'
        );
        return view('template', $data);
    }

    // public function loadTable()
    // {
    //     $table = $this->request->getGet('table');

    //     if ($table === 'tabledaraft') {
    //         $data['stok'] = $this->KartuStokModel->getKartuStok();
    //         return view('stok/table/stok_opnamedraft_table', $data);
    //     } elseif ($table === 'tablefix') {
    //         $data['stok'] = $this->KartuStokModel->getKartuStok();
    //         $data['stokopname'] = $this->StokOpnameDraftModel->getStokOpnameDraft();
    //         return view('stok/table/stok_opname_table', $data);
    //     }

    //     return 'Invalid table name';
    // }

    public function simpan()
    {
        $data = $this->request->getPost('data');

        if ($data && is_array($data)) {

            foreach ($data as $row) {

                if (!isset($row['checked']) || $row['checked'] != '1') {
                    continue;
                }

                $datastokawal = $this->StokAwalModel->getByIdBarang($row['barang_idbarang']);
                $databarang = $this->BarangModel->getById($row['barang_idbarang']);
                $namaproduk = $databarang->nama_barang ?? 'Tidak diketahui';

                if (!$datastokawal || $datastokawal->satuan_terkecil == null) {
                    session()->setFlashdata(
                        'gagal',
                        'Barang "' . $namaproduk . '" belum memiliki data satuan di stok awal.'
                    );
                    return redirect()->back();
                }

                $satuanterkecil = $datastokawal->satuan_terkecil;

                $datahppbarang = $this->HppBarangModel->getById($row['barang_idbarang']);
                $hppbarang = $datahppbarang->hpp ?? 0;

                $exists = $this->StokOpnameDraftModel
                            ->existsForToday(
                                $row['barang_idbarang'],
                                $row['unit_idunit']
                            );

                if ($exists) {
                    continue; // lewati jika sudah ada
                }

                $insertData = [
                    'tanggal'           => date('Y-m-d'),
                    'hpp'               => $hppbarang,
                    'jumlah_real'       => $row['jumlah_real'],
                    'jumlah_komp'       => $row['jumlah_komp'],
                    'jumlah_selisih'    => $row['jumlah_selisih'],
                    'satuan_terkecil'   => $satuanterkecil,
                    'barang_idbarang'   => $row['barang_idbarang'],
                    'unit_idunit'       => $row['unit_idunit']
                ];

                $this->StokOpnameDraftModel->insert_StokOpnameDraft($insertData);
            }

            return redirect()->to(base_url('stok_opname'))
                            ->with('sukses', 'Data stok opname berhasil disimpan.');
        }

        return redirect()->back()
                        ->with('gagal', 'Tidak ada data yang dipilih.');
    }



    public function simpanFix()
    {
        $data = $this->request->getPost('data');

        if ($data && is_array($data)) {
            foreach ($data as $row) {

                if (!isset($row['checked']) || $row['checked'] != '1') {
                    continue;
                }

                $datastokawal = $this->StokAwalModel->getByIdBarang($row['barang_idbarang']);
                $databarang = $this->BarangModel->getById($row['barang_idbarang']);
                $namaproduk = $databarang->nama_barang ?? 'Tidak diketahui';

                if (!$datastokawal || $datastokawal->satuan_terkecil == null) {
                    session()->setFlashdata('gagal', 'Barang "' . $namaproduk . '" belum memiliki data satuan di stok awal.');
                    return redirect()->back();
                }

                $satuanterkecil = $datastokawal->satuan_terkecil;
                $datahppbarang = $this->HppBarangModel->getById($row['barang_idbarang']);
                $hppbarang = $datahppbarang->hpp ?? 0;

                $exists = $this->StokOpnameModel->existsForToday($row['barang_idbarang'], $row['unit_idunit']);

                if ($exists) {
                    session()->setFlashdata('gagal', 'Barang "' . $namaproduk . '" dari unit yang sama sudah ada di draft stok opname hari ini.');
                    return redirect()->back();
                }

                $data = array(
                    'tanggal' => date('Y-m-d'),
                    'hpp' => $hppbarang,
                    'jumlah_real' => $row['jumlah_real'],
                    'jumlah_komp' => $row['jumlah_komp'],
                    'jumlah_selisih' => $row['jumlah_selisih'],
                    'satuan_terkecil' => $satuanterkecil,
                    'barang_idbarang' => $row['barang_idbarang'],
                    'unit_idunit' => $row['unit_idunit']
                );
                $result = $this->StokOpnameModel->insert_StokOpnameFix($data);
                if ($result) {
                    return redirect()->to(base_url('stok_opname'))->with('sukses', 'Data stok opname berhasil disimpan.');
                }
            }
        }
    }
}