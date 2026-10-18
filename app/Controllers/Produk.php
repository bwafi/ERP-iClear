<?php

namespace App\Controllers;

use App\Models\ModelBarang;
use App\Models\ModelKategori;
use App\Models\ModelSubKategori;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Config\Database;
use App\Models\ModelAuth;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\ModelUnit;

class Produk extends BaseController

{

    protected $BarangModel;
    protected $KategoriModel;
    protected $SubKategoriModel;
    protected $AuthModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->BarangModel = new ModelBarang();
        $this->KategoriModel = new ModelKategori();
        $this->SubKategoriModel = new ModelSubKategori();
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'produk' => $this->BarangModel->getBarang(),
            'kategori' => $this->KategoriModel->getKategoriTanpaId7(),
            'sub_kategori' => $this->SubKategoriModel->getSubKategori(),
            'body'  => 'datamaster/produk',
            'unit' => $this->UnitModel->getUnit()
        );
        return view('template', $data);
    }


    public function insert_produk()
    {

        $nama_barang = $this->request->getPost('nama_barang');
        $harga = str_replace([',', '.'], '', $this->request->getPost('harga'));
        $harga_beli = str_replace([',', '.'], '', $this->request->getPost('harga_beli'));

        $dataakun = $this->AuthModel->getById(session('ID_AKUN'));
        $input_by = $dataakun->NAMA_AKUN;


        $stok_minimum = $this->request->getPost('stok_minimum');
        $kategori = $this->request->getPost('kategori');
        $sub_kategori = $this->request->getPost('sub_kategori');
        $data_kategori = $this->KategoriModel->getByName($kategori);

        $idkategori = $data_kategori->id;
        $kode_kategori = $data_kategori->idkategori;

        $lastBarang = $this->BarangModel->getLastBarangByKategori($idkategori, $kode_kategori);
        $newNumber = (int) ($lastBarang->max_kode ?? 0) + 1;

        $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);

        $kode_barang = $kode_kategori . $formattedNumber;
        $status_ppn = $this->request->getPost('status_ppn');
        $warna = $this->request->getPost('warna');


        $data = array(
            'kode_barang' => $kode_barang,
            'nama_barang' => $nama_barang,
            'harga' => $harga,
            'harga_beli' => $harga_beli,
            'input' => $input_by,
            'stok_minimum' => $stok_minimum,
            'idkategori' => $idkategori,
            'id_sub_kategori' => $sub_kategori ?: null,
            'warna' => $warna,
            'status' => "1",
            'status_ppn' => $status_ppn,
            'deleted' => '0',
            'nama_barang_id' => 0


        );

        $result = $this->BarangModel->insert_Barang($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Disimpan');
            return redirect()->to(base_url('/produk'));
        }
    }

    public function update_produk()
    {
        $idbarang = $this->request->getPost('id_barang');
        $nama_barang = $this->request->getPost('nama_barang');
        $harga = str_replace([',', '.'], '', $this->request->getPost('harga'));
        $harga_beli = str_replace([',', '.'], '', $this->request->getPost('harga_beli'));

        $input = $this->request->getPost('input_by');
        $kategori = $this->request->getPost('kategori');
        $sub_kategori = $this->request->getPost('sub_kategori');
        $stok_minimum = $this->request->getPost('stok_minimum');


        $barangLama = $this->BarangModel->find($idbarang);


        $data_kategori = $this->KategoriModel->getByName($kategori);
        $idkategori_baru = $data_kategori->id;
        $kode_kategori_baru = $data_kategori->idkategori;

        $idkategori = $barangLama->idkategori;
        $kode_barang = $barangLama->kode_barang;


        if ($idkategori != $idkategori_baru) {

            $lastBarang = $this->BarangModel->getLastBarangByKategori($idkategori_baru, $kode_kategori_baru);
            $newNumber = (int) ($lastBarang->max_kode ?? 0) + 1;

            $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);
            $kode_barang = $kode_kategori_baru . $formattedNumber;


            $idkategori = $idkategori_baru;
        }

        $status_ppn = (int)$this->request->getPost('status_ppn');
        $warna = $this->request->getPost('warna');

        $data = array(
            'nama_barang' => $nama_barang,
            'harga' => $harga,
            'harga_beli' => $harga_beli,
            'idkategori' => $idkategori,
            'id_sub_kategori' => $sub_kategori ?: null,
            'kode_barang' => $kode_barang,
            'status_ppn' => $status_ppn,
            'warna' => $warna,
            'deleted' => '0',

            'stok_minimum' => $stok_minimum,
        );

        $result = $this->BarangModel->update($idbarang, $data);

        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
            return redirect()->to(base_url('/produk'));
        }
    }



    public function export_produk()
    {

        $produk = $this->BarangModel->getBarang();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = ['Kode Barang', 'Nama Barang', 'Warna', 'Harga', 'Harga Beli', 'Kategori', 'Status PPN', 'Input'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Styling Header
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data
        $row = 2;
        foreach ($produk as $product) {
            $ppn = ($product->status_ppn === '1') ? 'PPN' : (($product->status_ppn === '0') ? 'NON PPN' : 'Belum Diset');

            $sheet->setCellValue('A' . $row, $product->kode_barang);
            $sheet->setCellValue('B' . $row, $product->nama_barang);
            $sheet->setCellValue('C' . $row, $product->warna);
            $sheet->setCellValue('D' . $row, $product->harga);
            $sheet->setCellValue('E' . $row, $product->harga_beli);
            $sheet->setCellValue('F' . $row, $product->nama_kategori);
            $sheet->setCellValue('G' . $row, $ppn);
            $sheet->setCellValue('H' . $row, $product->input);
            $row++;
        }

        // Auto width
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle('A1:H' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // File name (safe format)
        $filename = 'data_produk_' . date('Ymd') . '.xlsx';

        // Set header
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function import_produk()
    {
        $file = $this->request->getFile('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        $db = Database::connect();

        $lastNumbers = [];

        for ($i = 1; $i < count($rows); $i++) {

            $nama_barang       = addslashes($rows[$i][0]);
            $harga             = str_replace(',', '', $rows[$i][1]);
            $harga_beli        = str_replace(',', '', $rows[$i][2]);
            $warna             = addslashes($rows[$i][3]);
            $idkategori_raw    = addslashes($rows[$i][4]);

            $id_sub_kategori   = addslashes($rows[$i][5]); // SUB KATEGORI
            $status_ppn_raw    = strtoupper(trim(addslashes($rows[$i][6])));
            $stok_minimum      = addslashes($rows[$i][7]); // STOK MINIMUM
            $input             = addslashes($rows[$i][8] ?? 'system'); // INPUT BY (jika tidak ada default "system")

            $status_ppn        = ($status_ppn_raw === 'PPN') ? 1 : 0;

            // Ambil data kategori berdasarkan ID kategori
            $dataKategori = $this->KategoriModel->getById($idkategori_raw);
            if (!$dataKategori) continue;

            $idnyakategori  = $dataKategori->id;
            $kode_kategori  = $dataKategori->idkategori;

            // Ambil nomor terakhir berdasarkan kategori
            if (!isset($lastNumbers[$idnyakategori])) {
                $lastBarang = $this->BarangModel->getLastBarangByKategori($idnyakategori, $kode_kategori);
                $lastNumbers[$idnyakategori] = (int) ($lastBarang->max_kode ?? 0);
            }

            $lastNumbers[$idnyakategori]++;
            $formattedNumber = str_pad($lastNumbers[$idnyakategori], 2, '0', STR_PAD_LEFT);
            $kode_barang     = $kode_kategori . $formattedNumber;

            // QUERY INSERT SUDAH DISESUAIKAN
            $sql = "INSERT INTO barang 
            (kode_barang, nama_barang, harga, harga_beli, warna, input, idkategori, id_sub_kategori, stok_minimum, status_ppn, status, deleted) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0)";

            $db->query($sql, [
                $kode_barang,
                $nama_barang,
                $harga,
                $harga_beli,
                $warna,
                $input,
                $idnyakategori,
                $id_sub_kategori,
                $stok_minimum,
                $status_ppn,
            ]);
        }

        session()->setFlashdata('sukses', 'Data Berhasil Disimpan');
        return redirect()->to(base_url('/produk'));
    }





    public function delete_produk()
    {
        $id_barang = $this->request->getPost('id_barang');
        $data = array(
            'deleted' => '1'
        );
        $result = $this->BarangModel->update($id_barang, $data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Di Hapus');
            return redirect()->to(base_url('/produk'));
        }
    }


    public function get_sub_kategori($kategoriNama)
    {
        // Get category by name
        //for push ulang
        $kategori = $this->KategoriModel->getByName($kategoriNama);
        if (!$kategori) {
            return $this->response->setJSON([]);
        }

        // Get sub-categories for this category
        $subKategori = $this->SubKategoriModel->getSubKategoriByParent($kategori->id);

        return $this->response->setJSON($subKategori);
    }
}
