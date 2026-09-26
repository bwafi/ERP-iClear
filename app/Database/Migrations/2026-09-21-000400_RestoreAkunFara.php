<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kembalikan akun bank FARA (bank 4) yang sempat dihapus di
 * CorrectBankRekeningOwnership (000300). Diputuskan TIDAK dihapus tetapi
 * dibiarkan terdaftar: dibuat nonaktif tanpa unit sehingga tidak muncul pada
 * pilihan akun aktif / tidak dipakai transaksi, tapi tetap ada di master.
 */
class RestoreAkunFara extends Migration
{
    public function up()
    {
        $akun = $this->db->table('akun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', '4')
            ->get()
            ->getRow();
        if ($akun) {
            return;
        }
        $this->db->table('akun_kas_bank')->insert([
            'unit_id'     => null,
            'tipe'        => 'BANK',
            'nama_akun'   => 'Bank BCA 3251427508 (FARA DINDA AYUWANDA)',
            'bank_idbank' => '4',
            'no_akun_coa' => null,
            'status'      => 'nonaktif',
            'is_shared'   => 0,
        ]);
    }

    public function down()
    {
        $akun = $this->db->table('akun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', '4')
            ->where('status', 'nonaktif')
            ->get()
            ->getRow();
        if (!$akun) {
            return;
        }
        $dipakai = $this->db->table('transaksi_kas_bank')
            ->where('akun_kas_bank_id', $akun->idakun_kas_bank)
            ->countAllResults();
        if ($dipakai === 0) {
            $this->db->table('akun_kas_bank')->where('idakun_kas_bank', $akun->idakun_kas_bank)->delete();
        }
    }
}