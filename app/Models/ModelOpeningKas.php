<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * OPENING KAS — baseline SALDO RIIL laci yang ditetapkan Finance pada cut-off.
 *
 * Bedanya dari ModelSaldoAwalKasBank (statement bank):
 *   - statement bank = angka KORAN bank, diverifikasi Finance.
 *   - opening KAS     = angka BASELINE (uang fisik laci) yang Finance input
 *                      langsung pada tanggal cut-off. Tidak ada verifikasi
 *                      dan tidak ada perbandingan dengan hitungan laci.
 *
 * Keduanya baseline, bukan transaksi. Tidak ada opening yang boleh muncul
 * sebagai movement di `transaksi_kas_bank`.
 *
 * Kolom legacy `real_cash`, `selisih`, `status`, `verifikasi_by`,
 * `verifikasi_at` sengaja TIDAK di-drop dari database dan TIDAK dipakai lagi
 * — hanya dibiarkan sebagai sisa versi lama.
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
        'keterangan',
        'input_by',
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