<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ganti nama jabatan "ADMIN CENTER" (ID 0) menjadi "Finance".
 *
 * KEPUTUSAN BISNIS (sudah disetujui user)
 * --------------------------------------
 * Jabatan ID 0 selama ini tampil sebagai "ADMIN CENTER" di seluruh ERP.
 * Nama tersebut disamakan menjadi "Finance" karena peran ini memang
 * memegang modul Finance (dashboard Finance, rekonsiliasi, Kas & Bank
 * lintas unit). Yang berubah HANYA label; seluruh logika akses tetap
 * memakai ID jabatan (0, 1, 2, 34) sehingga permission tidak bergeser.
 *
 * Yang ikut diubah:
 *   - jabatan.NAMA_JABATAN ID 0  : "ADMIN CENTER" -> "Finance"
 *   - akun_kas_bank ID 1         : "Bank BCA Admin Center/Finance 0391943558
 *                                   (IRA)" -> "Bank BCA Finance 0391943558 (IRA)"
 *
 * Yang SENGAJA tidak dilakukan:
 *   - tidak menambah/menghapus jabatan
 *   - tidak menyentuh ROLES_JABATAN (daftar permission tetap sama)
 *   - tidak menyentuh tabel menu, akun_kas_bank lain, atau saldo
 *   - tidak mengubah kode/permission yang memakai angka jabatan
 *
 * Migration lama tidak diedit supaya riwayatnya tetap apa adanya; label
 * di komentar/docblock file lama dibersihkan terpisah.
 */
class RenameAdminCenterJabatanToFinance extends Migration
{
    /** ID jabatan yang dipakai Finance/HO dan role lintas unit. */
    private const JABATAN_ID = '0';

    private const JABATAN_LAMA = 'ADMIN CENTER';
    private const JABATAN_BARU = 'Finance';

    /** Rekening Finance/HO, sebelumnya bernama "Bank BCA Admin Center/Finance ...". */
    private const IRA_ID = 1;

    private const IRA_LAMA = 'Bank BCA Admin Center/Finance 0391943558 (IRA)';
    private const IRA_BARU = 'Bank BCA Finance 0391943558 (IRA)';

    public function up()
    {
        $this->renameJabatan(self::JABATAN_LAMA, self::JABATAN_BARU);
        $this->renameIraAccount(self::IRA_LAMA, self::IRA_BARU);
    }

    public function down()
    {
        $this->renameJabatan(self::JABATAN_BARU, self::JABATAN_LAMA);
        $this->renameIraAccount(self::IRA_BARU, self::IRA_LAMA);
    }

    /**
     * Ganti label jabatan hanya selama masih bernilai lama. Kalau labelnya
     * sudah diubah manual di luar migration ini, tidak ditimpa.
     */
    private function renameJabatan(string $dari, string $ke): void
    {
        if (! $this->db->fieldExists('NAMA_JABATAN', 'jabatan')) {
            return;
        }

        $this->db->query(
            'UPDATE jabatan SET NAMA_JABATAN = ' . $this->db->escape($ke) . '
              WHERE ID_JABATAN = ' . $this->db->escape(self::JABATAN_ID) . '
                AND NAMA_JABATAN = ' . $this->db->escape($dari)
        );
    }

    /**
     * Ganti nama rekening IRA selama masih memuat "Admin Center", supaya
     * migration ini tetap aman kalauripsnya sudah sedikit berbeda.
     */
    private function renameIraAccount(string $dari, string $ke): void
    {
        if (! $this->db->fieldExists('nama_akun', 'akun_kas_bank')) {
            return;
        }

        $this->db->query(
            'UPDATE akun_kas_bank SET nama_akun = ' . $this->db->escape($ke) . '
              WHERE idakun_kas_bank = ' . (int) self::IRA_ID . '
                AND nama_akun LIKE ' . $this->db->escape('%Admin Center%', false) . '
                AND nama_akun <> ' . $this->db->escape($ke)
        );
    }
}