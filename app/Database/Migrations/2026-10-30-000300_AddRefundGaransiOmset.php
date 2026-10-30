<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Porsi omset refund garansi dipisah dari nominal kas.
 *
 * `refund_garansi_jumlah` tetap nominal uang yang keluar (kas/jurnal).
 * `refund_garansi_omset` hanya sebesar gross profit yang dulu masuk omset.
 * Refund lama diisi nilai penuh agar omset historis tidak berubah.
 */
class AddRefundGaransiOmset extends Migration
{
    public function up()
    {
        $tabel = $this->db->prefixTable('service');
        $sudahAda = $this->kolomAda('service', 'refund_garansi_omset');

        $this->tambahKolom('service', 'refund_garansi_omset', 'INT NOT NULL DEFAULT 0');

        if (! $sudahAda) {
            $this->db->query(
                "UPDATE {$tabel}
                    SET refund_garansi_omset = refund_garansi_jumlah
                  WHERE refund_garansi_at IS NOT NULL"
            );
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('refund_garansi_omset', 'service')
            && $this->db->table('service')->where('refund_garansi_omset !=', 0)->countAllResults() > 0) {
            throw new \RuntimeException('Porsi omset refund sudah dicatat; rollback migrasi tidak menghapus data.');
        }

        $this->hapusKolom('service', 'refund_garansi_omset');
    }

    private function kolomAda(string $table, string $column): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS jumlah FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->db->prefixTable($table), $column]
        )->getRow();

        return (int) ($row->jumlah ?? 0) > 0;
    }

    private function tambahKolom(string $table, string $column, string $definition): void
    {
        if (! $this->kolomAda($table, $column)) {
            $tabel = $this->db->prefixTable($table);
            $this->db->query("ALTER TABLE {$tabel} ADD COLUMN {$column} {$definition}");
        }
    }

    private function hapusKolom(string $table, string $column): void
    {
        if ($this->kolomAda($table, $column)) {
            $tabel = $this->db->prefixTable($table);
            $this->db->query("ALTER TABLE {$tabel} DROP COLUMN {$column}");
        }
    }
}
