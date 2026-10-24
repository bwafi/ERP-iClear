<?php

namespace App\Controllers;

use App\Models\ModelKategori;
use App\Models\ModelDetailPembelian;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelReturCustomer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Models\ModelService;
use App\Models\ModelHppKlaimLoss;

class RiwayatLabaService extends BaseController

{

    protected $KategoriModel;
    protected $DetailPembelianModel;
    protected $AuthModel;
    protected $ReturCustomerModel;
    protected $ServiceModel;
    protected $HppKlaimLossModel;


    public function __construct()
    {
        $this->KategoriModel = new ModelKategori();
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->AuthModel = new ModelAuth();
        $this->ReturCustomerModel = new ModelReturCustomer();
        $this->ServiceModel = new ModelService();
        $this->HppKlaimLossModel = new ModelHppKlaimLoss();
    }

    public function index()
    {
        $service = $this->ServiceModel->getServiceWithLaba();
        $hppKlaim = $this->HppKlaimLossModel->getLoss(date('Y-m-01'), date('Y-m-t'));
        $data = array(
            'service' => $service,
            'hpp_klaim' => $hppKlaim,
            'total_hpp_klaim' => array_sum(array_map(static fn ($row) => (int) $row->total, $hppKlaim)),
            'body' => 'riwayat/laba_service'
        );
        return view('template', $data);
    }


    public function export()
    {

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $tanggal_awal  = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        // Ambil data dari model
        $dataservice = $this->ServiceModel->filterexportlaba($tanggal_awal, $tanggal_akhir);
        $lossStart = $tanggal_awal ?: '1970-01-01';
        $lossEnd = $tanggal_akhir ?: date('Y-m-d');
        $loss = $this->HppKlaimLossModel->getLoss($lossStart, $lossEnd);

        // Header kolom
        $headers = [
            'A1' => 'No. Service',
            'B1' => 'Tanggal Masuk',
            'C1' => 'Total Service',
            'D1' => 'Total DIskon',
            'E1' => 'Sub Total',
            'F1' => 'Laba Service',
            'G1' => 'Nama Teknisi'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling header
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2EFDA'); // Warna hijau muda

        // Tulis data ke baris berikutnya
        $row = 2;
        foreach ($dataservice as $item) {
            $sheet->setCellValue('A' . $row, $item->no_service);
            $sheet->setCellValue('B' . $row, $item->created_at);
            $sheet->setCellValue('C' . $row, $item->total_service);
            $sheet->setCellValue('D' . $row, $item->total_diskon);
            $sheet->setCellValue('E' . $row, $item->harus_dibayar);
            $sheet->setCellValue('F' . $row, $item->laba_service);
            $sheet->setCellValue('G' . $row, $item->nama_teknisi);
            $row++;
        }

        // Border seluruh tabel
        $sheet->getStyle('A1:G' . ($row - 1))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Auto-width untuk semua kolom
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format tanggal kolom B
        $sheet->getStyle('B2:B' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        // Freeze header
        $sheet->freezePane('A2');

        // Sheet HPP Klaim: loss mengikuti tanggal_hpp_klaim, bukan tanggal service.
        $lossSheet = $spreadsheet->createSheet();
        $lossSheet->setTitle('HPP Klaim');
        $lossHeaders = [
            'A1' => 'Tanggal Klaim',
            'B1' => 'No. Service',
            'C1' => 'Nama Teknisi',
            'D1' => 'Barang',
            'E1' => 'HPP',
            'F1' => 'Qty',
            'G1' => 'Total',
        ];
        foreach ($lossHeaders as $cell => $text) {
            $lossSheet->setCellValue($cell, $text);
        }
        $lossSheet->getStyle('A1:G1')->getFont()->setBold(true);
        $lossSheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $lossSheet->getStyle('A1:G1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFE2E2');

        $lossRow = 2;
        foreach ($loss as $item) {
            $lossSheet->setCellValue('A' . $lossRow, $item->tanggal_hpp_klaim);
            $lossSheet->setCellValue('B' . $lossRow, $item->no_service);
            $lossSheet->setCellValue('C' . $lossRow, $item->nama_teknisi);
            $lossSheet->setCellValue('D' . $lossRow, $item->nama_barang);
            $lossSheet->setCellValue('E' . $lossRow, $item->hpp);
            $lossSheet->setCellValue('F' . $lossRow, $item->qty);
            $lossSheet->setCellValue('G' . $lossRow, $item->total);
            $lossRow++;
        }
        $lossSheet->getStyle('A1:G' . ($lossRow - 1))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'G') as $col) {
            $lossSheet->getColumnDimension($col)->setAutoSize(true);
        }
        if ($lossRow > 2) {
            $lossSheet->getStyle('A2:A' . ($lossRow - 1))
                ->getNumberFormat()
                ->setFormatCode('yyyy-mm-dd');
        }
        $lossSheet->freezePane('A2');

        $spreadsheet->setActiveSheetIndex(0);

        // Nama file
        $filename = 'Riwayat_Laba_Service_' . date('Ymd_His') . '.xlsx';

        // Header untuk response browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        // Output file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
