<?php

namespace App\Controllers;

use App\Models\ModelKategori;
use Config\Database;
use App\Models\ModelAuth;
use App\Models\ModelReturSuplier;
use App\Models\ModelDetailPembelian;
use App\Services\Inventory\SupplierReturnQuantity;
use App\Services\Inventory\SupplierReturnAvailability;

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
            'return_availability' => (new SupplierReturnAvailability(Database::connect()))->forUnit((int) ($akun->ID_UNIT ?? 0)),
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
                    throw new \DomainException('Unit retur tidak ditemukan.');
            }

            $lastRetur = $this->ReturSuplierModel
                ->where('unit_idunit', $useridunit)
                ->where('tanggal', $tanggalRetur)
                ->orderBy('no_retur_suplier', 'DESC')
                ->first();
            $nextNumber = $lastRetur ? (int) substr($lastRetur->no_retur_suplier, -3) + 1 : 1;
            $noRetur = 'RTS' . $useridunit . $tanggal->format('Ymd') . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $seen = [];
            $supplierId = null;

            foreach ($selected as $item) {
                $rawDetailId = $item['iddetail_pembelian'] ?? null;
                $rawRequested = $item['jumlah_retur'] ?? null;
                if (! is_scalar($rawDetailId) || ! ctype_digit((string) $rawDetailId)
                    || ! is_scalar($rawRequested) || ! ctype_digit((string) $rawRequested)) {
                    throw new \DomainException('Detail atau jumlah retur tidak valid.');
                }
                $detailId = (int) $rawDetailId;
                $requested = (int) $rawRequested;
                if ($detailId <= 0 || $requested <= 0 || isset($seen[$detailId])) {
                    throw new \DomainException('Detail pembelian retur tidak valid.');
                }
                $seen[$detailId] = true;

                $purchase = $db->query(
                    'SELECT detail.jumlah, detail.satuan_beli, detail.barang_idbarang, detail.unit_idunit, '
                    . 'detail.pembelian_idpembelian, detail.no_batch, '
                    . 'purchase.suplier_id_suplier FROM ' . $db->prefixTable('detail_pembelian') . ' detail '
                    . 'JOIN ' . $db->prefixTable('pembelian') . ' purchase '
                    . 'ON purchase.idpembelian = detail.pembelian_idpembelian '
                    . 'WHERE detail.iddetail_pembelian = ? FOR UPDATE',
                    [$detailId]
                )->getRow();
                if (! $purchase || (int) $purchase->unit_idunit !== $useridunit
                    || (int) $purchase->suplier_id_suplier <= 0) {
                    throw new \DomainException('Pembelian harus berasal dari unit akun yang sama dan memiliki supplier tercatat.');
                }
                if ($supplierId !== null && $supplierId !== (int) $purchase->suplier_id_suplier) {
                    throw new \DomainException('Satu dokumen retur hanya boleh berisi barang dari satu supplier.');
                }
                $supplierId = (int) $purchase->suplier_id_suplier;

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
                    throw new \DomainException('Jumlah retur melebihi sisa pembelian.');
                }

                $legacyReturn = $db->query(
                    'SELECT COUNT(*) AS total FROM ' . $db->prefixTable('retur_suplier') . ' retur '
                    . 'JOIN ' . $db->prefixTable('detail_pembelian') . ' detail '
                    . 'ON detail.iddetail_pembelian = retur.detail_pembelian_iddetail_pembelian '
                    . 'WHERE detail.pembelian_idpembelian = ? AND detail.no_batch = ? '
                    . 'AND detail.barang_idbarang = ? AND retur.barang_rusak_id IS NULL',
                    [$purchase->pembelian_idpembelian, $purchase->no_batch, $purchase->barang_idbarang]
                )->getRow();
                if ((int) ($legacyReturn->total ?? 0) > 0) {
                    throw new \DomainException('Retur lama harus direkonsiliasi sebelum retur baru.');
                }

                $damagedRows = $db->query(
                    'SELECT idbarang_rusak, jumlah FROM ' . $db->prefixTable('barang_rusak')
                    . ' WHERE idpembelian = ? AND no_nota_sup = ? AND barang_idbarang = ?'
                    . ' AND unit_idunit = ? ORDER BY idbarang_rusak FOR UPDATE',
                    [$purchase->pembelian_idpembelian, $purchase->no_batch,
                        $purchase->barang_idbarang, $useridunit]
                )->getResult();
                $remaining = $requested;
                foreach ($damagedRows as $damaged) {
                    $alreadyReturned = $db->table('retur_suplier')
                        ->selectSum('jumlah')
                        ->where('barang_rusak_id', $damaged->idbarang_rusak)
                        ->get()->getRow();
                    $available = (int) $damaged->jumlah - (int) ($alreadyReturned->jumlah ?? 0);
                    $allocated = min($remaining, max(0, $available));
                    if ($allocated === 0) {
                        continue;
                    }
                    if (! $this->ReturSuplierModel->insert_ReturSuplier([
                        'no_retur_suplier' => $noRetur,
                        'tanggal' => $tanggalRetur,
                        'jumlah' => $allocated,
                        'satuan' => $purchase->satuan_beli,
                        'barang_idbarang' => $purchase->barang_idbarang,
                        'detail_pembelian_iddetail_pembelian' => $detailId,
                        'barang_rusak_id' => $damaged->idbarang_rusak,
                        'input_by' => session('ID_AKUN'),
                        'unit_idunit' => $purchase->unit_idunit,
                    ])) {
                        throw new \RuntimeException('Retur supplier gagal disimpan.');
                    }
                    $remaining -= $allocated;
                    if ($remaining === 0) {
                        break;
                    }
                }
                if ($remaining !== 0) {
                    throw new \DomainException('Barang Rusak dari supplier/nota ini yang siap diretur: ' . ($requested - $remaining) . ' unit; jumlah diminta: ' . $requested . ' unit. Catat Barang Rusak terlebih dahulu atau pilih nota asal yang sesuai.');
                }
            }

            if (! $db->table('kredit_retur_suplier')->insert([
                'no_retur_suplier' => $noRetur,
                'suplier_id' => $supplierId,
                'unit_id' => $useridunit,
                'status' => 'menunggu',
                'nominal' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ])) {
                throw new \RuntimeException('Kredit retur menunggu persetujuan gagal dicatat.');
            }

            if ($db->transStatus() === false || ! $db->transCommit()) {
                throw new \RuntimeException('Transaksi retur supplier gagal disimpan.');
            }

            return redirect()->to(base_url('/retur_suplier'))->with('sukses', 'Data Berhasil Disimpan');
        } catch (\Throwable $error) {
            $db->transRollback();
            log_message('error', 'Retur_Suplier::insert gagal: ' . $error->getMessage());
            $message = $error instanceof \DomainException
                ? $error->getMessage()
                : 'Retur supplier gagal disimpan. Hubungi administrator dengan waktu kejadian untuk pemeriksaan log.';
            return redirect()->to(base_url('/retur_suplier'))->with('gagal', $message);
        }
    }
}
