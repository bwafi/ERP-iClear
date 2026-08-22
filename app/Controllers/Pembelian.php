<?php

namespace App\Controllers;

use App\Models\ModelBarang;

use App\Models\ModelKategori;
use App\Models\ModelNamaHandphone;
use App\Models\ModelPembelian;
use App\Models\ModelStokAwal;
use App\Models\ModelDetailPembelian;
use App\Models\ModelSubKategori;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Config\Database;
use App\Models\ModelSuplier;
use App\Models\ModelAuth;
use App\Models\ModelPelanggan;
use App\Models\ModelHppBarang;
use App\Models\ModelJurnal;
use App\Models\ModelBank;
use App\Models\ModelPembayaranBank;

class Pembelian extends BaseController

{

    protected $BarangModel;
    protected $KategoriModel;
    protected $SuplierModel;
    protected $PembelianModel;
    protected $StokAwalModel;
    protected $DetailPembelianModel;
    protected $AuthModel;
    protected $PelangganModel;
    protected $HppBarangModel;
    protected $JurnalModel;
    protected $BankModel;
    protected $PembayaranBankModel;
    protected $NamaHandphoneModel;
    protected $SubKategoriModel;
    public function __construct()
    {
        $this->BarangModel = new ModelBarang();
        $this->KategoriModel = new ModelKategori();
        $this->SuplierModel = new ModelSuplier();
        $this->PembelianModel = new ModelPembelian();
        $this->StokAwalModel = new ModelStokAwal();
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->AuthModel = new ModelAuth();
        $this->PelangganModel = new ModelPelanggan();
        $this->HppBarangModel = new ModelHppBarang();
        $this->JurnalModel = new ModelJurnal();
        $this->BankModel = new ModelBank();
        $this->PembayaranBankModel = new ModelPembayaranBank();
        $this->NamaHandphoneModel = new ModelNamaHandphone();
        $this->SubKategoriModel = new ModelSubKategori();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'produk' => $this->BarangModel->getAllBarang(),
            'kategori' => $this->KategoriModel->getKategori(),
            'suplier' => $this->SuplierModel->getSuplier(),
            'frontliner' => $this->AuthModel->getAkunFrontliner(),
            'bank' => $this->BankModel->getBank(),
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'body'  => 'transaksi/pembelian',
            'nama_handphone' => $this->NamaHandphoneModel->getNamaHandphone(),
            'sub_kategori' => $this->SubKategoriModel->getSubKategori()
        );
        return view('template', $data);
    }

    function cleanRupiah($value)
    {

        $value = str_replace(['Rp', '.', ' '], '', $value);
        return (int) $value;
    }

    public function insert()
    {
        $produkData = $this->request->getPost('produk');

        date_default_timezone_set('Asia/Jakarta');

        $datauser = $this->AuthModel->getById(session('ID_AKUN'));
        $useridunit = $datauser->ID_UNIT;
        $namauser = $datauser->NAMA_AKUN;
        $suplier = $this->request->getPost('suplier');
        $id_suplier_text = $this->request->getPost('id_suplier_text');
        $id_pelanggan = $this->request->getPost('id_pelanggan');
        $nota_file = $this->request->getFile('nota_file');
        $tanggal_masuk = $this->request->getPost('tanggal_masuk');
        $jam_sekarang = date('H:i:s');
        $tanggal_masuk_full = $tanggal_masuk . ' ' . $jam_sekarang;
        $jatuh_tempot = $this->request->getPost('jatuh_tempo');
        $sisa = $this->cleanRupiah($this->request->getPost('hutang'));
        $total_harga = $this->cleanRupiah($this->request->getPost('total-harga'));
        $total_diskon = $this->cleanRupiah($this->request->getPost('total-diskon'));
        $total_ppn = $this->cleanRupiah($this->request->getPost('total-ppn'));
        $bayar_tunai = $this->cleanRupiah($this->request->getPost('bayar')); //ini tunai
        $bankData = $this->request->getPost('bank');

        $jurnal = [];
        $bankPembayaran = [];
        $totalBayarBank = 0;
        $kodePembayaran = 'PBL' . date('Ymd') . session('ID_UNIT') . rand(1000, 9999);

        $db = \Config\Database::connect();
        $db->transStart();

        if (!empty($bankData) && is_array($bankData)) {
            foreach ($bankData as $b) {
                $jumlah = $this->sanitizeCurrency($b['jumlah'] ?? '0');
                if ($jumlah > 0) {
                    $bankPembayaran = array(
                        'kode_pembayaran' => $kodePembayaran,
                        'bank_idbank' => $b['id'],
                        'jumlah' => $jumlah,
                        'tabel_referensi' => 'pembelian'
                    );

                    $this->PembayaranBankModel->insertPembayaranBank($bankPembayaran);
                    $totalBayarBank += $jumlah;
                    $jurnal[] = [
                        'tanggal' => $tanggal_masuk_full,
                        'kode_template' => 'pembelian_lunas_bank_' . $b['id'],
                        'ar_value' => [$jumlah],
                        'keterangan' => 'Pembayaran Bank - Pembelian',
                        'id_referensi' => $kodePembayaran,
                        'tabel_referensi' => 'pembayaran_bank'
                    ];
                }
            }
        }
        if ($bayar_tunai > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_masuk_full,
                'kode_template' => 'pembelian_lunas_tunai',
                'ar_value' => [$bayar_tunai],
                'keterangan' => 'Pembayaran Tunai - Pembelian',
                'id_referensi' => $kodePembayaran,
                'tabel_referensi' => 'pembayaran_bank'
            ];
        }
        $total_bayar = $bayar_tunai + $totalBayarBank;
        // dd($id_suplier_text);

        //invoice otomatis 
        $ymd = date('Ymd');
        $tgl_hari_ini = date('Y-m-d');

        // Ambil nota pembelian terakhir hari ini berdasarkan unit
        $lastNota = $this->PembelianModel
            ->select('no_nota_supplier')
            ->where('DATE(tanggal_masuk)', $tgl_hari_ini)
            ->where('unit_idunit', $useridunit)
            ->orderBy('no_nota_supplier', 'DESC')
            ->first();

        if ($lastNota) {
            // Ambil 4 digit terakhir (urutan)
            $lastNumber = (int) substr($lastNota->no_nota_supplier, -4);
            $urutan = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $urutan = '0001';
        }

        $no_nota = 'PCR' . $useridunit . $ymd . $urutan;

        //end invoice otomatis


        $status = '';
        if ($sisa == 0) {
            $status = 'Lunas';
        } else {
            $status = 'Belum Lunas';
        }

        $foto_nota_name = null;
        if ($nota_file && $nota_file->isValid() && !$nota_file->hasMoved()) {
            $foto_nota_name = $nota_file->getRandomName();
            $nota_file->move(ROOTPATH . 'public/foto_nota', $foto_nota_name);
        }
        $input_by = session('ID_AKUN');


        $nama = $this->request->getPost('nama');
        $alamat = $this->request->getPost('alamat');
        $nik = $this->request->getPost('nik');
        $nomor = $this->request->getPost('nomor');

        $resultp = true;
        if (empty($id_pelanggan)) {
            // Pastikan ada data yang diinput sebelum insert
            if (!empty($nama) || !empty($alamat) || !empty($nik) || !empty($nomor)) {

                $datapelanggan = $this->PelangganModel->getByNomor($nomor);

                if (empty($datapelanggan)) {

                    $datap = array(
                        'nama' => $nama,
                        'alamat' => $alamat,
                        'nik' => $nik,
                        'no_hp' => $nomor,
                        'deleted' => 0
                    );
                    $resultp = $this->PelangganModel->insert_Pelanggan($datap);
                    $id_pelanggan = $this->PelangganModel->insertID();
                } else {

                    $id_pelanggan = $datapelanggan->id_pelanggan;
                }
            }
        }

        $frontliner = $this->request->getPost('frontliner');


        $data = array(
            'suplier_id_suplier' => $id_suplier_text,
            'no_nota_supplier' => $no_nota,
            'foto_nota' =>  $foto_nota_name,
            'tanggal_masuk' => $tanggal_masuk_full,
            'sisa' => $sisa,
            'status' => $status,
            'total_transaksi' => $total_harga,
            'total_diskon' => $total_diskon,
            'total_ppn' => $total_ppn,
            'total_bayar' => $total_bayar,
            'frontliner' => $frontliner,
            'bayar_tunai' => $bayar_tunai,
            'bayar_bank' => $totalBayarBank,
            'bank_idbank' => $kodePembayaran,
            'jatuh_tempo' => $this->request->getPost('jatuh_tempo'),
            'bayar' => $total_bayar,
            'unit_idunit' => $useridunit,
            'pelanggan_id_pelanggan' => $id_pelanggan ?? null,
            'input_by' => $input_by
        );

        $result = $this->PembelianModel->insert_Pembelian($data);
        $idPembelian = $this->PembelianModel->insertID();
        $pelengkap_pembayaranbank = array(
            'id_referensi' => $idPembelian
        );
        $this->PembayaranBankModel->updateByKodePembayaran($kodePembayaran, $pelengkap_pembayaranbank);

        $total_aksesoris = 0;
        $total_sparepart = 0;
        $total_hp_ppn = 0;
        $total_hp_non_ppn = 0;
        $total_hp_second = 0;

        //gor push ulang

        foreach ($produkData as $produk) {
            $datastokawal = $this->StokAwalModel->getByIdBarang($produk['id']);
            $databarang = $this->BarangModel->getById($produk['id']);

            $hrg_beli = $this->cleanRupiah($produk['harga_beli']);
            $produkjumlah = $produk['jumlah'];
            $produkdiskon = $this->cleanRupiah($produk['diskon']);
            $produkharga  = $this->cleanRupiah($produk['harga']);
            $nilaidiskon = $produkdiskon;
            $produkppn = $produk['ppn'] ?? null;
            $subtotalAwal = $hrg_beli * $produkjumlah;
            $subtotalSetelahDiskon = $subtotalAwal - $nilaidiskon;
            $nilaiPPN = !is_null($produkppn) ? $subtotalSetelahDiskon * 0.11 : 0;

            $produktotalharga = $subtotalSetelahDiskon + $nilaiPPN;
            $satuan_beli = $datastokawal ? $datastokawal->satuan_terkecil : 'pcs';
            //
            $datahpp = $this->HppBarangModel->getById($produk['id']);
            $hitung_hpp  = $datahpp->hpp ?? 0;
            if ($databarang->idkategori == 1 && $databarang->status_barang == 1) {
                $total_hp_second += $subtotalSetelahDiskon;
            } elseif ($databarang->idkategori == 1 && $databarang->status_ppn == 0) {
                $total_hp_non_ppn += $subtotalSetelahDiskon;
            } elseif ($databarang->idkategori == 1 && $databarang->status_ppn == 1) {
                $total_hp_ppn += $subtotalSetelahDiskon;
            } elseif ($databarang->idkategori == 3) {
                $total_sparepart += $subtotalSetelahDiskon;
            } else {
                $total_aksesoris += $subtotalSetelahDiskon;
            }

            $data2 = array(
                'no_batch' => $no_nota,
                'tanggal' => $tanggal_masuk_full,
                'jumlah' => $produkjumlah,
                'hrg_beli' => $hrg_beli,
                'diskon' => $nilaidiskon,
                'ppn' => $nilaiPPN,
                'hitung_hpp' => $hitung_hpp,
                'total_harga' => $produktotalharga,
                'satuan_beli' => $satuan_beli,
                'barang_idbarang' => $produk['id'],
                'biaya_tambahan' => $produk['biaya_tambahan'],
                'keterangan_tambahan' => $produk['keterangan'],
                'unit_idunit' => $useridunit,
                'pembelian_idpembelian' => $idPembelian,

            );

            $result2 = $this->DetailPembelianModel->insert_detail($data2);
        }
        if ($total_ppn > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_masuk_full,
                'kode_template' => 'pembelian_ppn_lunas',
                'ar_value' => [$total_ppn],
                'keterangan' => 'Pembelian PPN: No.Nota ' . $no_nota,
                'id_referensi' => $idPembelian,
                'tabel_referensi' => 'pembelian'
            ];
        }
        if ($total_aksesoris > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_masuk_full,
                'kode_template' => 'pembelian_aksesoris',
                'ar_value' => [$total_aksesoris],
                'keterangan' => 'Pembelian Aksesoris: No.Nota ' . $no_nota,
                'id_referensi' => $idPembelian,
                'tabel_referensi' => 'pembelian'
            ];
        }
        if ($total_sparepart > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_masuk_full,
                'kode_template' => 'pembelian_sparepart',
                'ar_value' => [$total_sparepart],
                'keterangan' => 'Pembelian Sparepart: No.Nota ' . $no_nota,
                'id_referensi' => $idPembelian,
                'tabel_referensi' => 'pembelian'
            ];
        }
        if ($total_hp_ppn > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_masuk_full,
                'kode_template' => 'pembelian_lunas_hp_baru_ppn',
                'ar_value' => [$total_hp_ppn],
                'keterangan' => 'Pembelian HP PPN: No.Nota ' . $no_nota,
                'id_referensi' => $idPembelian,
                'tabel_referensi' => 'pembelian'
            ];
        }
        if ($total_hp_non_ppn > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_masuk_full,
                'kode_template' => 'pembelian_lunas_hp_baru',
                'ar_value' => [$total_hp_non_ppn],
                'keterangan' => 'Pembelian HP NON PPN: No.Nota ' . $no_nota,
                'id_referensi' => $idPembelian,
                'tabel_referensi' => 'pembelian'
            ];
        }
        if ($total_hp_second > 0) {
            $jurnal[] = [
                'tanggal' => $tanggal_masuk_full,
                'kode_template' => 'pembelian_lunas_hp_second',
                'ar_value' => [$total_hp_second],
                'keterangan' => 'Pembelian HP Second : No.Nota ' . $no_nota,
                'id_referensi' => $idPembelian,
                'tabel_referensi' => 'pembelian'
            ];
        }

        // insert jurnal pembelian
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
        // die(json_encode($jurnal));
        $db->transComplete(); // Commits if all queries succeeded, rolls back otherwise

        if ($db->transStatus() === FALSE) {
            // Transaction failed, handle the error
            $db->transRollback();
            session()->setFlashdata('gagal', 'Data Gagal Di Simpan');
            return redirect()->to(base_url('/pembelian'));
        } else {
            $db->transCommit();
            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
            return redirect()->to(base_url('/pembelian'));
        }
    }


    public function insert_produk()
    {


        $harga      = str_replace('.', '', $this->request->getPost('harga'));
        $harga_beli      = str_replace('.', '', $this->request->getPost('harga_beli'));
        $input = $this->request->getPost('input_by');
        $stok_minimum = $this->request->getPost('stok_minimum');

        $kategori = $this->request->getPost('kategori');
        $data_kategori = $this->KategoriModel->getByName($kategori);

        $idkategori = $data_kategori->id;
        $kode_kategori = $data_kategori->idkategori;

        $lastBarang = $this->BarangModel->getLastBarangByKategori($idkategori, $kode_kategori);

        $lastNumber = $lastBarang->max_kode ?? 0;
        $newNumber = $lastNumber + 1;
        
        $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);
        
        $kode_barang = $kode_kategori . $formattedNumber;

        $formattedNumber = str_pad($newNumber, 2, '0', STR_PAD_LEFT);

        $kode_barang = $kode_kategori . $formattedNumber;
        $status_ppn = $this->request->getPost('status_ppn');

        $nama_barang = "";
        $id_nama_barang = 0;
        if ($kategori == "Handphone") {
            $nama_barang = $this->request->getPost('nama_barang');
            // $datanamahandphone =  $this->NamaHandphoneModel->getNamaHandphoneById($id_nama_barang);
            // $nama_barang = $datanamahandphone->nama;
        } else {
            $nama_barang = $this->request->getPost('nama_barang');
        }


        $data = array(
            'kode_barang' => $kode_barang,
            'nama_barang' => $nama_barang,
            'harga' => $harga,
            'harga_beli' => $harga_beli,
            'input' => $input,
            'stok_minimum' => $stok_minimum,
            'idkategori' => $idkategori,
            'id_sub_kategori' => $this->request->getPost('subkategori'),
            'imei' => $this->request->getPost('imei'),
            'warna' => $this->request->getPost('warna'),
            'status_barang' => $this->request->getPost('kondisi'),
            'jenis_hp' => $this->request->getPost('type'),
            'internal' => $this->request->getPost('size'),
            'status' => "1",
            'status_ppn' => $status_ppn,
            'nama_barang_id' => $id_nama_barang,
            'deleted' => '0'

        );

        $result = $this->BarangModel->insert_Barang($data);
        if ($result) {
            session()->setFlashdata('sukses', 'Data Berhasil Disimpan');
            return redirect()->to(base_url('/pembelian'));
        }
    }

    function sanitizeCurrency($value)
    {

        $cleaned = str_replace(['Rp', '.', ' '], '', $value);
        return (float) $cleaned;
    }
}
