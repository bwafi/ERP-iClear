<?php

namespace App\Database\Migrations;

use App\Libraries\ModeKasBank;
use CodeIgniter\Database\Migration;

/**
 * Seed akun KAS (Kas Besar) default per unit.
 *
 * MASALAH YANG DIPERBAIKI
 * ----------------------
 * Dudley tidak punya satu pun akun bertipe KAS di akun_kas_bank. Akibatnya
 * setiap kas_masuk / kas_keluar bertunai memanggil ModeKasBank::resolveAkun()
 * dan mendapat null, sehingga baris transaksi_kas_bank tidak pernah dibuat —
 * posting gagal SILENTLY.
 *
 * Setelah migration ini:
 *   - setiap unit punya 1 akun "Kas <NAMA_UNIT>" bertipe KAS, status aktif,
 *     COA 1010101000 (Kas Besar);
 *   - resolveAkun(unit, null) untuk transaksi tunai selalu menemukan rekening;
 *   - transaksi existing tetap UTUH. Migration ini tidak menyentuh
 *     kas_masuk / kas_keluar / transaksi_kas_bank sama sekali. Baris yang
 *     belum terposting tidak di-backfill di sini — itu pekerjaan perintah
 *     `spark kasbank:backfill`, dijalankan terpisah atas permintaan.
 *
 * Kenapa pakai COA 1010101000 (Kas Besar), bukan 1010103000 (Kas Kecil)?
 *   Karena resolver memilih SATU akun KAS per unit. Kalau satu unit punya dua
 *   akun KAS aktif, resolveAkun() bisa memakai Kas Kecil untuk uang yang
 *   sebenarnya Kas Besar. Urutan di resolveAkunDetail() mengutamakan
 *   1010101000, tapi lebih aman hanya ada satu KAS per unit. Kas Kecil
 *   (1010103000) sudah tersedia di master no_akun dan bisa ditambahkan lewat
 *   /kas_bank/akun bila benar-benar dipakai.
 *
 * IDEMPOTENT: aman dijalankan berkali-kali (seedAkunDefault() sendiri
 * sudah melewati unit yang punya akun KAS aktif).
 */class SeedAkunKasPerUnit extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('akun_kas_bank') || ! $this->db->tableExists('unit')) {
            return;
        }

        $hasil = (new ModeKasBank())->seedAkunDefault();

        log_message('info', sprintf(
            '[Migration SeedAkunKasPerUnit] akun KAS per unit: %d dibuat, %d sudah ada.',
            $hasil['created'],
            $hasil['skipped']
        ));
    }

    /**
     * Tidak menghapus apa pun. Akun KAS hasil seed adalah konfigurasi, bukan
     * transaksi:_account yang sudah dihapus akan dibuat ulang oleh
     * seedAkunDefault() pada run berikutnya, dan baris transaksi_kas_bank
     * yang menunjuk thereto TIDAK ikut terhapus.
     */
    public function down()
    {
        // Sengaja kosong — tidak ada data transaksi yang boleh hilang saat rollback.
    }
}
