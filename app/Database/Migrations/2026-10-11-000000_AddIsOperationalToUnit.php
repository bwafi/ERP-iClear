<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda unit OPERASIONAL vs NON-OPERASIONAL pada master `unit`.
 *
 * is_operational = 1 (default): unit operasional — satu-satunya yang wajib
 * punya opening KAS (saldo awal laci) pada cut-off KasBank.
 * is_operational = 0: unit non-operasional. Head Office termasuk kategori ini
 * dan TIDAK diwajibkan punya opening KAS / statement bank.
 *
 * Default 1 dipilih supaya unit lama tidak tanpa sengaja ikut dibebaskan dari
 * kewajiban opening KAS. Hanya unit yang terverifikasi sebagai Head Office
 * (idunit = 50, dipindahkan oleh migration 2026-09-14-001000_RenumberHOUnitTo50)
 * yang ditandai 0 di sini.
 */
class AddIsOperationalToUnit extends Migration
{
    public function up()
    {
        // Idempoten: ADD COLUMN hanya bila kolomnya belum ada. Pemeriksaan
        // lewat information_schema (bukan Connection::fieldExists()) karena
        // fieldExists memakai cache per-koneksi yang bisa basi setelah DDL.
        if (! $this->kolomAda('is_operational', 'unit')) {
            $this->forge->addColumn('unit', [
                'is_operational' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 1,
                    'after'      => 'kode_unit',
                ],
            ]);

            log_message('info', 'Migration AddIsOperationalToUnit: kolom unit.is_operational ditambahkan (default 1).');
        }

        // Head Office (unit 50). Idempoten: kondisi `is_operational <> 0`
        // membuat UPDATE tidak menyentuh baris lain dan aman diulang.
        $this->db->query('UPDATE unit SET is_operational = 0 WHERE idunit = 50 AND is_operational <> 0');
        log_message('info', 'Migration AddIsOperationalToUnit: Head Office (unit 50) ditandai non-operasional.');
    }

    public function down()
    {
        if ($this->kolomAda('is_operational', 'unit')) {
            $this->forge->dropColumn('unit', 'is_operational');
            log_message('info', 'Migration AddIsOperationalToUnit: kolom unit.is_operational dihapus.');
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