<?php

namespace App\Controllers;


use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelKerusakan;
use App\Models\ModelStokBarang;
use App\Models\ModelPelanggan;
use App\Models\ModelService;
use App\Models\ModelServiceKerusakan;
use App\Models\ModelServiceSparepart;
use App\Models\ModelStokAwal;
use App\Models\ModelHppBarang;
use App\Models\ModelJurnal;
use App\Models\ModelBank;
use App\Models\ModelDetailPenjualan;
use App\Models\ModelPembayaranBank;
use App\Models\ModelPenjualan;
use App\Models\ModelUnit;
use App\Models\ModelKartuStok;

class Service extends BaseController

{

    protected $AuthModel;
    protected $KerusakanModel;
    protected $StokBarangModel;
    protected $PelangganModel;
    protected $ServiceModel;
    protected $ServiceKerusakanModel;
    protected $ServiceSparepartModel;
    protected $StokAwalModel;
    protected $HppBarangModel;
    protected $JurnalModel;
    protected $BankModel;
    protected $PembayaranBankModel;
    protected $PenjualanModel;
    protected $DetailPenjualanModel;
    protected $UnitModel;
    protected $KartuStokModel;



    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->KerusakanModel = new ModelKerusakan();
        $this->StokBarangModel = new ModelStokBarang();
        $this->PelangganModel = new ModelPelanggan();
        $this->ServiceModel = new ModelService();
        $this->ServiceKerusakanModel = new ModelServiceKerusakan();
        $this->ServiceSparepartModel = new ModelServiceSparepart();
        $this->StokAwalModel = new ModelStokAwal();
        $this->HppBarangModel = new ModelHppBarang();
        $this->JurnalModel = new ModelJurnal();
        $this->BankModel = new ModelBank();
        $this->PembayaranBankModel = new ModelPembayaranBank();
        $this->PenjualanModel = new ModelPenjualan();
        $this->DetailPenjualanModel = new ModelDetailPenjualan();
        $this->UnitModel = new ModelUnit();
        $this->KartuStokModel = new ModelKartuStok();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $teknisi = $this->AuthModel->getdataakun();
        $idservice = session('idservice') ?? null;

        $unitId = session('ID_UNIT');

