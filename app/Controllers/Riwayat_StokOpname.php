<?php

namespace App\Controllers;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKartuStok;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\ModelStokOpname;
use App\Models\ModelStokOpnameDraft;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class Riwayat_StokOpname extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $StokOpnameModel;
    protected $StokOpnameDraftModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->StokOpnameModel = new ModelStokOpname();
        $this->StokOpnameDraftModel = new ModelStokOpnameDraft();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $stokopname = $this->StokOpnameModel->getStokOpnameAll();
        
        // Grouping stok opname by date untuk kemudahan modal filter
        $stokopname_grouped = [];
        foreach ($stokopname as $row) {
            $stokopname_grouped[$row->tanggal][] = $row;
        }

        $data =  array(
            'akun' => $akun,
            'stok' => $this->KartuStokModel->getKartuStok(),
            'stokopname' => $stokopname,
            'stokopname_grouped' => $stokopname_grouped,
            'stokopnamedraft' => $this->StokOpnameDraftModel->getStokOpname(),
            'body'  => 'riwayat/stok_opname'
        );
        return view('template', $data);
    }

    public function export()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Ambil filter dari input
        $unit = $this->request->getPost('unit');
        $tanggal_awal = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        // Ambil data dari model
        $dataopname = $this->StokOpnameModel->exportfilter($tanggal_awal, $tanggal_akhir, $unit);

        // Header kolom
        $headers = [
            'A1' => 'Tanggal',
            'B1' => 'Nama Unit',
            'C1' => 'Nama Barang',
            'D1' => 'Jumlah Komputer',
            'E1' => 'Jumlah Real',
            'F1' => 'Jumlah Selisih',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling header
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data isi
        $row = 2;
        foreach ($dataopname as $item) {
            $sheet->setCellValue('A' . $row, $item->tanggal);
            $sheet->setCellValue('B' . $row, $item->NAMA_UNIT);
            $sheet->setCellValue('C' . $row, $item->nama_barang);
            $sheet->setCellValue('D' . $row, $item->jumlah_komp);
            $sheet->setCellValue('E' . $row, $item->jumlah_real);
            $sheet->setCellValue('F' . $row, $item->jumlah_selisih);
            $row++;
        }

        // Auto width untuk semua kolom
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Tambahkan border ke seluruh data
        $sheet->getStyle('A1:F' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Format angka
        $sheet->getStyle('D2:F' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');

        // Freeze header
        $sheet->freezePane('A2');

        // Nama file
        $filename = 'Riwayat_Stok_Opname_' . date('Ymd_His') . '.xlsx';

        // Set header untuk browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        // Output file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
