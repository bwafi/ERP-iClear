<?php

namespace App\Controllers;

use App\Models\ModelKategori;
use App\Models\ModelStokAwal;
use App\Models\ModelBarang;
use App\Models\ModelPelanggan;
use App\Models\ModelSuplier;
use App\Models\ModelAuth;
use App\Models\ModelUnit;
use Config\Database;

class StokAwal extends BaseController
{
    protected $KategoriModel;
    protected $StokAwalModel;
    protected $BarangModel;

    protected $UnitModel;
    protected $SuplierModel;
    protected $PelangganModel;
    protected $AuthModel;

    protected $db;

    public function __construct()
    {
        $this->KategoriModel = new ModelKategori();
        $this->StokAwalModel = new ModelStokAwal();
        $this->BarangModel = new ModelBarang();
        $this->UnitModel = new ModelUnit();
        $this->SuplierModel = new ModelSuplier();
        $this->PelangganModel = new ModelPelanggan();
        $this->AuthModel = new ModelAuth();

        $this->db = \Config\Database::connect();
    }

    public function index()
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));

        $allBarang = $this->BarangModel->getAllBarang();
        $stok = $this->StokAwalModel->getAllStok();

        $barangSudahAda = [];
        foreach ($stok as $stockItem) {
            $barangSudahAda[$stockItem->unit_idunit][] = $stockItem->barang_idbarang;
        }

        $barangTersedia = $allBarang;

        $unitData = $this->UnitModel->getUnit();

        $data = [
            'akun' => $akun,
            'stok' => $stok,
            'barang' => $barangTersedia,
            'unit' => $unitData,
            'pelanggan' => $this->PelangganModel->getPelanggan(),
            'suplier' => $this->SuplierModel->getSuplier(),
            'body' => 'stok/stok_awal',
        ];
        return view('template', $data);
    }

    public function input_stokawal($jenis = null)
    {
        $akun = $this->AuthModel->getById(session('ID_AKUN'));

        $builder = $this->db->table('barang');

        // Filter berdasarkan jenis
        if (!empty($jenis)) {
            if ($jenis === 'ACC') {
                $builder->where('idkategori', 2);
            } elseif ($jenis === 'HP') {
                $builder->where('idkategori', 1);
            } else {
                $builder->like('nama_barang', $jenis);
            }
        }

        // =========================
        // SEARCH
        // =========================

        $search = trim($this->request->getGet('search') ?? '');

        if ($search !== '') {
            $builder->groupStart()->like('nama_barang', $search)->orLike('kode_barang', $search)->orLike('imei', $search)->groupEnd();
        }

        // =========================
        // PAGINATION
        // =========================

        $perPage = 25;

        $page = max(1, (int) $this->request->getGet('page'));

        // Total hasil setelah filter + search
        $total = $builder->countAllResults(false);

        $offset = ($page - 1) * $perPage;

        $allBarang = $builder->orderBy('idbarang', 'DESC')->limit($perPage, $offset)->get()->getResult();

        $totalPages = (int) ceil($total / $perPage);

        // =========================
        // DATA LAINNYA
        // =========================

        $stok = $this->StokAwalModel->getAllStok();

        $barangSudahAda = [];

        foreach ($stok as $stockItem) {
            $barangSudahAda[$stockItem->unit_idunit][] = $stockItem->barang_idbarang;
        }

        $unitData = $this->UnitModel->getUnit();

        $data = [
            'akun' => $akun,

            'stok' => $stok,

            'barang' => $allBarang,

            'unit' => $unitData,

            'jenis' => $jenis,

            'search' => $search,

            'pelanggan' => $this->PelangganModel->getPelanggan(),

            'suplier' => $this->SuplierModel->getSuplier(),

            'currentPage' => $page,

            'perPage' => $perPage,

            'total' => $total,

            'totalPages' => $totalPages,

            'body' => 'stok/input_stokawal',
        ];

        return view('template', $data);
    }

    public function insert()
    {
        $selectedProducts = $this->request->getPost('selected_products');
        $idUnit = $this->request->getPost('global_unit') ?? '';

        if ($selectedProducts) {
            foreach ($selectedProducts as $kodeBarang) {
                $jumlah = $this->request->getPost('jumlah')[$kodeBarang] ?? 0;

                //hidden sementara
                // $hargaBeli = $this->request->getPost("harga_beli")[$kodeBarang] ?? 0;
                //hidden sementara

                $satuanTerkecil = $this->request->getPost('satuan_terkecil')[$kodeBarang] ?? '';
                $tipeRelasi = $this->request->getPost('tipe_relasi')[$kodeBarang] ?? '';
                $idSuplier = $this->request->getPost('id_suplier_text')[$kodeBarang] ?? 0;
                $idPelanggan = $this->request->getPost('id_pelanggan_text')[$kodeBarang] ?? 0;
                $databarang = $this->BarangModel->getBykode($kodeBarang);
                $idbarang = $databarang->idbarang;

                //sementara
                $hargaBeli = $databarang->harga_beli;

                $data = [
                    'tanggal' => date('Y-m-d'),
                    'jumlah' => $jumlah,
                    'barang_idbarang' => $idbarang,
                    'harga_beli' => $hargaBeli,
                    'satuan_terkecil' => $satuanTerkecil,
                    'unit_idunit' => $idUnit,
                    'suplier_id_suplier' => $tipeRelasi === 'suplier' ? $idSuplier : 0,
                    'pelanggan_id_pelanggan' => $tipeRelasi === 'pelanggan' ? $idPelanggan : 0,
                ];

                $this->StokAwalModel->insert_Stok($data);
            }

            session()->setFlashdata('sukses', 'Data Berhasil Di Simpan');
            return redirect()->to(base_url('/kartu_stok'));
        } else {
            session()->setFlashdata('error', 'Tidak ada produk yang dipilih');
            return redirect()->to(base_url('/input_stokawal'));
        }
    }

    public function stok()
    {
        $data = [
            'body' => 'stok/stok',
        ];
        return view('template', $data);
    }
}