        $oldkerusakan = $this->ServiceKerusakanModel->getSerModelServiceKerusakanByServiceId($idservice);
        $oldsparepart = $this->ServiceSparepartModel->getSerModelServiceSparepartByServiceId($idservice);
        $data =  array(
            'akun' => $akun,
            'teknisi' => $teknisi,
            'bank' => $this->BankModel->getBank(),
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'idservice' => $idservice,
            'old_service_pelanggan' => $this->ServiceModel->getByIdWithPelanggan($idservice),
            'oldkerusakan' => $oldkerusakan,
            'oldsparepart' => $oldsparepart,
            'unit' => $this->UnitModel->getUnit(),
            'body'  => 'transaksi/service'
        );
        return view('template', $data);
    }

    public function indexedit($idservice)
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $teknisi = $this->AuthModel->getdataakun();

        $oldkerusakan = $this->ServiceKerusakanModel->getSerModelServiceKerusakanByServiceId($idservice);
        $oldsparepart = $this->ServiceSparepartModel->getSerModelServiceSparepartByServiceId($idservice);
        $data =  array(
            'akun' => $akun,
            'teknisi' => $teknisi,
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'idservice' => $idservice,
            'old_service_pelanggan' => $this->ServiceModel->getByIdWithPelanggan($idservice),
            'oldkerusakan' => $oldkerusakan,
            'oldsparepart' => $oldsparepart,
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'sparepart' => $this->StokBarangModel->getSparepart(),
            'body'  => 'transaksi/service'
        );
        return view('template', $data);
    }




    public function insert_service()
    {
        $idservice = $this->request->getPost('idservice');

        if (!empty($idservice)) {
            session()->setFlashdata('gagal', 'Gagal! Data service sudah ada. Silakan selesaikan atau batalkan transaksi terlebih dahulu.');
            return redirect()->back();
        }

        if (session()->has('idservice') && !empty(session('idservice'))) {
            $existingId = session('idservice');
            $existingService = $this->ServiceModel->find($existingId);
            
            if ($existingService && in_array($existingService->status_service, [1, 2, 3])) {
                session()->setFlashdata('gagal', 'Gagal! Anda masih memiliki transaksi aktif (No: ' . $existingService->no_service . '). Selesaikan terlebih dahulu atau <a href="' . base_url('service/cancel/' . $existingId) . '">batalkan</a>.');
                return redirect()->back();
            } else {
                session()->remove('idservice');
            }
        }


        $idpelanggan  = $this->request->getPost('selectedidpelanggan');
        $no_hp = $this->request->getPost('no_hp');
        $imei = $this->request->getPost('imei');
        $dp_bayar = $this->rupiahToInt($this->request->getPost('dp_bayar'));
        $tipe_hp = $this->request->getPost('tipe_hp');
        $passcode = $this->request->getPost('passcode');
        $email_icloud = $this->request->getPost('email_icloud');
        $password_icloud = $this->request->getPost('password_icloud');
        $keluhan = $this->request->getPost('keluhan');
        $keterangan = $this->request->getPost('keterangan');

        $idunit = session('ID_UNIT');
        $idakun = session('ID_AKUN');

        date_default_timezone_set('Asia/Jakarta');
        $tanggal = date('Ymd');
        $tanggal_cek = date('Y-m-d');
        $created_at = date('Y-m-d H:i:s');


        // noservice
        $lastService = $this->ServiceModel
            ->where('unit_idunit', $idunit)
            ->like('DATE(created_at)', $tanggal_cek)
            ->orderBy('no_service', 'DESC')
            ->first();

        if ($lastService) {
            $lastKode = substr($lastService->no_service, -3);
            $newKode = str_pad((int)$lastKode + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newKode = '001';
        }


        $no_service =  'SRV' . $idunit . $tanggal . $newKode;
        // noservice

        $data = array(
            'no_service' => $no_service,
            'no_hp' => $no_hp,
            'imei' => $imei,
            'dp_bayar' => $dp_bayar,
            'keluhan' => $keluhan,
            'keterangan' => $keterangan,
            'tipe_hp' => $tipe_hp,
            'passcode' => $passcode,
            'email_icloud' => $email_icloud,
            'password_icloud' => $password_icloud,
            'pelanggan_id_pelanggan' => $idpelanggan,
            'unit_idunit' => $idunit,
            'service_by' => $idakun,
            'input_by' => $idakun,
            'created_at' => $created_at,
        );

        $result = $this->ServiceModel->insertService($data);


        if ($result) {
            $idservice = $this->ServiceModel->insertID();
            session()->set('idservice', $idservice);

            $ar_nilai[] = $dp_bayar;
            $ar_nilai[] = 0;
            $this->JurnalModel->insertJurnal($tanggal_cek, 'dp_service_tunai', $ar_nilai, "Pembayaran Uang Muka Jasa Service", $idservice, 'service');


            session()->setFlashdata('sukses', 'Berhasil Menambahkan Data');
            return redirect()->to('/service?tab=kerusakan')->with('success', 'Data kerusakan berhasil diperbarui.');
        }
    }


    public function insert_kerusakan()
    {
        $fungsiTerpilih = $this->request->getPost('fungsi');
        $keteranganInput = $this->request->getPost('keterangan');
        $idservice = $this->request->getPost('idservice_k');

        if (empty($fungsiTerpilih)) {
            return redirect()->to('/service?tab=kerusakan')->with('info', 'Tidak ada kerusakan yang dipilih.');
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

        return redirect()->to('cetak/invoice_service/' . $idservice)->with('success', 'Data kerusakan berhasil diperbarui.');
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
                    'harga_penjualan_garansi' => 0,
                    'sub_total' => $total,
                    'hpp_penjualan' => $hpp,
                    'satuan_jual' => $satuan_terkecil,
                    'diskon_penjualan' => $diskon_item,
                    'service_idservice' => $idservice,
                    'barang_idbarang' => $id,
                    'unit_idunit' => session('ID_UNIT'),
                    'diskon_penjualan_garansi' => 0,
                    'jumlah_tambahan_garansi' => 0,
                    'sub_total_garansi' => 0
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
        return redirect()->to('/service?tab=pembayaran')->with('success', 'Data kerusakan berhasil diperbarui.');
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
            'id_pelanggan' => $service->pelanggan_id_pelanggan ?? null,
            'service_idservice' => $idservice,
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
        $service_by = $this->request->getPost('service_by_pembayaran');
        $diskon_pembayaran = $this->rupiahToInt($this->request->getPost('diskon_pembayaran'));


        $total_harga_pembayaran = $this->rupiahToInt($this->request->getPost('total_harga_pembayaran'));
        $status_service = $this->request->getPost('status_service_pembayaran');
        $service_by_pembayaran = $this->request->getPost('service_by_pembayaran');
        $bayar_pembayaran = $this->rupiahToInt($this->request->getPost('bayar_pembayaran')); // ini tunai
        $idservice = $this->request->getPost('idservice_p');

        $kodePembayaran =  'ksr' . date('Ymd') . session('ID_UNIT') . rand(1000, 9999);
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


        $total_bayar = $bayar_pembayaran + $totalBayarBank;



        $datap = array(

            'total_service' => $total_harga_pembayaran,
            'total_diskon' => $diskon_pembayaran,
            'harus_dibayar' => $total_harga_pembayaran,
            'bayar' => $total_bayar,
            'total_service_garansi' => 0,
            'biaya_tambahan_garansi' => 0,
            'total_diskon_garansi' => 0,
            'harga_penjualan_garansi' => 0,
            'bayar_tunai' => $bayar_pembayaran,
            'bayar_bank' => $kodePembayaran
        );
        $this->ServiceModel->updateService($idservice, $datap);

        session()->remove('idservice');
        session()->setFlashdata('sukses', 'Berhasil Menambahkan Data Service');
        return redirect()->to(base_url('/proses_service'));
    }


    function rupiahToInt($rupiah)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $rupiah);


        return (int) preg_replace('/[^0-9]/', '', $cleaned);
    }

    public function search_sparepart_ajax()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['error' => 'Invalid request']);
        }

        $search = $this->request->getPost('search') ?? '';
        $unitId = session('ID_UNIT');

        $builder = $this->StokBarangModel;
        
        if (!empty($search)) {
            $builder->groupStart()
                ->like('nama_barang', $search)
                ->orLike('kode_barang', $search)
                ->orLike('warna', $search)
                ->groupEnd();
        }

        $sparepart = $builder
            ->where('id_unit', $unitId)
            ->where('stok_akhir >', 0)
            ->groupStart()
                ->like('kode_barang', 'sprt')
                ->orLike('kode_barang', 'acc')
            ->groupEnd()
            ->orderBy('nama_barang', 'ASC')
            ->limit(50)
            ->findAll();

        return $this->response->setJSON($sparepart);
    }

    public function search_pelanggan_ajax()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['error' => 'Invalid request']);
        }

        $search = $this->request->getPost('search') ?? '';

        $builder = $this->PelangganModel;
        
        if (!empty($search)) {
            $builder->groupStart()
                ->like('nama', $search)
                ->orLike('no_hp', $search)
                ->groupEnd()
                ->orderBy('nama', 'ASC');
        } else {
            $builder->orderBy('id_pelanggan', 'DESC');
        }

        $pelanggan = $builder
            ->limit(50)
            ->findAll();

        return $this->response->setJSON($pelanggan);
    }

    public function cancel_service($idservice)
    {
        $service = $this->ServiceModel->find($idservice);
        
        if (!$service) {
            session()->setFlashdata('gagal', 'Service tidak ditemukan');
            return redirect()->to(base_url('service'));
        }

        if ($service->status_service >= 4) {
            session()->setFlashdata('gagal', 'Service sudah selesai, tidak dapat dibatalkan');
            return redirect()->to(base_url('service'));
        }

        $this->ServiceModel->delete($idservice);
        
        $this->ServiceKerusakanModel->where('service_idservice', $idservice)->delete();
        $this->ServiceSparepartModel->where('service_idservice', $idservice)->delete();
        
        session()->remove('idservice');
        session()->setFlashdata('sukses', 'Transaksi berhasil dibatalkan');
        
        return redirect()->to(base_url('service'));
    }

    public function clear_session()
    {
        session()->remove('idservice');
        session()->setFlashdata('sukses', 'Session berhasil dihapus');
        return redirect()->to(base_url('service'));
    }

    function sanitizeCurrency($value)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $value);
        return (float) $cleaned;
    }
}
