<?php

namespace App\Controllers;

use App\Models\ModelKasKeluar;
use App\Models\ModelAuth;
use App\Models\ModelKategoriKas;
use App\Models\ModelNoAkun;
use App\Models\ModelBank;
use App\Models\ModelJurnal;
use App\Models\ModelPelanggan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\ModelUnit;
use App\Models\ModelPiutang;
use App\Models\ModelPembayaranPiutang;


class Piutang extends BaseController
{
    protected $KasKeluarModel;
    protected $AuthModel;
    protected $KategoriKasModel;
    protected $NoAkunModel;
    protected $BankModel;
    protected $JurnalModel;
    protected $UnitModel;
    protected $PiutangModel;
    protected $PelangganModel;
    protected $PembayaranPiutangModel;

    public function __construct()
    {
        $this->KasKeluarModel = new ModelKasKeluar();
        $this->AuthModel = new ModelAuth();
        $this->KategoriKasModel = new ModelKategoriKas();
        $this->NoAkunModel = new ModelNoAkun();
        $this->BankModel = new ModelBank();
        $this->JurnalModel = new ModelJurnal();
        $this->UnitModel = new ModelUnit();
        $this->PiutangModel = new ModelPiutang();
        $this->PelangganModel = new ModelPelanggan();
        $this->PembayaranPiutangModel = new ModelPembayaranPiutang();
    }

    public function index()
    {
        $data = array(
            'body' => 'piutang/input',
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'pegawai' => $this->AuthModel->getAkunPegawai(),
            'bank' => $this->BankModel->getBank()
        );
        return view('template', $data);
    }


    public function umur_piutang()
    {
        $data  = array(
            'body' => 'piutang/umur_piutang',
            'aging' => $this->PiutangModel->getAgingPiutang()
        );

        return view('template', $data);
    }


