<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registry terpusat Hutang Piutang.
 *
 * CATATAN PENTING:
 * - Baris `is_projection = 1` (pembelian, piutang_legacy) adalah penunjuk ke
 *   sumber; saldo otoritatif TIDAK boleh ditulis lewat model ini. Semua
 *   penulisan saldo untuk jenis authoritative (piutang_pelanggan, kasbon)
 *   dilakukan oleh HutangPiutangService di dalam DB transaction.
 */
class ModelHutangPiutang extends Model
{
    protected $table = 'hutang_piutang';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'kode',
        'jenis',
        'sumber_tipe',
        'sumber_id',
        'is_projection',
        'pihak_tipe',
        'pihak_id',
        'nama_pihak',
        'tanggal',
        'jatuh_tempo',
        'uraian',
        'total',
        'total_dibayar',
        'sisa',
        'status',
        'keterangan',
        'unit_id',
        'input_by',
        'deleted',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Baris registry aktif (belum dihapus) untuk sumber tertentu.
     */
    public function findBySumber(string $sumberTipe, int $sumberId)
    {
        return $this->where('sumber_tipe', $sumberTipe)
            ->where('sumber_id', $sumberId)
            ->first();
    }

    /**
     * Cari satu baris authoritative berdasarkan id + jenis (guard pembayaran).
     */
    public function findAuthoritative(int $id)
    {
        return $this->where('id', $id)
            ->where('deleted', 0)
            ->whereIn('sumber_tipe', ['piutang_pelanggan', 'kasbon'])
            ->first();
    }
}
