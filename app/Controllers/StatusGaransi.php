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
use App\Models\ModelBank;
use App\Models\ModelPembayaranBank;
use App\Models\ModelRiwayatKlaimGaransi;
use App\Models\ModelRegion;



class StatusGaransi extends BaseController

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
    protected $BankModel;
    protected $PembayaranBankModel;
    protected $RiwayatKlaimGaransiModel;
    protected $RegionModel;



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
        $this->BankModel = new ModelBank();
        $this->PembayaranBankModel = new ModelPembayaranBank();
        $this->RiwayatKlaimGaransiModel = new ModelRiwayatKlaimGaransi();
        $this->RegionModel = new ModelRegion();
    }

    public function index()
    {
        $service = $this->ServiceModel->getRiwayatService();
        $riwayatKlaim = [];
        foreach ($service as $s) {
            $riwayatKlaim[$s->idservice] = $this->RiwayatKlaimGaransiModel->getByService($s->idservice);
        }
        $data =  array(
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'riwayatKlaim' => $riwayatKlaim,
            'service' => $this->ServiceModel->getRiwayatService(),
            'body'  => 'riwayat/garansi_service'
        );
        return view('template', $data);
    }


    public function getRiwayatKlaim($idservice)
    {
        $riwayat = $this->RiwayatKlaimGaransiModel->getByService($idservice);

        if (empty($riwayat)) {
            return $this->response->setJSON([
                'html' => '<div class="alert alert-info">Belum ada klaim garansi untuk service ini.</div>'
            ]);
        }

        $html = '<table class="table table-sm table-bordered">
                <thead><tr><th>#</th><th>Tanggal Klaim</th></tr></thead><tbody>';

        foreach ($riwayat as $i => $row) {
            $html .= '<tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . date('d-m-Y', strtotime($row->tanggal_claim)) . '</td>
                </tr>';
        }

        $html .= '</tbody></table>';

        return $this->response->setJSON(['html' => $html]);
    }


    public function claim_garansi()
    {
        $idservice = $this->request->getPost('idservice');
        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $data = array(
            'status_service' => 2,
            'tanggal_bisa_diambil' => null,
            'tanggal_selesai' =>  null,
            'status_proses' => 1,
            'tanggal_claim_garansi' => $wibTime->format('Y-m-d H:i:s'),
        );
        $this->ServiceModel->updateService($idservice, $data);
        $this->ProsesServiceModel->deleteByServiceId($idservice);

        $data2 = array(
            'service_idservice' => $idservice,
            'tanggal_claim' => $wibTime->format('Y-m-d H:i:s'),
        );
        $this->RiwayatKlaimGaransiModel->insertRiwayat($data2);

        session()->setFlashData('sukses', 'Berhasil claim garansi service');
        return redirect()->to(base_url('garansi_service'));
    }

    public function service_by_garansi($idservice)
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $oldkerusakan = $this->ServiceKerusakanModel->getSerModelServiceKerusakanByServiceId($idservice);
        $oldsparepart = $this->ServiceSparepartModel->getSerModelServiceSparepartByServiceId($idservice);
        $lama_garansi = $this->ServiceSparepartModel->getGaransiHariByServiceId($idservice);
        $teknisi = $this->AuthModel->getdataakun();
        $data =  array(
            'akun' => $akun,
            'teknisi' => $teknisi,
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'idservice' => $idservice,
            'old_service_pelanggan' => $this->ServiceModel->getByIdWithPelanggan($idservice),
            'oldkerusakan' => $oldkerusakan,
            'pembayaran_lama' => $this->PembayaranBankModel->getByServiceGaransi($idservice),
            'oldsparepart' => $oldsparepart,
            'bank' => $this->BankModel->getBank(),
            'lama_garansi' => $lama_garansi ? (int)$lama_garansi->garansi_hari : null,
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'sparepart' => $this->StokBarangModel->getSparepart(),
            'provinsi' => $this->RegionModel->getProvinces(),
            'body'  => 'transaksi/table_garansi/service'
        );
        return view('template', $data);
    }

    public function update_service_pelanggan_garansi()
    {
        $idservice = $this->request->getPost('idservice');
        $imei = $this->request->getPost('imei');
        // $dp_bayar = $this->rupiahToInt($this->request->getPost('dp_bayar'));
        $tipe_passcode = $this->request->getPost('tipe_passcode');
        $passcode = $this->request->getPost('passcode');
        $email_icloud = $this->request->getPost('email_icloud');
        $password_icloud = $this->request->getPost('password_icloud');
        $keluhan = $this->request->getPost('keluhan');
        $keterangan = $this->request->getPost('keterangan');
        $estimasi_biaya = $this->request->getPost('estimasi_biaya');

        $data = array(
            'imei' => $imei,
            // 'dp_bayar' => $dp_bayar,
            'tipe_passcode' => $tipe_passcode,
            'passcode' => $passcode,
            'email_icloud' => $email_icloud,
            'password_icloud' => $password_icloud,
            'keluhan' => $keluhan,
            'keterangan' => $keterangan,
            'estimasi_biaya' => $estimasi_biaya,

        );
        $this->ServiceModel->updateService($idservice, $data);
        return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=kerusakan'))->with('success', 'Data kerusakan berhasil diperbarui.');
    }


    public function insert_kerusakan_garansi()
    {
        $fungsiTerpilih = $this->request->getPost('fungsi');
        $keteranganInput = $this->request->getPost('keterangan');
        $idservice = $this->request->getPost('idservice_k');

        if (empty($fungsiTerpilih)) {
            return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=sparepart'))->with('info', 'Tidak ada kerusakan yang dipilih.');
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

        return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=sparepart'))->with('success', 'Data kerusakan berhasil diperbarui.');
    }


    public function insert_sparepart_garansi()
    {
        $produkData = $this->request->getPost('produk');
        $idservice = $this->request->getPost('idservice_s');


        $existingItems = $this->ServiceSparepartModel->getByServiceId($idservice);
        $existingMap = [];

        foreach ($existingItems as $item) {
            $existingMap[$item->barang_idbarang] = $item;
        }

        $submittedIds = [];

        if (!empty($produkData)) {
            foreach ($produkData as $produk) {
                $id     = $produk['id'];
                $jumlahAcuan = (int) $produk['jumlah'];
                $jumlah_tambahan_garansi = (int) $produk['qty_tambahan'];
                $jumlah_akhir = $jumlahAcuan + $jumlah_tambahan_garansi;



                $harga  = $this->rupiahToInt($produk['harga']);
                $diskon_item_garansi = $this->rupiahToInt($produk['diskon_penjualan_garansi']);
                $diskon_acuan = $this->rupiahToInt($produk['diskon_acuan']);
                $diskon_akhir = $diskon_acuan + $diskon_item_garansi;




                $sub_total_acuan = $this->rupiahToInt($produk['sub_total_acuan']);
                $total  = $this->rupiahToInt($produk['total']);
                $sub_total_akhir = $sub_total_acuan + $total;

                $submittedIds[] = $id;

                $datahppbarang = $this->HppBarangModel->getById($id);
                $hpp = $datahppbarang->hpp ?? 0;

                $datastokawal = $this->StokAwalModel->getById($id);
                $satuan_terkecil = $datastokawal->satuan_terkecil ?? 'pcs';
                $biaya_tambahan_garansi = $this->rupiahToInt($this->request->getPost('biaya_garansi'));

                $datas = [
                    'jumlah_tambahan_garansi' => $jumlah_tambahan_garansi,
                    'harga_penjualan_garansi' => $harga,
                    'sub_total_garansi' => $total,
                    'hpp_penjualan_garansi' => $hpp,
                    'satuan_jual' => $satuan_terkecil,
                    'diskon_penjualan_garansi' => $diskon_item_garansi,
                    'service_idservice' => $idservice,
                    'barang_idbarang' => $id,
                    'unit_idunit' => session('ID_UNIT')
                ];

                $chace_d = array(
                    'biaya_tambahan_garansi' => $biaya_tambahan_garansi,
                );

                if (array_key_exists($id, $existingMap)) {
                    // ID sudah ada → Update
                    $this->ServiceSparepartModel
                        ->updateByServiceAndBarang($idservice, $id, $datas);
                    $this->ServiceModel->updateService($idservice, $chace_d);
                } else {
                    // ID belum ada → Insert
                    $this->ServiceSparepartModel
                        ->insert_SerModelServiceSparepart($datas);
                }
            }
        }

        // Hapus data sparepart yang tidak lagi ada di form
        foreach ($existingMap as $barangId => $item) {
            if (!in_array($barangId, $submittedIds)) {
                $this->ServiceSparepartModel
                    ->deleteByServiceAndBarang($idservice, $barangId);
            }
        }
        return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=pembayaran'))->with('success', 'Data kerusakan berhasil diperbarui.');
    }


    public function insert_pembayaran_garansi()
    {

        //pembayaran
        $idservice = $this->request->getPost('idservice_p');
        $data_service = $this->ServiceModel->getServiceById($idservice);

        $service_by = $this->request->getPost('service_by_pembayaran');

        $diskon_pembayaran_garansi = $this->rupiahToInt($this->request->getPost('diskon_pembayaran_garansi'));
        $diskon_acuan = $this->rupiahToInt($this->request->getPost('total_diskon_acuan'));
        $diskon_akhir = $diskon_acuan + $diskon_pembayaran_garansi;



        $total_harga_pembayaran_akhir = $this->rupiahToInt($this->request->getPost('total_harga_pembayaran_akhir'));
        $sub_total_acuan = $this->rupiahToInt($this->request->getPost('sub_total_acuan'));
        $sub_total_akhir = $sub_total_acuan + $total_harga_pembayaran_akhir;

        $status_service = $this->request->getPost('status_service_pembayaran');

        $bayar_pembayaran = $this->rupiahToInt($this->request->getPost('bayar_pembayaran')); //inii tunai

        $biaya_garansi_pembayaran = $this->rupiahToInt($this->request->getPost('biaya_garansi_pembayaran'));

        $harus_dibayar_garansi = $total_harga_pembayaran_akhir;
        $oldservicepelanggan = $this->ServiceModel->getServiceById($idservice);
        $biaya_tambahan_garansi_lama = $oldservicepelanggan->biaya_tambahan_garansi;
        $biaya_tambahan_garansi_akhir = $biaya_garansi_pembayaran + $biaya_tambahan_garansi_lama;
        $total_service_garansi_lama = $oldservicepelanggan->total_service_garansi;
        $total_service_garansi_akhir = $total_harga_pembayaran_akhir + $total_service_garansi_lama;
        $total_diskon_garansi_lama = $oldservicepelanggan->total_diskon_garansi;
        $total_diskon_garansi_akhir = $diskon_pembayaran_garansi + $total_diskon_garansi_lama;

        $tunailama = $oldservicepelanggan->bayar_tunai_garansi;
        $pembayaranbanklama = $this->PembayaranBankModel->getByServiceGaransi($idservice);
        $jumlahbanklama = 0;
        foreach ($pembayaranbanklama as $item) {
            $jumlahbanklama += $item->jumlah;
        }

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
                        'tabel_referensi' => 'service_garansi_1',
                        'id_referensi' => $idservice
                    );

                    $this->PembayaranBankModel->insertPembayaranBank($bankPembayaran);
                    $totalBayarBank += $jumlah;
                }
            }
        }

        $total_bayar_akhir = $bayar_pembayaran + $totalBayarBank + $tunailama + $jumlahbanklama;
        $tunaitotal = $bayar_pembayaran + $tunailama;



        $datap = array(

            'service_by_garansi' => $service_by,
            'total_service_garansi' => $total_service_garansi_akhir,
            'bayar_garansi' => $total_bayar_akhir,
            'harus_dibayar_garansi' => $harus_dibayar_garansi,
            'bayar_tunai_garansi' => $tunaitotal,
        );

        $this->ServiceModel->updateService($idservice, $datap);

        session()->remove('idservice');
        session()->setFlashdata('sukses', 'Berhasil Menambahkan Data');
        return redirect()->to(base_url('/proses_service'));
    }


    function sanitizeCurrency($value)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $value);
        return (float) $cleaned;
    }

    function rupiahToInt($rupiah)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $rupiah);


        return (int) preg_replace('/[^0-9]/', '', $cleaned);
    }
}
