<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Status aktif produk per unit.
 *
 * Tanpa baris = aktif. Baris hanya ditulis saat status diubah, jadi tabel
 * tidak perlu diisi untuk semua produk+unit.
 */
class ModelBarangUnit extends Model
{
    protected $table = 'barang_unit';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['barang_idbarang', 'unit_idunit', 'aktif', 'updated_by', 'updated_at'];

    public function getFor(int $idbarang, int $idunit)
    {
        return $this->where([
            'barang_idbarang' => $idbarang,
            'unit_idunit'     => $idunit,
        ])->first();
    }

    public function setAktif(int $idbarang, int $idunit, bool $aktif, ?int $userId = null): bool
    {
        return (bool) $this->db->table($this->table)->upsert([
            'barang_idbarang' => $idbarang,
            'unit_idunit'     => $idunit,
            'aktif'           => $aktif ? 1 : 0,
            'updated_by'      => $userId,
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Aktifkan lagi produk yang nonaktif di satu unit tanpa membuat baris baru
     * untuk produk yang memang sudah aktif.
     */
    public function aktifkanJikaNonaktif(int $idbarang, int $idunit, ?int $userId = null): bool
    {
        return $this->db->table($this->table)
            ->where([
                'barang_idbarang' => $idbarang,
                'unit_idunit'     => $idunit,
                'aktif'           => 0,
            ])
            ->update([
                'aktif'      => 1,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
