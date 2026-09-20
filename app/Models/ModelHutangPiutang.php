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
        'lawan_unit_id',
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

    /**
     * Baris posisi antar unit (pihak_tipe = unit), opsional filter unit/jenis.
     */
    public function getHutangPiutangUnit(?int $unitId = null, ?string $jenis = null)
    {
        $builder = $this->select('hutang_piutang.*, unit.NAMA_UNIT, lawan.NAMA_UNIT as nama_lawan')
            ->join('unit', 'unit.idunit = hutang_piutang.unit_id', 'left')
            ->join('unit as lawan', 'lawan.idunit = hutang_piutang.lawan_unit_id', 'left')
            ->where('hutang_piutang.pihak_tipe', 'unit')
            ->where('hutang_piutang.deleted', 0);

        if (!empty($unitId)) {
            $builder->where('hutang_piutang.unit_id', (int) $unitId);
        }
        if (!empty($jenis)) {
            $builder->where('hutang_piutang.jenis', $jenis);
        }

        return $builder->orderBy('hutang_piutang.id', 'DESC')->findAll();
    }

    /**
     * Pasangan (hutang dikawankan piutang, dst) dari sumber mutasi yang sama.
     */
    public function getPasangan(object $hp): ?object
    {
        if (empty($hp->sumber_tipe) || empty($hp->sumber_id)) {
            return null;
        }
        $lawan = $hp->jenis === 'hutang' ? 'piutang' : 'hutang';
        return $this
            ->where('sumber_tipe', $hp->sumber_tipe)
            ->where('sumber_id', $hp->sumber_id)
            ->where('jenis', $lawan)
            ->first();
    }
}
