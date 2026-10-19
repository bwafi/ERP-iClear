<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda per-akun "menerima gaji atau tidak" pada `akun`.
 *
 * Sebelumnya "siapa yang digaji" ditentukan daftar jabatan yang di-hardcode,
 * dan aturannya beda-beda per halaman (asset_berjalan tanpa filter, /gaji buang
 * jabatan 1 saja, /penilaian_kinerja buang 1 & 2, PayrollGenerator [1,2]).
 * Satu kolom ini jadi satu sumber kebenaran untuk semua tempat itu.
 *
 * Default 1 dipilih supaya perilaku lama tidak berubah untuk akun lain. Hanya
 * Admin Root (jabatan 1) dan Direktur (jabatan 2) yang di-backfill jadi 0,
 * meniru pengecualian yang sudah berlaku di payroll.
 */
class AddTerimaGajiToAkun extends Migration
{
    public function up()
    {
        // Idempoten: ADD COLUMN hanya bila kolomnya belum ada. Pemeriksaan
        // lewat information_schema (bukan Connection::fieldExists()) karena
        // fieldExists memakai cache per-koneksi yang bisa basi setelah DDL.
        if (! $this->kolomAda('TERIMA_GAJI', 'akun')) {
            $this->forge->addColumn('akun', [
                'TERIMA_GAJI' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 1,
                    'after'      => 'JENIS_PEGAWAI',
                ],
            ]);

            log_message('info', 'Migration AddTerimaGajiToAkun: kolom akun.TERIMA_GAJI ditambahkan (default 1).');
        }

        // Idempoten: hanya menyentuh baris yang belum 0, aman diulang.
        $this->db->query('UPDATE akun SET TERIMA_GAJI = 0 WHERE ID_JABATAN IN (1, 2) AND TERIMA_GAJI <> 0');
        log_message('info', 'Migration AddTerimaGajiToAkun: Admin Root (1) & Direktur (2) ditandai tidak menerima gaji.');
    }

    public function down()
    {
        if ($this->kolomAda('TERIMA_GAJI', 'akun')) {
            $this->forge->dropColumn('akun', 'TERIMA_GAJI');
            log_message('info', 'Migration AddTerimaGajiToAkun: kolom akun.TERIMA_GAJI dihapus.');
        }
    }

    private function kolomAda(string $kolom, string $tabel): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabel, $kolom]
        )->getRow();

        return (int) ($row->n ?? 0) > 0;
    }
}
