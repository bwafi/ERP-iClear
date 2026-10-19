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
use App\Services\Inventory\ProductInventoryScope;

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

        $allBarang = array_values(array_filter(
            $this->BarangModel->getAllBarang(),
            static fn ($barang) => ProductInventoryScope::isPhysicalProduct(
                (string) $barang->nama_barang,
                $barang->jenis_item ?? null
            )
        ));
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
        $builder = $this->db->table('barang')->where('deleted', 0)
            ->where(ProductInventoryScope::sqlPredicate('barang.nama_barang', true, 'barang.jenis_item'), null, false);

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
            // 'pelanggan' => $this->PelangganModel->searchPelanggan($search, 20),
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

        if ($selectedProducts && is_array($selectedProducts) && ctype_digit((string) $idUnit) && (int) $idUnit > 0) {
            $selectedProducts = array_unique(array_map('strval', $selectedProducts));
            $this->db->transBegin();
            foreach ($selectedProducts as $idBarang) {
                if (!ctype_digit($idBarang) || (int) $idBarang <= 0) {
                    $this->db->transRollback();
                    session()->setFlashdata('error', 'ID barang tidak valid');
                    return redirect()->to(base_url('/input_stokawal'));
                }
                $databarang = $this->BarangModel->getById((int) $idBarang);
                $jumlah = $this->request->getPost('jumlah')[$idBarang] ?? 0;
                if (!$databarang || (int) $databarang->deleted !== 0
                    || !ProductInventoryScope::isPhysicalProduct((string) $databarang->nama_barang, $databarang->jenis_item ?? null)
                    || !is_numeric($jumlah) || (float) $jumlah <= 0
                    || $this->StokAwalModel->where('barang_idbarang', (int) $idBarang)->where('unit_idunit', (int) $idUnit)->first()) {
                    $this->db->transRollback();
                    session()->setFlashdata('error', 'Barang tidak valid atau stok awal sudah tercatat. Gunakan Pembelian untuk restock.');
                    return redirect()->to(base_url('/input_stokawal'));
                }

                //hidden sementara
                // $hargaBeli = $this->request->getPost("harga_beli")[$kodeBarang] ?? 0;
                //hidden sementara

                $satuanTerkecil = $this->request->getPost('satuan_terkecil')[$idBarang] ?? '';
                $tipeRelasi = $this->request->getPost('tipe_relasi')[$idBarang] ?? '';
                $idSuplier = $this->request->getPost('id_suplier_text')[$idBarang] ?? 0;
                $idPelanggan = $this->request->getPost('id_pelanggan_text')[$idBarang] ?? 0;

                //sementara
                $hargaBeli = $databarang->harga_beli;

                $data = [
                    'tanggal' => date('Y-m-d'),
                    'jumlah' => $jumlah,
                    'barang_idbarang' => (int) $idBarang,
                    'harga_beli' => $hargaBeli,
                    'satuan_terkecil' => $satuanTerkecil,
                    'unit_idunit' => $idUnit,
                    'input_by' => (int) session('ID_AKUN'),
                    'suplier_id_suplier' => $tipeRelasi === 'suplier' ? $idSuplier : 0,
                    'pelanggan_id_pelanggan' => $tipeRelasi === 'pelanggan' ? $idPelanggan : 0,
                ];

                if (!$this->StokAwalModel->insert_Stok($data) || $this->db->transStatus() === false) {
                    $this->db->transRollback();
                    session()->setFlashdata('error', 'Stok awal gagal disimpan');
                    return redirect()->to(base_url('/input_stokawal'));
                }
                $stockId = (int) $this->StokAwalModel->insertID();
                $inventoryBridge = new \App\Services\Inventory\InventoryLedgerBridge($this->db);
                try {
                    if ($inventoryBridge->isTracked((int) $idBarang, (int) $idUnit)
                        || $inventoryBridge->isFirstIncoming((int) $idBarang, (int) $idUnit, 'stok_awal', $stockId, (int) $jumlah)) {
                        $inventoryBridge->incoming((int) $idBarang, (int) $idUnit,
                            'stok_awal', (string) $stockId, 'opening', (int) $jumlah, (int) $hargaBeli);
                    }
                    (new \App\Services\Inventory\InventoryBatchLedger($this->db))->post(
                        (int) $idBarang, (int) $idUnit, 'stok_awal', (string) $stockId, 'opening', (int) $jumlah
                    );
                } catch (\Throwable $error) {
                    $this->db->transRollback();
                    log_message('error', 'Ledger stok awal gagal: ' . $error->getMessage());
                    return redirect()->to(base_url('/input_stokawal'))->with('error', 'Stok awal dibatalkan karena ledger tidak cocok.');
                }
            }
            $this->db->transCommit();

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
