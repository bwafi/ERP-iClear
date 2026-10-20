<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class ClaimDamagedOrigin
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function get(int $claimId, int $unitId): array
    {
        $claim = $this->db->table('riwayat_claim_garansi claim')
            ->select('claim.*, service.no_service')
            ->join('service', 'service.idservice = claim.service_idservice')
            ->where('claim.idriwayat_claim_garansi', $claimId)
            ->where('service.unit_idunit', $unitId)->get()->getRowArray();
        if (! $claim || empty($claim['original_service_sparepart_id']) || empty($claim['original_barang_id'])) {
            throw new \DomainException('Klaim lama belum mencatat sparepart yang diklaim. Administrator perlu memeriksa bukti klaim; jangan memilih supplier perkiraan.');
        }
        $itemId = (int) $claim['original_barang_id'];
        $item = $this->db->table('barang')->where('idbarang', $itemId)->get()->getRowArray();
        if (! $item || ($item['jenis_item'] ?? null) !== 'fisik') {
            throw new \DomainException('Barang klaim belum disetujui sebagai barang fisik. Minta Administrator memeriksa Review Tipe Item.');
        }
        $origin = null;
        if (! empty($claim['original_purchase_detail_id'])) {
            $origin = $this->db->table('detail_pembelian detail')
                ->select('detail.pembelian_idpembelian, detail.no_batch, supplier.nama_suplier, purchase.no_nota_supplier')
                ->join('pembelian purchase', 'purchase.idpembelian = detail.pembelian_idpembelian')
                ->join('suplier supplier', 'supplier.id_suplier = purchase.suplier_id_suplier', 'left')
                ->where('detail.iddetail_pembelian', (int) $claim['original_purchase_detail_id'])
                ->where('detail.barang_idbarang', $itemId)->get()->getRowArray();
            if (! $origin) {
                throw new \DomainException('Referensi pembelian klaim tidak ditemukan. Administrator perlu memeriksa bukti asal.');
            }
        }
        $recorded = $this->db->table('barang_rusak')->selectSum('jumlah')
            ->where('claim_id', $claimId)->where('barang_idbarang', $itemId)->get()->getRow();
        return [
            'claim_id' => $claimId,
            'no_service' => $claim['no_service'],
            'item_id' => $itemId,
            'name' => $item['nama_barang'],
            'purchase_id' => (int) ($origin['pembelian_idpembelian'] ?? 0),
            'batch' => (string) ($origin['no_batch'] ?? ''),
            'supplier' => $origin['nama_suplier'] ?? 'Belum diketahui',
            'invoice' => $origin['no_nota_supplier'] ?? 'Belum diketahui',
            'origin_status' => $origin ? 'terverifikasi' : 'belum_diketahui',
            'available' => max(0, (int) $claim['original_quantity'] - (int) ($recorded->jumlah ?? 0)),
        ];
    }
}
