<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Satu referensi transaksi bisa melunasi DUA posisi H/P sekaligus
 * (hutang unit pengirim + piutang unit penerima). Unique index lama
 * (sumber, referensi_tipe, referensi_id) menolak baris kedua, sehingga
 * pelunasan antar unit selalu rollback dengan duplicate entry.
 *
 * Uniqueness yang benar adalah per posisi H/P, bukan per referensi saja.
 */
class FixUniquePembayaranHutangPiutangReferensi extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('pembayaran_hutang_piutang')) {
            return;
        }

        $columns = $this->indexColumns('uniq_php_referensi');
        if (in_array('hutang_piutang_id', $columns, true)) {
            return; // sudah benar
        }

        $table = $this->db->prefixTable('pembayaran_hutang_piutang');

        if ($columns !== []) {
            $this->db->query("ALTER TABLE `{$table}` DROP INDEX `uniq_php_referensi`");
        }

        $this->db->query(
            "ALTER TABLE `{$table}` ADD UNIQUE KEY `uniq_php_referensi` "
            . '(`sumber`, `referensi_tipe`, `referensi_id`, `hutang_piutang_id`)'
        );
    }

    /**
     * @return string[] kolom index, kosong bila index tidak ada
     */
    private function indexColumns(string $keyName): array
    {
        $table = $this->db->prefixTable('pembayaran_hutang_piutang');
        $rows  = $this->db->query(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$keyName]
        )->getResultArray();

        return array_column($rows, 'Column_name');
    }

    public function down()
    {
        if (! $this->db->tableExists('pembayaran_hutang_piutang')) {
            return;
        }

        $columns = $this->indexColumns('uniq_php_referensi');
        if ($columns === [] || ! in_array('hutang_piutang_id', $columns, true)) {
            return;
        }

        // Hanya aman bila tidak ada pasangan H/P yang berbagi referensi.
        // Bila ada, ALTER sengaja gagal: data harus dibersihkan manual.
        $table = $this->db->prefixTable('pembayaran_hutang_piutang');
        $this->db->query("ALTER TABLE `{$table}` DROP INDEX `uniq_php_referensi`");
        $this->db->query(
            "ALTER TABLE `{$table}` ADD UNIQUE KEY `uniq_php_referensi` "
            . '(`sumber`, `referensi_tipe`, `referensi_id`)'
        );
    }
}
