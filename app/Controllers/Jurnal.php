<?php

namespace App\Controllers;

use App\Models\ModelPhone;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelUnit;
use App\Models\ModelJurnal;
use App\Services\Finance\JournalReportFilters;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class Jurnal extends BaseController

{

    protected $PhoneModel;
    protected $AuthModel;
    protected $UnitModel;
    protected $JurnalModel;

    public function __construct()
    {
        $this->PhoneModel = new ModelPhone();
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
        $this->JurnalModel = new ModelJurnal();
    }

    //laporan jurnal
    public function index()
    {
        $filters = JournalReportFilters::fromArray([
            'startDate' => $this->request->getGet('startDate'),
            'endDate' => $this->request->getGet('endDate'),
            'filterUnit' => $this->request->getGet('filterUnit'),
            'no_akun' => $this->request->getGet('no_akun'),
            'tabel_referensi' => $this->request->getGet('tabel_referensi'),
            'id_referensi' => $this->request->getGet('id_referensi'),
        ]);
        $data = array(
            'body' => 'jurnal/laporan_jurnal',
            'jurnal' => $this->JurnalModel->getJurnalWithUnit($filters),
            'journal_filters' => $filters,
            'data_unit' => $this->UnitModel->getUnit(),
        );
        return view('template', $data);
    }

    public function export_jurnal()
    {
        $filters = JournalReportFilters::fromArray([
            'startDate' => $this->request->getPost('startDate'),
            'endDate' => $this->request->getPost('endDate'),
            'filterUnit' => $this->request->getPost('filterUnit'),
            'no_akun' => $this->request->getPost('no_akun'),
            'tabel_referensi' => $this->request->getPost('tabel_referensi'),
            'id_referensi' => $this->request->getPost('id_referensi'),
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $data = $this->JurnalModel->getJurnalWithUnit($filters);


        $headers = [
            'A1' => 'Tanggal',
            'B1' => 'No Akun',
            'C1' => 'Nama Akun',
            'D1' => 'Debet',
            'E1' => 'Kredit',
            'F1' => 'Keterangan',
            'G1' => 'Unit',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }


        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');


        $row = 2;
        foreach ($data as $item) {
            $sheet->setCellValue('A' . $row, $item->tanggal);
            $sheet->setCellValue('B' . $row, $item->no_akun);
            $sheet->setCellValue('C' . $row, $item->nama_akun);
            $sheet->setCellValue('D' . $row, $item->debet);
            $sheet->setCellValue('E' . $row, $item->kredit);
            $sheet->setCellValue('F' . $row, $item->keterangan);
            $sheet->setCellValue('G' . $row, $item->NAMA_UNIT);
            $row++;
        }


        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }


        $sheet->getStyle('A1:G' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('A2');

        $filename = 'Laporan_Jurnal_' . date('Ymd_His') . '.xlsx';


        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
