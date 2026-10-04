<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * OPENING KAS — baseline yang ditetapkan Finance pada tanggal cut-off.
 *
 * Bedanya dari ModelSaldoAwalKasBank (statement bank):
 *   - statement bank = angka KORAN bank, diverifikasi Finance.
 *   - opening KAS     = angka BASELINE yang Finance tetapkan sendiri, lalu
 *                      dicocokkan dengan hitungan fisik laci (tutup_kasir).
 *
 * Keduanya baseline, bukan transaksi. Tidak ada opening yang boleh muncul
 * sebagai movement di `transaksi_kas_bank`.
 */
class ModelOpeningKas extends Model
{
    protected $table = 'opening_kas';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'id',
        'akun_kas_bank_id',
        'unit_id',
        'tanggal',
        'opening',
        'real_cash',
        'selisih',
        'status',
        'keterangan',
        'input_by',
        'verifikasi_by',
        'verifikasi_at',
        'created_at',
        'updated_at',
    ];

    /** Opening pada (akun, tanggal) tertentu. */
    public function getByAkunTanggal(int $akunId, string $tanggal)
    {
        return $this->where('akun_kas_bank_id', $akunId)
            ->where('tanggal', $tanggal)
            ->first();
    }

    /**
     * Opening yang berlaku pada atau sebelum `$tanggal`.
     *
     * Fallback mirroring statementAt(): supaya saldo tidak tiba-tiba nol
     * ketika baris opening untuk cutoff berikutnya belum diinput. Bedanya,
     * untuk opening KAS fallback TIDAK boleh dipakai sebagai baseline —
     * pemanggil yang butuh baseline wajib memakai getByAkunTanggal() pada
     * tanggal cut-off.
     *
     * @return list<object>
     */
    public function getSemua(int $akunId): array
    {
        return $this->where('akun_kas_bank_id', $akunId)
            ->orderBy('tanggal', 'DESC')
            ->findAll();
    }
}