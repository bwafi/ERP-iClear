<?php

namespace App\Services\Inventory;

use CodeIgniter\Database\BaseConnection;

final class ProductTypeReview
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function rows(int $offset = 0, int $limit = 100): array
    {
        $sql = <<<'SQL'
SELECT b.idbarang, b.kode_barang, b.nama_barang, b.jenis_item, k.nama_kategori,
    COALESCE(st.stok, 0) AS stok, COALESCE(p.jumlah, 0) AS dibeli,
    COALESCE(p.transaksi, 0) AS nota_pembelian, COALESCE(s.jumlah, 0) AS dipakai_service,
    COALESCE(j.jumlah, 0) AS terjual
FROM barang b
LEFT JOIN kategori k ON k.id = b.idkategori
LEFT JOIN (SELECT idbarang, SUM(stok_akhir) AS stok FROM stok_barang GROUP BY idbarang) st ON st.idbarang = b.idbarang
LEFT JOIN (SELECT barang_idbarang, SUM(jumlah) AS jumlah, COUNT(DISTINCT pembelian_idpembelian) AS transaksi
    FROM detail_pembelian GROUP BY barang_idbarang) p ON p.barang_idbarang = b.idbarang
LEFT JOIN (SELECT barang_idbarang, SUM(jumlah) AS jumlah FROM service_sparepart GROUP BY barang_idbarang) s
    ON s.barang_idbarang = b.idbarang
LEFT JOIN (SELECT barang_idbarang, SUM(jumlah) AS jumlah FROM detail_penjualan GROUP BY barang_idbarang) j
    ON j.barang_idbarang = b.idbarang
WHERE b.deleted = '0' ORDER BY b.idbarang LIMIT ? OFFSET ?
SQL;
        $rows = $this->db->query($sql, [$limit, $offset])->getResultArray();
        foreach ($rows as &$row) {
            [$row['usulan'], $row['alasan'], $row['ambigu']] = self::suggest($row);
        }
        return $rows;
    }

    public function count(): int
    {
        return $this->db->table('barang')->where('deleted', '0')->countAllResults();
    }

    public static function suggest(array $row): array
    {
        if (in_array($row['jenis_item'], ['fisik', 'jasa'], true)) {
            return [$row['jenis_item'], 'Tipe master sudah ditetapkan.', false];
        }

        $name = strtoupper(trim((string) $row['nama_barang']));
        $serviceName = preg_match('/^(PUP|JASA|MESIN|RESTORE|KOMISI|SERVICE MESIN)\b/u', $name) === 1;
        $physicalEvidence = (float) $row['stok'] != 0 || (float) $row['dibeli'] > 0;
        if ($physicalEvidence && !$serviceName) {
            return ['fisik', 'Ada stok atau riwayat pembelian; perlu persetujuan sebelum mengubah master.', false];
        }
        if ($physicalEvidence && $serviceName) {
            return ['perlu review', 'Nama mengarah jasa tetapi ada stok/pembelian; bukti bertentangan.', true];
        }
        if ($serviceName) {
            return ['jasa', 'Hanya indikasi nama; tanpa bukti stok/pembelian, wajib konfirmasi.', true];
        }
        if ((float) $row['dipakai_service'] > 0 || (float) $row['terjual'] > 0) {
            return ['perlu review', 'Pernah dipakai/terjual tanpa bukti stok; bisa jasa atau barang habis.', true];
        }
        return ['perlu review', 'Belum ada bukti transaksi yang cukup.', true];
    }
}
