<?php

namespace App\Models;

use App\Services\Finance\KasBankCutoffService;
use CodeIgniter\Model;

class ModelSaldoAwalKasBank extends Model
{
    protected $table = 'saldo_awal_kas_bank';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'id',
        'akun_kas_bank_id',
        'tanggal',
        'saldo',
        'keterangan',
        'status',
        'input_by',
        'created_at',
        'updated_at',
    ];

    /**
     * Statement pada tanggal tertentu.
     *
     * INI yang dipakai untuk setiap pembacaan saldo. `getByAkun()` di bawah
     * sengaja dibiarkan ada hanya untuk kompatibilitas, bukan untuk dipakai
     * di jalur perhitungan.
     */
    public function getByAkunTanggal(int $akunId, string $tanggal)
    {
        return $this->where('akun_kas_bank_id', $akunId)
            ->where('tanggal', $tanggal)
            ->first();
    }

    /**
     * Semua statement untuk satu rekening, terbaru lebih dulu.
     *
     * @return list<object>
     */
    public function getSemua(int $akunId): array
    {
        return $this->where('akun_kas_bank_id', $akunId)
            ->orderBy('tanggal', 'DESC')
            ->findAll();
    }

    /**
     * Statement terakhir pada atau sebelum `$tanggal`.
     *
     * DELEGASI ke KasBankCutoffService supaya definisi "statement yang
     * berlaku" hanya ada di satu tempat. Dipakai controller/view yang butuh
     * menampilkan saldo referensi tanpa menghitung ulang sendiri.
     */
    public function getLatest(int $akunId, ?string $tanggal = null)
    {
        return (new KasBankCutoffService())->statementAt($akunId, $tanggal);
    }

    /**
     * PERINGATAN: ambigu setelah migration 2026-10-03-000400.
     *
     * Satu rekening bisa punya banyak baris statement (satu per tanggal).
     * `first()` di sini tidak menentukan tanggal, jadi hasilnya bisa baris
     * dari periode yang salah. Jangan dipakai untuk update maupun untuk
     * perhitungan saldo — pakai getByAkunTanggal() atau delegasi ke
     * KasBankCutoffService.
     */
    public function getByAkun(int $akunId)
    {
        return $this->where('akun_kas_bank_id', $akunId)->first();
    }
}