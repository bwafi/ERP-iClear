<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kolom nomor telepon KHUSUS KOMPLAIN pada master `unit`.
 *
 * Nomor komplain dipisah dari NOTELP karena keduanya beda peruntukan:
 *   - NOTELP         : nomor sales / info umum (tetap, mis. 0851 8327 0910)
 *   - NOTELP_KOMPLAIN: nomor yang dicetak pada invoice service untuk komplain
 *
 * Invoice service kini mengambil nomor ini dari data unit, bukan hardcode.
 * Default diisi nomor komplain yang berlaku saat ini; unit baru bisa
 * menyesuaikan lewat modul Datamaster Unit.
 */
class AddNotelpKomplainToUnit extends Migration
{
    private const NOMOR_KOMPLAIN_DEFAULT = '0856-0124-8829';

    public function up()
    {
        // Idempoten: ADD COLUMN hanya bila kolomnya belum ada.
        if (! $this->kolomAda('NOTELP_KOMPLAIN', 'unit')) {
            $this->forge->addColumn('unit', [
                'NOTELP_KOMPLAIN' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'NOTELP',
                ],
            ]);

            log_message('info', 'Migration AddNotelpKomplainToUnit: kolom unit.NOTELP_KOMPLAIN ditambahkan.');
        }

        // Isi unit yang belum punya nomor komplain. Idempoten: baris yang sudah
        // diisi (termasuk kustomisasi admin) tidak disentuh.
        $this->db->table('unit')
            ->groupStart()
            ->where('NOTELP_KOMPLAIN', null)
            ->orWhere('NOTELP_KOMPLAIN', '')
            ->groupEnd()
            ->update(['NOTELP_KOMPLAIN' => self::NOMOR_KOMPLAIN_DEFAULT]);

        log_message('info', 'Migration AddNotelpKomplainToUnit: NOTELP_KOMPLAIN unit diisi ' . self::NOMOR_KOMPLAIN_DEFAULT . '.');
    }

    public function down()
    {
        if ($this->kolomAda('NOTELP_KOMPLAIN', 'unit')) {
            $this->forge->dropColumn('unit', 'NOTELP_KOMPLAIN');
            log_message('info', 'Migration AddNotelpKomplainToUnit: kolom unit.NOTELP_KOMPLAIN dihapus.');
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
