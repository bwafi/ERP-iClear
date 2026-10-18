<?php

namespace App\Controllers;

use App\Models\ModelPhone;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelUnit;
use App\Models\ModelJurnal;
use App\Services\Finance\BalanceSheetReconciliation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Mpdf\Mpdf;

class SisiKeuangan extends BaseController
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

    // laporan jurnal
    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));
        $unit = $this->UnitModel->getUnit();

        $tanggal_awal = $this->request->getGet('startDate') ?: null;
        $tanggal_akhir = $this->request->getGet('endDate') ?: null;
        $id_unit = $this->request->getGet('filterUnit') ?: null;
        $reconciliationDate = $tanggal_akhir ?: date('Y-m-d');

        $data_parent = $this->JurnalModel->getSummaryPerParent($tanggal_awal, $tanggal_akhir, $id_unit);

        $journalQuery = Database::connect()->table('jurnal')
            ->select('no_akun, COALESCE(SUM(debet), 0) AS debet, COALESCE(SUM(kredit), 0) AS kredit', false)
            ->where('tanggal <=', $reconciliationDate);
        if ($id_unit !== null) {
            $journalQuery->where('id_unit', $id_unit);
        }
        $reconciliation = BalanceSheetReconciliation::summarize(
            $journalQuery->groupBy('no_akun')->get()->getResultArray()
        );


        foreach ($data_parent as &$parent) {
            $parent_no = $parent['parent_no_akun'];
            $prefix = substr($parent_no, 0, 3);


            $children = $this->JurnalModel->getChildByParent($prefix, $tanggal_awal, $tanggal_akhir, $id_unit);


            $filteredChildren = array_filter($children, function ($child) {
                return substr($child->no_akun, -7) !== '0000000';
            });

            $parent['children'] = $filteredChildren;
        }



        $data = [
            'akun' => $akun,
            'data_parent' => $data_parent,
            'data_grand_parent' => $this->JurnalModel->getTotalGrandparent($tanggal_awal, $tanggal_akhir, $id_unit),
            'data_unit' => $unit,
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'id_unit' => $id_unit,
            'reconciliation' => $reconciliation,
            'reconciliation_date' => $reconciliationDate,
            'body' => 'jurnal/sisi_keuangan'
        ];

        return view('template', $data);
    }


    public function export_pdf()
    {
        $tanggal_awal = $this->request->getGet('startDate');
        $tanggal_akhir = $this->request->getGet('endDate');
        $id_unit = $this->request->getGet('filterUnit');

        $showZeroSaldo = $this->request->getGet('showZeroSaldo') == '1';
        $showChildren = $this->request->getGet('showChildren') == '1';

        $data_parent = $this->JurnalModel->getSummaryPerParent($tanggal_awal, $tanggal_akhir, $id_unit);

        foreach ($data_parent as $key => &$parent) {
            $prefix = substr($parent['parent_no_akun'], 0, 3);
            $children = $this->JurnalModel->getChildByParent($prefix, $tanggal_awal, $tanggal_akhir, $id_unit);

            // Filter child saldo nol jika toggle tidak diaktifkan
            $filteredChildren = array_filter($children, function ($child) use ($showZeroSaldo) {
                $saldo = $child->total_debet - $child->total_kredit;
                return $showZeroSaldo || $saldo != 0;
            });

            $parent['children'] = $showChildren ? $filteredChildren : [];

            // Filter parent saldo nol jika toggle tidak diaktifkan dan tidak ada child
            $saldo = $parent['total_debet'] - $parent['total_kredit'];
            if (!$showZeroSaldo && $saldo == 0 && empty($parent['children'])) {
                unset($data_parent[$key]); // Hapus baris parent dari array
            }
        }

        $data = [
            'data_parent' => $data_parent,
            'data_grand_parent' => $this->JurnalModel->getTotalGrandparent($tanggal_awal, $tanggal_akhir, $id_unit),
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'id_unit' => $id_unit,
        ];

        $html = view('cetak/posisi_keuangan', $data);

        $mpdf = new \Mpdf\Mpdf([
            'curlAllowUnsafeSslRequests' => true,
            'curlUserAgent' => 'Mozilla/5.0',
        ]);

        ob_end_clean();
        $mpdf->WriteHTML($html);
        $mpdf->Output('posisi-keuangan.pdf', 'I');
        exit;
    }



    public function export_excel()
    {
        $tanggal_awal = $this->request->getGet('startDate');
        $tanggal_akhir = $this->request->getGet('endDate');
        $id_unit = $this->request->getGet('filterUnit');
        $showZeroSaldo = $this->request->getGet('showZeroSaldo') == '1';
        $showChildren = $this->request->getGet('showChildren') == '1';

        $data_parent = $this->JurnalModel->getSummaryPerParent($tanggal_awal, $tanggal_akhir, $id_unit);

        // Filter data berdasarkan toggle
        foreach ($data_parent as $key => &$parent) {
            $prefix = substr($parent['parent_no_akun'], 0, 3);
            $children = $this->JurnalModel->getChildByParent($prefix, $tanggal_awal, $tanggal_akhir, $id_unit);

            $filteredChildren = array_filter($children, function ($child) use ($showZeroSaldo) {
                $saldo = $child->total_debet - $child->total_kredit;
                return $showZeroSaldo || $saldo != 0;
            });

            $parent['children'] = $showChildren ? $filteredChildren : [];

            $saldo = $parent['total_debet'] - $parent['total_kredit'];
            if (!$showZeroSaldo && $saldo == 0 && empty($parent['children'])) {
                unset($data_parent[$key]);
            }
        }

        // === MULAI GENERATE EXCEL ===
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $headers = ['Kode Akun', 'Nama Akun', 'Saldo'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        $row = 2;
        foreach ($data_parent as $parent) {
            $saldo = $parent['total_debet'] - $parent['total_kredit'];

            $sheet->setCellValue('A' . $row, $parent['parent_no_akun']);
            $sheet->setCellValue('B' . $row, $parent['parent_nama_akun']);
            $sheet->setCellValue('C' . $row, abs($saldo));
            $row++;

            foreach ($parent['children'] as $child) {
                $saldoAnak = $child->total_debet - $child->total_kredit;
                $sheet->setCellValue('A' . $row, $child->no_akun);
                $sheet->setCellValue('B' . $row, '↳ ' . $child->nama_akun);
                $sheet->setCellValue('C' . $row, abs($saldoAnak));
                $row++;
            }
        }

        // Auto width
        foreach (range('A', 'C') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        $filename = 'posisi_keuangan_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
