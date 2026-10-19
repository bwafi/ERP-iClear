<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class PurchaseBatchOrigin
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public static function selection($input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }
        if (! is_scalar($input) || ! ctype_digit((string) $input) || (int) $input <= 0) {
            throw new \InvalidArgumentException('Pilih batch pembelian yang valid atau biarkan asal tidak diketahui.');
        }
        return (int) $input;
    }

    public function candidates(int $itemId, int $unitId): array
    {
        if ($itemId <= 0 || $unitId <= 0) {
            return [];
        }

        return $this->db->table('detail_pembelian detail')
            ->select('detail.iddetail_pembelian, detail.jumlah, detail.tanggal, purchase.no_nota_supplier, supplier.nama_suplier')
            ->join('pembelian purchase', 'purchase.idpembelian = detail.pembelian_idpembelian')
            ->join('suplier supplier', 'supplier.id_suplier = purchase.suplier_id_suplier', 'left')
            ->where('detail.barang_idbarang', $itemId)
            ->where('detail.unit_idunit', $unitId)
            ->orderBy('detail.iddetail_pembelian', 'DESC')
            ->limit(100)
            ->get()->getResultArray();
    }

    public function validate(?int $purchaseDetailId, int $itemId, int $unitId, int $quantity, ?string $serviceAt = null): ?int
    {
        if ($purchaseDetailId === null) {
            return null;
        }
        if ($purchaseDetailId <= 0 || $itemId <= 0 || $unitId <= 0 || $quantity <= 0) {
            throw new \InvalidArgumentException('Batch pembelian tidak valid.');
        }

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('SELECT iddetail_pembelian FROM ' . $this->db->prefixTable('detail_pembelian')
                . ' WHERE iddetail_pembelian = ? FOR UPDATE', [$purchaseDetailId]);
        }

        $detail = $this->db->table('detail_pembelian detail')
            ->select('detail.barang_idbarang, detail.unit_idunit, detail.jumlah, detail.tanggal, purchase.suplier_id_suplier')
            ->join('pembelian purchase', 'purchase.idpembelian = detail.pembelian_idpembelian')
            ->where('detail.iddetail_pembelian', $purchaseDetailId)
            ->get()->getRow();
        if ($detail === null || (int) $detail->barang_idbarang !== $itemId
            || (int) $detail->unit_idunit !== $unitId
            || (int) $detail->suplier_id_suplier <= 0) {
            throw new \InvalidArgumentException('Batch bukan pembelian barang dan unit tersebut dengan supplier tercatat.');
        }
        if ($serviceAt !== null && $serviceAt !== '' && (string) $detail->tanggal > $serviceAt) {
            throw new \InvalidArgumentException('Tanggal pembelian batch lebih baru dari service asal.');
        }

        $attributed = (int) ($this->db->table('detail_penjualan')
            ->selectSum('jumlah', 'total')
            ->where('source_purchase_detail_id', $purchaseDetailId)
            ->get()->getRow('total') ?? 0);
        if ($attributed + $quantity > (int) $detail->jumlah) {
            throw new \InvalidArgumentException('Jumlah yang ditautkan melebihi jumlah pada batch pembelian.');
        }

        return $purchaseDetailId;
    }
}
