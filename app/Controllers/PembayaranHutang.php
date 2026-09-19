<?php

namespace App\Controllers;

use App\Models\ModelKasKeluar;
use App\Models\ModelAuth;
use App\Models\ModelKategoriKas;
use App\Models\ModelNoAkun;
use App\Models\ModelBank;
use App\Models\ModelKasMasuk;
use App\Models\ModelJurnal;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\ModelPembayaranHutang;
use App\Models\ModelPembelian;



class PembayaranHutang extends BaseController
{
    protected $KasKeluarModel;
    protected $AuthModel;
    protected $KategoriKasModel;
    protected $NoAkunModel;
    protected $BankModel;
    protected $KasMasukModel;
    protected $JurnalModel;
    protected $PembayaranHutangModel;
    protected $PembelianModel;

    public function __construct()
    {
        $this->KasKeluarModel = new ModelKasKeluar();
        $this->AuthModel = new ModelAuth();
        $this->KategoriKasModel = new ModelKategoriKas();
        $this->NoAkunModel = new ModelNoAkun();
        $this->BankModel = new ModelBank();
        $this->KasMasukModel = new ModelKasMasuk();
        $this->JurnalModel = new ModelJurnal();
        $this->PembayaranHutangModel = new ModelPembayaranHutang;
        $this->PembelianModel = new ModelPembelian();
    }

    public function riwayat_pembayaran()
    {
        $data = array(
            'body' => 'hutang/riwayat_pembayaran_hutang',
            'pembayaran' => $this->PembayaranHutangModel->getAll()
        );
        return view('template', $data);
    }


    public function umur_hutang()
    {
        $data = array(
            'body' => 'hutang/umur_hutang',
            'aging' => $this->PembelianModel->getAgingHutang()
        );
        return view('template', $data);
    }


