<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Koreksi kepemilikan rekening bank fisik (data master, mapping benar dari user).
 *
 *   bank 1 (0391796181 SABRINA RATU SALSABILLA)          -> Pandaan (unit 4)
 *   bank 2 (0393778773 CV ICLEAR DIGITAL SOLUSI)         -> Jember (2) + Probolinggo (1), SHARED
 *   bank 3 (0391943558 IRA KURNIAWATI)                   -> Admin Center/Finance, tanpa unit (shared internal)
 *   bank 4 (3251427508 FARA DINDA AYUWANDA)              -> TIDAK DIPAKAI (hapus akun 10 yang keliru dibuat)
 *   bank 5 (1802016667 WAHID ALFARIZKI)                  -> Banyuwangi (unit 3), akun baru
 *
 * Tidak mengubah baris transaksi lama; hanya ownership/penamaan master akun.
 */
class CorrectBankRekeningOwnership extends Migration
{
    public function up()
    {
        $this->setAkun('1', 'Bank BCA Pandaan 0391796181', 4, 0);
        $this->setAkun('2', 'Bank BCA CV ICLEAR 0393778773 (Jember & Probolinggo)', 2, 1);
        $this->setAkun('3', 'Bank BCA Admin Center/Finance 0391943558 (IRA)', null, 1);

        // Rekening IRA (bank 3) internal: alokasi placeholder tidak diperlukan.

        // Hapus akun FARA (bank 4) yang keliru dibuat untuk Pandaan.
        $fara = $this->db->table('akun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', '4')
            ->get()
            ->getRow();
        if ($fara) {
            $dipakai = $this->db->table('transaksi_kas_bank')
                ->where('akun_kas_bank_id', $fara->idakun_kas_bank)
                ->countAllResults();
            if ($dipakai === 0) {
                $this->db->table('akun_kas_bank')->where('idakun_kas_bank', $fara->idakun_kas_bank)->delete();
            }
        }

        // Bank 5 = Banyuwangi (belum punya akun).
        $bwi = $this->db->table('akun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', '5')
            ->get()
            ->getRow();
        if (!$bwi) {
            $this->db->table('akun_kas_bank')->insert([
                'unit_id'     => 3,
                'tipe'        => 'BANK',
                'nama_akun'   => 'Bank BCA Banyuwangi 1802016667 (ALFARIZKI)',
                'bank_idbank' => '5',
                'no_akun_coa' => null,
                'status'      => 'aktif',
                'is_shared'   => 0,
            ]);
        }

        // Visibilitas cabang Probolinggo ke rekening bersama CV (bank 2):
        // placeholder alokasi 0 (nominal di-set via modul Master Akun & Saldo Awal).
        $cv  = $this->db->table('akun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', '2')
            ->get()
            ->getRow();
        if ($cv) {
            $ada = $this->db->table('alokasi_saldo_kas_bank')
                ->where('akun_kas_bank_id', $cv->idakun_kas_bank)
                ->where('unit_id', 1)
                ->countAllResults();
            if ($ada === 0) {
                $this->db->table('alokasi_saldo_kas_bank')->insert([
                    'akun_kas_bank_id' => $cv->idakun_kas_bank,
                    'unit_id'          => 1,
                    'nominal'          => 0,
                    'keterangan'       => 'placeholder visibilitas; atur nominal via modul',
                    'input_by'         => null,
                    'created_at'       => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        // Best-effort balikan ke mapping lama (catatan: penghapusan akun FARA
        // tidak bisa dikembalikan otomatis).
        $this->setAkun('1', 'Bank BCA Probolinggo 0391796181', 1, 0);
        $this->setAkun('2', 'Bank BCA Jember 0393778773', 2, 0);
        $this->setAkun('3', 'Bank BCA Banyuwangi 0391943558', 3, 0);
    }

    private function setAkun(string $bank, string $nama, ?int $unit, int $shared): void
    {
        $akun = $this->db->table('akun_kas_bank')
            ->where('tipe', 'BANK')
            ->where('bank_idbank', $bank)
            ->get()
            ->getRow();
        if (!$akun) {
            return;
        }
        $this->db->table('akun_kas_bank')
            ->where('idakun_kas_bank', $akun->idakun_kas_bank)
            ->update([
                'nama_akun' => $nama,
                'unit_id'   => $unit,
                'is_shared' => $shared,
            ]);
    }
}