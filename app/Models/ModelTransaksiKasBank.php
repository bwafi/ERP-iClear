<?php

namespace App\Models;

use App\Services\Finance\KasBankCutoffService;
use CodeIgniter\Model;

class ModelTransaksiKasBank extends Model
{
    protected $table = 'transaksi_kas_bank';
    protected $primaryKey = 'idtransaksi';
    protected $returnType = 'object';
    protected $allowedFields = [
        'idtransaksi',
        'tanggal',
        'unit_id',
        'akun_kas_bank_id',
        'jenis',
        'arah',
        'jumlah',
        'akun_tujuan_id',
        'transfer_ref',
        'submission_key',
        'sumber_tipe',
        'sumber_id',
        'keterangan',
        'bukti',
        'input_by',
        'created_at',
        'updated_at',
    ];

    protected ?KasBankCutoffService $cutoff = null;

    /**
     * Sumber tunggal perhitungan saldo periode baru. Semua angka saldo di
     * modul ini berasal dari sana, supaya tidak ada dua definisi "saldo"
     * yang bisa berbeda defraudasi.
     */
    public function cutoffService(): KasBankCutoffService
    {
        return $this->cutoff ??= new KasBankCutoffService();
    }

    /**
     * Saldo fisik satu rekening (seluruh unit).
     *
     * Delegates ke KasBankCutoffService::saldoFisik():
     *
     *     statement(akun, tanggal cut-off) + net movement sejak periode mulai
     *
     * Statement adalah BASELINE dari `saldo_awal_kas_bank`, bukan transaksi —
     * tidak ada baris ledger yang mewakili saldo cut-off. Batas bawah
     * movement adalah FinanceScopeService::periodeMulaiDate() (2026-10-06),
     * BUKAN cutoffDate() (2026-10-05): tanggal cut-off adalah tanggal
     * statement, dan menghitungnya sebagai mutasi akan menjumlahkan saldo
     * dua kali. Transaksi 1–5 Okt pun tidak ikut dihitung — saldonya sudah
     * terserap di baseline.
     */
    public function getSaldoAkun(int $akunId): int
    {
        return $this->cutoffService()->saldoFisik($akunId);
    }

    /**
     * Alias getSaldoAkun: saldo fisik rekening (seluruh unit).
     */
    public function getSaldoFisikAkun(int $akunId): int
    {
        return $this->getSaldoAkun($akunId);
    }

    /**
     * Saldo statement (baseline) satu rekening pada tanggal cut-off.
     */
    public function getSaldoStatement(int $akunId): int
    {
        return $this->cutoffService()->saldoStatement($akunId);
    }

    /**
     * LEGACY / UNASSIGNED = statement - SUM(opening allocation).
     *
     * Residual milik kelompok yang tidak diketahui. BUKAN saldo unit dan tidak
     * bisa dipakai menarik: tidak ada baris alokasi yang memegang bagian ini,
     * jadi tidak ada unit yang entitled atasnya.
     */
    public function getLegacyUnassigned(int $akunId): int
    {
        return $this->cutoffService()->legacyUnassigned($akunId);
    }

    /**
     * Saldo alokasi per unit pada satu rekening fisik:
     * opening allocation + net movement unit sejak cut-off.
     *
     * TIDAK mengubah saldo fisik; hanya atribusi untuk laporan/KPI per unit.
     *
     * GUARD ACCOUNT SCOPE: unit yang tidak punya HAK atas rekening ini
     * (non-shared milik unit lain, atau shared tanpa baris alokasi untuk unit
     * tsb) selalu bernilai 0 — bukan "hak 0" karena tidak ada alokasi, tapi
     * karena unit tsb memang tidak berhak atas rekening tersebut. Ini
     * mencegah total per unit terlihat asynchronous dengan saldo fisik, dan
     * mencegah saldo LEGACY ikut terhitung sebagai saldo seseorang.
     */
    public function getSaldoUnitAkun(int $akunId, int $unitId): int
    {
        return $this->cutoffService()->posisiUnit($akunId, $unitId);
    }

    /**
     * Total opening allocation lintas unit untuk satu rekening fisik.
     *
     * Hanya OPENING ALLOCATION (keputusan Finance saat cut-off). Angka harian
     * tidak pernah ditulis ke sana; posisinya bergerak sendiri karena ledger.
     */
    public function getTotalAlokasiUnit(int $akunId): int
    {
        return $this->cutoffService()->totalOpeningAllocation($akunId);
    }

    /**
     * Periksa invariant satu rekening:
     * saldo_fisik == LEGACY + SUM(posisi unit entitled).
     *
     * @return array<string, mixed>
     */
    public function cekInvariant(int $akunId): array
    {
        return $this->cutoffService()->cekInvariant($akunId);
    }

    public function getByTransferRef(string $transferRef)
    {
        return $this->where('transfer_ref', $transferRef)->findAll();
    }

    public function getPasanganTransfer(string $transferRef, string $arah): ?object
    {
        return $this->where('transfer_ref', $transferRef)->where('arah', $arah)->first();
    }
}
