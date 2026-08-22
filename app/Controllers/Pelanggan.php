<?php

namespace App\Controllers;

use App\Models\ModelPhone;
use App\Models\ModelPelanggan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelPenjualan;
use App\Models\ModelRegion;

class Pelanggan extends BaseController

{

    protected $PhoneModel;
    protected $PelangganModel;
    protected $AuthModel;
    protected $PenjualanModel;
    protected $RegionModel;

    public function __construct()
    {
        $this->PhoneModel = new ModelPhone();
        $this->PelangganModel = new ModelPelanggan();
        $this->AuthModel = new ModelAuth();
        $this->PenjualanModel = new ModelPenjualan();
        $this->RegionModel = new ModelRegion();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'phone' => $this->PhoneModel->getPhone(),
            'body'  => 'datamaster/pelanggan',
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'provinsi'  => $this->RegionModel->getProvinces()
        );
        return view('template', $data);
    }

    public function getKabupaten($provinsi)
    {
        $provinsi = urldecode($provinsi);

        $data = $this->RegionModel->getRegenciesByProvinceName($provinsi);

        return $this->response->setJSON($data);
    }


    public function getKecamatan($kabupaten)
    {
        $kabupaten = urldecode($kabupaten);

        $data = $this->RegionModel->getDistrictsByRegencyName($kabupaten);

        return $this->response->setJSON($data);
    }




    public function insert_pelanggan()
    {
        $nik       = $this->request->getPost('nik');
        $nama      = $this->request->getPost('nama');
        $alamat    = $this->request->getPost('alamat');
        $provinsi  = $this->request->getPost('provinsi');
        $kabupaten = $this->request->getPost('kabupaten');
        $kecamatan = $this->request->getPost('kecamatan');
        $no_hp     = $this->request->getPost('no_hp');
        $kategori  = $this->request->getPost('kategori');
        $mengetahui_dari     = $this->request->getPost('mengetahui_dari');

        $data = array(
            'nik'       => $nik,
            'nama'      => $nama,
            'alamat'    => $alamat,
            'provinsi'  => $provinsi,
            'kabupaten' => $kabupaten,
            'kecamatan' => $kecamatan,
            'no_hp'     => $no_hp,
            'kategori'  => $kategori,
            'mengetahui_dari'  => $mengetahui_dari,
            'deleted'   => '0'
        );

        $result = $this->PelangganModel->insert_Pelanggan($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
            return redirect()->to(base_url('/pelanggan'));
        }
    }


    public function update_pelanggan()
    {
        $id_pelanggan = $this->request->getPost('id_pelanggan');
        $nik          = $this->request->getPost('nik');
        $nama         = $this->request->getPost('nama');
        $alamat       = $this->request->getPost('alamat');
        $provinsi     = $this->request->getPost('provinsi');
        $kabupaten    = $this->request->getPost('kabupaten');
        $kecamatan    = $this->request->getPost('kecamatan');
        $no_hp        = $this->request->getPost('no_hp');
        $kategori     = $this->request->getPost('kategori');
        $mengetahui_dari     = $this->request->getPost('mengetahui_dari');

        $data = array(
            'nik'       => $nik,
            'nama'      => $nama,
            'alamat'    => $alamat,
            'provinsi'  => $provinsi,
            'kabupaten' => $kabupaten,
            'kecamatan' => $kecamatan,
            'no_hp'     => $no_hp,
            'kategori'  => $kategori,
            'mengetahui_dari'  => $mengetahui_dari,
            'deleted'   => '0'
        );

        $result = $this->PelangganModel->update($id_pelanggan, $data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Update');
            return redirect()->to(base_url('/pelanggan'));
        }
    }


    public function delete_pelanggan()
    {
        $id_pelanggan = $this->request->getPost('id_pelanggan');
        $data = array(
            'deleted' => '1'
        );
        $result = $this->PelangganModel->update($id_pelanggan, $data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Hapus');
            return redirect()->to(base_url('/pelanggan'));
        }
    }

    public function export_pelanggan()
    {
        $pelanggan = $this->PelangganModel->getPelanggan();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = ['NIK', 'Nama', 'Alamat', 'Nomor Handphone'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Styling Header
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data
        $row = 2;
        foreach ($pelanggan as $customer) {
            $sheet->setCellValueExplicit('A' . $row, $customer->nik, DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $customer->nama);
            $sheet->setCellValue('C' . $row, $customer->alamat);
            $sheet->setCellValue('D' . $row, $customer->no_hp); // agar tidak diubah jadi angka ilmiah
            $row++;
        }

        // Auto width untuk semua kolom
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle('A1:D' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // Format filename agar aman (YYYYMMDD_HHMMSS)
        $filename = 'data_pelanggan_' . date('Ymd_His') . '.xlsx';

        // Set header
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function import_pelanggan()
    {

        $file = $this->request->getFile('file');

        // Load spreadsheet
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Koneksi DB
        $db = Database::connect();

        // Skip header (baris pertama)
        for ($i = 1; $i < count($rows); $i++) {
            $nik = addslashes($rows[$i][0]);
            $nama = addslashes($rows[$i][1]);
            $alamat = addslashes($rows[$i][2]);
            $no_hp = addslashes($rows[$i][3]);


            $sql = "INSERT INTO pelanggan (nik, nama, alamat, no_hp, deleted) 
                    VALUES ('$nik', '$nama', '$alamat', '$no_hp', '0')";

            $db->query($sql);
        }

        session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
        return redirect()->to(base_url('/pelanggan'));
    }


    public function simpanPelanggan()
    {
        $data = $this->request->getPost();

        $insertData = [
            'nik'             => $data['nik'], // sesuai permintaan
            'nama'            => $data['nama'],
            'no_hp'           => $data['no_hp'],
        ];

        $insertId = $this->PelangganModel->insert_Pelanggan($insertData);

        if ($insertId) {
            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'id_pelanggan' => $insertId,
                    'nama'         => $data['nama'],
                    'no_hp'        => $data['no_hp'],
                    'deleted'      => 0,
                ]
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Gagal menyimpan data pelanggan'
        ]);
    }

    public function riwayat_transaksi_pelanggan($id)
    {
        $data = array(
            'body' => 'riwayat/transaksi_pelanggan',
            'transaksi' => $this->PenjualanModel->getByIdPelanggan($id)
        );
        return view('template', $data);
    }
}
