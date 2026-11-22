<?php

namespace App\Models;

use CodeIgniter\Model;

class ModelNoAkun extends Model
{
    protected $table = 'no_akun';
    protected $primaryKey = 'no_akun';
    protected $returnType = 'object';
    protected $allowedFields = ['no_akun', 'nama_akun', 'jenis_akun'];

    public function getAkun()
    {

        return $this->findAll();
    }

    public function getByNoAkun($no_akun)
    {
        return $this->where('no_akun', $no_akun)->first();
    }

    /**
     * Daftar akun yang boleh dipakai di form Kas Masuk.
     *
     * BLACKLIST, bukan whitelist: kategori `jenis_akun` baru tetap boleh
     * sampai ada alasan untuk menolaknya. Yang ditolak:
     *  - jenis_akun NULL/kosong (tidak bisa diklasifikasikan);
     *  - Kas & Bank (sisi kas/bank implisit);
     *  - Beban% (akun pengeluaran);
     *  - Harga Pokok Penjualan;
     *  - Akumulasi Penyusutan Aset.
     */
    public function getAkunMasuk(): array
    {
        return $this->where('jenis_akun IS NOT NULL', null, false)
            ->where("TRIM(jenis_akun) <> ''", null, false)
            ->where("LOWER(TRIM(jenis_akun)) NOT IN ('kas & bank', 'harga pokok penjualan', 'akumulasi penyusutan aset')", null, false)
            ->where("LOWER(TRIM(jenis_akun)) NOT LIKE 'beban%'", null, false)
            ->findAll();
    }

    /** Predikat yang sama dengan getAkunMasuk(), untuk validasi server. */
    public function bolehUntukKasMasuk($akun): bool
    {
        if (! is_object($akun) || ! isset($akun->jenis_akun)) {
            return false;
        }

        $jenis = strtolower(trim((string) $akun->jenis_akun));
        if ($jenis === '') {
            return false;
        }
        if (in_array($jenis, ['kas & bank', 'harga pokok penjualan', 'akumulasi penyusutan aset'], true)) {
            return false;
        }
        if (strpos($jenis, 'beban') === 0) {
            return false;
        }

        return true;
    }
}
