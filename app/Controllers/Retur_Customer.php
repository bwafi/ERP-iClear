<?php

namespace App\Controllers;

use App\Models\ModelKategori;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelReturCustomer;
use App\Models\ModelDetailPenjualan;
use App\Models\ModelJurnal;

class Retur_Customer extends BaseController

{

    protected $KategoriModel;
    protected $AuthModel;
    protected $ReturCustomerModel;
    protected $DetailPenjualanModel;
    protected $JurnalModel;

    public function __construct()
    {
        $this->KategoriModel = new ModelKategori();
        $this->AuthModel = new ModelAuth();
        $this->ReturCustomerModel = new ModelReturCustomer();
        $this->DetailPenjualanModel = new ModelDetailPenjualan();
        $this->JurnalModel = new ModelJurnal();
    }

    public function index()
    {
        $db = Database::connect();
        $invoice = trim((string) $this->request->getGet('invoice'));
        $unitId = filter_var($this->request->getGet('unit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $before = filter_var($this->request->getGet('before'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $query = $db->table('penjualan p')
            ->select('p.idpenjualan, p.kode_invoice, p.tanggal, u.NAMA_UNIT')
            ->join('unit u', 'u.idunit = p.unit_idunit')
            ->where('EXISTS (SELECT 1 FROM ' . $db->prefixTable('detail_penjualan')
                . ' dp WHERE dp.penjualan_idpenjualan = p.idpenjualan)', null, false)
            ->orderBy('p.idpenjualan', 'DESC')->limit(51);
        if ($invoice !== '') {
            $query->like('p.kode_invoice', $invoice, 'after');
        }
        if ($unitId !== null) {
            $query->where('p.unit_idunit', $unitId);
        }
        if ($before !== null) {
            $query->where('p.idpenjualan <', $before);
        }
        $invoices = $query->get()->getResultArray();
        $hasMore = count($invoices) > 50;
        if ($hasMore) {
            array_pop($invoices);
        }
        return view('template', [
            'akun' => $this->AuthModel->getById(session('ID_AKUN')),
            'invoices' => $invoices,
            'hasMore' => $hasMore,
            'invoiceFilter' => $invoice,
            'unitFilter' => $unitId,
            'units' => $db->table('unit')->select('idunit, NAMA_UNIT')->orderBy('NAMA_UNIT')->get()->getResultArray(),
            'body' => 'retur/retur_customer',
        ]);
    }

    public function invoiceDetail(int $invoiceId)
    {
        if ($invoiceId <= 0) {
            return $this->response->setStatusCode(404);
        }
        $db = Database::connect();
        $invoice = $db->table('penjualan')->select('idpenjualan, kode_invoice, unit_idunit')
            ->where('idpenjualan', $invoiceId)->get()->getRowArray();
        if ($invoice === null) {
            return $this->response->setStatusCode(404);
        }
        $details = $db->table('detail_penjualan dp')
            ->select('dp.iddetail_penjualan, dp.jumlah, dp.harga_penjualan, dp.diskon_penjualan, dp.sub_total, dp.satuan_jual, b.nama_barang, '
                . 'COALESCE((SELECT SUM(rp.jumlah) FROM ' . $db->prefixTable('retur_pelanggan')
                . ' rp WHERE rp.detail_penjualan_iddetail_penjualan = dp.iddetail_penjualan), 0) AS jumlah_diretur', false)
            ->join('barang b', 'b.idbarang = dp.barang_idbarang')
            ->where('dp.penjualan_idpenjualan', $invoiceId)
            ->orderBy('dp.iddetail_penjualan')->get()->getResultArray();
        return $this->response->setJSON(['invoice' => $invoice, 'details' => $details]);
    }

    public function insert()
    {
        $tanggal_retur = (string) $this->request->getPost('tanggal_pengembalian');
        $items = $this->request->getPost('items');
        $selected = array_values(array_filter(is_array($items) ? $items : [], static function ($item) {
            return is_array($item) && ($item['selected'] ?? null) === '1';
        }));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal_retur);
        if ($selected === [] || ! $date || $date->format('Y-m-d') !== $tanggal_retur) {
            return redirect()->to(base_url('/retur_customer'))->with('gagal', 'Tanggal atau barang retur tidak valid.');
        }
        $datauser = $this->AuthModel->getById(session('ID_AKUN'));
        $useridunit = (int) ($datauser->ID_UNIT ?? 0);
        $db = Database::connect();
        $db->transBegin();
        try {
            if (! $db->query('SELECT idunit FROM ' . $db->prefixTable('unit') . ' WHERE idunit = ? FOR UPDATE', [$useridunit])->getRow()) {
                throw new \RuntimeException('Unit retur tidak valid.');
            }

        $lastRetur = $this->ReturCustomerModel
            ->where('unit_idunit', $useridunit)
            ->like('DATE(tanggal)', $tanggal_retur)
            ->orderBy('no_retur_pelanggan', 'DESC')
            ->first();

        if ($lastRetur) {
            $lastKode = substr($lastRetur->no_retur_pelanggan, -3);
            $newKode = str_pad((int)$lastKode + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newKode = '001';
        }

        $ymd = str_replace('-', '', $tanggal_retur);
        $no_retur_pelanggan = 'RTC' . $useridunit . $ymd . $newKode;

        $total_harga = 0;
        $total_ppn = 0;
        $lastDetailId = null;
        $seen = [];
        $inventoryBridge = new \App\Services\Inventory\InventoryLedgerBridge($db);

        foreach ($selected as $item) {
                $rawQuantity = $item['jumlah_retur'] ?? null;
                $rawDetailId = $item['iddetail_penjualan'] ?? null;
                if (! ctype_digit((string) $rawQuantity) || ! ctype_digit((string) $rawDetailId)) {
                    throw new \RuntimeException('Jumlah atau detail retur tidak valid.');
                }
                $jumlah_retur = (int) $rawQuantity;
                $iddetail_penjualan = (int) $rawDetailId;
                if ($jumlah_retur <= 0 || $iddetail_penjualan <= 0 || isset($seen[$iddetail_penjualan])) {
                    throw new \RuntimeException('Detail retur duplikat atau jumlah tidak valid.');
                }
                $seen[$iddetail_penjualan] = true;
                $detail = $db->query('SELECT * FROM ' . $db->prefixTable('detail_penjualan')
                    . ' WHERE iddetail_penjualan = ? FOR UPDATE', [$iddetail_penjualan])->getRow();
                if (! $detail || (int) $detail->unit_idunit !== $useridunit) {
                    throw new \RuntimeException('Detail penjualan tidak sesuai unit.');
                }
                $returned = $db->table('retur_pelanggan')->selectSum('jumlah')
                    ->where('detail_penjualan_iddetail_penjualan', $iddetail_penjualan)->get()->getRow();
                if ($jumlah_retur > (int) $detail->jumlah - (int) ($returned->jumlah ?? 0)) {
                    throw new \RuntimeException('Jumlah retur melebihi sisa penjualan.');
                }
                $sub_total = $jumlah_retur * (int) ($detail->harga_penjualan ?? 0);
                $ppn_total = $jumlah_retur * (int) ($detail->ppn ?? 0);

                $total_harga += $sub_total;
                $total_ppn += $ppn_total;

                $data = [
                    'no_retur_pelanggan' => $no_retur_pelanggan,
                    'tanggal' => $tanggal_retur,
                    'jumlah' => $jumlah_retur,
                    'satuan' => (string) ($detail->satuan_jual ?? 'pcs'),
                    'barang_idbarang' => (int) $detail->barang_idbarang,
                    'detail_penjualan_iddetail_penjualan' => $iddetail_penjualan,
                    'unit_idunit' => $useridunit,
                    'input_by' => session('ID_AKUN')
                ];
                if (! $this->ReturCustomerModel->insert_ReturCustomer($data)) {
                    throw new \RuntimeException('Retur pelanggan gagal disimpan.');
                }
                $returnId = (int) $this->ReturCustomerModel->insertID();
                if ($inventoryBridge->isTracked((int) $detail->barang_idbarang, $useridunit)) {
                    $inventoryBridge->incoming((int) $detail->barang_idbarang, $useridunit,
                        'retur_pelanggan', (string) $returnId, 'customer_return', $jumlah_retur,
                        (int) $detail->hpp_penjualan);
                }
                $lastDetailId = $iddetail_penjualan;
        }

        $ar_nilai = [$total_harga, $total_ppn];
        $this->JurnalModel->insertJurnal(
            $tanggal_retur,
            'retur_penjualan',
            $ar_nilai,
            "Retur Penjualan",
            $lastDetailId,
            'retur_pelanggan'
        );
        if ($db->transStatus() === false || ! $db->transCommit()) {
            throw new \RuntimeException('Retur pelanggan gagal diselesaikan.');
        }

            return redirect()->to(base_url('/retur_customer'))->with('sukses', 'Data Berhasil Disimpan');
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'Retur_Customer::insert gagal: ' . $error->getMessage());
            return redirect()->to(base_url('/retur_customer'))->with('gagal', 'Retur dibatalkan karena stok atau data tidak cocok.');
        }
    }
}
