<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reopen tanpa menghapus data.
 *
 * Sebelumnya reopen memakai `DELETE FROM stok_opname WHERE periode_id = ?`.
 * Dua masalah:
 *   1. Nilai final lama hilang permanen — tidak ada jejak untuk audit.
 *   2. View stok_barang menjumlahkan jumlah_selisih dari stok_opname tanpa
 *      filter tanggal, jadi menghapus baris tersebut diam-diam mengubah
 *      stok_akhir untuk semua unit tanpa ada yang bisa ditelusuri.
 *
 * Solusi: kolom is_reverted pada stok_opname. Reopen menandai baris, bukan
 * menghapus; view stok_barang hanya menjumlahkan baris aktif (is_reverted = 0).
 * Karena satu periode bisa difinalisasi ulang setelah reopen, tiap finalisasi
 * menulis revisi baris sendiri — jadi UNIQUE (periode_id, barang_idbarang)
 * di stok_opname dilepas, unique hanya dipertahankan di draft.
 */
class StokOpnameReopenAudit extends Migration
{
    public function up()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $this->addColumnIfMissing('stok_opname', 'is_reverted', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `periode_id`');
        $this->addColumnIfMissing('stok_opname', 'reverted_by', 'INT NULL AFTER `is_reverted`');
        $this->addColumnIfMissing('stok_opname', 'reverted_at', 'DATETIME NULL AFTER `reverted_by`');
        $this->addColumnIfMissing('stok_opname', 'revert_alasan', 'VARCHAR(255) NULL AFTER `reverted_at`');

        $this->db->query('UPDATE stok_opname SET is_reverted = 0 WHERE is_reverted IS NULL');

        // Satu periode boleh punya beberapa revisi baris setelah reopen.
        if ($this->hasIndex('stok_opname', 'uk_opname_periode_barang')) {
            $this->db->query('ALTER TABLE stok_opname DROP INDEX uk_opname_periode_barang');
        }
        if (! $this->hasIndex('stok_opname', 'idx_opname_reverted')) {
            $this->db->query('ALTER TABLE stok_opname ADD INDEX idx_opname_reverted (periode_id, is_reverted)');
        }

        $this->restrictViewToActiveRows();

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Rollback ke semantik v1.
     *
     * Dua hal sengaja TIDAK dilakukan di sini:
     *
     * 1. TIDAK menjatuhkan tabel `stok_opname_audit`. Tabel itu dibuat oleh
     *    migration 000001 dan di-drop oleh down() miliknya sendiri. Kalau
     *    000002 ikut menjatuhkannya, me-rollback 000002 saja akan menghapus
     *    jejak audit milik 000001 secara diam-diam.
     *
     * 2. TIDAK diam-diam membuang baris yang sudah di-revert. Setelah kolom
     *    `is_reverted` hilang, view `stok_barang` kembali menjumlahkan semua
     *    baris, sehingga revisi lama ikut terhitung lagi dan `stok_akhir`
     *    setiap unit menjadi dobel. Karena itu rollback ditolak bila ada
     *    baris reverted, dan operator yang harus memutuskan sendiri
     *    (hapus eksplisit, atau tetap di v2).
     */
    public function down()
    {
        $reverted = (int) $this->db->query(
            'SELECT COUNT(*) AS total FROM stok_opname WHERE is_reverted = 1'
        )->getRow()->total;

        if ($reverted > 0) {
            throw new \RuntimeException(
                "Rollback ditolak: ada {$reverted} baris stok_opname dengan is_reverted = 1. "
                . 'Baris ini adalah nilai final lama sebelum reopen. Setelah kolom is_reverted '
                . 'dihapus, view stok_barang akan menjumlahkannya lagi sehingga stok_akhir '
                . 'setiap unit menjadi dobel. Hapus baris tersebut secara eksplisit bila memang '
                . 'ingin kembali ke semantik v1 (data nilainya hilang permanen), atau batalkan '
                . 'rollback dan pertahankan v2. Jejak siapa/kapan/mengapa ada di stok_opname_audit.'
            );
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $this->restoreView();

        if ($this->hasIndex('stok_opname', 'idx_opname_reverted')) {
            $this->db->query('ALTER TABLE stok_opname DROP INDEX idx_opname_reverted');
        }

        // Balikkan unique key yang dilepas di up(). Aman karena rollback sudah
        // dipastikan tidak ada baris reverted, jadi tidak mungkin ada duplikat
        // (periode_id, barang_idbarang) dari finalisasi ulang.
        if (! $this->hasIndex('stok_opname', 'uk_opname_periode_barang')) {
            $this->db->query(
                'ALTER TABLE stok_opname ADD UNIQUE KEY uk_opname_periode_barang (periode_id, barang_idbarang)'
            );
        }

        foreach (['revert_alasan', 'reverted_at', 'reverted_by', 'is_reverted'] as $col) {
            $rows = $this->db->query("SHOW COLUMNS FROM `stok_opname` LIKE '$col'")->getResultArray();
            if ($rows !== []) {
                $this->db->query("ALTER TABLE stok_opname DROP COLUMN `$col`");
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Definisi view diambil ulang dari database lalu hanya satu fragmen yang
     * diubah, supaya tidak perlu menyalin ulang SELECT yang panjang dan mudah
     * salah ketik.
     */
    private function restrictViewToActiveRows(): void
    {
        $this->replaceView(
            "from `stok_opname` group by",
            "from `stok_opname` where `stok_opname`.`is_reverted` = 0 group by"
        );
    }

    private function restoreView(): void
    {
        $this->replaceView(
            "from `stok_opname` where `stok_opname`.`is_reverted` = 0 group by",
            "from `stok_opname` group by"
        );
    }

    private function replaceView(string $search, string $replace): void
    {
        $row = $this->db->query('SHOW CREATE VIEW stok_barang')->getRowArray();
        if ($row === null) {
            return;
        }

        $column = array_key_first(array_filter(
            $row,
            static fn($k) => stripos($k, 'create view') !== false,
            ARRAY_FILTER_USE_KEY
        ));
        if ($column === null) {
            return;
        }

        $definition = $row[$column];

        if (str_contains($definition, $replace)) {
            return; // sudah dalam kondisi target
        }
        if (! str_contains($definition, $search)) {
            throw new \RuntimeException('Definisi view stok_barang tidak mengandung fragmen yang diharapkan: ' . $search);
        }

        $updated = str_replace($search, $replace, $definition);
        $this->db->query('DROP VIEW IF EXISTS stok_barang');
        $this->db->query($updated);
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $rows = $this->db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->getResultArray();
        if (empty($rows)) {
            $this->db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $rows = $this->db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'")->getResultArray();
        return !empty($rows);
    }
}
