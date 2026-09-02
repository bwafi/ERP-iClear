<?php

namespace App\Controllers;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKerusakan;
use App\Models\ModelKartuStok;
use App\Models\ModelPelanggan;
use App\Models\ModelService;
use App\Models\ModelServiceKerusakan;
use App\Models\ModelServiceSparepart;
use App\Models\ModelStokBarang;
use App\Models\ModelHppBarang;
use App\Models\ModelStokAwal;
use App\Models\ModelProsesService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\ModelJurnal;
use App\Models\ModelUnit;
use App\Models\ModelBank;
use App\Models\ModelDetailPenjualan;
use App\Models\ModelPembayaranBank;
use App\Models\ModelPenjualan;

class Riwayat_Service extends BaseController

{

    protected $AuthModel;
    protected $KerusakanModel;
    protected $KartuStokModel;
    protected $PelangganModel;
    protected $ServiceModel;
    protected $ServiceKerusakanModel;
    protected $ServiceSparepartModel;
    protected $StokBarangModel;
    protected $HppBarangModel;
    protected $StokAwalModel;
    protected $ProsesServiceModel;
    protected $JurnalModel;
    protected $UnitModel;
    protected $BankModel;
    protected $PembayaranBankModel;
    protected $PenjualanModel;
    protected $DetailPenjualanModel;



    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KerusakanModel = new ModelKerusakan();
        $this->KartuStokModel = new ModelKartuStok();
        $this->PelangganModel = new ModelPelanggan();
        $this->ServiceModel = new ModelService();
        $this->ServiceKerusakanModel = new ModelServiceKerusakan();
        $this->ServiceSparepartModel = new ModelServiceSparepart();
        $this->StokBarangModel = new ModelStokBarang();
        $this->HppBarangModel = new ModelHppBarang();
        $this->StokAwalModel = new ModelStokAwal();
        $this->ProsesServiceModel = new ModelProsesService();
        $this->JurnalModel = new ModelJurnal();
        $this->UnitModel = new ModelUnit();
        $this->BankModel = new ModelBank();
        $this->PembayaranBankModel = new ModelPembayaranBank();
        $this->PenjualanModel = new ModelPenjualan();
        $this->DetailPenjualanModel = new ModelDetailPenjualan();
    }

    public function index()
    {

        $data =  array(

            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => $this->ServiceModel->getRiwayatService(),
            'body'  => 'riwayat/service'
        );
        return view('template', $data);
    }

    public function index2()
    {

        $data =  array(

            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => $this->ServiceModel->getRiwayatServiceGaransi(),
            'body'  => 'riwayat/service_garansi'
        );
        return view('template', $data);
    }

    public function detail_service($idservice)
    {
        $unitId = session('ID_UNIT');
       
       $sparepart = $this->StokBarangModel
            ->where('id_unit', $unitId)
            ->where('stok_akhir >', 0)
            ->groupStart()
                ->like('kode_barang', 'sprt')
                ->orLike('kode_barang', 'acc')
            ->groupEnd()
            ->findAll();
            
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $oldkerusakan = $this->ServiceKerusakanModel->getSerModelServiceKerusakanByServiceId($idservice);
        $oldsparepart = $this->ServiceSparepartModel->getSerModelServiceSparepartByServiceId($idservice);
        $lama_garansi = $this->ServiceSparepartModel->getGaransiHariByServiceId($idservice);
        $teknisi = $this->AuthModel->getdataakun();
        $data =  array(
            'akun' => $akun,
            'teknisi' => $teknisi,
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'bank' => $this->BankModel->getBank(),
            'idservice' => $idservice,
            'pembayaran_lama' => $this->PembayaranBankModel->getByServiceBaru($idservice),
            'old_service_pelanggan' => $this->ServiceModel->getByIdWithPelanggan($idservice),
            'oldkerusakan' => $oldkerusakan,
            'oldsparepart' => $oldsparepart,
            'lama_garansi' => $lama_garansi ? (int)$lama_garansi->garansi_hari : null,
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'sparepart' => $sparepart,
            'body'  => 'riwayat/table/service'
        );
        return view('template', $data);
    }

    public function update_service_pelanggan()
    {
        $idservice = $this->request->getPost('idservice');
        $imei = $this->request->getPost('imei');
        $dp_bayar = $this->rupiahToInt($this->request->getPost('dp_bayar'));
        $tipe_passcode = $this->request->getPost('tipe_passcode');
        $passcode = $this->request->getPost('passcode');
        $tipe_hp = $this->request->getPost('tipe_hp');
        $email_icloud = $this->request->getPost('email_icloud');
        $password_icloud = $this->request->getPost('password_icloud');
        $keluhan = $this->request->getPost('keluhan');
        $keterangan = $this->request->getPost('keterangan');
        $estimasi_biaya = $this->request->getPost('estimasi_biaya');

        $data = array(
            'imei' => $imei,
            'tipe_hp' => $tipe_hp,
            'tipe_passcode' => $tipe_passcode,
            'passcode' => $passcode,
            'email_icloud' => $email_icloud,
            'password_icloud' => $password_icloud,
            'keluhan' => $keluhan,
            'keterangan' => $keterangan,
            'estimasi_biaya' => $estimasi_biaya,

        );
        $this->ServiceModel->updateService($idservice, $data);
        return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=kerusakan'))->with('success', 'Data kerusakan berhasil diperbarui.');
    }




    public function insert_kerusakan()
    {
        $fungsiTerpilih = $this->request->getPost('fungsi');
        $keteranganInput = $this->request->getPost('keterangan');
        $idservice = $this->request->getPost('idservice_k');

        if (empty($fungsiTerpilih)) {
            return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=sparepart'))->with('info', 'Tidak ada kerusakan yang dipilih.');
        }

        date_default_timezone_set('Asia/Jakarta');
        $now = date('Y-m-d H:i:s');

        // Ambil data kerusakan lama dari database
        $dataLama = $this->ServiceKerusakanModel->getSerModelServiceKerusakanByServiceId($idservice);
        $fungsiLama = []; // format: idfungsi => keterangan
        foreach ($dataLama as $item) {
            $fungsiLama[$item->fungsi_idfungsi] = $item->keterangan;
        }

        $fungsiTerpilihMap = array_flip($fungsiTerpilih); // untuk pencarian cepat

        // 1. Tambah atau update yang baru
        foreach ($fungsiTerpilih as $idfungsi) {
            $catatan = $keteranganInput[$idfungsi] ?? '';

            if (array_key_exists($idfungsi, $fungsiLama)) {
                // Cek apakah keterangan berubah
                if (trim($fungsiLama[$idfungsi]) !== trim($catatan)) {
                    $this->ServiceKerusakanModel->updateKeterangan($idservice, $idfungsi, $catatan);
                }
                unset($fungsiLama[$idfungsi]); // tidak akan dihapus
            } else {
                // Tambah baru
                $this->ServiceKerusakanModel->insert_SerModelServiceKerusakan([
                    'fungsi_idfungsi' => $idfungsi,
                    'keterangan' => $catatan,
                    'service_idservice' => $idservice,
                    'created_at' => $now,
                ]);
            }
        }

        // 2. Hapus yang sudah tidak dipilih
        foreach ($fungsiLama as $idfungsi => $keteranganLama) {
            $this->ServiceKerusakanModel->deleteByServiceAndFungsi($idservice, $idfungsi);
        }

        return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=sparepart'))->with('success', 'Data kerusakan berhasil diperbarui.');
    }


    public function insert_sparepart()
    {
        $produkData = $this->request->getPost('produk');
        $idservice = $this->request->getPost('idservice_s');

        $garansi = $this->request->getPost('garansi');

        if ($garansi === 'manual') {

            $garansi = $this->request->getPost('garansi_manual');
        }


        $garansi = (int) $garansi;
        $garansiupdate = array(
            'garansi_hari' => $garansi
        );
        $this->ServiceModel->updateService($idservice, $garansiupdate);


        $existingItems = $this->ServiceSparepartModel->getByServiceId($idservice);
        $existingMap = [];

        foreach ($existingItems as $item) {
            $existingMap[$item->barang_idbarang] = $item;
        }

        $submittedIds = [];

        if (!empty($produkData)) {
            foreach ($produkData as $produk) {
                $id     = $produk['id'];
                $jumlah = (int) $produk['jumlah'];
                $harga  = $this->rupiahToInt($produk['harga']);
                $diskon_item = $this->rupiahToInt($produk['diskon']);
                $total  = $this->rupiahToInt($produk['total']);
                $submittedIds[] = $id;

                $datahppbarang = $this->HppBarangModel->getById($id);
                $hpp = $datahppbarang->hpp ?? 0;

                $datastokawal = $this->StokAwalModel->getById($id);
                $satuan_terkecil = $datastokawal->satuan_terkecil ?? 'pcs';

                $datas = [
                    'jumlah' => $jumlah,
                    'harga_penjualan' => $harga,
                    'sub_total' => $total,
                    'hpp_penjualan' => $hpp,
                    'satuan_jual' => $satuan_terkecil,
                    'diskon_penjualan' => $diskon_item,
                    'service_idservice' => $idservice,
                    'barang_idbarang' => $id,
                    'unit_idunit' => session('ID_UNIT')
                ];

                if (array_key_exists($id, $existingMap)) {
                    // ID sudah ada → Update
                    $this->ServiceSparepartModel
                        ->updateByServiceAndBarang($idservice, $id, $datas);
                } else {
                    // ID belum ada → Insert
                    $this->ServiceSparepartModel
                        ->insert_SerModelServiceSparepart($datas);
                }
            }
        }

        $this->buatPenjualanDariService($idservice, $produkData);

        // Hapus data sparepart yang tidak lagi ada di form
        foreach ($existingMap as $barangId => $item) {
            if (!in_array($barangId, $submittedIds)) {
                $this->ServiceSparepartModel
                    ->deleteByServiceAndBarang($idservice, $barangId);
            }
        }
        return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=pembayaran'))->with('success', 'Data kerusakan berhasil diperbarui.');
    }

    private function buatPenjualanDariService($idservice, $produkData)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        // ambil data service
        $service = $this->ServiceModel->find($idservice);

        // generate invoice (copy dari insert_penjualan, tapi sederhana)
        $no_invoice = 'SRV' . date('YmdHis');

        $total = 0;
        foreach ($produkData as $p) {
            $harga = $this->rupiahToInt($p['harga']);
            $qty   = (int)$p['jumlah'];
            $diskon = $this->rupiahToInt($p['diskon']);

            $total += ($harga * $qty) - $diskon;
        }

        // insert penjualan (tanpa pembayaran dulu)
        $dataPenjualan = [
            'kode_invoice' => $no_invoice,
            'tanggal' => date('Y-m-d H:i:s'),
            'total_penjualan' => $total,
            'harus_dibayar' => $total,
            'bayar' => 0,
            'keterangan' => 'Belum Lunas',
            'unit_idunit' => session('ID_UNIT'),
            'id_pelanggan' => $service->id_pelanggan ?? null,
        ];

        $this->PenjualanModel->insert_Penjualan($dataPenjualan);
        $idPenjualan = $this->PenjualanModel->insertID();

        // insert detail + potong stok
        foreach ($produkData as $p) {
            $produkid = $p['id'];
            $qty = (int)$p['jumlah'];
            $harga = $this->rupiahToInt($p['harga']);
            $diskon = $this->rupiahToInt($p['diskon']);

            $subtotal = ($harga * $qty) - $diskon;

            $hpp = $this->HppBarangModel->getById($produkid);

            $this->DetailPenjualanModel->insert_detail([
                'barang_idbarang' => $produkid,
                'jumlah' => $qty,
                'harga_penjualan' => $harga,
                'sub_total' => $subtotal,
                'penjualan_idpenjualan' => $idPenjualan,
                'hpp_penjualan' => $hpp->hpp ?? 0,
                'unit_idunit' => session('ID_UNIT'),
            ]);
        }

        $db->transComplete();
    }

    public function insert_pembayaran()
    {

        //pembayaran
        $idservice = $this->request->getPost('idservice_p');
        $data_service = $this->ServiceModel->getServiceById($idservice);
        $service_by = $this->request->getPost('service_by_pembayaran');
        $diskon_pembayaran = $this->rupiahToInt($this->request->getPost('diskon_pembayaran'));


        $total_harga_pembayaran = $this->rupiahToInt($this->request->getPost('total_harga_pembayaran'));
        $status_service = $this->request->getPost('status_service_pembayaran');
        $service_by_pembayaran = $this->request->getPost('service_by_pembayaran');
        $bayar_pembayaran = $this->rupiahToInt($this->request->getPost('bayar_pembayaran')); //ini tunai
        $dp_bayar = $data_service->dp_bayar;

        $harus_dibayar = $total_harga_pembayaran  - $dp_bayar;

        $pembayaranbanklama = $this->PembayaranBankModel->getByServiceBaru($idservice);
        $jumlahbanklama = 0;
        foreach ($pembayaranbanklama as $item) {
            $jumlahbanklama += $item->jumlah;
        }
        $oldservicepelanggan = $this->ServiceModel->getServiceById($idservice);
        $tunailama = $oldservicepelanggan->bayar_tunai;
        $kodePembayaran = $oldservicepelanggan->bayar_bank;

        if (empty($kodePembayaran)) {

            $noService = $oldservicepelanggan->no_service;


            $angkaNoService = preg_replace('/\D/', '', $noService);


            $kodePembayaran = 'ksr' . $angkaNoService;
        }

        $bankData = $this->request->getPost('bank');
        $bankPembayaran = [];
        $totalBayarBank = 0;

        if (!empty($bankData) && is_array($bankData)) {
            foreach ($bankData as $b) {
                $jumlah = $this->sanitizeCurrency($b['jumlah'] ?? '0');
                if ($jumlah > 0) {
                    $bankPembayaran = array(
                        'kode_pembayaran' => $kodePembayaran,
                        'bank_idbank' => $b['id'],
                        'jumlah' => $jumlah,
                        'tabel_referensi' => 'service_baru',
                        'id_referensi' => $idservice
                    );

                    $this->PembayaranBankModel->insertPembayaranBank($bankPembayaran);
                    $totalBayarBank += $jumlah;
                }
            }
        }


        $total_bayar = $bayar_pembayaran + $totalBayarBank + $tunailama + $jumlahbanklama;







        $datap = array(

            'bayar_tunai' => $bayar_pembayaran,
            'total_service' => $total_harga_pembayaran,
            'total_diskon' => $diskon_pembayaran,
            'harus_dibayar' => $harus_dibayar,
            'bayar' => $total_bayar,
            'service_by' => $service_by_pembayaran,
        );
        $this->ServiceModel->updateService($idservice, $datap);

        session()->remove('idservice');
        session()->setFlashdata('sukses', 'Berhasil Menambahkan Data');
        return redirect()->to(base_url('/proses_service'));
    }


    function rupiahToInt($rupiah)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $rupiah);


        return (int) preg_replace('/[^0-9]/', '', $cleaned);
    }

    public function cetak_invoice($idservice)
    {
    helper('qr');

    $sparepart  = $this->ServiceModel->getSparepartWithBarang($idservice);
    $kerusakan  = $this->ServiceModel->getKerusakanWithFungsi($idservice);
    $human      = $this->ServiceModel->getServiceWithAkunAndPelanggan($idservice);
    $service    = $this->ServiceModel->getServiceById($idservice);

    $qrData = base_url('status_service/' . $idservice);
    $uniqueName = 'qr_' . md5($idservice . time()) . '.png';
    $qrImageUrl = generateQrToFile($qrData, $uniqueName);

    $data = [
        'sparepart'  => $sparepart,
        'service'    => $service,
        'kerusakan'  => $kerusakan,
        'human'      => $human,
        'qrImageUrl' => $qrImageUrl,
        'dataunit'   => $this->UnitModel->getById(session('ID_UNIT'))
    ];

    $mode = $this->request->getGet('mode');

    if ($mode === 'thermal') {
        $html = view('cetak/invoice_service_thermal', $data);

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => [80, 200],
            'margin_left'   => 2,
            'margin_right'  => 2,
            'margin_top'    => 2,
            'margin_bottom' => 2,
        ]);

    } else {
        $html = view('cetak/invoice_service', $data);

        $mpdf = new \Mpdf\Mpdf([
            'curlUserAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:108.0) Gecko/20100101 Firefox/108.0'
        ]);
    }

    error_reporting(0);
    ob_end_clean();
    $mpdf->curlAllowUnsafeSslRequests = true;

    $this->response->setHeader('Content-Type', 'application/pdf');
    $this->response->setHeader('Content-Transfer-Encoding', 'binary');
    $this->response->setHeader('Accept-Ranges', 'bytes');

    $mpdf->WriteHTML($html);
    $mpdf->Output();
    exit;
    }

    public function export()
    {

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $tanggal_awal  = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        // Ambil data dari model
        $dataservice = $this->ServiceModel->filterexport($tanggal_awal, $tanggal_akhir);

        // Header kolom
        $headers = [
            'A1' => 'No. Service',
            'B1' => 'Tanggal Masuk',
            'C1' => 'Nama Pelanggan',
            'D1' => 'Total Service',
            'E1' => 'Total DIskon',
            'F1' => 'Sub Total',
            'G1' => 'Total Bayar',
            'H1' => 'Nama Teknisi'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling header
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2EFDA'); // Warna hijau muda

        // Tulis data ke baris berikutnya
        $row = 2;
        foreach ($dataservice as $item) {
            $sheet->setCellValue('A' . $row, $item->no_service);
            $sheet->setCellValue('B' . $row, $item->created_at);
            $sheet->setCellValue('C' . $row, $item->nama_pelanggan);
            $sheet->setCellValue('D' . $row, $item->total_service);
            $sheet->setCellValue('E' . $row, $item->total_diskon);
            $sheet->setCellValue('F' . $row, $item->harus_dibayar);
            $sheet->setCellValue('G' . $row, $item->bayar);
            $sheet->setCellValue('H' . $row, $item->nama_service_by);
            $row++;
        }

        // Border seluruh tabel
        $sheet->getStyle('A1:H' . ($row - 1))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Auto-width untuk semua kolom
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format tanggal kolom B
        $sheet->getStyle('B2:B' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        // Freeze header
        $sheet->freezePane('A2');

        // Nama file
        $filename = 'Riwayat_Service_' . date('Ymd_His') . '.xlsx';

        // Header untuk response browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        // Output file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }


    public function export2()
    {

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        $tanggal_awal  = $this->request->getPost('tanggal_awal');
        $tanggal_akhir = $this->request->getPost('tanggal_akhir');

        // Ambil data dari model
        $dataservice = $this->ServiceModel->filterExportGaransi($tanggal_awal, $tanggal_akhir);

        // Header kolom
        $headers = [
            'A1' => 'No. Service',
            'B1' => 'Tanggal Masuk',
            'C1' => 'Tanggal Claim Garansi',
            'D1' => 'Nama Pelanggan',
            'E1' => 'Total Service',
            'F1' => 'Total DIskon',
            'G1' => 'Sub Total',
            'H1' => 'Total Bayar',
            'I1' => 'Nama Teknisi'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling header
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2EFDA'); // Warna hijau muda

        // Tulis data ke baris berikutnya
        $row = 2;
        foreach ($dataservice as $item) {
            $sheet->setCellValue('A' . $row, $item->no_service);
            $sheet->setCellValue('B' . $row, $item->created_at);
            $sheet->setCellValue('C' . $row, $item->tanggal_claim_garansi);
            $sheet->setCellValue('D' . $row, $item->nama_pelanggan);
            $sheet->setCellValue('E' . $row, $item->total_service_garansi);
            $sheet->setCellValue('F' . $row, $item->total_diskon_garansi);
            $sheet->setCellValue('G' . $row, $item->harus_dibayar_garansi);
            $sheet->setCellValue('H' . $row, $item->bayar_garansi);
            $sheet->setCellValue('I' . $row, $item->nama_service_by_garansi);
            $row++;
        }

        // Border seluruh tabel
        $sheet->getStyle('A1:I' . ($row - 1))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Auto-width untuk semua kolom
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Format tanggal kolom B
        $sheet->getStyle('B2:B' . ($row - 1))
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        // Freeze header
        $sheet->freezePane('A2');

        // Nama file
        $filename = 'Riwayat_Service_Garansi' . date('Ymd_His') . '.xlsx';

        // Header untuk response browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        // Output file
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }


    //service dibatalkan

    public function service_dibatalkan()
    {

        $data =  array(
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => $this->ServiceModel->ProsesServiceDibatalkan(),
            'body'  => 'riwayat/service_dibatalkan'
        );
        return view('template', $data);
    }


    //proses service

    public function proses_service()
    {

        $data =  array(
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => $this->ServiceModel->ProsesServiceAktif(),
            'body'  => 'riwayat/proses_service'
        );
        return view('template', $data);
    }

    public function togglePrioritas()
    {
        if ($this->request->isAJAX()) {
            $idservice = $this->request->getPost('idservice');
            $prioritas = $this->request->getPost('prioritas'); // 1 = prioritas, 0 = non-prioritas

            $this->ServiceModel->update($idservice, [
                'prioritas' => $prioritas
            ]);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => $prioritas == 1 ? 'Diprioritaskan' : 'Prioritas dihapus'
            ]);
        }
        return $this->response->setJSON(['status' => 'error']);
    }


    public function update_status_proses()
    {
        $status_proses = $this->request->getPost('status_proses');
        $idservice = $this->request->getPost('idservice');

        $data = array(
            'status_service' => 2,
            'status_proses' => $status_proses
        );
        $result = $this->ServiceModel->updateService($idservice, $data);

        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $data2 = array(
            'service_idservice' => $idservice,
            'status_statusproses' => $status_proses,
            'updated_at' => $wibTime->format('Y-m-d H:i:s'),
        );
        $result2 = $this->ProsesServiceModel->insertProses($data2);

        if ($result && $result2) {
            session()->setFlashdata('sukses', 'Berhasil Memperbarui Status');
            return redirect()->to(base_url('proses_service'));
        }
    }

    public function update_bisa_diambil()
    {

        $idservice = $this->request->getPost('idservice');
        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $data = array(
            'status_service' => 3,
            'tanggal_bisa_diambil' => $wibTime->format('Y-m-d H:i:s'),
        );
        $result = $this->ServiceModel->updateService($idservice, $data);

        if ($result) {
            session()->setFlashdata('sukses', 'Berhasil Memperbarui Status');
            return redirect()->to(base_url('proses_service'));
        }
    }


    //dibatalkan sudah diambil
    public function dibatalkan_sudah_diambil()
    {

        $idservice = $this->request->getPost('idservice');
        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $data = array(
            'status_service' => 91,
            'tanggal_selesai' => $wibTime->format('Y-m-d H:i:s'),
        );
        $result = $this->ServiceModel->updateService($idservice, $data);

        if ($result) {
            session()->setFlashdata('sukses', 'Berhasil Memperbarui Status');
            return redirect()->to(base_url('list/service/dibatalkan'));
        }
    }


    public function dibatalkan_aktifkan_lagi()
    {

        $idservice = $this->request->getPost('idservice');
        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $data = array(
            'status_service' => 2,
            'tanggal_bisa_diambil' => null,
        );
        $result = $this->ServiceModel->updateService($idservice, $data);

        if ($result) {
            session()->setFlashdata('sukses', 'Berhasil Memperbarui Status');
            return redirect()->to(base_url('list/service/dibatalkan'));
        }
    }


    public function update_dibatalkan()
    {

        $idservice = $this->request->getPost('idservice');
        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $data = array(
            'status_service' => 90,
            'tanggal_bisa_diambil' => $wibTime->format('Y-m-d H:i:s'),
        );
        $result = $this->ServiceModel->updateService($idservice, $data);

        if ($result) {
            session()->setFlashdata('sukses', 'Berhasil Memperbarui Status');
            return redirect()->to(base_url('proses_service'));
        }
    }

    //end proses service


    //service bisa diambll
    public function service_bisa_diambil()
    {
        $data =  array(
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => $this->ServiceModel->ServiceBisaDiambil(),
            'body'  => 'riwayat/bisa_diambil'
        );
        return view('template', $data);
    }

    public function update_sudah_diambil()
    {
        $idservice = $this->request->getPost('idservice');
        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));

        $dataservice = $this->ServiceModel->getServiceById($idservice);
        $bankData = $this->PembayaranBankModel->getByServiceBaru($idservice);
        $harus_dibayar = $dataservice->harus_dibayar;
        $dp_bayar = $dataservice->dp_bayar;
        $created_at = $dataservice->created_at;
        $tanggal_saja = date('Y-m-d', strtotime($created_at));
        $total_service = $dataservice->total_service;
        $tanggal = date('Y-m-d', strtotime($created_at));
        $data = [
            'status_service' => 4,
            'tanggal_selesai' => $wibTime->format('Y-m-d H:i:s'),
        ];

         $this->ServiceModel->updateService($idservice, $data);

        if (!empty($bankData) && is_array($bankData)) {
            foreach ($bankData as $b) {
                    $this->JurnalModel->insertJurnal($tanggal, 'pembayaran_service_' . $b->bank_idbank, [$b->jumlah], "Pembayaran Uang Lunas Jasa Service", $idservice, 'service');
            }
        }
        if ($dataservice->bayar_tunai > 0) {
            $this->JurnalModel->insertJurnal($tanggal, 'pembayaran_service_tunai', [$dataservice->bayar_tunai], "Pembayaran Uang Tunai Jasa Service", $idservice, 'service');
        }
        if ($dataservice->dp_bayar > 0) {
            $this->JurnalModel->insertJurnal($tanggal, 'pembayaran_service_penguranganDP', [$dataservice->dp_bayar], "Pembayaran Jasa Service", $idservice, 'service');
        }
        $this->JurnalModel->insertJurnal($tanggal, 'pembayaran_service', [$dataservice->total_service], "Pembayaran Jasa Service", $idservice, 'service');

         $datapbgaransi = array(
             'tabel_referensi' => 'service_garansi_0'
         );
         $this->PembayaranBankModel->updateByReferensi($idservice, $datapbgaransi);

        session()->setFlashdata('sukses', 'Berhasil Memperbarui Status');
        return redirect()->to(base_url('bisa_diambil'));
    }


    //end service bisa diambil

    // sudah diambil
    public function service_sudah_diambil()
    {
        $data =  array(
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => [],
            'body'  => 'riwayat/sudah_diambil'
        );
        return view('template', $data);
    }

    public function service_sudah_diambil_ajax()
    {
        $request = \Config\Services::request();
        
        $draw = $request->getPost('draw');
        $start = $request->getPost('start') ?? 0;
        $length = $request->getPost('length') ?? 10;
        $searchValue = $request->getPost('search')['value'] ?? '';
        $orderColumnIndex = $request->getPost('order')[0]['column'] ?? 2;
        $orderDir = $request->getPost('order')[0]['dir'] ?? 'desc';
        
        $startDate = $request->getPost('startDate') ?? '';
        $endDate = $request->getPost('endDate') ?? '';

        $result = $this->ServiceModel->ServiceSudahDiambilServerSide(
            $start,
            $length,
            $searchValue,
            $orderColumnIndex,
            $orderDir,
            $startDate,
            $endDate
        );

        $recordsTotal = $this->ServiceModel->ServiceSudahDiambilTotal();

        return $this->response->setJSON([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data']
        ]);
    }

    function sanitizeCurrency($value)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $value);
        return (float) $cleaned;
    }
    
    public function sparepart()
    {
        $db = \Config\Database::connect();

        $selected_unit = $this->request->getGet('unit') ?? 1;
        $selected_day  = $this->request->getGet('day');

        $builder = $db->table('detail_penjualan dp')
            ->select('
                p.kode_invoice,
                dp.iddetail_penjualan,
                dp.sub_total,
                dp.hpp_penjualan,
                b.nama_barang,
                p.tanggal,
                u.NAMA_UNIT
            ')
            ->join('barang b', 'b.idbarang = dp.barang_idbarang')
            ->join('penjualan p', 'p.idpenjualan = dp.penjualan_idpenjualan')
            ->join('unit u', 'u.idunit = p.unit_idunit')
            ->where('p.unit_idunit', $selected_unit)
            ->where('YEAR(p.tanggal)', date('Y'));

        // Jika tanggal dipilih
        if (!empty($selected_day)) {
            $builder->where('DATE(p.tanggal)', $selected_day);
        }

        $data_pen = $builder->get()->getResultArray();

        // Dropdown unit
        $list_unit = $db->table('unit')
            ->orderBy('NAMA_UNIT', 'ASC')
            ->get()
            ->getResultArray();

        $data = [
            'data_penjualan' => $data_pen,
            'list_unit'      => $list_unit,
            'selected_unit'  => $selected_unit,
            'selected_day'   => $selected_day,
            'body'           => 'riwayat/sparepart_keluar'
        ];

        return view('template', $data);
    }
}