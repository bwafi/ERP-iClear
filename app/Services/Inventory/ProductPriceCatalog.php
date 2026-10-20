<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ProductPriceCatalog
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function update(int $itemId, int $sellingPrice, ?int $warrantyDays, int $actorId): bool
    {
        if ($itemId <= 0 || $sellingPrice < 0 || $warrantyDays !== null && ($warrantyDays < 0 || $warrantyDays > 9999) || $actorId <= 0) {
            throw new RuntimeException('Harga, garansi, petugas, atau ID master tidak valid.');
        }

        $this->db->transBegin();
        try {
            $table = $this->db->prefixTable('barang');
            $item = $this->db->query(
                "SELECT idbarang, harga, garansi_hari_default FROM {$table} WHERE idbarang = ? AND deleted = '0' FOR UPDATE",
                [$itemId]
            )->getRow();
            if (! $item) {
                throw new RuntimeException('ID master barang/jasa tidak ditemukan atau sudah tidak aktif.');
            }

            $oldPrice = (int) $item->harga;
            $oldWarranty = $item->garansi_hari_default === null ? null : (int) $item->garansi_hari_default;
            if ($sellingPrice === $oldPrice && $warrantyDays === $oldWarranty) {
                $this->db->transRollback();
                return false;
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('barang_harga_history')->insert([
                'barang_idbarang' => $itemId,
                'harga_sebelum' => $oldPrice,
                'harga_sesudah' => $sellingPrice,
                'garansi_sebelum' => $oldWarranty,
                'garansi_sesudah' => $warrantyDays,
                'petugas_idakun' => $actorId,
                'berlaku_mulai' => $now,
                'created_at' => $now,
            ]);
            $this->db->table('barang')->where('idbarang', $itemId)->update([
                'harga' => $sellingPrice,
                'garansi_hari_default' => $warrantyDays,
            ]);
            if (! $this->db->transStatus()) {
                throw new RuntimeException('Gagal menyimpan perubahan Kartu Harga.');
            }
            $this->db->transCommit();
            return true;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
