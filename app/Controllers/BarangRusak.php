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
        $claims = Database::connect()->table('riwayat_claim_garansi claim')
            ->select('claim.idriwayat_claim_garansi')
            ->join('service', 'service.idservice = claim.service_idservice')
            ->where('service.unit_idunit', (int) session('ID_UNIT'))
            ->orderBy('claim.idriwayat_claim_garansi', 'DESC')
            ->limit(100)->get()->getResult();

        $data = array(
            'body' => 'stok/input_barang_rusak',
            'detail_pembelian' => $this->DetailPembelianModel->getDetailAll(),
            'unit' => $this->UnitModel->getUnit(),
            'warrantyClaims' => array_map(static function ($claim) {
                return (int) $claim->idriwayat_claim_garansi;
            }, $claims)
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
        $claimIds = $this->request->getPost('claim_id');
        $originStatuses = $this->request->getPost('asal_dokumen');
        $unitId = (int) session('ID_UNIT');
        $count = is_array($products) ? count($products) : 0;
        if ($unitId <= 0 || $count === 0 || ! is_array($purchases) || ! is_array($batches)
            || ! is_array($quantities) || ! is_array($dates) || ! is_array($notes)
            || ! is_array($originStatuses)
            || count($purchases) !== $count || count($batches) !== $count
            || count($quantities) !== $count || count($dates) !== $count || count($notes) !== $count
            || array_keys($products) !== array_keys($purchases) || array_keys($products) !== array_keys($batches)
            || array_keys($products) !== array_keys($quantities) || array_keys($products) !== array_keys($dates)
            || array_keys($products) !== array_keys($notes)
            || array_keys($products) !== array_keys($originStatuses)) {
            return redirect()->back()->with('gagal', 'Data barang rusak tidak lengkap.');
        }
        if ($claimIds === null) {
            $claimIds = array_fill_keys(array_keys($products), '');
        }
        if (! is_array($claimIds) || array_keys($claimIds) !== array_keys($products)) {
            return redirect()->back()->with('gagal', 'Data klaim garansi tidak lengkap.');
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
                $originStatus = (string) $originStatuses[$index];
                if (! in_array($originStatus, ['terverifikasi', 'belum_diketahui'], true)) {
                    throw new \RuntimeException('Status asal barang rusak tidak valid.');
                }
                $unknownOrigin = $originStatus === 'belum_diketahui';
                $quantity = (int) $quantities[$index];
                $batch = trim((string) $batches[$index]);
                $date = (string) $dates[$index];
                $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                $dateErrors = \DateTimeImmutable::getLastErrors();
                $key = ($unknownOrigin ? 'unknown' : $purchaseId . ':' . $batch) . ':' . $productId;
                if ($purchaseId <= 0 || $productId <= 0 || $quantity <= 0 || $batch === '' || isset($seen[$key])
                    || ! $parsedDate || $parsedDate->format('Y-m-d') !== $date
                    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                    throw new \RuntimeException('Baris barang rusak tidak valid.');
                }
                $seen[$key] = true;
                $rawClaimId = $claimIds[$index];
                if (! is_scalar($rawClaimId) || ((string) $rawClaimId !== '' && ! ctype_digit((string) $rawClaimId))) {
                    throw new \RuntimeException('ID klaim tidak valid.');
                }
                $claimId = (int) $rawClaimId;
                if ((string) $rawClaimId !== '' && $claimId <= 0) {
                    throw new \RuntimeException('ID klaim tidak valid.');
                }

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
                if (! $unknownOrigin && $quantity > (int) ($purchased->jumlah ?? 0) - (int) ($previous->jumlah ?? 0)) {
                    throw new \RuntimeException('Jumlah rusak melebihi pembelian.');
                }
                if ($claimId > 0) {
                    $claim = $db->query(
                        'SELECT service.idservice, service.unit_idunit, detail.jumlah FROM '
                        . $db->prefixTable('riwayat_claim_garansi') . ' claim '
                        . 'JOIN ' . $db->prefixTable('service') . ' service ON service.idservice = claim.service_idservice '
                        . 'JOIN ' . $db->prefixTable('service_sparepart') . ' detail '
                        . 'ON detail.service_idservice = service.idservice AND detail.barang_idbarang = ? '
                        . 'WHERE claim.idriwayat_claim_garansi = ? FOR UPDATE',
                        [$productId, $claimId]
                    )->getRow();
                    if (! $claim || (int) $claim->unit_idunit !== $unitId) {
                        throw new \RuntimeException('Klaim tidak sesuai barang atau unit.');
                    }
                    $serviceOrigin = $db->table('service_sparepart')
                        ->select('source_purchase_detail_id')
                        ->where('service_idservice', (int) $claim->idservice)
                        ->where('barang_idbarang', $productId)->get()->getRow();
                    if ($serviceOrigin === null || $serviceOrigin->source_purchase_detail_id === null) {
                        if (! $unknownOrigin) {
                            throw new \RuntimeException('Asal sparepart service lama belum diketahui; pilih asal belum diketahui.');
                        }
                    } elseif ($unknownOrigin) {
                        throw new \RuntimeException('Asal service sudah terbukti; gunakan supplier dan nota asal.');
                    }
                    if (! $unknownOrigin) {
                        $origin = $db->table('detail_pembelian')
                            ->select('pembelian_idpembelian, no_batch')
                            ->where('iddetail_pembelian', (int) $serviceOrigin->source_purchase_detail_id)
                            ->get()->getRow();
                        if ($origin === null || (int) $origin->pembelian_idpembelian !== $purchaseId
                            || (string) $origin->no_batch !== $batch) {
                            throw new \RuntimeException('Supplier atau nota barang rusak tidak cocok dengan asal service.');
                        }
                    }
                    $priorIssue = $db->table('garansi_sparepart_keluar')
                        ->select('jumlah')
                        ->where('service_id', $claim->idservice)
                        ->where('barang_id', $productId)
                        ->where('claim_id <', $claimId)
                        ->orderBy('claim_id', 'DESC')->get()->getRow();
                    $maxReturned = (int) ($priorIssue->jumlah ?? $claim->jumlah);
                    $damaged = $db->table('barang_rusak')->selectSum('jumlah')
                        ->where('claim_id', $claimId)
                        ->where('barang_idbarang', $productId)->get()->getRow();
                    if ($maxReturned <= 0 || $quantity > $maxReturned - (int) ($damaged->jumlah ?? 0)) {
                        throw new \RuntimeException('Jumlah barang rusak melebihi sparepart yang dipakai customer.');
                    }
                }

                if (! $this->BarangRusakModel->insert([
                    'idpembelian' => $unknownOrigin ? null : $purchaseId,
                    'no_nota_sup' => $unknownOrigin ? null : $batch,
                    'barang_idbarang' => $productId,
                    'jumlah' => $quantity,
                    'tanggal_rusak' => $date,
                    'unit_idunit' => $unitId,
                    'input_by' => session('ID_AKUN'),
                    'keterangan' => (string) $notes[$index],
                    'claim_id' => $claimId > 0 ? $claimId : null,
                ])) {
                    throw new \RuntimeException('Barang rusak gagal disimpan.');
                }
                if ($claimId <= 0) {
                    $inventoryBridge = new \App\Services\Inventory\InventoryLedgerBridge($db);
                    if ($inventoryBridge->isTracked($productId, $unitId)) {
                        $inventoryBridge->outgoing($productId, $unitId, 'barang_rusak',
                            (string) $this->BarangRusakModel->insertID(), 'damaged', $quantity);
                        (new \App\Services\Inventory\InventoryBatchLedger($db))->post(
                            $productId, $unitId, 'barang_rusak',
                            (string) $this->BarangRusakModel->insertID(), 'damaged', -$quantity);
                    }
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
