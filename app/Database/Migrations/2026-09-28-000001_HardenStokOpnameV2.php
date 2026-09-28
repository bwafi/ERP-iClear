<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stok Opname v2 — hardening schema.
 *
 * Prasyarat sistem opname periode (DRAFT -> FINAL) yang berlaku mulai bulan depan:
 *
 * 1. ENGINE InnoDB untuk stok_opname & stok_opname_draft.
 *    Tabel lama MyISAM membuat transBegin/transCommit/transRollback di
 *    StokOpnameService tidak atomic sama sekali.
 * 2. DECIMAL untuk hpp/jumlah_* — kolom lama INT memotong nilai desimal
 *    (service sudah menyimpan float, jadi 2.7 jadi 3).
 * 3. UNIQUE (periode_id, barang_idbarang) — mencegah satu barang ter-count
 *    dua kali dalam satu periode, yang akan menggandakan jumlah_selisih di stok_barang.
 * 4. Indeks (unit_idunit, status, tanggal) — StokOpnameCalculator dijalankan
 *    per karyawan per bulan per konteks.
 * 5. Jejak audit stok_opname_audit — reopen menghapus baris final, tanpa audit
 *    maka nilai lama hilang permanen.
 * 6. Rekonsiliasi periode FINAL lama: jumlah_real/jumlah_selisih NULL dan
 *    stok_opname tidak sinkron dengan draft (periode 41: total_barang=100 tapi
 *    hanya 1 baris final).
 */
class HardenStokOpnameV2 extends Migration
{
    private const TABLES = ['stok_opname', 'stok_opname_draft'];

    public function up()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $this->db->query("ALTER TABLE stok_opname ENGINE=InnoDB");
        $this->db->query("ALTER TABLE stok_opname_draft ENGINE=InnoDB");

        $this->widenAmountColumns('stok_opname');
        $this->widenAmountColumns('stok_opname_draft');
        $this->widenAmountColumns('stok_opname_periode', true);

        $this->addColumnIfMissing('stok_opname_periode', 'reopen_by', 'INT NULL AFTER `finalisasi_by`');
        $this->addColumnIfMissing('stok_opname_periode', 'tanggal_reopen', 'DATETIME NULL AFTER `reopen_by`');
        $this->addColumnIfMissing('stok_opname_periode', 'alasan_reopen', 'VARCHAR(255) NULL AFTER `tanggal_reopen`');
        $this->addColumnIfMissing('stok_opname_periode', 'catatan_finalisasi', 'VARCHAR(255) NULL AFTER `alasan_reopen`');

        $this->createAuditTable();

        $this->dedupe('stok_opname_draft');
        $this->dedupe('stok_opname');

        foreach (self::TABLES as $table) {
            if (!$this->hasIndex($table, 'uk_opname_periode_barang')) {
                $this->db->query(
                    "ALTER TABLE `$table` ADD UNIQUE KEY uk_opname_periode_barang (periode_id, barang_idbarang)"
                );
            }
        }

        if (!$this->hasIndex('stok_opname_periode', 'idx_periode_status_tanggal')) {
            $this->db->query(
                'ALTER TABLE stok_opname_periode
                 ADD INDEX idx_periode_status_tanggal (unit_idunit, status, tanggal)'
            );
        }

