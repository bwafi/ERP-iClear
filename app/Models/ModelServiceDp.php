<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ledger DP (uang muka) service.
 *
 * Hanya mencatat DP, bukan pelunasan. `service.dp_bayar` dipelihara sebagai
 * proyeksi oleh ServiceDpService, bukan sumber otoritatif lagi untuk service
 * baru.
 *
 * Catatan: method baca memakai `$this->db->table()` (builder segar) agar tidak
 * mengotori builder internal Model — Model hanya dipakai untuk `insert()`.
 */
class ModelServiceDp extends Model
{
    protected $table = 'service_dp';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'id',
        'service_idservice',
        'kode_dp',
        'tanggal',
        'jumlah',
        'metode',
        'bank_idbank',
        'referensi',
        'status',
        'keterangan',
        'unit_id',
        'input_by',
        'created_at',
        'updated_at',
    ];

    public function getByService($idservice)
    {
        return $this->db->table($this->table)
            ->where('service_idservice', (int) $idservice)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResult();
    }

    public function getAktifByService($idservice)
    {
        return $this->db->table($this->table)
            ->where('service_idservice', (int) $idservice)
            ->where('status', 'aktif')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResult();
    }

    public function totalAktif($idservice): int
    {
        $row = $this->db->table($this->table)
            ->selectSum('jumlah')
            ->where('service_idservice', (int) $idservice)
            ->where('status', 'aktif')
            ->get()
            ->getRow();

        return (int) ($row->jumlah ?? 0);
    }

    public function kodeDpSudahAda(string $kodeDp): bool
    {
        return $this->db->table($this->table)
            ->where('kode_dp', $kodeDp)
            ->countAllResults() > 0;
    }
}
