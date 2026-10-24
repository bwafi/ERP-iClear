<?php

namespace App\Controllers;

use Config\Database;
use App\Services\Service\GaransiClaimService;
use CodeIgniter\Exceptions\PageNotFoundException;
use App\Models\ModelAuth;
use App\Models\ModelBarang;
use App\Models\ModelBarangRusakClaim;
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
    protected $BarangModel;
    protected $BarangRusakClaimModel;
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
        $this->BarangModel = new ModelBarang();
        $this->BarangRusakClaimModel = new ModelBarangRusakClaim();
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
        $data =  array(
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'service' => [],
            'body'  => 'riwayat/garansi_service'
        );
        return view('template', $data);
    }


    public function garansi_service_ajax()
    {
        $request = \Config\Services::request();

        $draw = (int) ($request->getPost('draw') ?? 0);
        $start = max(0, (int) ($request->getPost('start') ?? 0));
        $length = max(0, (int) ($request->getPost('length') ?? 10));
        $search = $request->getPost('search');
        $searchValue = is_array($search) ? (string) ($search['value'] ?? '') : '';
        $order = $request->getPost('order');
        $orderColumnIndex = is_array($order) && isset($order[0]['column']) ? (int) $order[0]['column'] : 2;
        $orderDir = is_array($order) && isset($order[0]['dir']) ? (string) $order[0]['dir'] : 'desc';
        $startDate = (string) ($request->getPost('startDate') ?? '');
        $endDate = (string) ($request->getPost('endDate') ?? '');
        $unitFilter = $this->isHO() ? '' : (int) session('ID_UNIT');

        $result = $this->ServiceModel->getGaransiServiceServerSide(
            $start,
            $length,
            $searchValue,
            $orderColumnIndex,
            $orderDir,
            $startDate,
            $endDate,
            $unitFilter
        );

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Jakarta'));
        foreach ($result['data'] as $row) {
            $end = GaransiClaimService::warrantyEnd($row);
            $row->warranty_end = $end ? $end->format('Y-m-d') : null;
            $row->claimed = GaransiClaimService::isClaimed($row);
            $row->claimable = GaransiClaimService::isClaimable($row, $today);
            $row->status_garansi = GaransiClaimService::statusText($row, $today);
            $row->riwayat_klaim = $row->claimed
                ? 'Klaim pada ' . date('d-m-Y', strtotime((string) $row->tanggal_claim_garansi))
                : 'Tidak ada riwayat';

            // Nilai ini juga masuk ke template literal JS (tombol WhatsApp),
            // jadi netralkan backtick dan ${ agar tidak dieksekusi browser.
            $row->no_service = str_replace(['`', '$'], ['&#96;', '&#36;'], esc((string) ($row->no_service ?? '')));
            $row->nama_pelanggan = str_replace(['`', '$'], ['&#96;', '&#36;'], esc((string) ($row->nama_pelanggan ?? '')));
            $row->no_hp = str_replace(['`', '$'], ['&#96;', '&#36;'], esc((string) ($row->no_hp ?? '')));
            $row->alamat = str_replace(['`', '$'], ['&#96;', '&#36;'], esc((string) ($row->alamat ?? '')));
        }

        return $this->response->setJSON([
            'draw' => intval($draw),
            'recordsTotal' => $this->ServiceModel->getGaransiServiceTotal($unitFilter),
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data']
        ]);
    }


    public function getRiwayatKlaim($idservice)
    {
        $idservice = (int) $idservice;
        $service = $this->claimedServiceForUser($idservice);

        if ($service === null) {
            return $this->response->setJSON([
                'html' => '<div class="alert alert-info">Data klaim tidak ditemukan.</div>'
            ]);
        }

        $riwayat = $this->RiwayatKlaimGaransiModel->getByService($idservice);

        if (empty($riwayat)) {
            return $this->response->setJSON([
                'html' => '<div class="alert alert-info">Belum ada klaim garansi untuk service ini.</div>'
            ]);
        }

        $html = '<table class="table table-sm table-bordered">
                <thead><tr><th>#</th><th>Tanggal Klaim</th><th>Catatan</th></tr></thead><tbody>';

        foreach ($riwayat as $i => $row) {
            $tanggal = $row->tanggal_claim ? date('d-m-Y', strtotime($row->tanggal_claim)) : '-';
            $html .= '<tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . esc($tanggal) . '</td>
                    <td>' . esc($row->catatan ?? '-') . '</td>
                </tr>';
        }

        $html .= '</tbody></table>';

        return $this->response->setJSON(['html' => $html]);
    }


    public function claim_detail($idservice)
    {
        $idservice = (int) $idservice;
        $service = $this->claimableServiceForUser($idservice);

        if ($service === null) {
            return redirect()->to(base_url('garansi_service'))
                ->with('gagal', 'Service garansi tidak ditemukan atau sudah tidak bisa diklaim.');
        }

        $oldkerusakan = $this->ServiceKerusakanModel->getSerModelServiceKerusakanByServiceId($idservice);
        $serviceSparepart = $this->ServiceSparepartModel->getSerModelServiceSparepartByServiceId($idservice);

        $fungsiMap = [];
        foreach ($this->KerusakanModel->getKerusakan() as $fungsi) {
            $fungsiMap[(int) $fungsi->idfungsi] = $fungsi->nama_fungsi;
        }

        $unitId = (int) $service->unit_idunit;
        $sparepart = array_values(array_filter(
            $this->StokBarangModel->getSparepart(),
            static fn ($barang) => (int) ($barang->id_unit ?? 0) === $unitId
                && (int) ($barang->stok_akhir ?? 0) > 0
        ));

        $sparepartIds = array_map(static fn ($barang) => (int) ($barang->idbarang ?? 0), $sparepart);
        $hppMap = [];
        if ($sparepartIds !== []) {
            foreach ($this->HppBarangModel->whereIn('idbarang', $sparepartIds)->findAll() as $hpp) {
                $hppMap[(int) $hpp->idbarang] = (int) ($hpp->hpp ?? 0);
            }
        }
        $supplierMap = $this->BarangModel->getSuplierPerUnit($unitId)[$unitId] ?? [];
        foreach ($sparepart as $barang) {
            $id = (int) $barang->idbarang;
            $barang->hpp = $hppMap[$id] ?? 0;
            $barang->nama_suplier = $supplierMap[$id] ?? '';
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Jakarta'));

        return view('template', [
            'service' => $service,
            'oldkerusakan' => $oldkerusakan,
            'service_sparepart' => $serviceSparepart,
            'fungsiMap' => $fungsiMap,
            'sparepart' => $sparepart,
            'warranty_end' => GaransiClaimService::warrantyEnd($service),
            'status_garansi' => GaransiClaimService::statusText($service, $today),
            'body' => 'transaksi/table_garansi/claim_detail',
        ]);
    }

    public function claim_garansi()
    {
        $idservice = (int) $this->request->getPost('idservice');
        $catatan = trim((string) $this->request->getPost('catatan'));
        $produkData = $this->request->getPost('produk');
        $produkData = is_array($produkData) ? $produkData : [];
        $barangRusakData = $this->request->getPost('barang_rusak');
        $barangRusakData = is_array($barangRusakData) ? $barangRusakData : [];

        if ($idservice <= 0) {
            return redirect()->to(base_url('garansi_service'))->with('gagal', 'Service tidak valid.');
        }

        if ($catatan === '') {
            return redirect()->to(base_url('claim_garansi/detail/' . $idservice))
                ->with('gagal', 'Catatan klaim wajib diisi.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $serviceTable = $db->prefixTable('service');
            $service = $db->query("SELECT * FROM {$serviceTable} WHERE idservice = ? FOR UPDATE", [$idservice])->getRow();

            if ($service === null) {
                throw new \RuntimeException('Service tidak ditemukan.');
            }

            if (!$this->isHO() && (int) $service->unit_idunit !== (int) session('ID_UNIT')) {
                throw new \RuntimeException('Service ini bukan milik unit Anda.');
            }

            $sudahAdaRiwayat = $db->table('riwayat_claim_garansi')
                ->where('service_idservice', $idservice)
                ->countAllResults() > 0;

            if (GaransiClaimService::isClaimed($service) || $sudahAdaRiwayat) {
                throw new \RuntimeException('Garansi service ini sudah pernah diklaim.');
            }

            if (!GaransiClaimService::isClaimable($service)) {
                throw new \RuntimeException('Garansi service ini belum aktif atau sudah kadaluarsa.');
            }

            $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));

            $this->simpanSparepartKlaim($db, $service, $produkData);
            $this->simpanBarangRusakKlaim($db, $service, $barangRusakData, $wibTime);

            $db->table('service')->where('idservice', $idservice)->update([
                'status_service' => 2,
                'tanggal_bisa_diambil' => null,
                'tanggal_selesai' => null,
                'status_proses' => 1,
                'tanggal_claim_garansi' => $wibTime->format('Y-m-d H:i:s'),
                'total_service_garansi' => 0,
                'total_diskon_garansi' => 0,
                'biaya_tambahan_garansi' => 0,
                'harus_dibayar_garansi' => 0,
            ]);
            $db->table('proses_service')->where('service_idservice', $idservice)->delete();
            $db->table('riwayat_claim_garansi')->insert([
                'service_idservice' => $idservice,
                'tanggal_claim' => $wibTime->format('Y-m-d'),
                'catatan' => $catatan,
            ]);

            if (!$db->transStatus()) {
                throw new \RuntimeException('Gagal menyimpan klaim garansi.');
            }

            $db->transCommit();

            session()->setFlashData('sukses', 'Berhasil claim garansi service');

            return redirect()->to(base_url('garansi_service'));
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'claim_garansi service ' . $idservice . ': ' . $e->getMessage());

            return redirect()->to(base_url('claim_garansi/detail/' . $idservice))->with('gagal', $e->getMessage());
        }
    }

    public function service_by_garansi($idservice)
    {
        $idservice = (int) $idservice;
        $service = $this->claimedServiceForUser($idservice);

        if ($service === null) {
            throw PageNotFoundException::forPageNotFound('Service garansi tidak ditemukan.');
        }

        $unitId = (int) $service->unit_idunit;
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $oldkerusakan = $this->ServiceKerusakanModel->getSerModelServiceKerusakanByServiceId($idservice);
        $oldsparepart = $this->ServiceSparepartModel->getSerModelServiceSparepartByServiceId($idservice);
        $lama_garansi = $this->ServiceSparepartModel->getGaransiHariByServiceId($idservice);
        $teknisi = $this->AuthModel->getdataakun();
        $sparepart = array_values(array_filter(
            $this->StokBarangModel->getSparepart(),
            static fn ($barang) => (int) ($barang->id_unit ?? 0) === $unitId
                && (int) ($barang->stok_akhir ?? 0) > 0
        ));

        $data =  array(
            'akun' => $akun,
            'teknisi' => $teknisi,
            'fungsi' => $this->KerusakanModel->getKerusakan(),
            'idservice' => $idservice,
            'old_service_pelanggan' => $this->ServiceModel->getByIdWithPelanggan($idservice),
            'oldkerusakan' => $oldkerusakan,
            'pembayaran_lama' => $this->PembayaranBankModel->getByServiceGaransi($idservice),
            'oldsparepart' => $oldsparepart,
            'bank' => $this->BankModel->getBankDalamScopeUnit($unitId),
            'lama_garansi' => $lama_garansi ? (int)$lama_garansi->garansi_hari : null,
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'sparepart' => $sparepart,
            'provinsi' => $this->RegionModel->getProvinces(),
            'body'  => 'transaksi/table_garansi/service'
        );
        return view('template', $data);
    }

    public function update_service_pelanggan_garansi()
    {
        $idservice = (int) $this->request->getPost('idservice');

        if ($this->claimedServiceForUser($idservice) === null) {
            return redirect()->to(base_url('garansi_service'))->with('gagal', 'Service garansi tidak ditemukan.');
        }

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
        return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=kerusakan'))->with('sukses', 'Data kerusakan berhasil diperbarui.');
    }


    public function insert_kerusakan_garansi()
    {
        $fungsiTerpilih = $this->request->getPost('fungsi');
        $fungsiTerpilih = is_array($fungsiTerpilih) ? $fungsiTerpilih : [];
        $keteranganInput = $this->request->getPost('keterangan');
        $keteranganInput = is_array($keteranganInput) ? $keteranganInput : [];
        $idservice = (int) $this->request->getPost('idservice_k');

        if ($this->claimedServiceForUser($idservice) === null) {
            return redirect()->to(base_url('garansi_service'))->with('gagal', 'Service garansi tidak ditemukan.');
        }

        if (empty($fungsiTerpilih)) {
            return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=sparepart'))->with('gagal', 'Tidak ada kerusakan yang dipilih.');
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

        return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=sparepart'))->with('sukses', 'Data sparepart berhasil diperbarui.');
    }


    public function insert_sparepart_garansi()
    {
        $idservice = (int) $this->request->getPost('idservice_s');
        $service = $this->claimedServiceForUser($idservice);

        if ($service === null) {
            return redirect()->to(base_url('garansi_service'))->with('gagal', 'Service garansi tidak ditemukan.');
        }

        $produkData = $this->request->getPost('produk');
        $produkData = is_array($produkData) ? $produkData : [];
        $biayaTambahan = $this->rupiahToInt($this->request->getPost('biaya_garansi'));
        $unitId = (int) $service->unit_idunit;
        $db = Database::connect();
        $db->transBegin();

        try {
            $existingMap = [];
            foreach ($this->ServiceSparepartModel->getByServiceId($idservice) as $item) {
                $existingMap[(int) $item->barang_idbarang] = $item;
            }

            $submittedIds = [];
            $totalKotor = 0;
            $totalDiskon = 0;

            foreach ($produkData as $produk) {
                if (!is_array($produk)) {
                    continue;
                }

                $id = (int) ($produk['id'] ?? 0);
                if ($id <= 0 || in_array($id, $submittedIds, true)) {
                    continue;
                }

                $jumlahTambahan = max(0, (int) ($produk['qty_tambahan'] ?? 0));
                $harga = max(0, $this->rupiahToInt($produk['harga'] ?? ''));
                $diskonItem = max(0, $this->rupiahToInt($produk['diskon_penjualan_garansi'] ?? ''));
                $diskonItem = min($diskonItem, $harga * $jumlahTambahan);
                $totalKotor += $harga * $jumlahTambahan;
                $totalDiskon += $diskonItem;

                $dataHpp = $this->HppBarangModel->getById($id);
                if ($dataHpp === null) {
                    throw new \RuntimeException('Sparepart tidak ditemukan.');
                }

                $stokUnit = $this->StokBarangModel->where('idbarang', $id)->where('id_unit', $unitId)->first();
                if ($stokUnit === null) {
                    throw new \RuntimeException('Sparepart tidak tersedia di unit ini.');
                }

                $dataStokAwal = $this->StokAwalModel->getByIdBarang($id);
                $datas = [
                    'jumlah_tambahan_garansi' => $jumlahTambahan,
                    'harga_penjualan_garansi' => $harga,
                    'sub_total_garansi' => max(0, $harga * $jumlahTambahan - $diskonItem),
                    'hpp_penjualan_garansi' => (int) ($dataHpp->hpp ?? 0),
                    'satuan_jual' => $dataStokAwal->satuan_terkecil ?? 'pcs',
                    'diskon_penjualan_garansi' => $diskonItem,
                    'service_idservice' => $idservice,
                    'barang_idbarang' => $id,
                    'unit_idunit' => $unitId,
                ];

                $submittedIds[] = $id;

                if (isset($existingMap[$id])) {
                    $this->ServiceSparepartModel->updateByServiceAndBarang($idservice, $id, $datas);
                } else {
                    // Sparepart yang hanya dipakai untuk garansi tidak boleh
                    // menambah qty/harga penjualan normal pada invoice asal.
                    $this->ServiceSparepartModel->insert_SerModelServiceSparepart($datas + [
                        'jumlah' => 0,
                        'harga_penjualan' => 0,
                        'sub_total' => 0,
                        'diskon_penjualan' => 0,
                    ]);
                }
            }

            foreach ($existingMap as $barangId => $item) {
                if (in_array($barangId, $submittedIds, true)) {
                    continue;
                }

                if ((int) ($item->jumlah ?? 0) === 0 && (int) ($item->harga_penjualan ?? 0) === 0) {
                    $this->ServiceSparepartModel->deleteByServiceAndBarang($idservice, $barangId);
                    continue;
                }

                // Baris ini milik service normal. Jangan hapus; hanya bersihkan
                // tambahan garansinya supaya invoice asal tidak ikut hilang.
                $this->ServiceSparepartModel->updateByServiceAndBarang($idservice, $barangId, [
                    'jumlah_tambahan_garansi' => 0,
                    'harga_penjualan_garansi' => 0,
                    'sub_total_garansi' => 0,
                    'hpp_penjualan_garansi' => 0,
                    'diskon_penjualan_garansi' => 0,
                ]);
            }

            // shortcut: pemakaian sparepart garansi belum membuat mutasi stok
            // keluar; sambungkan ke alur penjualan/sparepart keluar saat
            // akuntansi stok garansi disepakati.
            $totals = GaransiClaimService::totals([[
                'harga' => $totalKotor,
                'qty' => 1,
                'diskon' => $totalDiskon,
            ]], $biayaTambahan);

            $this->ServiceModel->updateService($idservice, [
                'biaya_tambahan_garansi' => $totals['biaya'],
                'total_service_garansi' => $totals['total'],
                'total_diskon_garansi' => $totals['diskon'],
                'harus_dibayar_garansi' => $totals['due'],
            ]);

            if (!$db->transStatus()) {
                throw new \RuntimeException('Gagal menyimpan sparepart garansi.');
            }

            $db->transCommit();

            return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=pembayaran'))->with('sukses', 'Data pembayaran berhasil disimpan.');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'insert_sparepart_garansi service ' . $idservice . ': ' . $e->getMessage());

            return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=sparepart'))->with('gagal', $e->getMessage());
        }
    }


    public function insert_pembayaran_garansi()
    {
        $idservice = (int) $this->request->getPost('idservice_p');
        $service = $this->claimedServiceForUser($idservice);

        if ($service === null) {
            return redirect()->to(base_url('garansi_service'))->with('gagal', 'Service garansi tidak ditemukan.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $serviceTable = $db->prefixTable('service');
            $service = $db->query("SELECT * FROM {$serviceTable} WHERE idservice = ? FOR UPDATE", [$idservice])->getRow();

            if ($service === null || !GaransiClaimService::isClaimed($service)) {
                throw new \RuntimeException('Service garansi tidak ditemukan.');
            }

            if (!$this->isHO() && (int) $service->unit_idunit !== (int) session('ID_UNIT')) {
                throw new \RuntimeException('Service ini bukan milik unit Anda.');
            }

            $baris = $db->table('service_sparepart')
                ->select('COALESCE(SUM(harga_penjualan_garansi * jumlah_tambahan_garansi), 0) AS kotor, COALESCE(SUM(diskon_penjualan_garansi), 0) AS diskon', false)
                ->where('service_idservice', $idservice)
                ->where('jumlah_tambahan_garansi >', 0)
                ->get()
                ->getRow();

            // Biaya tambahan adalah hasil langkah sparepart yang sudah tersimpan.
            // Jangan percaya nominal dari form pembayaran.
            $biaya = max(0, (int) ($service->biaya_tambahan_garansi ?? 0));
            $totals = GaransiClaimService::totals([[
                'harga' => (int) ($baris->kotor ?? 0),
                'qty' => 1,
                'diskon' => (int) ($baris->diskon ?? 0),
            ]], $biaya);

            $oldCash = (int) ($service->bayar_tunai_garansi ?? 0);
            $oldBankRow = $db->table('pembayaran_bank')
                ->selectSum('jumlah', 'total')
                ->where('tabel_referensi', 'service_garansi_1')
                ->where('id_referensi', $idservice)
                ->get()
                ->getRow();
            $oldBankTotal = (int) ($oldBankRow->total ?? 0);
            $paidBefore = max(
                (int) ($service->bayar_garansi ?? 0),
                $oldCash + $oldBankTotal
            );

            $cash = max(0, $this->rupiahToInt($this->request->getPost('bayar_pembayaran')));
            $bankData = $this->request->getPost('bank');
            $bankData = is_array($bankData) ? $bankData : [];

            $allowedBanks = [];
            foreach ($this->BankModel->getBankDalamScopeUnit((int) $service->unit_idunit) as $bank) {
                $allowedBanks[(string) $bank->idbank] = true;
            }

            $kodePembayaran = trim((string) ($service->bayar_bank ?? ''));
            if ($kodePembayaran === '') {
                $kodePembayaran = 'ksr' . preg_replace('/\D/', '', (string) $service->no_service);
            }

            $bankRows = [];
            $totalBank = 0;

            foreach ($bankData as $bank) {
                if (!is_array($bank)) {
                    continue;
                }

                $jumlah = (int) $this->sanitizeCurrency($bank['jumlah'] ?? '0');
                if ($jumlah <= 0) {
                    continue;
                }

                $bankId = (string) ($bank['id'] ?? '');
                if ($bankId === '' || !isset($allowedBanks[$bankId])) {
                    throw new \RuntimeException('Rekening pembayaran tidak valid untuk unit ini.');
                }

                $bankRows[] = [
                    'kode_pembayaran' => $kodePembayaran,
                    'bank_idbank' => $bankId,
                    'jumlah' => $jumlah,
                    'tabel_referensi' => 'service_garansi_1',
                    'id_referensi' => $idservice,
                ];
                $totalBank += $jumlah;
            }

            $due = $totals['due'];
            $sisa = max(0, $due - $paidBefore);

            // Transfer tidak boleh melebihi sisa tagihan. Kelebihan tunai
            // dianggap kembalian, jadi yang dicatat hanya yang menutup tagihan.
            if ($totalBank > $sisa) {
                throw new \RuntimeException('Pembayaran transfer melebihi sisa tagihan garansi.');
            }
            $cash = min($cash, max(0, $sisa - $totalBank));
            $newPaid = $cash + $totalBank;

            foreach ($bankRows as $bankRow) {
                $this->PembayaranBankModel->insertPembayaranBank($bankRow);
            }

            $this->ServiceModel->updateService($idservice, [
                'service_by_garansi' => (int) $this->request->getPost('service_by_pembayaran'),
                'total_service_garansi' => $totals['total'],
                'total_diskon_garansi' => $totals['diskon'],
                'biaya_tambahan_garansi' => $totals['biaya'],
                'harus_dibayar_garansi' => $totals['due'],
                'bayar_garansi' => $paidBefore + $newPaid,
                'bayar_tunai_garansi' => max($oldCash, $paidBefore - $oldBankTotal) + $cash,
            ]);

            if (!$db->transStatus()) {
                throw new \RuntimeException('Gagal menyimpan pembayaran garansi.');
            }

            $db->transCommit();

            session()->remove('idservice');
            session()->setFlashdata('sukses', 'Berhasil Menambahkan Data');

            return redirect()->to(base_url('/proses_service'));
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'insert_pembayaran_garansi service ' . $idservice . ': ' . $e->getMessage());

            return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=pembayaran'))->with('gagal', $e->getMessage());
        }
    }


    private function simpanSparepartKlaim($db, object $service, array $produkData): void
    {
        $idservice = (int) $service->idservice;
        $unitId = (int) $service->unit_idunit;

        $existingMap = [];
        foreach ($db->table('service_sparepart')->where('service_idservice', $idservice)->get()->getResult() as $item) {
            $existingMap[(int) $item->barang_idbarang] = $item;
        }

        $submitted = [];

        foreach ($produkData as $produk) {
            if (!is_array($produk)) {
                continue;
            }

            $id = (int) ($produk['id'] ?? 0);
            $qty = max(0, (int) ($produk['qty'] ?? 0));

            if ($id <= 0 || $qty <= 0 || isset($submitted[$id])) {
                continue;
            }

            $stokUnit = $this->StokBarangModel->where('idbarang', $id)->where('id_unit', $unitId)->first();
            if ($stokUnit === null) {
                throw new \RuntimeException('Sparepart tidak tersedia di unit ini.');
            }

            // shortcut: stok dibaca tanpa lock barang; dua klaim berbeda untuk part yang sama bisa oversell. Upgrade saat konkurensi klaim nyata.
            if ((int) ($stokUnit->stok_akhir ?? 0) < $qty) {
                throw new \RuntimeException('Stok sparepart tidak cukup.');
            }

            $dataStokAwal = $this->StokAwalModel->getByIdBarang($id);
            $data = [
                'jumlah_tambahan_garansi' => $qty,
                'harga_penjualan_garansi' => 0,
                'sub_total_garansi' => 0,
                'diskon_penjualan_garansi' => 0,
                'hpp_penjualan_garansi' => 0,
                'satuan_jual' => $dataStokAwal->satuan_terkecil ?? 'pcs',
                'service_idservice' => $idservice,
                'barang_idbarang' => $id,
                'unit_idunit' => $unitId,
            ];

            $submitted[$id] = true;

            if (isset($existingMap[$id])) {
                $db->table('service_sparepart')
                    ->where('idservice_sparepart', $existingMap[$id]->idservice_sparepart)
                    ->update($data);
            } else {
                $db->table('service_sparepart')->insert($data + [
                    'jumlah' => 0,
                    'harga_penjualan' => 0,
                    'sub_total' => 0,
                    'diskon_penjualan' => 0,
                ]);
            }
        }

        foreach ($existingMap as $barangId => $item) {
            if (isset($submitted[$barangId])) {
                continue;
            }

            if ((int) ($item->jumlah ?? 0) === 0 && (int) ($item->harga_penjualan ?? 0) === 0) {
                $db->table('service_sparepart')
                    ->where('idservice_sparepart', $item->idservice_sparepart)
                    ->delete();
                continue;
            }

            // Baris normal tetap disimpan; hanya tambahan garansinya dibersihkan.
            $db->table('service_sparepart')
                ->where('idservice_sparepart', $item->idservice_sparepart)
                ->update([
                    'jumlah_tambahan_garansi' => 0,
                    'harga_penjualan_garansi' => 0,
                    'sub_total_garansi' => 0,
                    'diskon_penjualan_garansi' => 0,
                    'hpp_penjualan_garansi' => 0,
                ]);
        }
    }

    private function simpanBarangRusakKlaim($db, object $service, array $barangRusakData, \DateTime $wibTime): void
    {
        $seen = [];

        foreach ($barangRusakData as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (int) ($item['id'] ?? 0);
            $qty = max(0, (int) ($item['qty'] ?? 0));

            if ($id <= 0 || $qty <= 0 || isset($seen[$id])) {
                continue;
            }

            $barang = $db->table('barang')
                ->where('idbarang', $id)
                ->where('deleted', '0')
                ->get()
                ->getRow();

            if ($barang === null) {
                throw new \RuntimeException('Barang rusak tidak ditemukan.');
            }

            $seen[$id] = true;

            $db->table('barang_rusak_claim')->insert([
                'service_idservice' => (int) $service->idservice,
                'barang_idbarang' => $id,
                'jumlah' => $qty,
                'keterangan' => trim((string) ($item['keterangan'] ?? '')),
                'unit_idunit' => (int) $service->unit_idunit,
                'input_by' => (int) session('ID_AKUN'),
                'created_at' => $wibTime->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function claimableServiceForUser(int $idservice): ?object
    {
        if ($idservice <= 0) {
            return null;
        }

        $service = $this->ServiceModel->getByIdWithPelanggan($idservice);
        if ($service === null || !GaransiClaimService::isClaimable($service)) {
            return null;
        }

        if (!$this->isHO() && (int) $service->unit_idunit !== (int) session('ID_UNIT')) {
            return null;
        }

        return $service;
    }

    private function claimedServiceForUser(int $idservice): ?object
    {
        if ($idservice <= 0) {
            return null;
        }

        $service = $this->ServiceModel->getServiceById($idservice);
        if ($service === null || !GaransiClaimService::isClaimed($service)) {
            return null;
        }

        if (!$this->isHO() && (int) $service->unit_idunit !== (int) session('ID_UNIT')) {
            return null;
        }

        return $service;
    }


    private function isHO(): bool
    {
        $idJabatan = session()->get('ID_JABATAN');

        return in_array($idJabatan, [0, 1, 42, '0', '1', '42'], true);
    }


    function sanitizeCurrency($value)
    {
        if (!is_scalar($value)) {
            return 0.0;
        }

        $cleaned = str_replace(['Rp', '.', ' '], '', (string) $value);

        return (float) $cleaned;
    }

    function rupiahToInt($rupiah)
    {
        if (!is_scalar($rupiah)) {
            return 0;
        }

        $cleaned = str_replace(['Rp', '.', ' '], '', (string) $rupiah);

        return (int) preg_replace('/[^0-9]/', '', $cleaned);
    }
}