    public function insert()
    {
        date_default_timezone_set('Asia/Jakarta');
        $tanggal_peminjaman = $this->request->getPost('tanggal_peminjaman');
        $jatuh_tempo = $this->request->getPost('jatuh_tempo');
        $pegawai_idpegawai = $this->request->getPost('pegawai_idpegawai');
        $bank_idbank = $this->request->getPost('bank_idbank');
        $jumlah_bank  = preg_replace('/[^0-9]/', '', $this->request->getPost('jumlah_bank'));
        $jumlah_tunai = preg_replace('/[^0-9]/', '', $this->request->getPost('jumlah_tunai'));
        $nilai_total  = preg_replace('/[^0-9]/', '', $this->request->getPost('nilai_total'));

        $unit_idunit = session('ID_UNIT');
        $tanggalSekarang = date('Ymd'); // 20250829


        $prefix = "PUT" . $tanggalSekarang . $unit_idunit;


        $lastPiutang = $this->PiutangModel
            ->like('kode_piutang', $prefix, 'after')
            ->orderBy('kode_piutang', 'DESC')
            ->first();

        if ($lastPiutang) {

            $lastNumber = intval(substr($lastPiutang->kode_piutang, -4));
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {

            $nextNumber = "0001";
        }

        $kode_piutang = $prefix . $nextNumber;


        $data = array(
            'tanggal' => $tanggal_peminjaman,
            'kode_piutang' => $kode_piutang,
            'jumlah_hutang' => $nilai_total,
            'sisa_hutang' => $nilai_total,
            'status' => 0,
            'pegawai_idpegawai' => $pegawai_idpegawai,
            'jatuh_tempo' => $jatuh_tempo,
            'bank_idbank' => $bank_idbank,
            'kirim_bank' => $jumlah_bank,
            'kirim_tunai' => $jumlah_tunai,
            'input_by' => session('ID_AKUN'),
            'unit_idunit' => session('ID_UNIT')
        );
        $this->PiutangModel->insert_Piutang($data);
        try {
            (new \App\Services\Finance\HutangPiutangService())->syncFromPiutangLegacy((int) $this->PiutangModel->insertID());
        } catch (\Throwable $e) {
            log_message('error', 'syncFromPiutangLegacy gagal: ' . $e->getMessage());
        }
        session()->setFlashData('sukses', 'Berhasil Tambah Data');
        return redirect()->to(base_url('piutang'));
    }


    //riwayat pembayaran piutang
    public function riwayat_pembayaran_piutang()
    {
        $data = array(
            'body' => 'piutang/riwayat_pembayaran_piutang',
            'pembayaran' => $this->PembayaranPiutangModel->getPembayaran()
        );

        return view('template', $data);
    }

    public function daftar_tagihan()
    {
        $data = array(
            'body' => 'piutang/daftar_tagihan',
            'piutang' => $this->PiutangModel->getPiutangStatus0(),
            'bank' => $this->BankModel->getBank()
        );
        return view('template', $data);
    }

    public function bayar_piutang()
    {

        $sisa = preg_replace('/[^0-9]/', '',  $this->request->getPost('sisa'));
        $bank_idbank = $this->request->getPost('bank_idbank');
        $bayar_bank = preg_replace('/[^0-9]/', '', $this->request->getPost('bayar_bank'));
        $bayar_tunai = preg_replace('/[^0-9]/', '', $this->request->getPost('bayar_tunai'));
        $jumlah_bayar = $bayar_bank + $bayar_tunai;
        $idpiutang = $this->request->getPost('idpiutang');
        $data = array(
            'idpiutang' => $idpiutang,
            'jumlah_bayar' => $jumlah_bayar,
            'sisa_hutang' => $sisa,
            'bank_idbank' => $bank_idbank,
            'bayar_tunai' => $bayar_tunai,
            'bayar_bank' => $bayar_bank,
            'input_by' => session('ID_AKUN')
        );

        $this->PembayaranPiutangModel->insert_Pembayaran($data);
        $piutangsebelumnya = $this->PiutangModel->getById($idpiutang);
        $bayarbankold = $piutangsebelumnya->kirim_bank;
        $bayartunaiold = $piutangsebelumnya->kirim_tunai;
        $statusnya = "";
        if ($sisa >= 0) {
            $statusnya = 0;
        } else {
            $statusnya = 1;
        }

        $data2 = array(
            'kirim_bank' => $bayarbankold + $bayar_bank,
            'kirim_tunai' => $bayar_tunai + $bayartunaiold,
            'sisa_hutang' => $sisa,
            'status' => $statusnya
        );
        $this->PiutangModel->update($idpiutang, $data2);
        try {
            (new \App\Services\Finance\HutangPiutangService())->syncFromPiutangLegacy((int) $idpiutang);
        } catch (\Throwable $e) {
            log_message('error', 'syncFromPiutangLegacy #' . $idpiutang . ' gagal: ' . $e->getMessage());
        }
        session()->setFlashData('sukses', 'Berhasil Update Piutang');
        return redirect()->to(base_url('daftar_piutang'));
    }




    public function export_riwayat_piutang()
    {

        $tanggalAwal = $this->request->getPost('tanggal_awal');
        $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $namaUnit = $this->request->getPost('nama_unit');

        $unit = $this->UnitModel->where('NAMA_UNIT', $namaUnit)->first();
        $id_unit = $unit ? $unit->idunit : null;
        $piutang = $this->PembayaranPiutangModel->getPembayaranFiltered($tanggalAwal, $tanggalAkhir, $id_unit);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Kode Piutang',
            'Tanggal',
            'Nama Unit',
            'Nama Pegawai',
            'Jatuh Tempo',
            'Jumlah Bayar',
            'Sisa Piutang',
            'Input Oleh'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');

        $row = 2;
        foreach ($piutang as $data) {
            $sheet->setCellValue('A' . $row, $data->kode_piutang);
            $sheet->setCellValue('b' . $row, date('d-m-Y', strtotime($data->tanggal)));
            $sheet->setCellValue('C' . $row, $data->NAMA_UNIT);
            $sheet->setCellValue('D' . $row, $data->nama_pegawai);
            $sheet->setCellValue('E' . $row, date('d-m-Y', strtotime($data->jatuh_tempo)));
            $sheet->setCellValue('F' . $row, $data->jumlah_bayar);
            $sheet->setCellValue('G' . $row, $data->sisa_hutang);
            $sheet->setCellValue('H' . $row, $data->nama_input);
            $row++;
        }

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle("A1:{$lastCol}" . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->freezePane('A2');

        $filename = 'riwayat_pembayaran_piutang_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }




    public function export_aging_piutang()
    {
        $tanggalAwal = $this->request->getPost('tanggal_awal');
        $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $namaUnit = $this->request->getPost('nama_unit');


        $piutang = $this->PiutangModel->getAgingPiutangFiltered($tanggalAwal, $tanggalAkhir, $namaUnit);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header kolom
        $headers = [
            'Kode Piutang',
            'Tanggal',
            'Nama Unit',
            'Nama Pegawai',
            'Jatuh Tempo',
            'Jumlah Hutang',
            'Sisa Hutang',
            '0-30 Hari',
            '31-60 Hari',
            '61-90 Hari',
            '>90 Hari',
            'Input Oleh'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $lastCol = chr(ord('A') + count($headers) - 1);

        // Style header
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCE6F1');

        // Isi data
        $row = 2;
        foreach ($piutang as $data) {
            $sheet->setCellValue('A' . $row, $data->kode_piutang);
            $sheet->setCellValue('B' . $row, date('d-m-Y', strtotime($data->tanggal)));
            $sheet->setCellValue('C' . $row, $data->nama_unit);
            $sheet->setCellValue('D' . $row, $data->nama_pegawai);
            $sheet->setCellValue('E' . $row, date('d-m-Y', strtotime($data->jatuh_tempo)));
            $sheet->setCellValue('F' . $row, $data->jumlah_hutang);
            $sheet->setCellValue('G' . $row, $data->sisa_hutang);
            $sheet->setCellValue('H' . $row, $data->{'0_30_hari'});
            $sheet->setCellValue('I' . $row, $data->{'31_60_hari'});
            $sheet->setCellValue('J' . $row, $data->{'61_90_hari'});
            $sheet->setCellValue('K' . $row, $data->lebih_90_hari);
            $sheet->setCellValue('L' . $row, $data->nama_input);
            $row++;
        }

        // Auto size kolom
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Border
        $sheet->getStyle("A1:{$lastCol}" . ($row - 1))
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->freezePane('A2');

        $filename = 'laporan_aging_piutang_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }


    public function export_daftar_piutang()
    {
        // Ambil data dari form
        $tanggalAwal = $this->request->getPost('tanggal_awal');
        $tanggalAkhir = $this->request->getPost('tanggal_akhir');
        $namaUnit = $this->request->getPost('nama_unit');


        $unit = $this->UnitModel->where('NAMA_UNIT', $namaUnit)->first();
        $idUnit = $unit ? $unit->idunit : null;

        // Panggil fungsi getPiutangStatus0Filtered dari model ModelPiutang

        $dataPiutang = $this->PiutangModel->getPiutangStatus0Filtered($tanggalAwal, $tanggalAkhir, $idUnit);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header kolom sesuai dengan data yang diminta
        $headers = [
            'Kode Piutang',
            'Tanggal',
            'Nama Unit',
            'Nama Pegawai',
            'Jatuh Tempo',
            'Sisa Piutang',
            'Input Oleh',
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
        foreach ($dataPiutang as $piutang) {
            $sheet->setCellValue('A' . $row, $piutang->kode_piutang);
            $sheet->setCellValue('B' . $row, date('d-m-Y', strtotime($piutang->tanggal)));
            $sheet->setCellValue('C' . $row, $piutang->NAMA_UNIT);
            $sheet->setCellValue('F' . $row, $piutang->pegawai_nama);
            $sheet->setCellValue('E' . $row, date('d-m-Y', strtotime($piutang->jatuh_tempo)));
            $sheet->setCellValue('F' . $row, $piutang->sisa_hutang);
            $sheet->setCellValue('G' . $row, $piutang->input_by_nama);
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
        $filename = 'daftar_piutang_' . date('Ymd_His') . '.xlsx';


        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