        $this->reconcileFinalPeriods();

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::TABLES as $table) {
            if ($this->hasIndex($table, 'uk_opname_periode_barang')) {
                $this->db->query("ALTER TABLE `$table` DROP INDEX uk_opname_periode_barang");
            }
        }
        if ($this->hasIndex('stok_opname_periode', 'idx_periode_status_tanggal')) {
            $this->db->query('ALTER TABLE stok_opname_periode DROP INDEX idx_periode_status_tanggal');
        }
        $this->db->query('DROP TABLE IF EXISTS stok_opname_audit');
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function widenAmountColumns(string $table, bool $isPeriode = false): void
    {
        if (!$isPeriode) {
            $this->db->query("ALTER TABLE `$table` MODIFY hpp DECIMAL(15,2) NULL");
            $this->db->query("ALTER TABLE `$table` MODIFY jumlah_komp DECIMAL(15,2) NULL");
        } else {
            $this->db->query("ALTER TABLE `$table` MODIFY jumlah_komp DECIMAL(15,2) NOT NULL DEFAULT 0");
        }

        $this->db->query("ALTER TABLE `$table` MODIFY jumlah_real DECIMAL(15,2) NULL");
        $this->db->query("ALTER TABLE `$table` MODIFY jumlah_selisih DECIMAL(15,2) NULL");
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

    private function createAuditTable(): void
    {
        if ($this->db->tableExists('stok_opname_audit')) {
            return;
        }

        $this->db->query(
            "CREATE TABLE stok_opname_audit (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                periode_id INT UNSIGNED NULL,
                unit_idunit INT NOT NULL,
                tanggal DATE NOT NULL,
                aksi ENUM('mulai','simpan','finalisasi','reopen') NOT NULL,
                actor_id INT NULL,
                jumlah_barang INT NOT NULL DEFAULT 0,
                jumlah_terisi INT NOT NULL DEFAULT 0,
                catatan VARCHAR(255) NULL,
                created_at DATETIME NULL,
                KEY idx_audit_periode (periode_id),
                KEY idx_audit_unit_tanggal (unit_idunit, tanggal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /**
     * Buang baris duplikat per (periode_id, barang_idbarang).
     * Nilai jumlah_real/selisih dari baris paling awal dipertahankan karena
     * itu input pertama; duplikat ditemukan karena tidak ada unique key.
     */
    private function dedupe(string $table): void
    {
        $dupes = $this->db->query(
            "SELECT periode_id, barang_idbarang, COUNT(*) AS c
             FROM `$table`
             WHERE periode_id IS NOT NULL
             GROUP BY periode_id, barang_idbarang
             HAVING c > 1"
        )->getResultArray();

        foreach ($dupes as $d) {
            $ids = $this->db->query(
                "SELECT idstok_opname FROM `$table`
                 WHERE periode_id = ? AND barang_idbarang = ?
                 ORDER BY idstok_opname ASC",
                [(int)$d['periode_id'], (int)$d['barang_idbarang']]
            )->getResultArray();

            $drop = array_map(static fn($r) => (int)$r['idstok_opname'], array_slice($ids, 1));

            $this->db->query(
                "DELETE FROM `$table` WHERE idstok_opname IN (" . implode(',', $drop) . ')'
            );
        }
    }

    /**
     * Periode FINAL hasil sistem lama tidak konsisten:
     *   - jumlah_real / jumlah_selisih di periode NULL (backfill 2026-09-07 mengosongkan)
     *   - stok_opname tidak sinkron dengan draft (contoh: total_barang=100, baris final=1)
     * Bangun ulang tabel final dari draft supaya selisih yang masuk ke view
     * stok_barang sama dengan angka yang pernah diinput.
     */
    private function reconcileFinalPeriods(): void
    {
        $periods = $this->db->query(
            "SELECT id, unit_idunit, tanggal FROM stok_opname_periode WHERE status = 'FINAL'"
        )->getResultArray();

        foreach ($periods as $p) {
            $periodeId = (int)$p['id'];

            $stats = $this->db->query(
                'SELECT COUNT(*) AS total,
                        COALESCE(SUM(CASE WHEN jumlah_real IS NOT NULL THEN 1 ELSE 0 END),0) AS terisi,
                        COALESCE(SUM(jumlah_komp),0) AS komp,
                        COALESCE(SUM(jumlah_real),0) AS real_jml,
                        COALESCE(SUM(jumlah_selisih),0) AS selisih
                 FROM stok_opname_draft WHERE periode_id = ?',
                [$periodeId]
            )->getRow();

            $draftTotal = (int)($stats->total ?? 0);

            if ($draftTotal > 0) {
                $this->db->query('DELETE FROM stok_opname WHERE periode_id = ?', [$periodeId]);
                $this->db->query(
                    'INSERT INTO stok_opname
                        (tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                         satuan_terkecil, barang_idbarang, unit_idunit, periode_id)
                     SELECT tanggal, hpp, jumlah_real, jumlah_komp, jumlah_selisih,
                            satuan_terkecil, barang_idbarang, unit_idunit, periode_id
                     FROM stok_opname_draft WHERE periode_id = ?',
                    [$periodeId]
                );
            } else {
                // Tidak ada draft (mis. periode 48 unit 4) — summary diisi dari baris final.
                $final = $this->db->query(
                    'SELECT COALESCE(SUM(jumlah_komp),0) AS komp,
                            COALESCE(SUM(jumlah_real),0) AS real_jml,
                            COALESCE(SUM(jumlah_selisih),0) AS selisih,
                            COUNT(*) AS total
                     FROM stok_opname WHERE periode_id = ?',
                    [$periodeId]
                )->getRow();

                $stats = (object) array_merge(
                    (array) $stats,
                    [
                        'total'  => (int)($final->total ?? 0),
                        'komp'   => (float)($final->komp ?? 0),
                        'real_jml' => (float)($final->real_jml ?? 0),
                        'selisih' => (float)($final->selisih ?? 0),
                    ]
                );
                $draftTotal = (int)$stats->total;
            }

            $this->db->table('stok_opname_periode')
                ->where('id', $periodeId)
                ->update([
                    'total_barang'    => $draftTotal,
                    'terisi_barang'   => (int)($stats->terisi ?? 0),
                    'jumlah_komp'     => (float)($stats->komp ?? 0),
                    'jumlah_real'     => (float)($stats->real_jml ?? 0),
                    'jumlah_selisih'  => (float)($stats->selisih ?? 0),
                    'updated_at'      => date('Y-m-d H:i:s'),
                ]);
        }
    }
}
