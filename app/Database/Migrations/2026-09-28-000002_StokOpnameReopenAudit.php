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
        if (! $this->viewExists()) {
            throw new \RuntimeException(
                'Baseline view `stok_barang` tidak ditemukan di database ini. Migration '
                . '2026-09-28-000002 membutuhkan view warisan `stok_barang` dari dump baseline '
                . '(view ini tidak pernah dibuat oleh migration mana pun). Import dump baseline '
                . 'yang memuat view tersebut terlebih dahulu, lalu jalankan migrate ulang. '
                . 'Tidak ada object yang diubah.'
            );
        }

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

        if (strpos($definition, $replace) !== false) {
            return; // sudah dalam kondisi target
        }

        $count = substr_count($definition, $search);
        if ($count !== 1) {
            throw new \RuntimeException(sprintf(
                'Fragmen transformasi ditemukan %d kali (harus tepat 1) pada definisi view stok_barang. '
                . 'Str_replace massal sengaja ditolak supaya tidak diam-diam mengubah SELECT lain. Fragmen: %s',
                $count,
                $search
            ));
        }

        $updated = str_replace($search, $replace, $definition);

        // SHOW CREATE VIEW mengembalikan DEFINER pemilik asli (mis.
        // DEFINER=`root`@`localhost`). Membaca view tidak butuh privilege
        // khusus, tetapi MENCIPATKAN view dengan DEFINER asing menuntut
        // SUPER/SET USER — itu penyebab dua kali gagal di staging. Tanpa
        // klausul DEFINER, MariaDB menetapkan definer = user eksekusi
        // (sah untuk user aplikasi). `SQL SECURITY DEFINER` TIDAK diubah:
        // semantik privilege view tetap sama untuk koneksi aplikasi.
        $replay = preg_replace('/\s+DEFINER=\S+/', '', $updated, 1);
        if ($replay === null) {
            throw new \RuntimeException('Gagal menormalisasi DEFINER pada definisi view stok_barang.');
        }

        // Ganti definisi tanpa pernah menjatuhkan view lebih dulu:
        // CREATE OR REPLACE tidak membuka jendela view hilang, dan bila CREATE
        // gagal view lama tetap utuh (tidak mungkin meninggalkan view lenyap).
        $replay = preg_replace('/^CREATE\s+/', 'CREATE OR REPLACE ', $replay, 1);
        if ($replay === null) {
            throw new \RuntimeException('Gagal menyiapkan statement CREATE OR REPLACE untuk view stok_barang.');
        }

        try {
            $result = $this->db->query($replay);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Gagal menulis ulang view `stok_barang`. View LAMA TIDAK dihapus dan tetap utuh. '
                . 'Kesalahan: ' . $e->getMessage(),
                0,
                $e
            );
        }
        if ($result === false) {
            $err = $this->db->error();
            throw new \RuntimeException(
                'Gagal menulis ulang view `stok_barang`. View LAMA TIDAK dihapus dan tetap utuh. '
                . 'Kesalahan: ' . ($err['message'] ?? 'query gagal tanpa pesan driver.')
            );
        }
    }

    /**
     * Cek keberadaan view lewat information_schema dulu. SHOW CREATE VIEW
     * langsung melempar error driver bila view tidak ada, sehingga guard
     * `$row === null` di replaceView() tidak pernah sempat berjalan.
     */
    private function viewExists(): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS jml FROM information_schema.VIEWS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stok_barang'"
        )->getRow();

        return $row !== null && (int) $row->jml > 0;
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
