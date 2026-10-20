<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ProductPriceCatalog
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function update(int $itemId, int $sellingPrice, ?string $warrantyOption, int $actorId, bool $preserveLegacy = false, bool $clearLegacy = false): bool
    {
        if ($itemId <= 0 || $sellingPrice < 0 || $actorId <= 0
            || (! $preserveLegacy && ! $clearLegacy && $warrantyOption !== null && ! \App\Services\Service\WarrantyTerm::isValid($warrantyOption))) {
            throw new RuntimeException('Harga, garansi, petugas, atau ID master tidak valid.');
        }

        $this->db->transBegin();
        try {
            $table = $this->db->prefixTable('barang');
            $item = $this->db->query(
                "SELECT idbarang, harga, garansi_hari_default, garansi_opsi_default FROM {$table} WHERE idbarang = ? AND deleted = '0' FOR UPDATE",
                [$itemId]
            )->getRow();
            if (! $item) {
                throw new RuntimeException('ID master barang/jasa tidak ditemukan atau sudah tidak aktif.');
            }

            $oldPrice = (int) $item->harga;
            $oldWarranty = $item->garansi_hari_default === null ? null : (int) $item->garansi_hari_default;
            $oldOption = $item->garansi_opsi_default === null ? null : (string) $item->garansi_opsi_default;
            $newOption = $preserveLegacy ? $oldOption : $warrantyOption;
            $newLegacyDays = $clearLegacy || ! $preserveLegacy && $warrantyOption !== null
                ? null
                : $oldWarranty;
            if ($sellingPrice === $oldPrice && $newOption === $oldOption && $newLegacyDays === $oldWarranty) {
                $this->db->transRollback();
                return false;
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('barang_harga_history')->insert([
                'barang_idbarang' => $itemId,
                'harga_sebelum' => $oldPrice,
                'harga_sesudah' => $sellingPrice,
                'garansi_sebelum' => $oldWarranty,
                'garansi_sesudah' => $newLegacyDays,
                'garansi_opsi_sebelum' => $oldOption,
                'garansi_opsi_sesudah' => $newOption,
                'petugas_idakun' => $actorId,
                'berlaku_mulai' => $now,
                'created_at' => $now,
            ]);
            $update = ['harga' => $sellingPrice];
            if (! $preserveLegacy) {
                $update['garansi_opsi_default'] = $newOption;
                $update['garansi_hari_default'] = $newLegacyDays;
            }
            $this->db->table('barang')->where('idbarang', $itemId)->update($update);
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
