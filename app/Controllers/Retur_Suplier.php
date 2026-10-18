<?php

namespace App\Controllers;

use App\Models\ModelKategori;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelReturSuplier;
use App\Models\ModelDetailPembelian;
use App\Services\Inventory\SupplierReturnQuantity;

class Retur_Suplier extends BaseController

{
    protected $KategoriModel;
    protected $AuthModel;
    protected $ReturSuplierModel;
    protected $DetailPembelianModel;

    public function __construct()
    {
        $this->KategoriModel = new ModelKategori();
        $this->AuthModel = new ModelAuth();
        $this->ReturSuplierModel = new ModelReturSuplier();
        $this->DetailPembelianModel = new ModelDetailPembelian();
    }

    public function index()
    {
        $akun =   $this->AuthModel->getById(session('ID_AKUN'));
        $data =  array(
            'akun' => $akun,
            'kategori' => $this->KategoriModel->getKategori(),
            'detail_pembelian' => $this->DetailPembelianModel->getDetailAll(),
            'retur_suplier' => $this->ReturSuplierModel->getReturPembelian(),
            'body'  => 'retur/retur_suplier'
        );
        return view('template', $data);
        // return $this->response->setJSON($data);
    }

    public function insert()
    {
        $datauser = $this->AuthModel->getById(session('ID_AKUN'));
        $useridunit = (int) ($datauser->ID_UNIT ?? 0);
        $tanggalRetur = (string) $this->request->getPost('tanggal_pengembalian');
        $tanggal = \DateTimeImmutable::createFromFormat('!Y-m-d', $tanggalRetur);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        $items = $this->request->getPost('items');
        $selected = array_values(array_filter(is_array($items) ? $items : [], static function ($item) {
            return is_array($item) && ($item['selected'] ?? null) === '1';
        }));

        if ($useridunit <= 0 || ! $tanggal || $tanggal->format('Y-m-d') !== $tanggalRetur
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $selected === []) {
            return redirect()->to(base_url('/retur_suplier'))->with('gagal', 'Tanggal atau barang retur tidak valid.');
        }

        $db = Database::connect();
        $db->transBegin();

        try {
            $unit = $db->query(
                'SELECT idunit FROM ' . $db->prefixTable('unit') . ' WHERE idunit = ? FOR UPDATE',
                [$useridunit]
            )->getRow();
            if (! $unit) {
                throw new \RuntimeException('Unit retur tidak ditemukan.');
            }

            $lastRetur = $this->ReturSuplierModel
                ->where('unit_idunit', $useridunit)
                ->where('tanggal', $tanggalRetur)
                ->orderBy('no_retur_suplier', 'DESC')
                ->first();
            $nextNumber = $lastRetur ? (int) substr($lastRetur->no_retur_suplier, -3) + 1 : 1;
            $noRetur = 'RTS' . $useridunit . $tanggal->format('Ymd') . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $seen = [];

            foreach ($selected as $item) {
                $detailId = (int) ($item['iddetail_pembelian'] ?? 0);
                $requested = (int) ($item['jumlah_retur'] ?? 0);
                if ($detailId <= 0 || isset($seen[$detailId])) {
                    throw new \RuntimeException('Detail pembelian retur tidak valid.');
                }
                $seen[$detailId] = true;

                $purchase = $db->query(
                    'SELECT jumlah, satuan_beli, barang_idbarang, unit_idunit FROM '
                    . $db->prefixTable('detail_pembelian') . ' WHERE iddetail_pembelian = ? FOR UPDATE',
                    [$detailId]
                )->getRow();
                if (! $purchase || (int) $purchase->unit_idunit !== $useridunit) {
                    throw new \RuntimeException('Detail pembelian tidak sesuai unit retur.');
                }

                $previous = $db->table('retur_suplier')
                    ->selectSum('jumlah')
                    ->where('detail_pembelian_iddetail_pembelian', $detailId)
                    ->get()
                    ->getRow();
                if (! SupplierReturnQuantity::allows(
                    (int) $purchase->jumlah,
                    (int) ($previous->jumlah ?? 0),
                    $requested
                )) {
                    throw new \RuntimeException('Jumlah retur melebihi sisa pembelian.');
                }

                if (! $this->ReturSuplierModel->insert_ReturSuplier([
                    'no_retur_suplier' => $noRetur,
                    'tanggal' => $tanggalRetur,
                    'jumlah' => $requested,
                    'satuan' => $purchase->satuan_beli,
                    'barang_idbarang' => $purchase->barang_idbarang,
                    'detail_pembelian_iddetail_pembelian' => $detailId,
                    'input_by' => session('ID_AKUN'),
                    'unit_idunit' => $purchase->unit_idunit,
                ])) {
                    throw new \RuntimeException('Retur supplier gagal disimpan.');
                }
            }

            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Transaksi retur supplier gagal disimpan.');
            }

            return redirect()->to(base_url('/retur_suplier'))->with('sukses', 'Data Berhasil Disimpan');
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'Retur_Suplier::insert gagal: ' . $error->getMessage());
            return redirect()->to(base_url('/retur_suplier'))->with('gagal', 'Retur supplier tidak dapat diproses. Periksa jumlah dan unit pembelian.');
        }
    }
}
