<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Harga jual per produk+unit.
 *
 * Tanpa baris = ikut `barang.harga`. Harga jual tidak disimpan di `hpp_unit`
 * karena baris HPP dengan nilai 0 akan merusak fallback HPP.
 */
class ModelHargaJualUnit extends Model
{
    protected $table = 'harga_jual_unit';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['barang_idbarang', 'unit_idunit', 'harga_jual', 'updated_by', 'updated_at'];

    public function getFor(int $idbarang, int $idunit)
    {
        return $this->where([
            'barang_idbarang' => $idbarang,
            'unit_idunit'     => $idunit,
        ])->first();
    }

    public function hargaJualUnit(int $idbarang, int $idunit): ?float
    {
        $row = $this->getFor($idbarang, $idunit);

        return $row === null ? null : (float) $row->harga_jual;
    }

    /**
     * Harga jual transaksi baru: override unit, lalu master `barang.harga`.
     */
    public function resolve(int $idbarang, int $idunit): float
    {
        // Raw SQL + prefixTable supaya tetap benar saat DBPrefix dipakai (test).
        $sql = 'SELECT COALESCE(hju.harga_jual, b.harga, 0) AS harga
                FROM ' . $this->db->prefixTable('barang') . ' b
                LEFT JOIN ' . $this->db->prefixTable('harga_jual_unit') . ' hju
                       ON hju.barang_idbarang = b.idbarang AND hju.unit_idunit = ?
                WHERE b.idbarang = ?';

        $row = $this->db->query($sql, [$idunit, $idbarang])->getRow();

        return $row === null ? 0.0 : (float) $row->harga;
    }

    public function setHarga(int $idbarang, int $idunit, float $harga, ?int $userId = null): bool
    {
        if ($idbarang <= 0 || $idunit <= 0 || $harga <= 0) {
            return false;
        }

        return (bool) $this->db->table($this->table)->upsert([
            'barang_idbarang' => $idbarang,
            'unit_idunit'     => $idunit,
            'harga_jual'      => $harga,
            'updated_by'      => $userId,
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Hapus override; produk+unit kembali mengikuti `barang.harga`.
     */
    public function clear(int $idbarang, int $idunit): bool
    {
        return $this->db->table($this->table)
            ->where([
                'barang_idbarang' => $idbarang,
                'unit_idunit'     => $idunit,
            ])
            ->delete();
    }
}
