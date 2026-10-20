<?php

namespace App\Services\Service;

use App\Services\Inventory\PurchaseBatchOrigin;
use CodeIgniter\Database\BaseConnection;

final class WarrantyClaimIntake
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function originals(int $serviceId): array
    {
        $parts = $this->db->table('service_sparepart part')
            ->select('part.idservice_sparepart, part.barang_idbarang, part.jumlah, part.source_purchase_detail_id, barang.kode_barang, barang.nama_barang, barang.jenis_item, detail.no_batch, purchase.no_nota_supplier, supplier.nama_suplier')
            ->join('barang', 'barang.idbarang = part.barang_idbarang')
            ->join('detail_pembelian detail', 'detail.iddetail_pembelian = part.source_purchase_detail_id AND detail.barang_idbarang = part.barang_idbarang', 'left')
            ->join('pembelian purchase', 'purchase.idpembelian = detail.pembelian_idpembelian', 'left')
            ->join('suplier supplier', 'supplier.id_suplier = purchase.suplier_id_suplier', 'left')
            ->where('part.service_idservice', $serviceId)->where('part.jumlah >', 0)
            ->orderBy('part.idservice_sparepart')->get()->getResultArray();
        foreach ($parts as &$part) {
            $lastClaim = $this->db->table('riwayat_claim_garansi')
                ->where('service_idservice', $serviceId)
                ->where('original_service_sparepart_id', (int) $part['idservice_sparepart'])
                ->orderBy('idriwayat_claim_garansi', 'DESC')->get()->getRow();
            if (! $lastClaim) {
                continue;
            }
            $installed = $this->db->table('garansi_sparepart_keluar issue')
                ->select('issue.barang_id AS barang_idbarang, issue.jumlah, issue.source_purchase_detail_id, barang.kode_barang, barang.nama_barang, barang.jenis_item, detail.no_batch, purchase.no_nota_supplier, supplier.nama_suplier')
                ->join('barang', 'barang.idbarang = issue.barang_id')
                ->join('detail_pembelian detail', 'detail.iddetail_pembelian = issue.source_purchase_detail_id AND detail.barang_idbarang = issue.barang_id', 'left')
                ->join('pembelian purchase', 'purchase.idpembelian = detail.pembelian_idpembelian', 'left')
                ->join('suplier supplier', 'supplier.id_suplier = purchase.suplier_id_suplier', 'left')
                ->where('issue.claim_id', (int) $lastClaim->idriwayat_claim_garansi)->get()->getResultArray();
            if (count($installed) === 1) {
                $part = array_replace($part, $installed[0]);
            } elseif (count($installed) > 1) {
                $part['ambiguous_replacement'] = true;
            }
        }
        unset($part);
        return $parts;
    }

    public function validate(int $serviceId, int $unitId, array $input): array
    {
        $positiveInteger = static function ($value): int {
            if (! is_scalar($value) || ! ctype_digit((string) $value) || (int) $value <= 0) {
                throw new \DomainException('Pilih sparepart dan jumlah bulat lebih dari nol.');
            }
            return (int) $value;
        };
        $originalId = $positiveInteger($input['original_part_id'] ?? null);
        $quantity = $positiveInteger($input['claim_quantity'] ?? null);
        $original = null;
        foreach ($this->originals($serviceId) as $part) {
            if ((int) $part['idservice_sparepart'] === $originalId) {
                $original = $part;
                break;
            }
        }
        if ($original === null || ! empty($original['ambiguous_replacement']) || $quantity > (int) $original['jumlah']) {
            throw new \DomainException('Sparepart asal atau jumlah klaim tidak sesuai invoice service.');
        }
        $reason = trim(is_string($input['claim_reason'] ?? null) ? $input['claim_reason'] : '');
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new \DomainException('Isi keluhan klaim, maksimal 1000 karakter.');
        }
        if ($original['jenis_item'] === 'jasa') {
            return ['original_id' => $originalId, 'original_item_id' => (int) $original['barang_idbarang'],
                'original_batch_id' => null, 'quantity' => $quantity, 'reason' => $reason, 'replacement' => null];
        }
        $replacementId = $positiveInteger($input['replacement_id'] ?? null);
        $replacementQuantity = $positiveInteger($input['replacement_quantity'] ?? null);
        $product = $this->db->table('barang')->select('jenis_item')
            ->where('idbarang', $replacementId)->get()->getRow();
        if (! $product || $product->jenis_item !== 'fisik') {
            throw new \DomainException('Pengganti harus barang fisik yang tipenya sudah ditetapkan di master.');
        }
        $batchId = PurchaseBatchOrigin::selection($input['replacement_batch_id'] ?? null);
        if ($batchId === null) {
            throw new \DomainException('Pilih supplier/nota asal sparepart pengganti sesuai barang yang diambil.');
        }
        (new PurchaseBatchOrigin($this->db))->validate($batchId, $replacementId, $unitId, $replacementQuantity);
        return ['original_id' => $originalId, 'original_item_id' => (int) $original['barang_idbarang'],
            'original_batch_id' => $original['source_purchase_detail_id'] === null ? null : (int) $original['source_purchase_detail_id'],
            'quantity' => $quantity, 'reason' => $reason,
            'replacement' => ['id' => $replacementId, 'qty_tambahan' => $replacementQuantity,
                'purchase_detail_id' => $batchId, 'harga' => '0', 'total' => '0']];
    }
}
