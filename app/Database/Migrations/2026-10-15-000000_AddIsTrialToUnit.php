<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Penanda unit PERCOBAAN/TRIAL pada master `unit`.
 *
 * is_trial = 1: unit percobaan (trial/experimental) — unit yang masih dalam
 *   tahap uji coba, belum resmi operasional penuh. Mungkin dibedakan untuk:
 *   - KPI: tidak dihitung dalam target/omset konsolidasi
 *   - Finance: tidak wajib opening KAS / statement bank
 *   - Laporan: bisa difilter/dikecualikan dari laporan reguler
 * is_trial = 0 (default): unit reguler/resmi
 *
 * Default 0 dipilih supaya unit lama tidak otomatis jadi trial.
 * Kolom diletakkan setelah is_operational agar berkelompok.
 */
class AddIsTrialToUnit extends Migration
{
    public function up()
    {
        // Idempoten: ADD COLUMN hanya bila kolomnya belum ada.
        if (! $this->kolomAda('is_trial', 'unit')) {
            $this->forge->addColumn('unit', [
                'is_trial' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_operational',
                ],
            ]);

            log_message('info', 'Migration AddIsTrialToUnit: kolom unit.is_trial ditambahkan (default 0).');
        }
    }

    public function down()
    {
        if ($this->kolomAda('is_trial', 'unit')) {
            $this->forge->dropColumn('unit', 'is_trial');
            log_message('info', 'Migration AddIsTrialToUnit: kolom unit.is_trial dihapus.');
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