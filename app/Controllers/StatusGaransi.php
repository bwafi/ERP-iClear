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
use App\Services\Service\ServiceHppSnapshot;
use App\Services\Service\WarrantyClaimEligibility;
use App\Services\Finance\WarrantyExpenseJournal;



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

        $draw = $request->getPost('draw');
        $start = $request->getPost('start') ?? 0;
        $length = $request->getPost('length') ?? 10;
        $searchValue = $request->getPost('search')['value'] ?? '';
        $orderColumnIndex = $request->getPost('order')[0]['column'] ?? 1;
        $orderDir = $request->getPost('order')[0]['dir'] ?? 'desc';

        $startDate = $request->getPost('startDate') ?? '';
        $endDate = $request->getPost('endDate') ?? '';

        $result = $this->ServiceModel->getGaransiServiceServerSide(
            $start,
            $length,
            $searchValue,
            $orderColumnIndex,
            $orderDir,
            $startDate,
            $endDate
        );

        $recordsTotal = $this->ServiceModel->getRiwayatServiceTotal();

        return $this->response->setJSON([
            'draw' => intval($draw),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $result['data']
        ]);
    }


    public function getRiwayatKlaim($idservice)
    {
        $idservice = (int) $idservice;
        $service = $this->ServiceModel->find($idservice);
        if (! $service || ((int) session('ID_JABATAN') !== 1
            && (int) ($service->unit_idunit ?? 0) !== (int) session('ID_UNIT'))) {
            return $this->response->setStatusCode(403)->setJSON(['html' => 'Akses ditolak.']);
        }
        $riwayat = $this->RiwayatKlaimGaransiModel->getByService($idservice);

        if (empty($riwayat)) {
            return $this->response->setJSON([
                'html' => '<div class="alert alert-info">Belum ada klaim garansi untuk service ini.</div>'
            ]);
        }

        $db = Database::connect();
        $canSeeHpp = (int) session('ID_JABATAN') === 1;
        $issuesByClaim = [];
        if ($db->tableExists($db->getPrefix() . 'garansi_sparepart_keluar', false)) {
            $issues = $db->table('garansi_sparepart_keluar issue')
                ->select('issue.claim_id, issue.jumlah, issue.hpp_satuan, barang.nama_barang')
                ->join('barang', 'barang.idbarang = issue.barang_id')
                ->where('issue.service_id', $idservice)
                ->orderBy('issue.id')->get()->getResult();
            foreach ($issues as $issue) {
                $issuesByClaim[(int) $issue->claim_id][] = $issue;
            }
        }

        $html = '<table class="table table-sm table-bordered">
                <thead><tr><th>#</th><th>ID Klaim</th><th>Tanggal Klaim</th><th>Sparepart Pengganti</th></tr></thead><tbody>';

        foreach ($riwayat as $i => $row) {
            $html .= '<tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . (int) $row->idriwayat_claim_garansi . '</td>
                    <td>' . date('d-m-Y', strtotime($row->tanggal_claim)) . '</td>
                    <td>';
            foreach ($issuesByClaim[(int) $row->idriwayat_claim_garansi] ?? [] as $issue) {
                $html .= esc($issue->nama_barang) . ' × ' . (int) $issue->jumlah;
                if ($canSeeHpp) {
                    $html .= ' — HPP Rp ' . number_format((int) $issue->hpp_satuan, 0, ',', '.');
                }
                $html .= '<br>';
            }
            $html .= '</td>
                </tr>';
        }

        $html .= '</tbody></table>';

        return $this->response->setJSON(['html' => $html]);
    }


    public function claim_garansi()
    {
        $idservice = (int) $this->request->getPost('idservice');
        if ($idservice <= 0) {
            return redirect()->to(base_url('garansi_service'))->with('gagal', 'Service tidak valid.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $serviceTable = $db->prefixTable('service');
            $service = $db->query(
                "SELECT unit_idunit, status_service, garansi_hari, tanggal_selesai FROM {$serviceTable} WHERE idservice = ? FOR UPDATE",
                [$idservice]
            )->getRow();
            $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Jakarta'));
            if (! $service || (int) session('ID_UNIT') <= 0
                || (int) $service->unit_idunit !== (int) session('ID_UNIT')
                || ! WarrantyClaimEligibility::allows($service, $today)) {
                throw new \RuntimeException('Service tidak memenuhi syarat klaim garansi.');
            }
            $untracked = $db->query(
                'SELECT COUNT(*) AS total FROM ' . $db->prefixTable('service_sparepart') . ' detail '
                . 'WHERE detail.service_idservice = ? AND detail.jumlah_tambahan_garansi > 0 '
                . 'AND NOT EXISTS (SELECT 1 FROM ' . $db->prefixTable('garansi_sparepart_keluar') . ' issue '
                . 'WHERE issue.service_id = detail.service_idservice AND issue.barang_id = detail.barang_idbarang)',
                [$idservice]
            )->getRow();
            if ((int) ($untracked->total ?? 0) > 0) {
                throw new \RuntimeException('HPP garansi lama belum memiliki histori mutasi; rekonsiliasi dahulu.');
            }

            $wibTime = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
            $data = array(
                'status_service' => 2,
                'tanggal_bisa_diambil' => null,
                'tanggal_selesai' => null,
                'status_proses' => 1,
                'tanggal_claim_garansi' => $wibTime->format('Y-m-d H:i:s'),
            );
            if (! $this->ServiceModel->updateService($idservice, $data)
                || ! $this->ProsesServiceModel->deleteByServiceId($idservice)
                || ! $db->table('service_sparepart')->where('service_idservice', $idservice)->update([
                    'jumlah_tambahan_garansi' => 0,
                    'hpp_penjualan_garansi' => 0,
                    'harga_penjualan_garansi' => 0,
                    'sub_total_garansi' => 0,
                    'diskon_penjualan_garansi' => 0,
                ])) {
                throw new \RuntimeException('Status klaim garansi gagal disimpan.');
            }

            $data2 = array(
                'service_idservice' => $idservice,
                'tanggal_claim' => $wibTime->format('Y-m-d H:i:s'),
            );
            if (! $this->RiwayatKlaimGaransiModel->insertRiwayat($data2)
                || $db->transStatus() === false
                || ! $db->transCommit()) {
                throw new \RuntimeException('Riwayat klaim garansi gagal disimpan.');
            }

            session()->setFlashData('sukses', 'Berhasil claim garansi service');
            return redirect()->to(base_url('garansi_service'));
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'StatusGaransi::claim_garansi gagal untuk service ' . $idservice . ': ' . $error->getMessage());
            return redirect()->to(base_url('garansi_service'))->with('gagal', 'Klaim garansi tidak dapat diproses. Periksa status dan masa garansi service.');
        }
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
        return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=kerusakan'))->with('sukses', 'Data kerusakan berhasil diperbarui.');
    }


    public function insert_kerusakan_garansi()
    {
        $fungsiTerpilih = $this->request->getPost('fungsi');
        $keteranganInput = $this->request->getPost('keterangan');
        $idservice = $this->request->getPost('idservice_k');

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
        $produkData = $this->request->getPost('produk');
        $idservice = (int) $this->request->getPost('idservice_s');
        if ($idservice <= 0 || ! is_array($produkData)) {
            return redirect()->back()->with('gagal', 'Data sparepart garansi tidak valid.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $service = $db->query(
                'SELECT unit_idunit, status_service FROM ' . $db->prefixTable('service') . ' WHERE idservice = ? FOR UPDATE',
                [$idservice]
            )->getRow();
            if (! $service || (int) session('ID_UNIT') <= 0
                || (int) $service->unit_idunit !== (int) session('ID_UNIT')
                || (int) $service->status_service !== 2) {
                throw new \RuntimeException('Service tidak sesuai unit.');
            }
            $claim = $db->query(
                'SELECT idriwayat_claim_garansi FROM ' . $db->prefixTable('riwayat_claim_garansi')
                . ' WHERE service_idservice = ? ORDER BY idriwayat_claim_garansi DESC LIMIT 1 FOR UPDATE',
                [$idservice]
            )->getRow();
            if (! $claim || ! $db->tableExists($db->getPrefix() . 'garansi_sparepart_keluar', false)) {
                throw new \RuntimeException('Riwayat klaim atau migrasi stok garansi belum tersedia.');
            }

            $existingMap = [];
            foreach ($this->ServiceSparepartModel->getByServiceId($idservice) as $item) {
                $existingMap[(int) $item->barang_idbarang] = $item;
            }

            $submittedIds = [];
            foreach ($produkData as $produk) {
                $id = (int) ($produk['id'] ?? 0);
                $quantity = (int) ($produk['qty_tambahan'] ?? -1);
                if ($id <= 0 || $quantity < 0 || isset($submittedIds[$id])) {
                    throw new \RuntimeException('Sparepart garansi atau jumlahnya tidak valid.');
                }
                $submittedIds[$id] = true;

                $existingItem = $existingMap[$id] ?? null;
                $previousIssue = $db->table('garansi_sparepart_keluar')
                    ->where('claim_id', (int) $claim->idriwayat_claim_garansi)
                    ->where('barang_id', $id)
                    ->get()->getRow();
                $hppBarang = $previousIssue || $quantity === 0 ? null : $this->HppBarangModel->getById($id);
                $hpp = ServiceHppSnapshot::resolveWarrantyClaimIssue(
                    $previousIssue, $quantity, $hppBarang->hpp ?? 0
                );
                if ($quantity > 0) {
                    if ($previousIssue) {
                        $journal = $db->table('jurnal')
                            ->select('COUNT(*) AS jumlah, SUM(debet) AS debet, SUM(kredit) AS kredit', false)
                            ->where('tabel_referensi', 'garansi_sparepart_keluar')
                            ->where('id_referensi', (int) $previousIssue->id)
                            ->get()->getRow();
                        if ((int) ($journal->jumlah ?? 0) !== 2
                            || (int) ($journal->debet ?? 0) !== $quantity * (int) $hpp
                            || (int) ($journal->kredit ?? 0) !== $quantity * (int) $hpp) {
                            throw new \RuntimeException('Mutasi lama belum memiliki jurnal seimbang; perlu rekonsiliasi.');
                        }
                    } else {
                        if ((int) ($existingItem->jumlah_tambahan_garansi ?? 0) > 0 || ! $hppBarang) {
                            throw new \RuntimeException('HPP atau histori stok pengganti belum tersedia.');
                        }
                        $product = $db->query(
                            'SELECT idbarang FROM ' . $db->prefixTable('barang') . ' WHERE idbarang = ? FOR UPDATE',
                            [$id]
                        )->getRow();
                        $stock = $db->table('stok_barang')
                            ->select('stok_akhir')
                            ->where('idbarang', $id)
                            ->where('id_unit', (int) $service->unit_idunit)
                            ->get()->getRow();
                        if (! $product || (int) ($stock->stok_akhir ?? 0) < $quantity) {
                            throw new \RuntimeException('Stok sparepart pengganti tidak mencukupi.');
                        }
                        if (! $db->table('garansi_sparepart_keluar')->insert([
                            'claim_id' => (int) $claim->idriwayat_claim_garansi,
                            'service_id' => $idservice,
                            'barang_id' => $id,
                            'unit_id' => (int) $service->unit_idunit,
                            'jumlah' => $quantity,
                            'hpp_satuan' => (int) $hpp,
                            'created_at' => date('Y-m-d H:i:s'),
                        ])) {
                            throw new \RuntimeException('Mutasi stok garansi gagal disimpan.');
                        }
                        $issueId = (int) $db->insertID();
                        $template = $db->table('template_jurnal')
                            ->select('no_akun, debet_kredit')
                            ->where('kode_template', 'garansi_sparepart_keluar')
                            ->get()->getResultArray();
                        $lines = WarrantyExpenseJournal::lines($template, $quantity * (int) $hpp);
                        $accounts = $db->table('no_akun')
                            ->select('no_akun, nama_akun')
                            ->whereIn('no_akun', array_column($lines, 0))
                            ->get()->getResult();
                        $names = [];
                        foreach ($accounts as $account) {
                            $names[$account->no_akun] = $account->nama_akun;
                        }
                        $inventoryAccount = $lines[0][2] > 0 ? $lines[0][0] : $lines[1][0];
                        if ($issueId <= 0 || count($names) !== 2
                            || ! preg_match('/persediaan|stok|inventory/i', $names[$inventoryAccount])) {
                            throw new \RuntimeException('Akun atau referensi jurnal garansi tidak valid.');
                        }
                        foreach ($lines as $line) {
                            if (! $db->table('jurnal')->insert([
                                'tanggal' => date('Y-m-d'),
                                'no_akun' => $line[0],
                                'nama_akun' => $names[$line[0]],
                                'debet' => $line[1],
                                'kredit' => $line[2],
                                'keterangan' => 'HPP pengganti garansi service ' . $idservice,
                                'id_referensi' => $issueId,
                                'tabel_referensi' => 'garansi_sparepart_keluar',
                                'id_unit' => (int) $service->unit_idunit,
                                'id_akun' => (int) session('ID_AKUN'),
                            ])) {
                                throw new \RuntimeException('Jurnal beban garansi gagal disimpan.');
                            }
                        }
                    }
                }
                $stokAwal = $this->StokAwalModel->getById($id);
                $data = [
                    'jumlah_tambahan_garansi' => $quantity,
                    'harga_penjualan_garansi' => $this->rupiahToInt($produk['harga'] ?? '0'),
                    'sub_total_garansi' => $this->rupiahToInt($produk['total'] ?? '0'),
                    'hpp_penjualan_garansi' => $hpp,
                    'satuan_jual' => $stokAwal->satuan_terkecil ?? 'pcs',
                    'diskon_penjualan_garansi' => $this->rupiahToInt($produk['diskon_penjualan_garansi'] ?? '0'),
                    'service_idservice' => $idservice,
                    'barang_idbarang' => $id,
                    'unit_idunit' => (int) $service->unit_idunit,
                ];

                $saved = $existingItem
                    ? $this->ServiceSparepartModel->updateByServiceAndBarang($idservice, $id, $data)
                    : $this->ServiceSparepartModel->insert_SerModelServiceSparepart($data);
                if (! $saved) {
                    throw new \RuntimeException('Sparepart garansi gagal disimpan.');
                }
            }

            if (array_diff_key($existingMap, $submittedIds)) {
                throw new \RuntimeException('Sparepart service awal tidak boleh dihapus dari garansi.');
            }

            if (! $this->ServiceModel->updateService($idservice, [
                'biaya_tambahan_garansi' => $this->rupiahToInt($this->request->getPost('biaya_garansi')),
            ]) || $db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Data garansi gagal disimpan.');
            }

            return redirect()->to(base_url('service_by_garansi/' . $idservice . '?tab=pembayaran'))
                ->with('sukses', 'Data sparepart garansi berhasil disimpan.');
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'StatusGaransi::insert_sparepart_garansi gagal: ' . $error->getMessage());
            return redirect()->back()->with('gagal', 'Sparepart garansi tidak dapat disimpan. Periksa kelengkapan dan jumlah pengganti.');
        }
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
