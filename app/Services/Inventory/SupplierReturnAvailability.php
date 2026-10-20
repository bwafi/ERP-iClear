<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class SupplierReturnAvailability
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function forUnit(int $unitId): array
    {
        if ($unitId <= 0) {
            return [];
        }
        $detail = $this->db->prefixTable('detail_pembelian');
        $purchase = $this->db->prefixTable('pembelian');
        $damaged = $this->db->prefixTable('barang_rusak');
        $returns = $this->db->prefixTable('retur_suplier');
        $rows = $this->db->query(
            "SELECT d.iddetail_pembelian, d.jumlah, p.suplier_id_suplier,
                COALESCE(returned.quantity, 0) AS returned_quantity,
                COALESCE(damage.quantity, 0) AS damaged_quantity,
                COALESCE(legacy.total, 0) AS legacy_returns
            FROM {$detail} d
            JOIN {$purchase} p ON p.idpembelian = d.pembelian_idpembelian
            LEFT JOIN (
                SELECT detail_pembelian_iddetail_pembelian, SUM(jumlah) AS quantity
                FROM {$returns} GROUP BY detail_pembelian_iddetail_pembelian
            ) returned ON returned.detail_pembelian_iddetail_pembelian = d.iddetail_pembelian
            LEFT JOIN (
                SELECT b.idpembelian, b.no_nota_sup, b.barang_idbarang, b.unit_idunit,
                    SUM(GREATEST(b.jumlah - COALESCE(used.quantity, 0), 0)) AS quantity
                FROM {$damaged} b
                LEFT JOIN (
                    SELECT barang_rusak_id, SUM(jumlah) AS quantity
                    FROM {$returns} WHERE barang_rusak_id IS NOT NULL GROUP BY barang_rusak_id
                ) used ON used.barang_rusak_id = b.idbarang_rusak
                GROUP BY b.idpembelian, b.no_nota_sup, b.barang_idbarang, b.unit_idunit
            ) damage ON damage.idpembelian = d.pembelian_idpembelian
                AND damage.no_nota_sup = d.no_batch AND damage.barang_idbarang = d.barang_idbarang
                AND damage.unit_idunit = d.unit_idunit
            LEFT JOIN (
                SELECT old.pembelian_idpembelian, old.no_batch, old.barang_idbarang, old.unit_idunit,
                    COUNT(*) AS total
                FROM {$returns} r JOIN {$detail} old
                    ON old.iddetail_pembelian = r.detail_pembelian_iddetail_pembelian
                WHERE r.barang_rusak_id IS NULL
                GROUP BY old.pembelian_idpembelian, old.no_batch, old.barang_idbarang, old.unit_idunit
            ) legacy ON legacy.pembelian_idpembelian = d.pembelian_idpembelian
                AND legacy.no_batch = d.no_batch AND legacy.barang_idbarang = d.barang_idbarang
                AND legacy.unit_idunit = d.unit_idunit
            WHERE d.unit_idunit = ?",
            [$unitId]
        )->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $remaining = max(0, (int) $row['jumlah'] - (int) $row['returned_quantity']);
            $available = max(0, (int) $row['damaged_quantity']);
            $reason = '';
            if ((int) $row['suplier_id_suplier'] <= 0) {
                $reason = 'Supplier pembelian belum tercatat.';
            } elseif ((int) $row['legacy_returns'] > 0) {
                $reason = 'Retur lama pada batch ini perlu direkonsiliasi.';
            } elseif ($remaining === 0) {
                $reason = 'Jumlah pembelian sudah seluruhnya diretur.';
            } elseif ($available === 0) {
                $reason = 'Belum ada Barang Rusak dari supplier/nota ini yang siap diretur.';
            }
            $result[(int) $row['iddetail_pembelian']] = [
                'remaining_purchase' => $remaining,
                'available_damaged' => $available,
                'returnable' => $reason === '' ? min($remaining, $available) : 0,
                'reason' => $reason,
            ];
        }
        return $result;
    }
}
