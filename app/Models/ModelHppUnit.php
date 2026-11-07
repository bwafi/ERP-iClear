<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * HPP per produk+unit.
 *
 * Nilai `manual` berasal dari koreksi admin dan tidak boleh ditimpa sync stok
 * awal. Nilai `stok_awal` boleh diperbarui setiap ada input stok awal baru.
 */
class ModelHppUnit extends Model
{
    protected $table = 'hpp_unit';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['barang_idbarang', 'unit_idunit', 'hpp', 'sumber', 'updated_by', 'updated_at'];

    public function getFor(int $idbarang, int $idunit)
    {
        return $this->where([
            'barang_idbarang' => $idbarang,
            'unit_idunit'     => $idunit,
        ])->first();
    }

    /**
     * Koreksi admin: hanya untuk satu produk+unit dan selalu menang atas sync.
     */
    public function setManual(int $idbarang, int $idunit, float $hpp, ?int $userId = null): bool
    {
        return (bool) $this->db->table($this->table)->upsert([
            'barang_idbarang' => $idbarang,
            'unit_idunit'     => $idunit,
            'hpp'             => $hpp,
            'sumber'          => 'manual',
            'updated_by'      => $userId,
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * HPP untuk transaksi baru: per unit, lalu global `hpp_barang`, lalu
     * master `barang.harga_beli`.
     */
    public function resolve(int $idbarang, int $idunit): float
    {
        return $this->resolveMany([$idbarang], $idunit)[$idbarang] ?? 0.0;
    }

    /**
     * @param list<int> $idbarang
     * @return array<int, float> idbarang => hpp
     */
    public function resolveMany(array $idbarang, int $idunit): array
    {
        $ids = array_values(array_unique(array_map('intval', $idbarang)));
        if ($ids === []) {
            return [];
        }

        // Raw SQL + prefixTable supaya tetap benar saat DBPrefix dipakai (test).
        $sql = 'SELECT b.idbarang, COALESCE(hu.hpp, hb.hpp, b.harga_beli, 0) AS hpp
                FROM ' . $this->db->prefixTable('barang') . ' b
                LEFT JOIN ' . $this->db->prefixTable('hpp_unit') . ' hu
                       ON hu.barang_idbarang = b.idbarang AND hu.unit_idunit = ?
                LEFT JOIN ' . $this->db->prefixTable('hpp_barang') . ' hb
                       ON hb.idbarang = b.idbarang
                WHERE b.idbarang IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';

        $rows = $this->db->query($sql, array_merge([$idunit], $ids))->getResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->idbarang] = (float) $row->hpp;
        }

        return $map;
    }

    /**
     * Ikutkan HPP dari stok awal baru, kecuali admin sudah override manual.
     */
    public function syncFromStokAwal(int $idbarang, int $idunit, float $hpp): bool
    {
        if ($hpp <= 0) {
            return false;
        }

        $existing = $this->getFor($idbarang, $idunit);
        if ($existing !== null && $existing->sumber === 'manual') {
            return false;
        }

        return (bool) $this->db->table($this->table)->upsert([
            'barang_idbarang' => $idbarang,
            'unit_idunit'     => $idunit,
            'hpp'             => $hpp,
            'sumber'          => 'stok_awal',
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }
}
