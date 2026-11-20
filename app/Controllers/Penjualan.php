<?php

namespace App\Controllers;

use App\Models\ModelBarang;


use App\Models\ModelDetailPenjualan;
use App\Models\ModelKategori;
use App\Models\ModelPembelian;
use App\Models\ModelStokAwal;
use App\Models\ModelDetailPembelian;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Config\Database;
use App\Models\ModelSuplier;
use App\Models\ModelPenjualan;
use App\Models\ModelPelanggan;
use DateTime;
use Mpdf\Mpdf;
use App\Models\ModelAuth;
use App\Models\ModelHppBarang;
use App\Models\ModelHppUnit;
use App\Models\ModelStokBarang;
use App\Models\ModelJurnal;
use App\Models\ModelPenilaianKPI;
use App\Models\ModelPenilaian;
use App\Models\ModelTemplateKpi;
use App\Models\ModelUnit;
use App\Models\ModelBank;
use App\Models\ModelBundle;
use App\Models\ModelDetailBundle;
use App\Models\ModelPembayaranBank;
use App\Models\ModelRegion;
use App\Services\Sales\PenjualanPaymentSplit;

class Penjualan extends BaseController
{
    /**
     * ID_JABATAN yang boleh melihat staff lintas unit pada dropdown Staff.
     *
     * 0 = Finance, 1 = Admin root, 34 = Manager. Tim toko hanya melihat staff
     * unitnya sendiri supaya penjualan tidak diatribusikan ke staff unit lain.
     */
    private const STAFF_SEMUA_UNIT_ROLES = [0, 1, 34];

    protected $BarangModel;
    protected $KategoriModel;
    protected $SuplierModel;
    protected $PenjualanModel;
    protected $StokAwalModel;
    protected $DetailPenjualanModel;
    protected $PelangganModel;
    protected $AuthModel;
    protected $HppBarangModel;
    protected $HppUnitModel;
    protected $StokBarangModel;
    protected $JurnalModel;
    protected $ModelPenilaianKPI;
    protected $ModelPenilaian;
    protected $ModelTemplateKPI;
    protected $UnitModel;
    protected $PenilaianKPIModel;
    protected $PenilaianModel;
    protected $TemplateKpiModel;
    protected $BankModel;
    protected $BundleModel;
    protected $DetailBundelModel;
    protected $PembayaranBankModel;

    protected $RegionModel;

    //ini perubahan baru
    public function __construct()
    {
        $this->BarangModel = new ModelBarang();
        $this->KategoriModel = new ModelKategori();
        $this->SuplierModel = new ModelSuplier();
        $this->PenjualanModel = new ModelPenjualan();
        $this->StokAwalModel = new ModelStokAwal();
        $this->DetailPenjualanModel = new ModelDetailPenjualan();
        $this->PelangganModel = new ModelPelanggan();
        $this->AuthModel = new ModelAuth();
        $this->HppBarangModel = new ModelHppBarang();
        $this->HppUnitModel = new ModelHppUnit();
        $this->StokBarangModel = new ModelStokBarang();
        $this->JurnalModel = new ModelJurnal();
        $this->PenilaianKPIModel = new ModelPenilaianKPI();
        $this->PenilaianModel = new ModelPenilaian();
        $this->TemplateKpiModel = new ModelTemplateKpi();
        $this->UnitModel = new ModelUnit();
        $this->BankModel = new ModelBank();
        $this->BundleModel = new ModelBundle();
        $this->DetailBundelModel = new ModelDetailBundle();
        $this->PembayaranBankModel = new ModelPembayaranBank();
        $this->RegionModel = new ModelRegion();
    }

    /**
     * Apakah role ini boleh melihat staff dari semua unit.
     */
    public static function bolehPilihStaffLintasUnit(int $role): bool
    {
        return in_array($role, self::STAFF_SEMUA_UNIT_ROLES, true);
    }

