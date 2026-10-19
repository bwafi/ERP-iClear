<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class StockCardLedger
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function forItem(int $itemId, int $unitId): array
    {
        $parameters = array_merge(...array_fill(0, 9, [$itemId, $unitId]));
        return $this->db->query(self::sql(), $parameters)->getResultArray();
    }

    public static function sql(): string
    {
        return <<<'SQL'
SELECT movements.*, SUM(movements.masuk - movements.keluar) OVER (
    ORDER BY movements.tanggal, movements.urutan, movements.referensi_id
) AS saldo
FROM (
    SELECT sa.tanggal, 1 AS urutan, 'Stok Awal' AS sumber, sa.idstok_awal AS referensi_id,
        sa.jumlah AS masuk, 0 AS keluar, sa.harga_beli AS hpp_satuan
    FROM stok_awal sa WHERE sa.barang_idbarang = ? AND sa.unit_idunit = ?
    UNION ALL
    SELECT dp.tanggal, 2, 'Pembelian', dp.iddetail_pembelian, dp.jumlah, 0, dp.hitung_hpp
    FROM detail_pembelian dp WHERE dp.barang_idbarang = ? AND dp.unit_idunit = ?
    UNION ALL
    SELECT dm.tanggal_terima, 3, 'Mutasi Masuk', dm.iddetail_mutasi, dm.jumlah_terima, 0, dm.hpp_barang
    FROM detail_mutasi dm WHERE dm.barang_idbarang = ? AND dm.terima_idunit = ? AND dm.jumlah_terima > 0
    UNION ALL
    SELECT rp.tanggal, 4, 'Retur Pelanggan', rp.idretur_pelanggan, rp.jumlah, 0, NULL
    FROM retur_pelanggan rp WHERE rp.barang_idbarang = ? AND rp.unit_idunit = ?
    UNION ALL
    SELECT p.tanggal, 5, 'Penjualan/Service', dp.iddetail_penjualan, 0, dp.jumlah, dp.hpp_penjualan
    FROM detail_penjualan dp JOIN penjualan p ON p.idpenjualan = dp.penjualan_idpenjualan
    WHERE dp.barang_idbarang = ? AND dp.unit_idunit = ?
    UNION ALL
    SELECT dm.tanggal_kirim, 6, 'Mutasi Keluar', dm.iddetail_mutasi, 0, dm.jumlah_kirim, dm.hpp_barang
    FROM detail_mutasi dm WHERE dm.barang_idbarang = ? AND dm.kirim_idunit = ?
    UNION ALL
    SELECT br.tanggal_rusak, 7, CASE WHEN br.claim_id IS NULL THEN 'Barang Rusak' ELSE 'Barang Rusak Klaim (tanpa mutasi)' END,
        br.idbarang_rusak, 0, CASE WHEN br.claim_id IS NULL THEN br.jumlah ELSE 0 END, NULL
    FROM barang_rusak br WHERE br.barang_idbarang = ? AND br.unit_idunit = ?
    UNION ALL
    SELECT rs.tanggal, 8, CASE WHEN rs.barang_rusak_id IS NULL THEN 'Retur Supplier' ELSE 'Retur Barang Rusak (tanpa mutasi)' END,
        rs.idretur_suplier, 0, CASE WHEN rs.barang_rusak_id IS NULL THEN rs.jumlah ELSE 0 END, NULL
    FROM retur_suplier rs WHERE rs.barang_idbarang = ? AND rs.unit_idunit = ?
    UNION ALL
    SELECT g.created_at, 9, 'Pengganti Garansi', g.id, 0, g.jumlah, g.hpp_satuan
    FROM garansi_sparepart_keluar g WHERE g.barang_id = ? AND g.unit_id = ?
) movements ORDER BY movements.tanggal, movements.urutan, movements.referensi_id
SQL;
    }
}
