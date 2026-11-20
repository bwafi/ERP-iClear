<?php

namespace App\Controllers;

use App\Models\ModelAuth;
use App\Models\ModelDetailPembelian;
use App\Models\ModelPembelian;
use App\Models\ModelUnit;
use App\Services\Finance\FinanceScopeService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class Riwayat_pembelian extends BaseController

{

    protected $DetailPembelianModel;
    protected $PembelianModel;
    protected $AuthModel;
    protected $UnitModel;

    public function __construct()
    {
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->PembelianModel = new ModelPembelian();
        $this->AuthModel = new ModelAuth();
        $this->UnitModel = new ModelUnit();
    }

    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));
        $scope = new FinanceScopeService();

        $data = [
            'akun' => $akun,
            'unit' => $scope->resolveAllowedUnits(),
            'boleh_semua_unit' => $scope->scopeInfo()['isLintas'],
            'body' => 'riwayat/pembelian',
        ];

        return view('template', $data);
    }

    /**
     * Data daftar nota untuk DataTables server-side.
     */
    public function ajax()
    {
        $request = $this->request;
        $scope = new FinanceScopeService();
        $allowedUnitIds = $scope->allowedUnitIds();

        $draw = (int) ($request->getPost('draw') ?? 0);
        $start = max(0, (int) ($request->getPost('start') ?? 0));
        $length = (int) ($request->getPost('length') ?? 10);
        $length = $length > 0 ? min($length, 100) : 10;

        $search = $request->getPost('search');
        $searchValue = is_array($search) ? trim((string) ($search['value'] ?? '')) : '';

        $order = $request->getPost('order');
        $orderRow = is_array($order) ? ($order[0] ?? []) : [];

        // Unit dari request hanya boleh mempersempit scope, tidak melebarkannya.
        $unitId = (int) ($request->getPost('unitFilter') ?? 0);
        if ($unitId > 0 && ! in_array($unitId, $allowedUnitIds, true)) {
            $unitId = 0;
        }

        $result = $this->PembelianModel->getRiwayatServerSide(
            $start,
            $length,
            $searchValue,
            (int) ($orderRow['column'] ?? 1),
            (string) ($orderRow['dir'] ?? 'desc'),
            (string) ($request->getPost('startDate') ?? ''),
            (string) ($request->getPost('endDate') ?? ''),
            $unitId,
            $allowedUnitIds
        );

        return $this->response->setJSON([
            'draw' => $draw,
            'recordsTotal' => $this->PembelianModel->countRiwayat($allowedUnitIds),
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Rincian barang satu nota untuk modal detail.
     */
    public function detail($idpembelian)
    {
        $pembelian = $this->PembelianModel->getById((int) $idpembelian);
        if (empty($pembelian)) {
            return $this->response->setStatusCode(404)->setJSON(['message' => 'Nota pembelian tidak ditemukan.']);
        }

        $scope = new FinanceScopeService();
        if (! in_array((int) $pembelian->unit_idunit, $scope->allowedUnitIds(), true)) {
            return $this->response->setStatusCode(404)->setJSON(['message' => 'Nota pembelian tidak ditemukan.']);
        }

        $items = $this->DetailPembelianModel->getByPembelianId((int) $pembelian->idpembelian);
        $unit = $this->UnitModel->find((int) $pembelian->unit_idunit);
        $namaUnit = $unit ? (string) $unit->NAMA_UNIT : '';

        $suplierId = (int) ($pembelian->suplier_id_suplier ?? 0);
        $noBatch = (string) ($pembelian->no_nota_supplier ?? '');
        if ($noBatch === '' && ! empty($items)) {
            $noBatch = (string) ($items[0]->no_batch ?? '');
        }

        return $this->response->setJSON([
            'idpembelian' => (int) $pembelian->idpembelian,
            'no_batch' => $noBatch,
            'tanggal' => $pembelian->tanggal_masuk,
            'nama_unit' => $namaUnit,
            'sumber' => $suplierId > 0 ? 'Suplier' : 'Pelanggan',
            'status' => $pembelian->status,
            'sisa' => (int) ($pembelian->sisa ?? 0),
            'total' => (int) ($pembelian->total_transaksi ?? 0),
            'items' => array_map(static function ($item) {
                return [
                    'nama_barang' => $item->nama_barang ?? '',
                    'kode_barang' => $item->kode_barang ?? '',
                    'imei' => $item->imei ?? '',
                    'jumlah' => (int) ($item->jumlah ?? 0),
                    'hrg_beli' => (int) ($item->hrg_beli ?? 0),
                    'diskon' => (int) ($item->diskon ?? 0),
                    'ppn' => (int) ($item->ppn ?? 0),
                    'total_harga' => (int) ($item->total_harga ?? 0),
                    'satuan_beli' => $item->satuan_beli ?? '',
                ];
            }, $items),
        ]);
    }

    public function export()
    {
        $scope = new FinanceScopeService();
        $allowedUnitIds = $scope->allowedUnitIds();

        $unit = (int) ($this->request->getPost('unit') ?? 0);
        if ($unit > 0 && ! in_array($unit, $allowedUnitIds, true)) {
            return redirect()->back()->with('gagal', 'Unit yang dipilih di luar wewenang akun Anda.');
        }

        $unitIds = $unit > 0 ? [$unit] : $allowedUnitIds;
        if ($unitIds === []) {
            return redirect()->back()->with('gagal', 'Tidak ada unit yang boleh diunduh.');
        }

        $tanggal_awal = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        $datapembelian = $this->DetailPembelianModel->exportfilter($tanggal_awal, $tanggal_akhir, null, $unitIds);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header Titles
        $headers = [
            'A1' => 'No. Nota Suplier',
            'B1' => 'Tanggal',
            'C1' => 'Nama Barang',
            'D1' => 'Jumlah',
            'E1' => 'Unit',
            'F1' => 'Diskon',
            'G1' => 'PPN',
            'H1' => 'Total Harga',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Styling Header
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Data Rows
        $row = 2;
        foreach ($datapembelian as $item) {
            $sheet->setCellValue('A' . $row, $item->no_batch);
            $sheet->setCellValue('B' . $row, $item->tanggal);
            $sheet->setCellValue('C' . $row, $item->nama_barang);
            $sheet->setCellValue('D' . $row, $item->jumlah);
            $sheet->setCellValue('E' . $row, $item->NAMA_UNIT);
            $sheet->setCellValue('F' . $row, $item->diskon);
            $sheet->setCellValue('G' . $row, $item->ppn);
            $sheet->setCellValue('H' . $row, $item->total_harga);
            $row++;
        }

        // Auto Width
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle('A1:H' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header row
        $sheet->freezePane('A2');

        // Format angka (optional)
        $sheet->getStyle('F2:F' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('G2:G' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('H2:H' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');

        // Filename
        $filename = 'Riwayat_Pembelian_' . date('Ymd_His') . '.xlsx';

        // Set header
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