    /**
     * Unit filter dropdown Staff. null berarti semua unit (role lintas unit).
     * Tim toko selalu memakai unit sesinya dan tidak boleh memilih unit lain.
     */
    public static function resolveStaffUnit(int $role, int $unitSesi): ?int
    {
        return self::bolehPilihStaffLintasUnit($role) ? null : $unitSesi;
    }

    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));
        $staffUnit = self::resolveStaffUnit((int) session('ID_JABATAN'), (int) session('ID_UNIT'));
        $unitId = (int) session('ID_UNIT');

        // HPP dan suplier hanya untuk tab Produk di picker. HPP memakai
        // resolver yang sama dengan transaksi (hpp_unit -> hpp_barang ->
        // harga_beli); suplier berasal dari stok awal terbaru unit ini.
        $produk = $this->StokBarangModel->getAllBarang2();
        $hppUnit = $this->HppUnitModel->resolveMany(
            array_map(static fn ($p) => (int) $p->idbarang, $produk),
            $unitId
        );
        $suplierUnit = $this->BarangModel->getSuplierPerUnit($unitId)[$unitId] ?? [];
        foreach ($produk as $p) {
            $p->hpp_unit = $hppUnit[(int) $p->idbarang] ?? (float) $p->harga_beli;
            $p->nama_suplier = $suplierUnit[(int) $p->idbarang] ?? '';
        }

        $data = array(
            'akun' => $akun,
            'produk' => $produk,
            'jasa' => $this->StokBarangModel->getJasa(),
            'frontliner' => $this->AuthModel->getdataakun($staffUnit),
            'bank' => $this->BankModel->getBankDalamScopeUnit((int) session('ID_UNIT')),
            'kategori' => $this->KategoriModel->getKategori(),
            'suplier' => $this->SuplierModel->getSuplier(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'bundle' => $this->BundleModel->getBundleWithDetail(),
            'body' => 'transaksi/penjualan',
            'provinsi' => $this->RegionModel->getProvinces()
        );

        return view('template', $data);
    }

    public function getKabupaten($provinsi)
    {
        $provinsi = urldecode($provinsi);

        $data = $this->RegionModel->getRegenciesByProvinceName($provinsi);

        return $this->response->setJSON($data);
    }


    public function getKecamatan($kabupaten)
    {
        $kabupaten = urldecode($kabupaten);

        $data = $this->RegionModel->getDistrictsByRegencyName($kabupaten);

        return $this->response->setJSON($data);
    }

    public function insert_penjualan()
    {
        $produkData = $this->request->getPost('produk');
        $total_penjualan = $this->sanitizeCurrency($this->request->getPost('total-harga'));

        if (!is_array($produkData) || $produkData === []) {
            session()->setFlashdata('gagal', 'Pilih minimal satu produk sebelum menyimpan penjualan.');
            return redirect()->to(base_url('/penjualan'));
        }

        if ($total_penjualan <= 0) {
            session()->setFlashdata('gagal', 'Total penjualan harus lebih besar dari nol.');
            return redirect()->to(base_url('/penjualan'));
        }

        // Staff yang diatribusikan harus berasal dari daftar yang tampil di
        // halaman: tim toko hanya unitnya sendiri, sedangkan Finance/Admin
        // root/Manager boleh memakai staff lintas unit.
        $sales_by = $this->request->getPost('sales_by');
        $staffUnit = self::resolveStaffUnit((int) session('ID_JABATAN'), (int) session('ID_UNIT'));
        $staffIds = array_map(
            static fn ($row) => (int) $row->ID_AKUN,
            $this->AuthModel->getdataakun($staffUnit)
        );

        if ((int) $sales_by <= 0 || !in_array((int) $sales_by, $staffIds, true)) {
            session()->setFlashdata('gagal', 'Staff yang dipilih tidak tersedia untuk unit ini.');
            return redirect()->to(base_url('/penjualan'));
        }

        // Rekening yang boleh dipakai harus sama dengan yang tampil di halaman:
        // rekening unit sesi + shared yang dialokasikan, tanpa Finance/HO.
        $allowedBankIds = [];
        foreach ($this->BankModel->getBankDalamScopeUnit((int) session('ID_UNIT')) as $bank) {
            $allowedBankIds[] = (string) $bank->idbank;
        }

        $pembayaran = (new PenjualanPaymentSplit())->normalize($this->request->getPost(), $allowedBankIds);

        if ($pembayaran['errors'] !== []) {
            session()->setFlashdata('gagal', $pembayaran['errors'][0]);
            return redirect()->to(base_url('/penjualan'));
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $datauser = $this->AuthModel->getById(session('ID_AKUN'));
        $useridunit = $datauser->ID_UNIT;
        $namauser = $datauser->NAMA_AKUN;

        //
        $tanggal = $this->request->getPost('tanggal_masuk');
        date_default_timezone_set('Asia/Jakarta');
        $waktu_sekarang = date('H:i:s');
        // langsung gunakan format Y-m-d dari input type="date"
        $tanggal_formatted = !empty($tanggal) ? $tanggal : date('Y-m-d'); // fallback jika kosong

        $tanggal_waktu = $tanggal_formatted . ' ' . $waktu_sekarang;
        //
        $ymd = date('Ymd');
        $tgl_hari_ini = date('Y-m-d');

        // Ambil invoice terakhir hari ini 
        $lastInvoice = $this->PenjualanModel
            ->select('kode_invoice')
            ->where('DATE(tanggal)', $tgl_hari_ini)
            ->where('unit_idunit', $useridunit)
            ->orderBy('kode_invoice', 'DESC')
            ->first();



        if ($lastInvoice) {

            preg_match('/(\d{4})$/', $lastInvoice->kode_invoice, $matches);
        
            $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;
        
            $urutan = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        
        } else {
        
            $urutan = '0001';
        }


        $no_invoice = 'SLL' . $useridunit . $ymd . $urutan;
        //end invoice otomatis

        $total_penjualan = $this->sanitizeCurrency($this->request->getPost('total-harga'));
        $diskon = $this->sanitizeCurrency($this->request->getPost('total-diskon'));
        $harus_dibayar = $total_penjualan;
        $waktu_penjualan = date('Y-m-d H:i:s');

        // Nominal tunai/bank sudah dinormalisasi oleh PenjualanPaymentSplit:
        // metode_bayar menentukan zona mana yang dipakai dan rekening bank
        // divalidasi terhadap scope unit.
        $bayar_tunai = $pembayaran['cash'];
        $bankData = $pembayaran['banks'];

        $bankPembayaran = [];
        $totalBayarBank = 0;
        $jurnal = [];
        $kodePembayaran = 'PJL' . date('Ymd') . session('ID_UNIT') . rand(1000, 9999);

        foreach ($bankData as $b) {
            $jumlah = (float) ($b['jumlah'] ?? 0);

            if ($jumlah <= 0) {
                continue;
            }

            $bankPembayaran = array(
                'kode_pembayaran' => $kodePembayaran,
                'bank_idbank' => $b['id'],
                'jumlah' => $jumlah,
                'tabel_referensi' => 'penjualan'
            );

            $this->PembayaranBankModel->insertPembayaranBank($bankPembayaran);
            $totalBayarBank += $jumlah;
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_lunas_bank_' . $b['id'],
                'ar_value' => [$jumlah],
                'keterangan' => 'Pembayaran Bank - Penjualan ' . $no_invoice,
                'id_referensi' => $kodePembayaran,
                'tabel_referensi' => 'pembayaran_bank'
            ];
        }

        if ($bayar_tunai > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_lunas_tunai',
                'ar_value' => [$bayar_tunai],
                'keterangan' => 'Pembayaran Tunai - Penjualan ' . $no_invoice,
                'id_referensi' => $kodePembayaran,
                'tabel_referensi' => 'pembayaran_bank'
            ];
        }

        $total_bayar = $bayar_tunai + $totalBayarBank;

        $created_on = $tanggal_waktu;
        $total_ppn = $this->sanitizeCurrency($this->request->getPost('total-ppn'));

        $unit_idunit = $useridunit;
        // Hutang dan status dihitung server dari nominal yang benar-benar
        // diterima, bukan dari hidden input yang bisa dimanipulasi.
        $hutang = max(0, $total_penjualan - $total_bayar);
        $keterangan = $hutang == 0 ? 'Lunas' : 'Belum Lunas';


        $id_pelanggan = $this->request->getPost('selectedidpelanggan');


        $data1 = array(
            'kode_invoice' => $no_invoice,
            'tanggal' => $tanggal_waktu,
            'keterangan' => $keterangan,
            'total_penjualan' => $total_penjualan,
            'diskon' => $diskon,
            'harus_dibayar' => $harus_dibayar,
            'waktu_penjualan' => $waktu_penjualan,
            'bayar' => $total_bayar,
            'bank_idbank' => $kodePembayaran,
            'bayar_bank' => $totalBayarBank,
            'bayar_tunai' => $bayar_tunai,
            'created_on' => $created_on,
            'input_by' => session('ID_AKUN'),
            'sales_by' => $sales_by,
            'unit_idunit' => $unit_idunit,
            'id_pelanggan' => $id_pelanggan,
            'total_ppn' => $total_ppn,
        );
        $this->PenjualanModel->insert_Penjualan($data1);
        $idPenjualan = $this->PenjualanModel->insertID();

        $pelengkap_pembayaranbank = array(
            'id_referensi' => $idPenjualan
        );
        $this->PembayaranBankModel->updateByKodePembayaran($kodePembayaran, $pelengkap_pembayaranbank);

        foreach ($produkData as $produk) {
            $produkid = $produk['id'];
            $namaproduk = $produk['nama'];
            $datastokawal = $this->StokAwalModel->getByIdBarang($produkid);

            // if (!$datastokawal || $datastokawal->satuan_terkecil == null) {
            //     session()->setFlashdata('gagal', 'Barang dengan ID ' . $namaproduk . ' belum memiliki data satuan di stok awal.');
            //     return redirect()->back();
            // }
        }

        $total_penjualan = array_sum(array_map(function ($p) {
            return $this->sanitizeCurrency($p['harga']) * $p['jumlah'];
        }, $produkData));

        $total_hp_ppn = 0;
        $total_hp_non_ppn = 0;
        $total_hp_second = 0;
        $total_aksesoris = 0;
        $total_sparepart = 0;

        foreach ($produkData as $produk) {
            $produkjumlah = $produk['jumlah'];
            $produkid = $produk['id'];
            $produkharga = $this->sanitizeCurrency($produk['harga']);
            $produkdiskon = $this->sanitizeCurrency($produk['diskon']);
            $nilaidiskon = $produkdiskon;
            $subtotalAwal = $produkharga * $produkjumlah;
            $sub_total = $subtotalAwal - $nilaidiskon;

            $harga_penjualan = $produkharga;
            $diskon_penjualan = $nilaidiskon;
            $penjualan_idpenjualan = $idPenjualan;
            $kode = $produk['kode'];
            $kenaPPN = isset($produk['ppn']) ? 1 : 0;

            $proporsi = $subtotalAwal / ($total_penjualan ?: 1);

            if (strpos($produkid, 'bundle') === 0) {
                // barang bundle
                $bundleId = (int) str_replace('bundle', '', $produkid);
                $detailBundle = $this->DetailBundelModel->getByBundleId($bundleId);

                foreach ($detailBundle as $item) {
                    $idBarangBundle = $item->barang_idbarang;
                    $jumlahBundle = $item->jumlah * $produkjumlah;

                    $datahpp = $this->HppBarangModel->getById($idBarangBundle);
                    $satuan_jual = $datahpp->satuan_terkecil ?? null;
                    $hpp_penjualan = $this->HppUnitModel->resolve((int) $idBarangBundle, (int) $unit_idunit);

                    $data2 = array(
                        'jumlah' => $jumlahBundle,
                        'barang_idbarang' => $idBarangBundle,
                        'harga_penjualan' => $harga_penjualan,
                        'sub_total' => $sub_total,
                        'penjualan_idpenjualan' => $penjualan_idpenjualan,
                        'hpp_penjualan' => $hpp_penjualan,
                        'satuan_jual' => $satuan_jual,
                        'diskon_penjualan' => $diskon_penjualan,
                        'unit_idunit' => $unit_idunit,
                        'bundle' => 1
                    );
                    $this->DetailPenjualanModel->insert_detail($data2);
                }
                if ($datahpp->idkategori == 1 && $datahpp->status_barang == 1) {
                    $total_hp_second += $subtotalAwal;
                } elseif ($datahpp->idkategori == 1 && $datahpp->status_ppn == 0) {
                    $total_hp_non_ppn += $subtotalAwal;
                } elseif ($datahpp->idkategori == 1 && $datahpp->status_ppn == 1) {
                    $total_hp_ppn += $subtotalAwal;
                } elseif ($datahpp->idkategori == 3) {
                    $total_sparepart += $subtotalAwal;
                } else {
                    $total_aksesoris += $subtotalAwal;
                }
            } else {
                // barang biasa
                $datahpp = $this->HppBarangModel->getById($produkid);
                $satuan_jual = $datahpp->satuan_terkecil ?? null;
                $hpp_penjualan = $this->HppUnitModel->resolve((int) $produkid, (int) $unit_idunit);

                $data2 = array(
                    'jumlah' => $produkjumlah,
                    'barang_idbarang' => $produkid,
                    'harga_penjualan' => $harga_penjualan,
                    'sub_total' => $sub_total,
                    'penjualan_idpenjualan' => $penjualan_idpenjualan,
                    'hpp_penjualan' => $hpp_penjualan,
                    'satuan_jual' => $satuan_jual,
                    'diskon_penjualan' => $diskon_penjualan,
                    'unit_idunit' => $unit_idunit,
                );
                $this->DetailPenjualanModel->insert_detail($data2);
                if ($datahpp->idkategori == 1 && $datahpp->status_barang == 1) {
                    $total_hp_second += $subtotalAwal;
                } elseif ($datahpp->idkategori == 1 && $datahpp->status_ppn == 0) {
                    $total_hp_non_ppn += $subtotalAwal;
                } elseif ($datahpp->idkategori == 1 && $datahpp->status_ppn == 1) {
                    $total_hp_ppn += $subtotalAwal;
                } elseif ($datahpp->idkategori == 3) {
                    $total_sparepart += $subtotalAwal;
                } else {
                    $total_aksesoris += $subtotalAwal;
                }
            }
        }

        //jurnal
        if ($total_ppn > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_bayar_ppn',
                'ar_value' => [$total_ppn],
                'keterangan' => 'Penjualan PPN: No.Invoice ' . $no_invoice,
                'id_referensi' => $idPenjualan,
                'tabel_referensi' => 'penjualan'
            ];
        }
        if ($diskon > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_diskon',
                'ar_value' => [$diskon],
                'keterangan' => 'Penjualan Diskon: No.Invoice ' . $no_invoice,
                'id_referensi' => $idPenjualan,
                'tabel_referensi' => 'penjualan'
            ];
        }
        if ($total_aksesoris > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_acc_cash',
                'ar_value' => [$total_aksesoris],
                'keterangan' => 'Pembelian Aksesoris: No.Nota ' . $no_invoice,
                'id_referensi' => $idPenjualan,
                'tabel_referensi' => 'penjualan'
            ];
        }
        if ($total_sparepart > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_acc_cash',
                'ar_value' => [$total_sparepart],
                'keterangan' => 'Penjualan Sparepart: No.Nota ' . $no_invoice,
                'id_referensi' => $idPenjualan,
                'tabel_referensi' => 'penjualan'
            ];
        }
        if ($total_hp_ppn > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_hp_baru_ppn',
                'ar_value' => [$total_hp_ppn],
                'keterangan' => 'Penjualan HP PPN: No.Nota ' . $no_invoice,
                'id_referensi' => $idPenjualan,
                'tabel_referensi' => 'penjualan'
            ];
        }
        if ($total_hp_non_ppn > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_hp_baru_cash',
                'ar_value' => [$total_hp_non_ppn],
                'keterangan' => 'Penjualan HP NON PPN: No.Nota ' . $no_invoice,
                'id_referensi' => $idPenjualan,
                'tabel_referensi' => 'penjualan'
            ];
        }
        if ($total_hp_second > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_waktu,
                'kode_template' => 'penjualan_hp_second_cash',
                'ar_value' => [$total_hp_second],
                'keterangan' => 'Penjualan HP Second : No.Nota ' . $no_invoice,
                'id_referensi' => $idPenjualan,
                'tabel_referensi' => 'penjualan'
            ];
        }
        // die(json_encode($jurnal));
        // insert jurnal penjualan
        foreach ($jurnal as $j) {
            $this->JurnalModel->insertJurnal(
                $j['tanggal'],
                $j['kode_template'],
                $j['ar_value'],
                $j['keterangan'],
                $j['id_referensi'],
                $j['tabel_referensi']
            );
        }
        // end jurnal

        $db->transComplete(); // Commits if all queries succeeded, rolls back otherwise

        if ($db->transStatus() === FALSE) {
            // Transaction failed, handle the error
            $db->transRollback();
            session()->setFlashdata('gagal', 'Transaksi Penjualan Gagal, Data Gagal Di Simpan');
            return redirect()->to(base_url('/penjualan'));
        } else {
            $db->transCommit();
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');

            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');

            $sub_total_cetak = $this->sanitizeCurrency($total_penjualan) - $this->sanitizeCurrency($nilaidiskon) + $total_ppn;
            $kembalian_cetak = max(0, $this->sanitizeCurrency($total_bayar) - $this->sanitizeCurrency($total_penjualan));
            $dataCustomer = $this->PelangganModel->getById($id_pelanggan);
            if ($dataCustomer !== null) {
                $namaCustomer = $dataCustomer->nama;
            } else {
                $namaCustomer = 'Pelanggan Umum';
            }
            // Pastikan semua produk punya id_kategori dan imei
            foreach ($produkData as &$p) {
                $barang = $this->BarangModel->where('idbarang', $p['id'])->first();
                if ($barang) {
                    // Ambil idkategori dari tabel barang
                    $p['id_kategori'] = $barang->idkategori ?? null;

                    // Gunakan IMEI dari input user, jika kosong ambil dari DB
                    if (empty($p['imei']) || $p['imei'] === 'null') {
                        $p['imei'] = $barang->imei ?? '-';
                    }
                } else {
                    // Jika barang tidak ditemukan
                    $p['id_kategori'] = null;
                    $p['imei'] = '-';
                }
            }
            unset($p);

            $data3 = array(
                'produk' => $produkData,
                'tanggal' => $tanggal_waktu,
                'kasir' => $namauser,
                'customer' => $namaCustomer,
                'total_ppn' => $total_ppn,
                'no_invoice' => $no_invoice,
                'sub_total' => $sub_total_cetak,
                'diskon' => $nilaidiskon,
                'total' => $total_penjualan,
                'bayar' => $total_bayar,
                'kembalian' => $kembalian_cetak,
                'dataunit' => $this->UnitModel->getById(session('ID_UNIT'))
            );

            $action = $this->request->getPost('action');

            if ($action == 'simpan_thermal') {
                // cetak thermal
                $html = view('cetak/cetak_penjualan_thermal', $data3);
            } else {
                // cetak default
                $html = view('cetak/cetak_penjualan_thermal', $data3);
            }

            error_reporting(0);

            $mpdf = new \Mpdf\Mpdf();
            $mpdf->WriteHTML($html);

            $uploadPath = FCPATH . 'uploads/';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $filename = 'Struk-' . $no_invoice . '.pdf';
            $pdfPath = $uploadPath . $filename;
            $mpdf->Output($pdfPath, 'F');
            $mpdf->Output($filename, 'I');
            exit;
        }
    }

    function sanitizeCurrency($value)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $value);
        return (float) $cleaned;
    }

    public function search_by_hp()
    {

        //for push ulang purpshose
        $no_hp = $this->request->getGet('no_hp');
        $pelanggan = $this->PelangganModel
            ->where('no_hp', $no_hp)
            ->where('deleted', '0')
            ->first();

        return $this->response->setJSON([
            'found' => (bool) $pelanggan,
            'pelanggan' => $pelanggan
        ]);
    }
}
