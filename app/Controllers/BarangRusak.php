<?php

namespace App\Controllers;

use App\Models\ModelBarangRusak;

use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelBank;

use App\Models\ModelBarang;
use App\Models\ModelPembelian;
use App\Models\ModelDetailPembelian;
use App\Models\ModelUnit;
use App\Models\ModelKategori;
use App\Models\ModelSubKategori;

class BarangRusak extends BaseController

{

    protected $AuthModel;
    protected $BankModel;

    protected $BarangRusakModel;
    protected $BarangModel;
    protected $PembelianModel;
    protected $DetailPembelianModel;
    protected $UnitModel;

    protected $KategoriModel;
    protected $SubKategoriModel;


    public function __construct()
    {
        $this->AuthModel = new ModelAuth();
        $this->BankModel = new ModelBank();
        $this->BarangRusakModel = new ModelBarangRusak();
        $this->BarangModel = new ModelBarang();
        $this->PembelianModel = new ModelPembelian();
        $this->DetailPembelianModel = new ModelDetailPembelian();
        $this->UnitModel = new ModelUnit();
        $this->KategoriModel = new ModelKategori();
        $this->SubKategoriModel = new ModelSubKategori();
    }

    public function index()
    {

        $data =  array(
            'barang_rusak' => $this->BarangRusakModel->getBarangRusak(),
            'unit' => $this->UnitModel->getUnit(),
            'body'  => 'stok/barang_rusak',
            'kategori' => $this->KategoriModel->getKategori(),
            'sub_kategori' => $this->SubKategoriModel->getSubKategori()
        );
        return view('template', $data);
    }

    public function input()
    {

        $data = array(
            'body' => 'stok/input_barang_rusak',
            'detail_pembelian' => $this->DetailPembelianModel->getDetailAll(),
            'unit' => $this->UnitModel->getUnit()
        );


        return view('template', $data);
    }


    public function insert_barang_rusak()
    {
        $products = $this->request->getPost('idbarang');
        $purchases = $this->request->getPost('idpembelian');
        $batches = $this->request->getPost('no_batch');
        $quantities = $this->request->getPost('jumlah_rusak');
        $dates = $this->request->getPost('tanggal_rusak');
        $notes = $this->request->getPost('keterangan');
        $unitId = (int) session('ID_UNIT');
        $count = is_array($products) ? count($products) : 0;
        if ($unitId <= 0 || $count === 0 || ! is_array($purchases) || ! is_array($batches)
            || ! is_array($quantities) || ! is_array($dates) || ! is_array($notes)
            || count($purchases) !== $count || count($batches) !== $count
            || count($quantities) !== $count || count($dates) !== $count || count($notes) !== $count
            || array_keys($products) !== array_keys($purchases) || array_keys($products) !== array_keys($batches)
            || array_keys($products) !== array_keys($quantities) || array_keys($products) !== array_keys($dates)
            || array_keys($products) !== array_keys($notes)) {
            return redirect()->back()->with('gagal', 'Data barang rusak tidak lengkap.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $seen = [];
            foreach ($products as $index => $productId) {
                if (! is_scalar($productId) || ! ctype_digit((string) $productId)
                    || ! is_scalar($purchases[$index]) || ! ctype_digit((string) $purchases[$index])
                    || ! is_scalar($quantities[$index]) || ! ctype_digit((string) $quantities[$index])) {
                    throw new \RuntimeException('ID atau jumlah barang rusak tidak valid.');
                }
                $productId = (int) $productId;
                $purchaseId = (int) $purchases[$index];
                $quantity = (int) $quantities[$index];
                $batch = trim((string) $batches[$index]);
                $date = (string) $dates[$index];
                $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                $dateErrors = \DateTimeImmutable::getLastErrors();
                $key = $purchaseId . ':' . $productId . ':' . $batch;
                if ($purchaseId <= 0 || $productId <= 0 || $quantity <= 0 || $batch === '' || isset($seen[$key])
                    || ! $parsedDate || $parsedDate->format('Y-m-d') !== $date
                    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                    throw new \RuntimeException('Baris barang rusak tidak valid.');
                }
                $seen[$key] = true;

                $purchase = $db->query(
                    'SELECT unit_idunit FROM ' . $db->prefixTable('pembelian') . ' WHERE idpembelian = ? FOR UPDATE',
                    [$purchaseId]
                )->getRow();
                if (! $purchase || (int) $purchase->unit_idunit !== $unitId) {
                    throw new \RuntimeException('Pembelian tidak sesuai unit.');
                }

                $purchased = $db->table('detail_pembelian')
                    ->selectSum('jumlah')
                    ->where('pembelian_idpembelian', $purchaseId)
                    ->where('barang_idbarang', $productId)
                    ->where('no_batch', $batch)
                    ->where('unit_idunit', $unitId)
                    ->get()->getRow();
                $previous = $db->table('barang_rusak')
                    ->selectSum('jumlah')
                    ->where('idpembelian', $purchaseId)
                    ->where('barang_idbarang', $productId)
                    ->where('no_nota_sup', $batch)
                    ->where('unit_idunit', $unitId)
                    ->get()->getRow();
                if ($quantity > (int) ($purchased->jumlah ?? 0) - (int) ($previous->jumlah ?? 0)) {
                    throw new \RuntimeException('Jumlah rusak melebihi pembelian.');
                }

                if (! $this->BarangRusakModel->insert([
                    'idpembelian' => $purchaseId,
                    'no_nota_sup' => $batch,
                    'barang_idbarang' => $productId,
                    'jumlah' => $quantity,
                    'tanggal_rusak' => $date,
                    'unit_idunit' => $unitId,
                    'input_by' => session('ID_AKUN'),
                    'keterangan' => (string) $notes[$index],
                ])) {
                    throw new \RuntimeException('Barang rusak gagal disimpan.');
                }
            }

            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Transaksi barang rusak gagal disimpan.');
            }

            return redirect()->to(base_url('/barang_rusak'))->with('sukses', 'Data Berhasil Di Simpan');
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'BarangRusak::insert_barang_rusak gagal: ' . $error->getMessage());
            return redirect()->back()->with('gagal', 'Barang rusak tidak dapat disimpan. Periksa jumlah, nota, dan unit.');
        }
    }
}
