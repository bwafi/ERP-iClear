<?php

namespace App\Controllers;

use App\Models\ModelPhone;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Config\Database;
use App\Models\ModelBarang;
use App\Models\ModelAuth;
use App\Models\ModelUnit;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\ModelNamaHandphone;
use App\Models\ModelSubKategori;

class Phone extends BaseController

{

    protected $PhoneModel;
    protected $BarangModel;
    protected $AuthModel;
    protected $NamaHandphoneModel;
    protected $UnitModel;
    protected $SubKategoriModel;

    public function __construct()
    {
        $this->PhoneModel = new ModelPhone();
        $this->BarangModel = new ModelBarang();
        $this->AuthModel = new ModelAuth();
        $this->NamaHandphoneModel = new ModelNamaHandphone();
        $this->UnitModel = new ModelUnit();
        $this->SubKategoriModel = new ModelSubKategori();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'phone' => $this->PhoneModel->getPhoneActive(),
            'nama_handphone' => $this->NamaHandphoneModel->getNamaHandphone(),
            'body'  => 'datamaster/phone',
            'sub_kategori' => $this->SubKategoriModel->getParentOne(),
            'unit' => $this->UnitModel->getUnit()
        );

        return view('template', $data);
    }

    public function insert_phone()
    {
        //ini id nama hanpdhone
        $id_nama_handphone =  $this->request->getPost('nama_barang');

        $data_nama_handphone = $this->NamaHandphoneModel->getNamaHandphoneById($id_nama_handphone);
        $nama_barang = $data_nama_handphone->nama;


        $imei       = $this->request->getPost('imei');
        $jenis_hp   = $this->request->getPost('jenis_hp');
        $harga      = str_replace(',', '', $this->request->getPost('harga'));
        $harga_beli      = str_replace(',', '', $this->request->getPost('harga_beli'));
        $internal   = $this->request->getPost('internal');
        $warna      = $this->request->getPost('warna');
        $status_ppn = $this->request->getPost('status_ppn');
        $datauser = $this->AuthModel->getById(session('ID_AKUN'));
        $namaakun = $datauser->NAMA_AKUN;


        $existingImei = $this->PhoneModel->where('imei', $imei)->first();
        if ($existingImei) {
            session()->setFlashdata('gagal', 'IMEI sudah terdaftar!');
            return redirect()->back()->withInput();
        }

        $lastBarang = $this->BarangModel->getLastBarangByKategori(1, 'HP');
        $newNumber = (int) ($lastBarang->max_kode ?? 0) + 1;

        $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);
        $kode_barang = 'HP' . $formattedNumber;
        $data = [
            'kode_barang' => $kode_barang,
            'nama_barang' => $nama_barang,
            'imei'       => $imei,
            'jenis_hp'   => $jenis_hp,
            'harga'      => $harga,
            'id_sub_kategori' => $this->request->getPost('sub_kategori'),
            'harga_beli'      => $harga_beli,
            'internal'   => $internal,
            'warna'      => $warna,
            'status_barang' => $this->request->getPost('kondisi'),
            'status'     => '0',
            'input'      => $namaakun,
            'idkategori' => '1',
            'stok_minimum' => 0,
            'status_ppn' => $status_ppn,
            'deleted'    => '0',
            'nama_barang_id' => $id_nama_handphone
        ];

        $result = $this->PhoneModel->insert($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Berhasil Menambahkan Data');
            return redirect()->to(base_url('/phone'));
        }
    }

    public function update_phone()
    {
        $id_phone = $this->request->getPost('id');

        $id_nama_handphone =  $this->request->getPost('nama_barang');

        $data_nama_handphone = $this->NamaHandphoneModel->getNamaHandphoneById($id_nama_handphone);
        $nama_barang = $data_nama_handphone->nama;

        $imei = $this->request->getPost('imei');
        $jenis_hp = $this->request->getPost('jenis_hp');
        $harga      = str_replace(',', '', $this->request->getPost('harga'));
        $harga_beli      = str_replace(',', '', $this->request->getPost('harga_beli'));
        $status_ppn = $this->request->getPost('status_ppn');
        $internal = $this->request->getPost('internal');
        $warna = $this->request->getPost('warna');
        $datauser = $this->AuthModel->getById(session('ID_AKUN'));
        $namaakun = $datauser->NAMA_AKUN;


        $existingImei = $this->PhoneModel
            ->where('imei', $imei)
            ->where('idbarang !=', $id_phone)
            ->first();

        if ($existingImei) {
            session()->setFlashdata('gagal', 'IMEI sudah digunakan oleh data lain!');
            return redirect()->back()->withInput();
        }


        $data = [
            'nama_barang' => $nama_barang,
            'imei'       => $imei,
            'jenis_hp'   => $jenis_hp,
            'harga'      => $harga,
            'harga_beli'      => $harga_beli,
            'internal'   => $internal,
            'warna'      => $warna,
            'id_sub_kategori' => $this->request->getPost('sub_kategori'),
            'status_barang' => $this->request->getPost('kondisi'),
            'idkategori' => '1',
            'status_ppn' => $status_ppn,
            'deleted'    => '0',
            'nama_barang_id' => $id_nama_handphone
        ];
        if ($this->PhoneModel->update($id_phone, $data)) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
        } else {
            session()->setFlashdata('gagal', 'Data Gagal Di Simpan');
        }
        return redirect()->to(base_url('/phone'));
    }

    public function delete_phone()
    {

        $id_phone = $this->request->getPost('id');
        $data = array(
            'deleted' => '1'
        );
        $result = $this->PhoneModel->update($id_phone, $data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Hapus');
            return redirect()->to(base_url('/phone'));
        }
    }

    public function export_phone()
    {


        $phone = $this->PhoneModel->getPhoneActive();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = ['Kode Barang', 'Nama Barang', 'IMEI', 'Jenis Handphone', 'Harga', 'Harga Beli', 'Internal', 'Warna', 'Kondisi', 'Status PPN'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Styling header
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data
        $row = 2;
        foreach ($phone as $handphone) {
            $ppn = $handphone->status_ppn == 1 ? 'PPN' : ($handphone->status_ppn == 0 ? 'NON PPN' : 'PPN Belum Di Set');

            $sheet->setCellValue('A' . $row, $handphone->kode_barang);
            $sheet->setCellValue('B' . $row, $handphone->nama_barang);
            $sheet->setCellValue('C' . $row, $handphone->imei);
            $sheet->setCellValue('D' . $row, $handphone->jenis_hp);
            $sheet->setCellValue('E' . $row, $handphone->harga);
            $sheet->setCellValue('F' . $row, $handphone->harga_beli);
            $sheet->setCellValue('G' . $row, $handphone->internal);
            $sheet->setCellValue('H' . $row, $handphone->warna);
            $sheet->setCellValue('I' . $row, $handphone->status_barang);
            $sheet->setCellValue('J' . $row, $ppn);
            $row++;
        }

        // Auto width semua kolom
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border semua sel
        $sheet->getStyle('A1:J' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // Output Excel
        $filename = 'data_Handphone_' . date('Ymd') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function import_phone()
    {
        $file = $this->request->getFile('file');

        // Load spreadsheet
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Koneksi DB dan model
        $db = Database::connect();

        // Skip header (baris pertama)
        //for push ulang
        for ($i = 1; $i < count($rows); $i++) {
            $imei = addslashes($rows[$i][0]);
            $nama_handphone = addslashes($rows[$i][1]);
            $nama_barang_id = addslashes($rows[$i][2]);
            $harga = addslashes($rows[$i][3]);
            $harga_beli = addslashes($rows[$i][4]);
            $id_sub_kategori =  addslashes($rows[$i][5]);
            $jenis_handphone = addslashes($rows[$i][6]);
            $internal = addslashes($rows[$i][7]);
            $warna = addslashes($rows[$i][8]);

            // Ambil nilai status_barang dari Excel (Baru/Bekas)
            $status_barang_text = strtolower(trim($rows[$i][9]));
            $status_barang = ($status_barang_text === 'bekas') ? 1 : 0; // default ke 0 jika bukan 'bekas'

            // Ambil dan konversi status_ppn
            $status_ppn_text = strtoupper(trim($rows[$i][10]));
            $status_ppn = ($status_ppn_text === 'PPN') ? 1 : 0;

            $input = addslashes($rows[$i][11]);

            // Generate kode_barang otomatis
            $lastBarang = $this->BarangModel->getLastBarangByKategori(1, 'HP');
            $newNumber = (int) ($lastBarang->max_kode ?? 0) + 1;
            $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);
            $kode_barang = 'HP' . $formattedNumber;

            // Insert ke DB
            $sql = "INSERT INTO barang 
(kode_barang, imei, nama_barang, nama_barang_id, harga, harga_beli, id_sub_kategori, idkategori, jenis_hp, internal, warna, status, status_ppn, stok_minimum, deleted, input, status_barang) 
VALUES 
('$kode_barang', '$imei', '$nama_handphone', '$nama_barang_id', '$harga', '$harga_beli', '$id_sub_kategori', '1', '$jenis_handphone', '$internal', '$warna', '1', '$status_ppn', '0', '0', '$input', '$status_barang')";

            $db->query($sql);
        }

        session()->setFlashdata('sukses', 'Data Berhasil Diimpor');
        return redirect()->to(base_url('/phone'));
    }

    public function menuimport_phone()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'phone' => $this->PhoneModel->getPhoneActive(),
            'body'  => 'datamaster/import_phone'
        );
        return view('template', $data);
    }


    public function insertnamahandphone()
    {
        $nama = $this->request->getPost('nama_handphone');
        $size = $this->request->getPost('size_handphone');
        $type = $this->request->getPost('type_handphone');

        // Validasi input wajib
        if (!$nama) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Nama handphone wajib diisi'
            ]);
        }

        if (!$size) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Size handphone wajib diisi'
            ]);
        }

        if (!$type) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Type handphone wajib diisi'
            ]);
        }

        $model = new \App\Models\ModelNamaHandphone();

        // Cek apakah kombinasi nama + size + type sudah ada
        $cek = $model->where([
            'nama' => $nama,
            'size' => $size,
            'type' => $type
        ])->first();

        if ($cek) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data handphone dengan kombinasi nama, size, dan type tersebut sudah ada'
            ]);
        }

        // Simpan data baru
        $model->insert([
            'nama' => $nama,
            'size' => $size,
            'type' => $type
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Data handphone berhasil ditambahkan',
            'data' => [
                'nama' => $nama,
                'size' => $size,
                'type' => $type
            ]
        ]);
    }
}
