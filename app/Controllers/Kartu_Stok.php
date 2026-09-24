<?php

namespace App\Controllers;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKartuStok;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\ModelUnit;

class Kartu_Stok extends BaseController

{

    protected $AuthModel;
    protected $KartuStokModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KartuStokModel = new ModelKartuStok();
        $this->UnitModel = new ModelUnit();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'summary' => $this->KartuStokModel->getSummaryKartuStok(),
            'body'  => 'stok/kartu_stok',
            'unit' => $this->UnitModel->getUnit()
        );
        return view('template', $data);
    }

    /**
     * Server-side processing DataTables Kartu Stok.
     */
    public function dt()
    {
        $draw    = (int)$this->request->getGet('draw');
        $start   = (int)$this->request->getGet('start');
        $length  = (int)$this->request->getGet('length');

        $searchParam = $this->request->getGet('search');
        $search = '';
        if (is_array($searchParam)) {
            $search = trim((string)($searchParam['value'] ?? ''));
        } else {
            $search = trim((string)$searchParam);
        }

        $orderCol = $this->request->getGet('order') ? $this->request->getGet('order')[0]['column'] : 0;
        $orderDir = $this->request->getGet('order') ? $this->request->getGet('order')[0]['dir'] : 'asc';

        $columnMap = [
            0 => 'stok_barang.kode_barang',
            1 => 'stok_barang.nama_barang',
            2 => 'stok_barang.nama_unit',
            3 => 'kategori.nama_kategori',
            4 => 'barang.status_ppn',
            5 => 'stok_barang.stok_akhir',
        ];
        $orderCol = $columnMap[$orderCol] ?? 'stok_barang.kode_barang';
        $orderDir = strtolower($orderDir) === 'desc' ? 'DESC' : 'ASC';

        $unit = trim((string)$this->request->getGet('unit'));
        $ppn  = trim((string)$this->request->getGet('status_ppn'));

        $totalRecords = $this->KartuStokModel->countKartuStokAll();
        $filteredRecords = $this->KartuStokModel->countKartuStokDT($search, $unit, $ppn);
        $rows = $this->KartuStokModel->getKartuStokDT($length, $start, $search, $orderCol, $orderDir, $unit, $ppn);

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'idbarang'            => (int)$row->idbarang,
                'id_unit'             => (int)$row->id_unit,
                'kode_barang'         => $row->kode_barang,
                'nama_barang'         => $row->nama_barang,
                'imei'                => $row->imei,
                'nama_unit'           => $row->nama_unit,
                'nama_kategori'       => $row->nama_kategori ?? '-',
                'jenis_hp'            => $row->jenis_hp,
                'warna'               => $row->warna,
                'harga'               => (float)$row->harga,
                'harga_beli'          => (float)$row->harga_beli,
                'status_ppn'          => (int)$row->status_ppn,
                'stok_awal'           => (float)$row->stok_awal,
                'stok_akhir'          => (float)$row->stok_akhir,
                'total_pembelian'     => (float)$row->total_pembelian,
                'total_penjualan'     => (float)$row->total_penjualan,
                'total_retur_pelanggan' => (float)$row->total_retur_pelanggan,
                'total_retur_supplier'  => (float)$row->total_retur_supplier,
                'total_mutasi_masuk'  => (float)$row->total_mutasi_masuk,
                'total_mutasi_keluar' => (float)$row->total_mutasi_keluar,
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data'            => $data,
        ]);
    }

    public function export()
    {
        // $tanggalAwal = $this->request->getPost('tanggal_awal');
        // $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $unit = $this->request->getPost('unit');
        $statusPpn = $this->request->getPost('status_ppn');

        // if (empty($tanggalAwal)) {
        //     $tanggalAwal = $this->KartuStokModel->getMinTanggalStok();
        // }

        // if (empty($tanggalAkhir)) {
        //     $tanggalAkhir = date('Y-m-d');
        // }

        $stok = $this->KartuStokModel->exportfilter( $unit, $statusPpn);
        // $stok = $this->KartuStokModel->exportfilter($tanggalAwal, $tanggalAkhir, $unit, $statusPpn);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header titles
        $headers = [
            'Kode Barang',
            'Nama Barang',
            'Imei',
            'Status PPN',
            'Unit',
            'Total Pembelian',
            'Total Penjualan',
            'Total Retur Pelanggan',
            'Total Retur Supplier',
            'Stok Akhir'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Header styling
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:L1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data rows
        $row = 2;
        foreach ($stok as $item) {
            $sheet->setCellValue('A' . $row, $item->kode_barang);
            $sheet->setCellValue('B' . $row, $item->nama_barang);

            // Jika imei kosong → tampilkan "Tidak ada IMEI"
            $imeiText = !empty($item->imei) ? $item->imei : 'Tidak ada IMEI';
            $sheet->setCellValue('C' . $row, $imeiText);

            $sheet->setCellValue('D' . $row, ((int)$item->status_ppn === 1) ? 'PPN' : 'NON PPN');
            $sheet->setCellValue('E' . $row, $item->nama_unit);
            
            
            $sheet->setCellValue('H' . $row, $item->total_pembelian);
            $sheet->setCellValue('I' . $row, $item->total_penjualan);
            $sheet->setCellValue('J' . $row, $item->total_retur_pelanggan);
            $sheet->setCellValue('K' . $row, $item->total_retur_supplier);
            $sheet->setCellValue('L' . $row, $item->stok_akhir);
            $row++;
        }

        // Auto width
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Borders
        $sheet->getStyle('A1:L' . ($row - 1))
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // File name
        $unitLabel = $unit ?: 'all_unit';
        $ppnLabel = $statusPpn ?: 'all_ppn';
        // $tglAwalFormatted = date('Ymd', strtotime($tanggalAwal));
        // $tglAkhirFormatted = date('Ymd', strtotime($tanggalAkhir));
        $filename = 'kartu_stok_' . $unitLabel . '_' . $ppnLabel . '.xlsx';
        // $filename = 'kartu_stok_' . $unitLabel . '_' . $ppnLabel . '_' . $tglAwalFormatted . '-' . $tglAkhirFormatted . '.xlsx';

        // Output
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}