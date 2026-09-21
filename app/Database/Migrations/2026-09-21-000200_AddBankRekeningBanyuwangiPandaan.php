<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Daftarkan rekening bank fisik Banyuwangi & Pandaan.
 *
 * Mapping (konfirmasi user): idbank 3 (0391943558 IRA KURNIAWATI) milik
 * Banyuwangi (unit 3), idbank 4 (3251427508 FARA DINDA AYUWANDA) milik
 * Pandaan (unit 4). Sebelumnya unit ini tidak punya akun BANK di
 * akun_kas_bank sehingga pengeluaran bank mereka tercatat ke akun 1
 * (Kas ICLEAR Probolinggo).
 *
 * Idempotent: guard per (tipe='BANK', bank_idbank). Tidak menulis saldo awal
 * (di-set lewat modul bila diperlukan) dan tidak menyentuh transaksi lama.
 */
class AddBankRekeningBanyuwangiPandaan extends Migration
{
    public function up()
    {
        $this->buat([
            'unit_id'     => 3,
            'bank_idbank' => '3',
            'nama_akun'   => 'Bank BCA Banyuwangi 0391943558',
        ]);
        $this->buat([
            'unit_id'     => 4,
            'bank_idbank' => '4',
            'nama_akun'   => 'Bank BCA Pandaan 3251427508',
        ]);
    }

    public function down()
    {
        foreach ([3, 4] as $idbank) {
            $akun = $this->db->table('akun_kas_bank')
                ->where('tipe', 'BANK')
                ->where('bank_idbank', (string) $idbank)
                ->get()
                ->getRow();
            if (!$akun) {
                continue;
            }
            $dipakai = $this->db->table('transaksi_kas_bank')
                ->where('akun_kas_bank_id', $akun->idakun_kas_bank)
                ->countAllResults();
            if ($dipakai === 0) {
                $this->db->table('akun_kas_bank')->where('idakun_kas_bank', $akun->idakun_kas_bank)->delete();
            }
        }
    }

    private function buat(array $row): void
    {
        $ada = $this->db->table('akun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', $row['bank_idbank'])
            ->get()
            ->getRow();
        if ($ada) {
            return;
        }
        $this->db->table('akun_kas_bank')->insert([
            'unit_id'     => $row['unit_id'],
            'tipe'        => 'BANK',
            'nama_akun'   => $row['nama_akun'],
            'bank_idbank' => $row['bank_idbank'],
            'no_akun_coa' => null,
            'status'      => 'aktif',
            'is_shared'   => 0,
        ]);
    }
}