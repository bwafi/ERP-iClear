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
use App\Models\ModelRegion;
use App\Services\Service\ServiceDpService;
use App\Services\Service\ServiceHppSnapshot;
use App\Services\Service\ServicePriceSnapshot;
use App\Services\Service\ServiceSparepartPenjualanSyncService;
use App\Services\Inventory\ProductInventoryScope;

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
    protected $RegionModel;
    protected $ServiceDpService;



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
        $this->RegionModel = new ModelRegion();
        $this->ServiceDpService = new ServiceDpService();
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
            'dp_bank' => $this->BankModel->getBankDalamScopeUnit((int) $unitId),
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'idservice' => $idservice,
            'old_service_pelanggan' => $this->ServiceModel->getByIdWithPelanggan($idservice),
            'oldkerusakan' => $oldkerusakan,
            'oldsparepart' => $oldsparepart,
            'unit' => $this->UnitModel->getUnit(),
            'provinsi' => $this->RegionModel->getProvinces(),
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
            'provinsi' => $this->RegionModel->getProvinces(),
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
        $dp_raw = $this->request->getPost('dp_bayar');
        if (is_array($dp_raw)) {
            $dp_raw = null;
        }
        $dp_raw = (string) $dp_raw;
        if (strpos($dp_raw, '-') !== false) {
            session()->setFlashdata('gagal', 'Nominal DP tidak boleh negatif.');
            return redirect()->back();
        }
        $dp_bayar = $this->rupiahToInt($dp_raw);
        $tipe_hp = $this->request->getPost('tipe_hp');
        $passcode = $this->request->getPost('passcode');
        $email_icloud = $this->request->getPost('email_icloud');
        $password_icloud = $this->request->getPost('password_icloud');
        $keluhan = $this->request->getPost('keluhan');
        $keterangan = $this->request->getPost('keterangan');

        $domisili_provinsi = $this->request->getPost('domisili_provinsi');
        $domisili_kabupaten = $this->request->getPost('domisili_kabupaten');
        $domisili_kecamatan = $this->request->getPost('domisili_kecamatan');

        if (empty($idpelanggan) || !$this->PelangganModel->getById($idpelanggan)) {
            session()->setFlashdata('gagal', 'Gagal! Silakan pilih atau buat pelanggan terlebih dahulu sebelum menyimpan service.');
            return redirect()->back();
        }

        $idunit = (int) session('ID_UNIT');
        $idakun = (int) session('ID_AKUN');

        if ($dp_bayar > 0 && $idunit <= 0) {
            session()->setFlashdata('gagal', 'Unit transaksi tidak valid untuk pencatatan DP.');
            return redirect()->back();
        }

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
            'dp_bayar' => 0,
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

        $metode_dp = strtolower(trim((string) ($this->request->getPost('metode_bayar') ?? 'tunai')));
        $metode_dp = $metode_dp === '' ? 'tunai' : $metode_dp;
        $bank_dp = $this->request->getPost('bank_idbank');
        if (is_array($bank_dp)) {
            $bank_dp = null;
        }
        $referensi_dp = $this->request->getPost('referensi_dp');
        if (is_array($referensi_dp)) {
            $referensi_dp = null;
        }

        $db = Database::connect();
        $db->transStart();

        try {
            if (! $this->ServiceModel->insertService($data)) {
                throw new \RuntimeException('Gagal menyimpan data service.');
            }

            $idservice = (int) $this->ServiceModel->insertID();
            if ($idservice <= 0) {
                throw new \RuntimeException('ID service tidak valid setelah penyimpanan.');
            }

            if (!empty($idpelanggan)) {
                $this->PelangganModel->update($idpelanggan, [
                    'provinsi' => $domisili_provinsi,
                    'kabupaten' => $domisili_kabupaten,
                    'kecamatan' => $domisili_kecamatan,
                ]);
            }

            // DP > 0 dicatat ke ledger `service_dp` + jurnal DP. DP 0 dilewati
            // (tidak ada baris ledger maupun jurnal). `kode_dp` dibuat stabil
            // dari no_service sehingga ulangan request (no_service sama karena
            // counter belum naik) memicu unique constraint / status duplikat
            // dan dibatalkan seluruhnya oleh pemeriksaan di bawah.
            $hasilDp = $this->ServiceDpService->catatDp([
                'service_idservice' => $idservice,
                'jumlah'            => $dp_bayar,
                'metode'            => $metode_dp,
                'bank_idbank'       => $bank_dp,
                'referensi'         => $referensi_dp,
                'kode_dp'           => 'DP' . $no_service,
                'tanggal'           => $tanggal_cek,
                'keterangan'        => 'Pembayaran Uang Muka Jasa Service',
                'unit_id'           => $idunit,
                'input_by'          => $idakun,
            ]);

            if ($dp_bayar > 0 && ($hasilDp['status'] ?? null) !== ServiceDpService::STATUS_OK) {
                throw new \RuntimeException('Pencatatan DP tidak menghasilkan ledger (status: ' . ($hasilDp['status'] ?? 'none') . '). Pengiriman duplikat dibatalkan seluruhnya.');
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Terjadi kegagalan saat menyimpan transaksi.');
            }

            if (! $db->transComplete()) {
                throw new \RuntimeException('Transaksi service gagal diselesaikan.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Service::insert_service gagal: ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal menambahkan data service: ' . $e->getMessage());
            return redirect()->back();
        }

        session()->set('idservice', $idservice);
        session()->setFlashdata('sukses', 'Berhasil Menambahkan Data');
        return redirect()->to('/service?tab=kerusakan')->with('sukses', 'Data kerusakan berhasil diperbarui.');
    }


    public function insert_kerusakan()
    {
        $fungsiTerpilih = $this->request->getPost('fungsi');
        $keteranganInput = $this->request->getPost('keterangan');
        $idservice = $this->request->getPost('idservice_k');

        if (empty($fungsiTerpilih)) {
            return redirect()->to('/service?tab=kerusakan')->with('gagal', 'Pilih minimal satu jenis kerusakan sebelum melanjutkan.');
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

        return redirect()->to(base_url('cetak/invoice_service/' . $idservice))
            ->with('sukses', 'Data kerusakan berhasil disimpan.');
    }


    public function insert_sparepart()
    {
        $idservice = (int) $this->request->getPost('idservice_s');
        $produkData = $this->request->getPost('produk');
        $produkData = is_array($produkData) ? $produkData : [];
        $garansi = $this->request->getPost('garansi');
        if ($garansi === 'manual') {
            $garansi = $this->request->getPost('garansi_manual');
        }

        if ($idservice <= 0) {
            return redirect()->to('/service?tab=sparepart')->with('gagal', 'ID service tidak valid. Sparepart tidak disimpan.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $service = $db->table('service')->where('idservice', $idservice)->get()->getRow();
            if (! $service) {
                throw new \RuntimeException('Data service tidak ditemukan.');
            }

            $existingItems = $this->ServiceSparepartModel->getByServiceId($idservice);
            (new ServiceSparepartPenjualanSyncService($db))->hapusTransaksiLama(
                $idservice,
                $existingItems,
                (int) ($service->unit_idunit ?? session('ID_UNIT'))
            );

            if (! $this->ServiceModel->updateService($idservice, [
                'garansi_hari' => (int) $garansi,
            ])) {
                throw new \RuntimeException('Gagal memperbarui garansi service.');
            }

            $existingMap = [];
            foreach ($existingItems as $item) {
                $existingMap[$item->barang_idbarang] = $item;
            }

            $submittedIds = [];
            $hppByBarangId = [];
            foreach ($produkData as &$produk) {
                $id = (int) ($produk['id'] ?? 0);
                if ($id <= 0 || in_array($id, $submittedIds, true)) {
                    throw new \RuntimeException('Data sparepart tidak valid.');
                }
                $jumlah = (int) ($produk['jumlah'] ?? 0);
                $barang = $db->table('barang')->where('idbarang', $id)->where('deleted', 0)->get()->getRow();
                if ($jumlah <= 0 || ! $barang
                    || ! ProductInventoryScope::isPhysicalProduct((string) $barang->nama_barang, $barang->jenis_item ?? null)) {
                    throw new \RuntimeException('Sparepart harus barang fisik aktif dengan jumlah positif.');
                }
                $diskonItem = $this->rupiahToInt($produk['diskon'] ?? '0');
                $priceSnapshot = ServicePriceSnapshot::calculate(
                    isset($existingMap[$id]) ? (int) $existingMap[$id]->harga_penjualan : null,
                    (int) $barang->harga,
                    $jumlah,
                    $diskonItem
                );
                $harga = $priceSnapshot['price'];
                $total = $priceSnapshot['subtotal'];
                $produk['harga'] = $harga;
                $produk['diskon'] = $diskonItem;
                $produk['total'] = $total;
                $submittedIds[] = $id;

                $dataHppBarang = array_key_exists($id, $existingMap)
                    ? null
                    : $this->HppBarangModel->getById($id);
                $currentHpp = array_key_exists($id, $existingMap)
                    ? 0
                    : ($this->HppBarangModel->currentUnitCost($id, (int) ($service->unit_idunit ?? session('ID_UNIT')))
                        ?? ($dataHppBarang->hpp ?? 0));
                $hppByBarangId[$id] = ServiceHppSnapshot::resolve(
                    $existingMap[$id] ?? null,
                    $currentHpp
                );
                $stokAwal = $this->StokAwalModel->getByIdBarang($id);
                $data = [
                    'jumlah' => $jumlah,
                    'harga_penjualan' => $harga,
                    'harga_penjualan_garansi' => 0,
                    'sub_total' => $total,
                    'hpp_penjualan' => $hppByBarangId[$id],
                    'satuan_jual' => $stokAwal->satuan_terkecil ?? 'pcs',
                    'diskon_penjualan' => $diskonItem,
                    'service_idservice' => $idservice,
                    'barang_idbarang' => $id,
                    'unit_idunit' => (int) ($service->unit_idunit ?? session('ID_UNIT')),
                    'diskon_penjualan_garansi' => 0,
                    'jumlah_tambahan_garansi' => 0,
                    'sub_total_garansi' => 0,
                ];

                $saved = array_key_exists($id, $existingMap)
                    ? $this->ServiceSparepartModel->updateByServiceAndBarang($idservice, $id, $data)
                    : $this->ServiceSparepartModel->insert_SerModelServiceSparepart($data);
                if (! $saved) {
                    throw new \RuntimeException('Gagal menyimpan detail sparepart service.');
                }
            }
            unset($produk);

            foreach ($existingMap as $barangId => $item) {
                if (! in_array((int) $barangId, $submittedIds, true)
                    && ! $this->ServiceSparepartModel->deleteByServiceAndBarang($idservice, $barangId)) {
                    throw new \RuntimeException('Gagal menghapus sparepart yang dilepas dari service.');
                }
            }

            $this->buatPenjualanDariService($idservice, $produkData, $hppByBarangId);

            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Sinkronisasi sparepart keluar gagal disimpan.');
            }

            return redirect()->to('/service?tab=pembayaran')->with('sukses', 'Sparepart service dan sparepart keluar berhasil disinkronkan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Service::insert_sparepart gagal untuk service ' . $idservice . ': ' . $e->getMessage());
            return redirect()->to('/service?tab=sparepart')->with('gagal', 'Sparepart tidak tersimpan: ' . $e->getMessage());
        }
    }

    private function buatPenjualanDariService($idservice, $produkData, $hppByBarangId)
    {
        if (empty($produkData)) {
            return;
        }

        // ambil data service
        $service = $this->ServiceModel->find($idservice);
        if (! $service) {
            throw new \RuntimeException('Data service tidak ditemukan saat membuat invoice sparepart.');
        }

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

        if (! $this->PenjualanModel->insert_Penjualan($dataPenjualan)) {
            throw new \RuntimeException('Gagal membuat invoice sparepart service.');
        }
        $idPenjualan = $this->PenjualanModel->insertID();
        if ((int) $idPenjualan <= 0) {
            throw new \RuntimeException('ID invoice sparepart service tidak valid.');
        }

        // insert detail + potong stok
        $inventoryBridge = new \App\Services\Inventory\InventoryLedgerBridge(\Config\Database::connect());
        foreach ($produkData as $p) {
            $produkid = $p['id'];
            $qty = (int)$p['jumlah'];
            $harga = $this->rupiahToInt($p['harga']);
            $diskon = $this->rupiahToInt($p['diskon']);

            $subtotal = ($harga * $qty) - $diskon;

            if (! $this->DetailPenjualanModel->insert_detail([
                'barang_idbarang' => $produkid,
                'jumlah' => $qty,
                'harga_penjualan' => $harga,
                'sub_total' => $subtotal,
                'penjualan_idpenjualan' => $idPenjualan,
                'hpp_penjualan' => $hppByBarangId[$produkid],
                'unit_idunit' => session('ID_UNIT'),
            ])) {
                throw new \RuntimeException('Gagal menyimpan detail invoice sparepart service.');
            }
            if ($inventoryBridge->isTracked((int) $produkid, (int) session('ID_UNIT'))) {
                $inventoryBridge->outgoing((int) $produkid, (int) session('ID_UNIT'),
                    'detail_penjualan', (string) $this->DetailPenjualanModel->insertID(), 'sale', $qty);
            }
        }
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
        $idservice = (int) $this->request->getPost('idservice_p');
        $no_service = (string) ($this->request->getPost('no_service_pembayaran') ?? '');

        // DP (dipindah dari tahap pelanggan ke tahap pembayaran)
        $dp_bayar = $this->rupiahToInt((string) ($this->request->getPost('dp_bayar_pembayaran') ?? '0'));
        $metode_dp = strtolower(trim((string) ($this->request->getPost('dp_metode_bayar') ?? '')));
        $metode_dp = $metode_dp === '' ? 'tunai' : $metode_dp;
        $bank_dp = $this->request->getPost('dp_bank_idbank');
        if (is_array($bank_dp)) { $bank_dp = null; }
        $referensi_dp = $this->request->getPost('dp_referensi');
        if (is_array($referensi_dp)) { $referensi_dp = null; }

        $kodePembayaran =  'ksr' . date('Ymd') . session('ID_UNIT') . rand(1000, 9999);
        $bankData = $this->request->getPost('bank');
        $bankPembayaran = [];
        $totalBayarBank = 0;

        // Hormati metode yang dipilih klien: bila operator memilih "transfer",
        // kolom tunai yang mungkin masih berisi nilai lama (mis. saat koreksi)
        // tidak boleh ikut terhitung, dan sebaliknya. Metode kosong (klien lama)
        // memakai perilaku lama: hitung keduanya apa adanya.
        $metodeBayar = strtolower(trim((string) $this->request->getPost('metode_bayar')));
        $hitungTunai = $metodeBayar === '' || $metodeBayar === 'tunai' || $metodeBayar === 'tunai_transfer';
        $hitungBank  = $metodeBayar === '' || $metodeBayar === 'transfer' || $metodeBayar === 'tunai_transfer';
        if (! $hitungTunai) {
            $bayar_pembayaran = 0;
        }
        if (! $hitungBank) {
            $bankData = [];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        if ($idservice <= 0) {
            $db->transRollback();
            session()->setFlashdata('gagal', 'Data service tidak valid; pembayaran tidak disimpan.');
            return redirect()->back();
        }

        // Baca state terbaru di dalam transaksi. Pada MySQL, kunci baris service
        // agar dua submit bersamaan tidak sama-sama lolos dari pemeriksaan lunas.
        $serviceQuery = $db->table('service')->where('idservice', $idservice);
        $serviceSql = $serviceQuery->getCompiledSelect();
        if (in_array(strtolower((string) $db->DBDriver), ['mysqli', 'postgre'], true)) {
            $serviceSql .= ' FOR UPDATE';
        }
        $serviceResult = $db->query($serviceSql);
        $serviceSaatIni = $serviceResult ? $serviceResult->getRow() : null;
        if (! $serviceSaatIni) {
            $db->transRollback();
            session()->setFlashdata('gagal', 'Data service tidak ditemukan; pembayaran tidak disimpan.');
            return redirect()->back();
        }

        // Total di DB berasal dari sparepart yang telah disimpan pada langkah
        // sebelumnya. Ini membuat guard tidak bergantung pada hidden input harga.
        $totalTagihan = max(0, (int) ($serviceSaatIni->total_service ?? 0));
        if ($db->tableExists($db->getPrefix() . 'service_sparepart', false)
            && $db->fieldExists('sub_total', 'service_sparepart')) {
            $ringkasanSparepart = $db->table('service_sparepart')
                ->select('COUNT(*) AS jumlah_baris, COALESCE(SUM(sub_total), 0) AS total_tagihan', false)
                ->where('service_idservice', $idservice)
                ->get()
                ->getRow();

            if ((int) ($ringkasanSparepart->jumlah_baris ?? 0) > 0) {
                $totalTagihan = max(0, (int) ($ringkasanSparepart->total_tagihan ?? 0));
            } elseif ($totalTagihan <= 0) {
                $totalTagihan = max(0, $total_harga_pembayaran);
            }
        } elseif ($totalTagihan <= 0) {
            // Fallback untuk tiket lama / instalasi yang belum memiliki tabel
            // detail sparepart, serta untuk fixture pengujian route.
            $totalTagihan = max(0, $total_harga_pembayaran);
        }
        $total_harga_pembayaran = $totalTagihan;

        $pembayaranBankTersimpan = $this->PembayaranBankModel->getByServiceBaru($idservice);
        $jumlahBankTersimpan = 0;
        foreach ($pembayaranBankTersimpan as $bankLama) {
            $jumlahBankTersimpan += (int) ($bankLama->jumlah ?? 0);
        }

        $tunaiTersimpan = max(0, (int) ($serviceSaatIni->bayar_tunai ?? 0));
        $bayarTersimpan = max(
            0,
            (int) ($serviceSaatIni->bayar ?? 0),
            $tunaiTersimpan + $jumlahBankTersimpan
        );
        $dpTersimpan = max(0, (int) ($serviceSaatIni->dp_bayar ?? 0));
        $sisaTagihan = max(0, $totalTagihan - $dpTersimpan - $bayarTersimpan);

        // Koreksi berlaku bila pembayaran tercatat MELEBIHI total (salah input).
        // Operator mengisi ulang nominal tunai/transfer; nilai lama diganti, DP
        // tetap. Flag `koreksi_pembayaran` menandakan niat mengganti (dikirim UI
        // hanya saat status kelebihan bayar) agar cicilan normal tetap menambah.
        $koreksiDiminta = $this->request->getPost('koreksi_pembayaran') === '1';
        $kelebihanTercatat = ($dpTersimpan + $bayarTersimpan) - $totalTagihan;
        $modeKoreksi = $koreksiDiminta && $totalTagihan > 0 && $kelebihanTercatat > 0;

        if ($totalTagihan > 0 && $sisaTagihan === 0 && ! $modeKoreksi) {
            $db->transRollback();
            session()->setFlashdata(
                'gagal',
                'Pembayaran ditolak: service ini sudah lunas (DP Rp '
                    . number_format($dpTersimpan, 0, ',', '.')
                    . ' + pembayaran Rp ' . number_format($bayarTersimpan, 0, ',', '.')
                    . ' dari total Rp ' . number_format($totalTagihan, 0, ',', '.') . ').'
            );
            return redirect()->back();
        }

        // Catat DP (ledger + jurnal) bila diisi di tahap pembayaran. Pada mode
        // koreksi DP tidak berubah, jadi tidak ada DP baru yang dicatat.
        if ($dp_bayar > 0 && ! $modeKoreksi) {
            try {
                $hasilDp = $this->ServiceDpService->catatDp([
                    'service_idservice' => (int) $idservice,
                    'jumlah'            => (int) $dp_bayar,
                    'metode'            => $metode_dp,
                    'bank_idbank'       => $bank_dp,
                    'referensi'         => $referensi_dp,
                    'kode_dp'           => $no_service !== '' ? ('DP' . $no_service) : null,
                    'tanggal'           => date('Y-m-d'),
                    'keterangan'        => 'Pembayaran Uang Muka Jasa Service',
                    'unit_id'           => (int) session('ID_UNIT'),
                    'input_by'          => (int) session('ID_AKUN'),
                ]);
                if (($hasilDp['status'] ?? null) !== \App\Services\Service\ServiceDpService::STATUS_OK) {
                    throw new \RuntimeException('Pencatatan DP gagal (status: ' . ($hasilDp['status'] ?? 'none') . ').');
                }
            } catch (\Throwable $e) {
                $db->transRollback();
                log_message('error', 'insert_pembayaran: gagal catat DP: ' . $e->getMessage());
                session()->setFlashdata('gagal', 'Gagal menyimpan DP: ' . $e->getMessage());
                return redirect()->back();
            }
        }

        // Mode koreksi mengganti (bukan menambah) pembayaran bank yang tercatat.
        if ($modeKoreksi) {
            if (! $db->table('pembayaran_bank')
                ->where('tabel_referensi', 'service_baru')
                ->where('id_referensi', $idservice)
                ->delete()) {
                $db->transRollback();
                session()->setFlashdata('gagal', 'Gagal menghapus pembayaran bank lama saat koreksi.');
                return redirect()->back();
            }
        }

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


        // Pembayaran lanjutan menambah nilai yang sudah tersimpan. Mode koreksi
        // mengganti nilai lama dengan nominal baru (DP tetap).
        if ($modeKoreksi) {
            $total_bayar = $bayar_pembayaran + $totalBayarBank;
            $bayar_tunai_baru = $bayar_pembayaran;
        } else {
            $total_bayar = $bayarTersimpan + $bayar_pembayaran + $totalBayarBank;
            $bayar_tunai_baru = $tunaiTersimpan + $bayar_pembayaran;
        }



        $datap = array(

            'total_service' => $total_harga_pembayaran,
            'total_diskon' => $diskon_pembayaran,
            'harus_dibayar' => $total_harga_pembayaran,
            'bayar' => $total_bayar,
            'total_service_garansi' => 0,
            'biaya_tambahan_garansi' => 0,
            'total_diskon_garansi' => 0,
            'harga_penjualan_garansi' => 0,
            'bayar_tunai' => $bayar_tunai_baru,
            'bayar_bank' => $kodePembayaran
        );
        $this->ServiceModel->updateService($idservice, $datap);

        if (! $db->transComplete()) {
            $db->transRollback();
            session()->setFlashdata('gagal', 'Transaksi pembayaran gagal diselesaikan.');
            return redirect()->back();
        }
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

        $db = \Config\Database::connect();
        // Nama suplier diambil lewat subquery agregat (GROUP_CONCAT) yang
        // dikorelasi per (barang, unit). Satu barang bisa punya stok_awal dari
        // beberapa suplier; kalau di-join langsung, hasilnya menggandakan baris
        // sehingga sparepart tampil dobel di modal. Subquery ini mengembalikan
        // satu baris per sparepart berisi daftar suplier (dipisah koma).
        $tabelStokAwal = $db->prefixTable('stok_awal');
        $tabelSuplier  = $db->prefixTable('suplier');

        $namaSuplier = "(SELECT GROUP_CONCAT(DISTINCT s.nama_suplier ORDER BY s.nama_suplier SEPARATOR ', ')
            FROM {$tabelStokAwal} sa
            JOIN {$tabelSuplier} s ON s.id_suplier = sa.suplier_id_suplier
            WHERE sa.barang_idbarang = sb.idbarang
              AND sa.unit_idunit = sb.id_unit
              AND sa.suplier_id_suplier != 0) AS nama_suplier";

        $builder = $db->table('stok_barang sb')
            ->select("sb.*, b.kode_barang, b.nama_barang, b.harga, b.harga_beli, b.warna, b.imei, u.NAMA_UNIT, {$namaSuplier}")
            ->join('barang b', 'b.idbarang = sb.idbarang', 'left')
            ->join('unit u', 'u.idunit = sb.id_unit', 'left');
        
        if (!empty($search)) {
            $builder->groupStart()
                ->like('b.nama_barang', $search)
                ->orLike('b.kode_barang', $search)
                ->orLike('b.warna', $search)
                ->groupEnd();
        }

        $sparepart = $builder
            ->where('sb.id_unit', $unitId)
            ->where('sb.stok_akhir >', 0)
            ->groupStart()
                ->like('b.kode_barang', 'sprt')
                ->orLike('b.kode_barang', 'acc')
            ->groupEnd()
            ->orderBy('b.nama_barang', 'ASC')
            ->limit(50)
            ->get()
            ->getResult();

        return $this->response->setJSON($sparepart);
    }

    public function search_pelanggan_ajax()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['error' => 'Invalid request']);
        }

        $search = $this->request->getPost('search') ?? '';

        $builder = $this->PelangganModel->where('deleted', '0');
        
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

        if ($this->ServiceModel->hasRefundedDp($idservice)) {
            session()->setFlashdata('gagal', 'Service dengan refund DP tidak dapat dihapus permanen karena transaksi refund harus tetap tercatat.');
            return redirect()->to(base_url('proses_service'));
        }

        $idservice = (int) $idservice;
        $db = Database::connect();
        $db->transBegin();

        try {
            // Catat keberhasilan langkah lewat return value query, bukan lewat
            // `transStatus()`: flag itu ter-set false saat ada query gagal dan
            // tidak di-reset lagi (transStrict default true) sehingga pada
            // koneksi yang dipakai ulang bisa membuat transaksi berikutnya salah
            // rollback.
            $ok = true;

            // Service ber-DP kini boleh dibatalkan permanen. Ledger `service_dp`
            // dan jurnal DP-nya dihapus dalam transaksi yang sama dengan service
            // supaya tidak ada ledger/jurnal yatim. Hanya jurnal DP milik service
            // ini yang dihapus (WHERE id_referensi IN ...).
            if ($db->tableExists($db->getPrefix() . 'service_dp', false)) {
                $dpIds = [];
                foreach ($db->table('service_dp')
                    ->select('id')
                    ->where('service_idservice', $idservice)
                    ->get()->getResult() as $dp) {
                    $dpIds[] = (string) $dp->id;
                }

                if ($dpIds !== []) {
                    $ok = $db->table('jurnal')
                        ->where('tabel_referensi', 'service_dp')
                        ->whereIn('id_referensi', $dpIds)
                        ->delete() && $ok;
                }

                $ok = $db->table('service_dp')->where('service_idservice', $idservice)->delete() && $ok;
            }

            $ok = $this->ServiceModel->delete($idservice) && $ok;

            $ok = $this->ServiceKerusakanModel->where('service_idservice', $idservice)->delete() && $ok;
            $ok = $this->ServiceSparepartModel->where('service_idservice', $idservice)->delete() && $ok;

            if (! $ok) {
                $db->transRollback();
                session()->setFlashdata('gagal', 'Gagal membatalkan transaksi service.');
                return redirect()->to(base_url('service'));
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'cancel_service gagal service ' . $idservice . ': ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal membatalkan transaksi: ' . $e->getMessage());
            return redirect()->to(base_url('service'));
        }

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
