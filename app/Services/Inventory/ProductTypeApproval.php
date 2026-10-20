<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class ProductTypeApproval
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function approve(int $itemId, string $type, int $actorId, string $reason): bool
    {
        $reason = trim($reason);
        if ($itemId <= 0 || $actorId <= 0 || ! in_array($type, ['fisik', 'jasa'], true)
            || mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new \DomainException('Pilih tipe dan tulis bukti pemeriksaan minimal 10, maksimal 500 karakter.');
        }
        $this->db->transBegin();
        try {
            $product = $this->db->query('SELECT jenis_item FROM ' . $this->db->prefixTable('barang')
                . " WHERE idbarang = ? AND deleted = '0' FOR UPDATE", [$itemId])->getRow();
            if (! $product) {
                throw new \DomainException('Barang aktif tidak ditemukan.');
            }
            if ($product->jenis_item === $type) {
                $this->db->transCommit();
                return false;
            }
            if (in_array($product->jenis_item, ['fisik', 'jasa'], true)) {
                throw new \DomainException('Tipe sudah ditetapkan. Perubahan tipe lama memerlukan review migrasi terpisah.');
            }
            if ($type === 'jasa') {
                foreach (['stok_awal', 'detail_pembelian', 'detail_penjualan', 'service_sparepart', 'barang_rusak', 'retur_suplier'] as $table) {
                    if ($this->db->table($table)->where('barang_idbarang', $itemId)->countAllResults() > 0) {
                        throw new \DomainException('Ada riwayat stok/transaksi. Penetapan jasa memerlukan rekonsiliasi terpisah, bukan menghapus persediaan.');
                    }
                }
                if ($this->db->table('stok_barang')->where('idbarang', $itemId)->where('stok_akhir !=', 0)->countAllResults() > 0) {
                    throw new \DomainException('Item masih memiliki stok. Tipe jasa ditolak sampai rekonsiliasi.');
                }
            }
            if (! $this->db->table('barang')->where('idbarang', $itemId)->update(['jenis_item' => $type])
                || ! $this->db->table('barang_tipe_history')->insert([
                    'barang_id' => $itemId, 'jenis_sebelum' => $product->jenis_item,
                    'jenis_sesudah' => $type, 'petugas_id' => $actorId, 'alasan' => $reason,
                    'created_at' => date('Y-m-d H:i:s'),
                ]) || $this->db->transStatus() === false || ! $this->db->transCommit()) {
                throw new \RuntimeException('Persetujuan tipe gagal tersimpan.');
            }
            return true;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
