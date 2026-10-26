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
use App\Models\ModelRegion;
use App\Services\Service\ServiceDpService;
use App\Services\Service\GaransiClaimService;
use App\Services\Service\SparepartKeluarHardDeleteService;
use App\Services\Service\ServiceSparepartPenjualanSyncService;
use App\Models\ModelServiceDp;

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
    protected $RegionModel;
    protected $DpService;
    protected $DpLedgerModel;



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
        $this->RegionModel = new ModelRegion();
        $this->DetailPenjualanModel = new ModelDetailPenjualan();
        $this->DpService = new ServiceDpService();
        $this->DpLedgerModel = new ModelServiceDp();
    }

    public function index()
    {
        $idJabatan = session()->get('ID_JABATAN');
        $isHO = ($idJabatan == 1 || $idJabatan == '1' || $idJabatan == 0 || $idJabatan == 42 || $idJabatan == '42');

        $data =  array(

            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => [],
            'unit' => $this->UnitModel->getUnit(),
            'is_admin' => session()->get('ID_JABATAN') == 1,
            'is_ho' => $isHO,
            'user_unit' => (int) session('ID_UNIT'),
            'body'  => 'riwayat/service'
        );
        return view('template', $data);
    }

    public function riwayat_service_ajax()
    {
        $request = \Config\Services::request();
        
        $draw = $request->getPost('draw');
        $start = $request->getPost('start') ?? 0;
        $length = $request->getPost('length') ?? 10;
        $searchValue = $request->getPost('search')['value'] ?? '';
        $orderColumnIndex = $request->getPost('order')[0]['column'] ?? 1;
        $orderDir = $request->getPost('order')[0]['dir'] ?? 'desc';
        
        $startDate = $request->getPost('startDate') ?? '';
        $endDate = $request->getPost('endDate') ?? '';
        $unitFilter = $request->getPost('unitFilter') ?? '';

        $idJabatan = session()->get('ID_JABATAN');
        $isHO = ($idJabatan == 1 || $idJabatan == '1' || $idJabatan == 0 || $idJabatan == 42 || $idJabatan == '42');
        if (!$isHO) {
            $unitFilter = (int) session('ID_UNIT');
        }

        $result = $this->ServiceModel->getRiwayatServiceServerSide(
            $start,
            $length,
            $searchValue,
            $orderColumnIndex,
            $orderDir,
            $startDate,
            $endDate,
            $unitFilter
        );

        $recordsTotal = $this->ServiceModel->getRiwayatServiceTotal();

        return $this->response->setJSON([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data']
        ]);
    }

    public function index2()
    {
        $idJabatan = (int) session('ID_JABATAN');
        $canSeeAllUnits = GaransiClaimService::canSeeAllUnits($idJabatan);
        $userUnit = (int) session('ID_UNIT');

        $requestedUnit = (int) ($this->request->getGet('unit') ?? 0);
        $selectedUnit = $canSeeAllUnits ? $requestedUnit : $userUnit;

        if ($canSeeAllUnits && $selectedUnit > 0 && $this->UnitModel->getById($selectedUnit) === null) {
            $selectedUnit = 0;
        }

        $unitList = $canSeeAllUnits ? $this->UnitModel->getUnit() : [];
        usort($unitList, static fn ($a, $b) => strcmp((string) $a->NAMA_UNIT, (string) $b->NAMA_UNIT));

        $selectedUnitName = $canSeeAllUnits ? 'Semua unit' : 'Unit Anda';
        if ($selectedUnit > 0) {
            $selectedUnitName = '';
            foreach ($unitList as $unit) {
                if ((int) $unit->idunit === $selectedUnit) {
                    $selectedUnitName = (string) $unit->NAMA_UNIT;
                    break;
                }
            }
            if ($selectedUnitName === '') {
                $unit = $this->UnitModel->getById($selectedUnit);
                $selectedUnitName = $unit->NAMA_UNIT ?? '-';
            }
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Jakarta'));
        $startDate = $this->request->getGet('tanggal_awal');
        $endDate = $this->request->getGet('tanggal_akhir');

        if ($startDate === null) {
            $startDate = $today->modify('first day of this month')->format('Y-m-d');
        }
        if ($endDate === null) {
            $endDate = $today->modify('last day of this month')->format('Y-m-d');
        }

        $unitFilter = $selectedUnit > 0 ? $selectedUnit : '';
        $service = ($canSeeAllUnits || $userUnit > 0)
            ? $this->ServiceModel->getRiwayatServiceGaransi($unitFilter, (string) $startDate, (string) $endDate)
            : [];

        $data = [
            'service' => $service,
            'unit' => $unitList,
            'can_see_all_units' => $canSeeAllUnits,
            'selected_unit' => $selectedUnit,
            'selected_unit_name' => $selectedUnitName,
            'start_date' => (string) $startDate,
            'end_date' => (string) $endDate,
            'body'  => 'riwayat/service_garansi'
        ];
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
            'dp_bank' => $this->BankModel->getBankDalamScopeUnit((int) $unitId),
            'idservice' => $idservice,
            'pembayaran_lama' => $this->PembayaranBankModel->getByServiceBaru($idservice),
            'old_service_pelanggan' => $this->ServiceModel->getByIdWithPelanggan($idservice),
            'oldkerusakan' => $oldkerusakan,
            'oldsparepart' => $oldsparepart,
            'lama_garansi' => $lama_garansi ? (int)$lama_garansi->garansi_hari : null,
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'sparepart' => $sparepart,
            'provinsi' => $this->RegionModel->getProvinces(),
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

        $service = $this->ServiceModel->find($idservice);
        if ($service && !empty($service->pelanggan_id_pelanggan)) {
            $this->PelangganModel->update($service->pelanggan_id_pelanggan, [
                'provinsi' => $this->request->getPost('domisili_provinsi'),
                'kabupaten' => $this->request->getPost('domisili_kabupaten'),
                'kecamatan' => $this->request->getPost('domisili_kecamatan'),
            ]);
        }

        // Form di /service memakai endpoint yang sama untuk menyunting ticket
        // yang sedang jalan. Kalau dari sana, kembalikan teknisi ke alur 4
        // langkahnya; kalau dari halaman detail, tetap di halaman detail.
        // Nilai return_to bukan URL, hanya penanda asal form.
        if ($this->request->getPost('return_to') === 'service') {
            return redirect()->to(base_url('service?tab=kerusakan'))->with('sukses', 'Data service berhasil diperbarui.');
        }

        return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=kerusakan'))->with('sukses', 'Data kerusakan berhasil diperbarui.');
    }




    public function insert_kerusakan()
    {
        $fungsiTerpilih = $this->request->getPost('fungsi');
        $keteranganInput = $this->request->getPost('keterangan');
        $idservice = $this->request->getPost('idservice_k');

        if (empty($fungsiTerpilih)) {
            return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=sparepart'))->with('gagal', 'Tidak ada kerusakan yang dipilih.');
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

        return redirect()->to(base_url('detail/riwayat_service/' . $idservice . '?tab=sparepart'))->with('sukses', 'Data sparepart berhasil diperbarui.');
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

        $urlKembali = base_url('detail/riwayat_service/' . $idservice . '?tab=sparepart');
        if ($idservice <= 0) {
            return redirect()->to($urlKembali)->with('gagal', 'ID service tidak valid. Sparepart tidak disimpan.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $service = $db->table('service')->where('idservice', $idservice)->get()->getRow();
            if (! $service) {
                throw new \RuntimeException('Data service tidak ditemukan.');
            }

            // Ambil snapshot lama sebelum mengubah service_sparepart. Invoice
            // lama dibersihkan/dicocokkan dalam transaksi yang sama, lalu satu
            // invoice baru dibuat dari pilihan sparepart terbaru.
            $existingItems = $this->ServiceSparepartModel->getByServiceId($idservice);
            $lockedIds = $this->returLockedSparepartIds($db, $idservice);
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
            foreach ($produkData as $produk) {
                $id = (int) ($produk['id'] ?? 0);
                if ($id <= 0) {
                    throw new \RuntimeException('Data sparepart tidak valid.');
                }

                if (array_key_exists($id, $existingMap)
                    && in_array((int) $existingMap[$id]->idservice_sparepart, $lockedIds, true)) {
                    throw new \RuntimeException('Sparepart klaim sudah diretur; batalkan retur dulu.');
                }
                $jumlah = (int) ($produk['jumlah'] ?? 0);
                $harga = $this->rupiahToInt($produk['harga'] ?? '0');
                $diskonItem = $this->rupiahToInt($produk['diskon'] ?? '0');
                $total = $this->rupiahToInt($produk['total'] ?? '0');
                $submittedIds[] = $id;

                $dataHppBarang = $this->HppBarangModel->getById($id);
                $stokAwal = $this->StokAwalModel->getById($id);
                $data = [
                    'jumlah' => $jumlah,
                    'harga_penjualan' => $harga,
                    'sub_total' => $total,
                    'hpp_penjualan' => $dataHppBarang->hpp ?? 0,
                    'satuan_jual' => $stokAwal->satuan_terkecil ?? 'pcs',
                    'diskon_penjualan' => $diskonItem,
                    'service_idservice' => $idservice,
                    'barang_idbarang' => $id,
                    'unit_idunit' => (int) ($service->unit_idunit ?? session('ID_UNIT')),
                ];

                $saved = array_key_exists($id, $existingMap)
                    ? $this->ServiceSparepartModel->updateByServiceAndBarang($idservice, $id, $data)
                    : $this->ServiceSparepartModel->insert_SerModelServiceSparepart($data);
                if (! $saved) {
                    throw new \RuntimeException('Gagal menyimpan detail sparepart service.');
                }
            }

            foreach ($existingMap as $barangId => $item) {
                if (in_array((int) $barangId, $submittedIds, true)) {
                    continue;
                }

                if (in_array((int) $item->idservice_sparepart, $lockedIds, true)) {
                    throw new \RuntimeException('Sparepart klaim sudah diretur; batalkan retur dulu.');
                }

                if (! $this->ServiceSparepartModel->deleteByServiceAndBarang($idservice, $barangId)) {
                    throw new \RuntimeException('Gagal menghapus sparepart yang dilepas dari service.');
                }
            }

            $this->buatPenjualanDariService($idservice, $produkData);

            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Sinkronisasi sparepart keluar gagal disimpan.');
            }

            return redirect()->to($urlKembali)->with('sukses', 'Sparepart service dan sparepart keluar berhasil disinkronkan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Riwayat_Service::insert_sparepart gagal untuk service ' . $idservice . ': ' . $e->getMessage());
            return redirect()->to($urlKembali)->with('gagal', 'Sparepart tidak tersimpan: ' . $e->getMessage());
        }
    }

    private function returLockedSparepartIds($db, int $idservice): array
    {
        if (! $db->tableExists('retur_klaim_item')) {
            return [];
        }

        $rows = $db->table('retur_klaim_item rki')
            ->select('rki.service_sparepart_idservice_sparepart')
            ->join('service_sparepart sp', 'sp.idservice_sparepart = rki.service_sparepart_idservice_sparepart')
            ->where('sp.service_idservice', $idservice)
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'service_sparepart_idservice_sparepart'));
    }

    private function buatPenjualanDariService($idservice, $produkData)
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
            'service_idservice' => (int) $idservice,
        ];

        if (! $this->PenjualanModel->insert_Penjualan($dataPenjualan)) {
            throw new \RuntimeException('Gagal membuat invoice sparepart service.');
        }
        $idPenjualan = $this->PenjualanModel->insertID();
        if ((int) $idPenjualan <= 0) {
            throw new \RuntimeException('ID invoice sparepart service tidak valid.');
        }

        // insert detail + potong stok
        foreach ($produkData as $p) {
            $produkid = $p['id'];
            $qty = (int)$p['jumlah'];
            $harga = $this->rupiahToInt($p['harga']);
            $diskon = $this->rupiahToInt($p['diskon']);

            $subtotal = ($harga * $qty) - $diskon;

            $hpp = $this->HppBarangModel->getById($produkid);

            if (! $this->DetailPenjualanModel->insert_detail([
                'barang_idbarang' => $produkid,
                'jumlah' => $qty,
                'harga_penjualan' => $harga,
                'sub_total' => $subtotal,
                'penjualan_idpenjualan' => $idPenjualan,
                'hpp_penjualan' => $hpp->hpp ?? 0,
                'unit_idunit' => session('ID_UNIT'),
            ])) {
                throw new \RuntimeException('Gagal menyimpan detail invoice sparepart service.');
            }
        }
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
        // Header invoice memakai unit milik service-nya, bukan unit user yang
        // sedang login. Kalau dicetak user unit lain, kopnya jadi unit yang
        // salah (mis. service Genteng dicetak dari user Banyuwangi).
        'dataunit'   => $this->UnitModel->getById($service->unit_idunit ?? session('ID_UNIT'))
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
        $sumber        = $this->request->getPost('sumber');

        $idJabatan = (int) session('ID_JABATAN');
        $canSeeAllUnits = GaransiClaimService::canSeeAllUnits($idJabatan);

        if ($canSeeAllUnits) {
            $requestedUnit = (int) $this->request->getPost('unit');
            $unitFilter = ($requestedUnit > 0 && $this->UnitModel->getById($requestedUnit) !== null)
                ? $requestedUnit
                : '';
        } else {
            $unitFilter = (int) session('ID_UNIT');
        }

        // Halaman garansi mengirim sumber=garansi (filter tanggal selesai);
        // halaman riwayat klaim memakai filter tanggal claim.
        $dataservice = $sumber === 'garansi'
            ? $this->ServiceModel->filterExportGaransiAktif($tanggal_awal, $tanggal_akhir, $unitFilter)
            : $this->ServiceModel->filterExportGaransi($tanggal_awal, $tanggal_akhir, $unitFilter);

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
        $idJabatan = session()->get('ID_JABATAN');
        $isHO = ($idJabatan == 1 || $idJabatan == '1' || $idJabatan == 0);

        // Hanya unit operasional yang boleh dipilih di filter role lintas unit.
        $unitList = $isHO
            ? array_values(array_filter(
                $this->UnitModel->getUnit(),
                static fn ($u) => (int) ($u->is_operational ?? 1) === 1
            ))
            : [];
        usort($unitList, static fn ($a, $b) => strcmp((string) $a->NAMA_UNIT, (string) $b->NAMA_UNIT));

        $data =  array(
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => [],
            'unit' => $unitList,
            'is_ho' => $isHO,
            'user_unit' => (int) session('ID_UNIT'),
            'body'  => 'riwayat/proses_service'
        );
        return view('template', $data);
    }

    public function proses_service_ajax()
    {
        $request = \Config\Services::request();
        
        $draw = $request->getPost('draw');
        $start = $request->getPost('start') ?? 0;
        $length = $request->getPost('length') ?? 10;
        $searchValue = $request->getPost('search')['value'] ?? '';
        $orderColumnIndex = $request->getPost('order')[0]['column'] ?? 3;
        $orderDir = $request->getPost('order')[0]['dir'] ?? 'desc';
        
        $startDate = $request->getPost('startDate') ?? '';
        $endDate = $request->getPost('endDate') ?? '';
        $unitFilter = $request->getPost('unitFilter') ?? '';

        $idJabatan = session()->get('ID_JABATAN');
        $isHO = ($idJabatan == 1 || $idJabatan == '1' || $idJabatan == 0);
        if (!$isHO) {
            $unitFilter = (int) session('ID_UNIT');
        }

        $result = $this->ServiceModel->ProsesServiceAktifServerSide(
            $start,
            $length,
            $searchValue,
            $orderColumnIndex,
            $orderDir,
            $startDate,
            $endDate,
            $unitFilter
        );

        $recordsTotal = $this->ServiceModel->ProsesServiceAktifTotal();

        return $this->response->setJSON([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data']
        ]);
    }

    /** Halaman daftar penerimaan DP service — akses dan cakupan unit mengikuti Proses Service. */
    public function dp_service()
    {
        $idJabatan = session()->get('ID_JABATAN');
        $isHO = ($idJabatan == 1 || $idJabatan == '1' || $idJabatan == 0);

        return view('template', [
            'unit'           => $this->UnitModel->getUnit(),
            'is_ho'          => $isHO,
            'user_unit'      => (int) session('ID_UNIT'),
            'dp_ledger_ada'  => $this->DpService->punyaTabelLedger(),
            'dp_refund_siap' => $this->DpService->refundSiap(),
            'body'           => 'riwayat/dp_service',
        ]);
    }

    /** DataTables DP service — pengguna non-HO selalu dibatasi ke unit sesi. */
    public function dp_service_ajax()
    {
        $request = \Config\Services::request();
        $draw = (int) ($request->getPost('draw') ?? 0);
        $idJabatan = session()->get('ID_JABATAN');
        $isHO = ($idJabatan == 1 || $idJabatan == '1' || $idJabatan == 0);
        $userUnit = (int) session('ID_UNIT');

        if (! $this->DpService->punyaTabelLedger()) {
            return $this->response->setJSON([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        if ($isHO) {
            $unitRaw = $request->getPost('unitFilter');
            $unitFilter = is_numeric($unitRaw) && (int) $unitRaw > 0
                ? (int) $unitRaw
                : null;
        } else {
            $unitFilter = $userUnit > 0 ? $userUnit : -1;
        }

        $dateFilter = static function ($value): string {
            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return '';
            }

            $date = \DateTime::createFromFormat('!Y-m-d', $value);

            return $date && $date->format('Y-m-d') === $value ? $value : '';
        };

        $search = $request->getPost('search');
        $searchValue = is_array($search) ? trim((string) ($search['value'] ?? '')) : '';
        $order = $request->getPost('order');
        $orderRow = is_array($order) ? ($order[0] ?? []) : [];
        $start = max(0, (int) ($request->getPost('start') ?? 0));
        $length = min(100, max(1, (int) ($request->getPost('length') ?? 10)));

        $result = $unitFilter === -1
            ? ['recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]
            : $this->DpLedgerModel->datatable(
                $start,
                $length,
                $searchValue,
                $dateFilter($request->getPost('startDate')),
                $dateFilter($request->getPost('endDate')),
                $unitFilter,
                (int) ($orderRow['column'] ?? 0),
                (string) ($orderRow['dir'] ?? 'desc')
            );

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data'            => $result['data'],
        ]);
    }

    /** Refund penuh DP untuk service yang sudah dibatalkan. */
    public function refund_dp_service()
    {
        $rawId = $this->request->getPost('dp_id');
        $idDp = is_scalar($rawId) ? (int) $rawId : 0;
        $alasan = $this->request->getPost('refund_alasan');
        $referensi = $this->request->getPost('refund_referensi');
        $alasan = is_string($alasan) ? $alasan : '';
        $referensi = is_string($referensi) ? $referensi : null;

        $idJabatan = session()->get('ID_JABATAN');
        $isHO = ($idJabatan == 1 || $idJabatan == '1' || $idJabatan == 0);
        $unitDiizinkan = $isHO ? null : (int) session('ID_UNIT');
        if (! $isHO && $unitDiizinkan <= 0) {
            session()->setFlashdata('gagal', 'Unit akun tidak valid; refund DP tidak dapat diproses.');

            return redirect()->to(base_url('dp_service'));
        }

        try {
            $hasil = $this->DpService->refund(
                $idDp,
                $alasan,
                $referensi,
                (int) session('ID_AKUN'),
                $unitDiizinkan
            );

            session()->setFlashdata(
                'sukses',
                'Refund DP berhasil dicatat sebesar Rp ' . number_format($hasil['jumlah'], 0, ',', '.') . '; service ditandai dibatalkan.'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Refund DP service #' . $idDp . ' gagal: ' . $e->getMessage());
            session()->setFlashdata('gagal', $e->getMessage());
        }

        return redirect()->to(base_url('dp_service'));
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
            'unit' => $this->UnitModel->getUnit(),
            'body'  => 'riwayat/bisa_diambil'
        );
        return view('template', $data);
    }

    public function update_sudah_diambil()
    {
        $idservice = (int) $this->request->getPost('idservice');
        $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));

        $dataservice = $this->ServiceModel->getServiceById($idservice);
        if (empty($dataservice)) {
            session()->setFlashdata('gagal', 'Data service tidak ditemukan.');
            return redirect()->to(base_url('bisa_diambil'));
        }

        // Idempotensi: service yang sudah berstatus 4 (diambil) tidak dijurnal ulang.
        if ((int) $dataservice->status_service === 4) {
            session()->setFlashdata('gagal', 'Service ini sudah berstatus diambil.');
            return redirect()->to(base_url('bisa_diambil'));
        }

        $db = Database::connect();

        $total_service = (int) $dataservice->total_service;
        $bayar_tunai   = (int) $dataservice->bayar_tunai;

        // Pembayaran via bank pada saat pengambilan (referensi 'service_baru').
        $bayar_bank = 0;
        $bankData = $this->PembayaranBankModel->getByServiceBaru($idservice);
        if (!empty($bankData) && is_array($bankData)) {
            foreach ($bankData as $b) {
                $bayar_bank += (int) $b->jumlah;
            }
        }

        // Sumber saldo DP: ledger baru (`service_dp`) atau historis (`service.dp_bayar`).
        $sumberDp    = $this->DpService->statusSumberDp($idservice);
        $sisaTagihan = max($total_service - $bayar_tunai - $bayar_bank, 0);
        $dpTerpakai  = min((int) $sumberDp['saldo'], $sisaTagihan);
        $pendapatan  = $bayar_tunai + $bayar_bank + $dpTerpakai;

        // Tanggal pengakuan: tanggal pengambilan (WIB), bukan tanggal intake.
        $tanggal = $wibTime->format('Y-m-d');

        $db->transBegin();

        try {
            $this->ServiceModel->updateService($idservice, [
                'status_service'  => 4,
                'tanggal_selesai' => $wibTime->format('Y-m-d H:i:s'),
            ]);

            // Idempotensi berbasis jurnal: bila jurnal pengambilan (tabel_referensi
            // 'service') untuk service ini sudah pernah tertulis, JANGAN posting
            // ulang. Guard status (=4) saja tidak cukup: jalur "Dibatalkan (90) ->
            // Aktifkan kembali (2)" membuat service kembali berstatus != 4 tanpa
            // membalik jurnal, sehingga pengambilan ulang akan menggandakan
            // pendapatan dan pelepasan PDM. Status akhir tetap dipastikan 4.
            $sudahDijurnal = $this->JurnalModel->ringkasByReferensi('service', $idservice)['jumlah'] > 0;

            if (! $sudahDijurnal) {
                // Batas id jurnal sebelum insert, untuk memverifikasi baris baru saja.
                $maks = $db->table('jurnal')->selectMax('idjurnal', 'maks')->get()->getRow();
                $maxIdjurnal = (int) ($maks->maks ?? 0);

                // Seluruh pelepasan PDM ditaruh di SATU template: akun PDM sama pada
                // kedua template, sehingga pendapatan dan PDM tidak mungkin dobel.
                $templateDp = $bayar_tunai > 0
                    ? 'pembayaran_service_tunai'
                    : ($bayar_bank > 0 ? 'pembayaran_service_bank' : 'pembayaran_service_tunai');

                $kaki = [
                    'pembayaran_service_tunai' => ['kas' => $bayar_tunai, 'dp' => 0],
                    'pembayaran_service_bank'  => ['kas' => $bayar_bank,  'dp' => 0],
                ];
                $kaki[$templateDp]['dp'] = $dpTerpakai;

                foreach ($kaki as $kodeTemplate => $nilai) {
                    $kas = (int) $nilai['kas'];
                    $dp  = (int) $nilai['dp'];
                    if ($kas === 0 && $dp === 0) {
                        continue;
                    }
                    $keterangan = $kodeTemplate === 'pembayaran_service_bank'
                        ? 'Pembayaran Uang Lunas Jasa Service'
                        : 'Pembayaran Uang Tunai Jasa Service';
                    $this->JurnalModel->insertJurnal(
                        $tanggal,
                        $kodeTemplate,
                        [$kas, $dp, $kas + $dp],
                        $keterangan,
                        $idservice,
                        'service'
                    );
                }

                // Verifikasi eksplisit: insertJurnal() tidak memberi sinyal kegagalan.
                $ringkas = $this->JurnalModel->ringkasByReferensi('service', $idservice, $maxIdjurnal);
                if ($pendapatan > 0 && $ringkas['jumlah'] === 0) {
                    throw new \RuntimeException('Jurnal pengambilan service tidak terbentuk (template jurnal tidak lengkap).');
                }
                if (! $ringkas['seimbang']) {
                    throw new \RuntimeException('Jurnal pengambilan service tidak seimbang.');
                }
                if ($ringkas['debet'] !== $pendapatan) {
                    throw new \RuntimeException('Nominal jurnal pengambilan service tidak sesuai (' . $ringkas['debet'] . ' != ' . $pendapatan . ').');
                }
            }

            // Penanda bank: perilaku lama dipertahankan (garansi_1 -> garansi_0).
            $this->PembayaranBankModel->updateByReferensi($idservice, [
                'tabel_referensi' => 'service_garansi_0',
            ]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'update_sudah_diambil gagal service ' . $idservice . ': ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal menyelesaikan service: ' . $e->getMessage());
            return redirect()->to(base_url('bisa_diambil'));
        }

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

    public function delete_service()
    {
        $idJabatan = session()->get('ID_JABATAN');
        if ($idJabatan != 1) {
            session()->setFlashdata('gagal', 'Akses ditolak. Hanya admin root yang dapat menghapus service.');
            return redirect()->to(base_url('riwayat_service'));
        }

        $idservice = $this->request->getPost('idservice');
        
        if (empty($idservice)) {
            session()->setFlashdata('gagal', 'ID service tidak valid.');
            return redirect()->to(base_url('riwayat_service'));
        }

        try {
            // Service ber-DP kini boleh dihapus permanen: hardDeleteService()
            // menghapus service bersama seluruh baris `service_dp` dan jurnal
            // DP-nya dalam satu transaksi (tidak meninggalkan ledger/jurnal
            // yatim).
            $result = $this->ServiceModel->hardDeleteService($idservice);

            if ($result) {
                session()->setFlashdata('sukses', 'Data service berhasil dihapus. Stok sparepart telah dikembalikan.');
            } else {
                session()->setFlashdata('gagal', 'Gagal menghapus data service. Transaksi gagal.');
            }
        } catch (\Exception $e) {
            log_message('error', 'Error delete service: ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Error: ' . $e->getMessage());
        }
        
        return redirect()->to(base_url('riwayat_service'));
    }

    function sanitizeCurrency($value)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $value);
        return (float) $cleaned;
    }
    
    public function sparepart()
    {
        $scope = $this->sparepartUnitScope((int) ($this->request->getGet('unit') ?? 0));

        $data = [
            'list_unit'         => $scope['list'],
            'selected_unit'     => $scope['selected'],
            'selected_day'      => $this->request->getGet('day'),
            'can_see_all_units' => $scope['can_see_all_units'],
            'is_admin'          => session('ID_JABATAN') == 1,
            'body'              => 'riwayat/sparepart_keluar'
        ];

        return view('template', $data);
    }

    /**
     * Daftar & unit terpilih pada filter sparepart keluar. Hanya Admin Root,
     * Manager, Finance, dan SPV yang boleh memilih unit; role toko lain
     * terkunci ke unit sesi agar tidak bisa melihat unit lain lewat request
     * manual. Unit non-operasional (mis. Head Office) tidak masuk filter.
     * Nilai 0 berarti seluruh unit dan hanya berlaku untuk role lintas unit.
     *
     * @return array{list: array<int, array<string, mixed>>, selected: int, can_see_all_units: bool}
     */
    private function sparepartUnitScope(int $requested): array
    {
        $canSeeAllUnits = GaransiClaimService::canSeeAllUnits((int) session('ID_JABATAN'));
        $userUnit = (int) session('ID_UNIT');

        $builder = \Config\Database::connect()->table('unit')->orderBy('NAMA_UNIT', 'ASC');
        if ($canSeeAllUnits) {
            $builder->where('is_operational', 1);
        } else {
            $builder->where('idunit', $userUnit);
        }

        $list = $builder->get()->getResultArray();
        $unitIds = array_map(static fn ($u) => (int) $u['idunit'], $list);
        $selected = $canSeeAllUnits ? $requested : $userUnit;

        if ($canSeeAllUnits && $requested === 0) {
            $selected = 0;
        } elseif (! in_array($selected, $unitIds, true)) {
            $selected = (int) ($unitIds[0] ?? $userUnit);
        }

        return [
            'list'              => $list,
            'selected'          => $selected,
            'can_see_all_units' => $canSeeAllUnits,
        ];
    }

    public function sparepart_keluar_ajax()
    {
        $request = \Config\Services::request();
        
        $draw = $request->getPost('draw');
        $start = $request->getPost('start') ?? 0;
        $length = $request->getPost('length') ?? 10;
        $searchValue = $request->getPost('search')['value'] ?? '';
        $orderColumnIndex = $request->getPost('order')[0]['column'] ?? 1;
        $orderDir = $request->getPost('order')[0]['dir'] ?? 'desc';
        
        $selected_unit = $this->sparepartUnitScope((int) ($request->getPost('unit') ?? 0))['selected'];
        $selected_day = $request->getPost('day') ?? '';
        $year = date('Y');

        return $this->response->setJSON($this->sparepartKeluarServerSide(
            $start, $length, $searchValue, $orderColumnIndex, $orderDir, $selected_unit, $selected_day, $year, $draw
        ));
    }

    private function sparepartKeluarServerSide($start, $length, $searchValue, $orderColumn, $orderDir, $selected_unit, $selected_day, $year, $draw)
    {
        $db = \Config\Database::connect();

        $tDetail = $db->prefixTable('detail_penjualan');
        $tBarang = $db->prefixTable('barang');
        $tPenjualan = $db->prefixTable('penjualan');
        $tUnit = $db->prefixTable('unit');
        $tServiceSparepart = $db->prefixTable('service_sparepart');
        $tService = $db->prefixTable('service');

        $allowedColumns = [
            0 => 'x.kode_invoice',
            1 => 'x.tanggal',
            2 => 'x.nama_barang',
            3 => 'x.NAMA_UNIT',
            4 => 'x.hpp_penjualan',
            5 => 'x.sub_total'
        ];

        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $orderColumnName = $allowedColumns[$orderColumn] ?? 'x.tanggal';

        // selected_unit 0 = seluruh unit: tanpa klausa unit sama sekali.
        $salesWhere = [];
        $claimWhere = [];
        $params = [];

        if ((int) $selected_unit > 0) {
            $salesWhere[] = 'p.unit_idunit = ?';
            $params[] = (int)$selected_unit;
        }

        $salesWhere[] = 'YEAR(p.tanggal) = ?';
        $params[] = (int)$year;

        if (!empty($selected_day)) {
            $salesWhere[] = 'DATE(p.tanggal) = ?';
            $params[] = $selected_day;
        }

        if ((int) $selected_unit > 0) {
            $claimWhere[] = 'COALESCE(sp.unit_idunit, s.unit_idunit) = ?';
            $params[] = (int)$selected_unit;
        }

        $claimWhere[] = 'YEAR(s.tanggal_claim_garansi) = ?';
        $params[] = (int)$year;

        if (!empty($selected_day)) {
            $claimWhere[] = 'DATE(s.tanggal_claim_garansi) = ?';
            $params[] = $selected_day;
        }

        // Penjualan dan sparepart klaim digabung agar pengeluaran stok klaim ikut terlihat.
        $baseSql = "SELECT
                p.kode_invoice,
                p.idpenjualan,
                dp.iddetail_penjualan,
                dp.jumlah,
                dp.harga_penjualan,
                dp.diskon_penjualan,
                dp.sub_total,
                dp.hpp_penjualan,
                dp.barang_idbarang,
                b.nama_barang,
                p.tanggal,
                u.NAMA_UNIT,
                'penjualan' AS sumber
            FROM {$tDetail} dp
            JOIN {$tBarang} b ON b.idbarang = dp.barang_idbarang
            JOIN {$tPenjualan} p ON p.idpenjualan = dp.penjualan_idpenjualan
            JOIN {$tUnit} u ON u.idunit = p.unit_idunit
            WHERE " . implode(' AND ', $salesWhere) . "
            UNION ALL
            SELECT
                s.no_service AS kode_invoice,
                NULL AS idpenjualan,
                NULL AS iddetail_penjualan,
                sp.jumlah_tambahan_garansi AS jumlah,
                0 AS harga_penjualan,
                0 AS diskon_penjualan,
                sp.sub_total_garansi AS sub_total,
                COALESCE(sp.hpp_penjualan_garansi, 0) AS hpp_penjualan,
                sp.barang_idbarang,
                b.nama_barang,
                s.tanggal_claim_garansi AS tanggal,
                u.NAMA_UNIT,
                'klaim' AS sumber
            FROM {$tServiceSparepart} sp
            JOIN {$tService} s ON s.idservice = sp.service_idservice
            JOIN {$tBarang} b ON b.idbarang = sp.barang_idbarang
            JOIN {$tUnit} u ON u.idunit = COALESCE(sp.unit_idunit, s.unit_idunit)
            WHERE " . implode(' AND ', $claimWhere) . "
              AND sp.jumlah_tambahan_garansi > 0
              AND s.tanggal_claim_garansi IS NOT NULL";

        $searchCondition = '';
        $filteredParams = $params;
        if (!empty($searchValue)) {
            $searchCondition = 'WHERE (x.kode_invoice LIKE ? OR x.nama_barang LIKE ? OR x.NAMA_UNIT LIKE ?)';
            $searchParam = "%{$searchValue}%";
            $filteredParams = array_merge($filteredParams, [$searchParam, $searchParam, $searchParam]);
        }

        $sql = "SELECT * FROM ({$baseSql}) x
            {$searchCondition}
            ORDER BY {$orderColumnName} {$orderDir}
            LIMIT ? OFFSET ?";

        $filteredParams[] = (int)$length;
        $filteredParams[] = (int)$start;

        $query = $db->query($sql, $filteredParams);
        $data = $query->getResultArray();

        $countSql = "SELECT COUNT(*) as total FROM ({$baseSql}) x {$searchCondition}";
        $countQuery = $db->query($countSql, array_slice($filteredParams, 0, -2));
        $totalFiltered = (int)$countQuery->getRow()->total;

        // Total records (tanpa search, dengan filter unit/day)
        $totalSql = "SELECT COUNT(*) as total FROM ({$baseSql}) x";
        $totalQuery = $db->query($totalSql, $params);
        $recordsTotal = (int)$totalQuery->getRow()->total;

        return [
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ];
    }

    public function edit_sparepart_keluar()
    {
        // hanya admin root (jabatan 1)
        if (session('ID_JABATAN') != 1) {
            session()->setFlashdata('gagal', 'Tidak punya hak akses');
            return redirect()->to(base_url('sparepart_keluar'));
        }
        $idDetail = $this->request->getPost('iddetail_penjualan');
        $qtyNew = (int) $this->request->getPost('jumlah');
        $hargaNew = $this->rupiahToInt($this->request->getPost('harga_penjualan'));
        $hppNew = $this->rupiahToInt($this->request->getPost('hpp_penjualan'));
        $diskon = $this->rupiahToInt($this->request->getPost('diskon_penjualan'));
        $subTotalNew = $hargaNew * $qtyNew - $diskon;

        // ambil data lama
        $detailOld = $this->DetailPenjualanModel->getById($idDetail);
        if (!$detailOld) {
            session()->setFlashdata('gagal', 'Detail tidak ditemukan');
            return redirect()->to(base_url('sparepart_keluar'));
        }

        // update detail penjualan
        $dataDetail = [
            'jumlah' => $qtyNew,
            'harga_penjualan' => $hargaNew,
            'hpp_penjualan' => $hppNew,
            'diskon_penjualan' => $diskon,
            'sub_total' => $subTotalNew,
        ];
        $this->DetailPenjualanModel->updateDetail($dataDetail, $idDetail);

        // Update total pada tabel penjualan (induk) agar tetap sinkron
        $db = \Config\Database::connect();
        $recalc = $db->table('detail_penjualan')
            ->selectSum('sub_total', 'total_sub')
            ->where('penjualan_idpenjualan', $detailOld->penjualan_idpenjualan)
            ->get()
            ->getRow();

        $newTotal = (int) ($recalc->total_sub ?? 0);

        $db->table('penjualan')
            ->where('idpenjualan', $detailOld->penjualan_idpenjualan)
            ->update([
                'total_penjualan' => $newTotal,
                'harus_dibayar'   => $newTotal,
            ]);

        session()->setFlashdata('sukses', 'Data sparepart berhasil diperbarui. Total penjualan & stok otomatis disesuaikan.');
        return redirect()->to(base_url('sparepart_keluar'));
    }

    public function delete_sparepart_keluar()
    {
        if (session('ID_JABATAN') != 1) {
            session()->setFlashdata('gagal', 'Tidak punya hak akses');
            return redirect()->to(base_url('sparepart_keluar'));
        }
        $idDetail = (int) $this->request->getPost('iddetail_penjualan');
        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $hasil = (new SparepartKeluarHardDeleteService($db))->hapusDetail($idDetail);
            if (! $hasil['ok']) {
                $db->transRollback();
                session()->setFlashdata('gagal', 'Data tidak ditemukan');

                return redirect()->to(base_url('sparepart_keluar'));
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Hard delete sparepart gagal.');
            }

            if (! $db->transCommit()) {
                throw new \RuntimeException('Commit hard delete sparepart gagal.');
            }
            session()->setFlashdata(
                'sukses',
                $hasil['invoice_deleted']
                    ? 'Data sparepart & invoice penjualan berhasil dihapus. Stok otomatis disesuaikan.'
                    : 'Data sparepart berhasil dihapus. Stok & total penjualan otomatis disesuaikan.'
            );
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'delete_sparepart_keluar gagal: ' . $e->getMessage());
            session()->setFlashdata('gagal', 'Gagal menghapus sparepart: ' . $e->getMessage());
        }

        return redirect()->to(base_url('sparepart_keluar'));
    }
}
