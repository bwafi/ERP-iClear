<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Alokasi saldo awal per unit pada satu rekening fisik (akun_kas_bank).
 *
 * Rekening fisik punya SATU saldo awal (saldo_awal_kas_bank). Alokasi di sini
 * membagi saldo awal itu secara audit-able ke unit-unit pemakai rekening,
 * untuk kebutuhan laporan / Kesehatan Keuangan per unit. TOTAL alokasi tidak
 * mengubah saldo fisik dan (harus) tidak melebihi saldo awal/fisik rekening.
 */
class ModelAlokasiSaldoKasBank extends Model
{
    protected $table = 'alokasi_saldo_kas_bank';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'id',
        'akun_kas_bank_id',
        'unit_id',
        'nominal',
        'keterangan',
        'input_by',
        'created_at',
        'updated_at',
    ];

    public function getByAkunUnit(int $akunId, int $unitId)
    {
        return $this->where('akun_kas_bank_id', $akunId)
            ->where('unit_id', $unitId)
            ->first();
    }

    /**
     * Total alokasi satu rekening fisik lintas unit.
     */
    public function sumByAkun(int $akunId): int
    {
        return (int) ($this->selectSum('nominal')
            ->where('akun_kas_bank_id', $akunId)
            ->get()
            ->getRow()->nominal ?? 0);
    }

    public function indexByAkun(): array
    {
        $rows = $this->select('alokasi_saldo_kas_bank.*, unit.NAMA_UNIT')
            ->join('unit', 'unit.idunit = alokasi_saldo_kas_bank.unit_id', 'left')
            ->orderBy('akun_kas_bank_id', 'ASC')
            ->orderBy('unit_id', 'ASC')
            ->findAll();

        $byAkun = [];
        foreach ($rows as $r) {
            $byAkun[(int)$r->akun_kas_bank_id][] = $r;
        }

        return $byAkun;
    }
}