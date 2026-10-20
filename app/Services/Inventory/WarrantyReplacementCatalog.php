<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class WarrantyReplacementCatalog
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function forUnit(int $unitId): array
    {
        if ($unitId <= 0) {
            return ['ready' => [], 'pending' => []];
        }
        $rows = $this->db->table('barang product')
            ->select('product.idbarang, product.kode_barang, product.nama_barang, product.harga, product.jenis_item, stock.stok_akhir')
            ->join('stok_barang stock', 'stock.idbarang = product.idbarang')
            ->where('product.deleted', '0')->where('stock.id_unit', $unitId)
            ->where('stock.stok_akhir >', 0)->orderBy('product.nama_barang')->get()->getResultArray();
        $result = ['ready' => [], 'pending' => []];
        $origin = new PurchaseBatchOrigin($this->db);
        foreach ($rows as $row) {
            if ($row['jenis_item'] === 'fisik') {
                $row['batches'] = $origin->candidates((int) $row['idbarang'], $unitId);
                $result['ready'][] = $row;
            } elseif ($row['jenis_item'] !== 'jasa') {
                $result['pending'][] = $row;
            }
        }
        return $result;
    }
}