    public function export_riwayat_cicilan()
    {
        $tanggalAwal = $this->request->getPost('tanggal_awal');
        $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $namaUnit = $this->request->getPost('nama_unit');


        $hutang = $this->PembelianModel->getBelumLunasFiltered($tanggalAwal, $tanggalAkhir, $namaUnit);


        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header kolom
        $headers = [
            'Tanggal Bayar',
            'Nama Unit',
            'No Nota Supplier',
            'Jatuh Tempo',
            'Bayar',
            'Sisa Hutang',
            'Input Oleh'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Styling header
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Isi data
        $row = 2;
        foreach ($hutang as $data) {
            $sheet->setCellValue('A' . $row, date('d-m-Y', strtotime($data->tanggal_bayar ?? $data->tanggal_masuk)));
            $sheet->setCellValue('B' . $row, $data->NAMA_UNIT);
            $sheet->setCellValue('C' . $row, $data->no_nota_supplier);
            $sheet->setCellValue('D' . $row, date('d-m-Y', strtotime($data->jatuh_tempo)));
            $sheet->setCellValue('E' . $row, $data->bayar);
            $sheet->setCellValue('F' . $row, $data->sisa);
            $sheet->setCellValue('G' . $row, $data->nama_input ?? '-');
            $row++;
        }

        // Auto width semua kolom
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle("A1:{$lastCol}" . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // Nama file
        $filename = 'tagihan_hutang_' . date('Ymd_His') . '.xlsx';

        // Output ke browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }



    //halaman daftar tagihan

    public function daftar_tagihan()
    {
        $data = array(
            'body' => 'hutang/daftar_tagihan',
            'bank' => $this->BankModel->getBank(),
            'hutang' => $this->PembelianModel->getBelumLunas()
        );
        return view('template', $data);
    }

    public function insert_cicilan()
    {
        date_default_timezone_set('Asia/Jakarta');
        $bayar_tunai = $this->sanitizeRupiah($this->request->getPost('bayar_tunai'));
        $bayar_bank  = $this->sanitizeRupiah($this->request->getPost('bayar_bank'));

        $total_bayar = $bayar_bank + $bayar_tunai;
        $bank_idbank = $this->request->getPost('bank_idbank');
        $idpembelian = $this->request->getPost('idpembelian');
        $sisa_hutang = $this->sanitizeRupiah($this->request->getPost('sisa'));
        $idpembelian = $this->request->getPost('idpembelian');

        $data = array(
            'tanggal_bayar' => date('Y-m-d'),
            'bayar' => $total_bayar,
            'bayar_tunai' => $bayar_tunai,
            'bayar_bank' => $bayar_bank,
            'bank_idbank' => $bank_idbank,
            'pembelian_idpembelian' => $idpembelian,
            'sisa_hutang' => $sisa_hutang,
            'input_by' => session('ID_AKUN')

        );
        $this->PembayaranHutangModel->insert($data);
        $datapembelian = $this->PembelianModel->getById($idpembelian);
        $total_bayar_lama = $datapembelian->total_bayar;
        $bayar_tunai_lama = $datapembelian->bayar_tunai;
        $bayar_bank_lama = $datapembelian->bayar_bank;
        $status_hutang = '';
        if ($sisa_hutang <= 0) {
            $status_hutang = 'Lunas';
        } else {
            $status_hutang = 'Belum Lunas';
        }


        $data2 = array(
            'total_bayar' => $total_bayar_lama + $total_bayar,
            'bayar' => $total_bayar_lama + $total_bayar,
            'bayar_tunai' => $bayar_tunai_lama + $bayar_tunai,
            'bayar_bank' => $bayar_bank_lama + $bayar_bank,
            'sisa' => $sisa_hutang,
            'status' => $status_hutang
        );

        $this->PembelianModel->update($idpembelian, $data2);
        try {
            (new \App\Services\Finance\HutangPiutangService())->syncFromPembelian((int) $idpembelian);
        } catch (\Throwable $e) {
            log_message('error', 'syncFromPembelian #' . $idpembelian . ' gagal: ' . $e->getMessage());
        }
        session()->setFlashdata('sukses', 'Data Berhasil Diupdate');
        return redirect()->to(base_url('daftar_tagihan'));
    }


    public function export_daftar_hutang()
    {
        $tanggalAwal = $this->request->getPost('tanggal_awal');
        $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $namaUnit = $this->request->getPost('nama_unit');


        $hutang = $this->PembayaranHutangModel->getFiltered($tanggalAwal, $tanggalAkhir, $namaUnit);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header kolom
        $headers = [
            'Tanggal Masuk',
            'Nama Unit',
            'No Nota Supplier',
            'Jatuh Tempo',
            'Sisa Hutang'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Styling header
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        // Isi data
        $row = 2;
        foreach ($hutang as $data) {
            $sheet->setCellValue('A' . $row, date('d-m-Y', strtotime($data->tanggal_bayar)));
            $sheet->setCellValue('B' . $row, $data->NAMA_UNIT);
            $sheet->setCellValue('C' . $row, $data->no_nota_supplier);
            $sheet->setCellValue('D' . $row, date('d-m-Y', strtotime($data->jatuh_tempo)));
            $sheet->setCellValue('E' . $row, $data->sisa_hutang);
            $row++;
        }

        // Auto width semua kolom
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle("A1:{$lastCol}" . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // Nama file
        $filename = 'pembayaran_hutang_' . date('Ymd_His') . '.xlsx';

        // Output ke browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }


    public function export_umur_hutang()
    {
        $tanggalAwal = $this->request->getPost('tanggal_awal');
        $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $namaUnit = $this->request->getPost('nama_unit');

        // Ambil data aging hutang dari model
        $hutang = $this->PembelianModel->getAgingHutangfiltered($tanggalAwal, $tanggalAkhir, $namaUnit);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header kolom
        $headers = [
            'No Nota Supplier',
            'Tanggal Masuk',
            'Jatuh Tempo',
            'Nama Unit',
            'InputBy',
            'Sisa Hutang',
            '0 - 30 Hari',
            '31 - 60 Hari',
            '61 - 90 Hari',
            '> 90 Hari'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Styling header
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCE6F1');

        // Isi data
        $row = 2;
        foreach ($hutang as $data) {
            $sheet->setCellValue('A' . $row, $data->no_nota_supplier);
            $sheet->setCellValue('B' . $row, date('d-m-Y', strtotime($data->tanggal_masuk)));
            $sheet->setCellValue('C' . $row, date('d-m-Y', strtotime($data->jatuh_tempo)));
            $sheet->setCellValue('D' . $row, $data->nama_unit);
            $sheet->setCellValue('E' . $row, $data->nama_akun);
            $sheet->setCellValue('F' . $row, $data->sisa);
            $sheet->setCellValue('G' . $row, $data->{"0_30_hari"});
            $sheet->setCellValue('H' . $row, $data->{"31_60_hari"});
            $sheet->setCellValue('I' . $row, $data->{"61_90_hari"});
            $sheet->setCellValue('J' . $row, $data->lebih_90_hari);

            $row++;
        }

        // Auto width semua kolom
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle("A1:{$lastCol}" . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Freeze header
        $sheet->freezePane('A2');

        // Nama file
        $filename = 'aging_hutang_' . date('Ymd_His') . '.xlsx';

        // Output ke browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }







    function sanitizeRupiah($value)
    {
        $value = preg_replace('/[^\d]/', '', $value);
        return $value === '' ? 0 : (int) $value;
    }
}
