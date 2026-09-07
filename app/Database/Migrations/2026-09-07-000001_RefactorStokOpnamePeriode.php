<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Refactor Stok Opname menjadi periode DRAFT/FINAL (mirip Kontrol Aset).
 *
 * 1. Tabel baru stok_opname_periode (unit + tanggal, status DRAFT/FINAL,
 *    ringkasan jumlah_komp/real/selisih, siapa mulai/finalisasi).
 * 2. Kolom periode_id di stok_opname_draft & stok_opname.
 * 3. Backfill data existing: kelompokkan (unit_idunit, tanggal) -> satu periode.
 */
class RefactorStokOpnamePeriode extends Migration
{
    public function up()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        if (!$this->db->tableExists('stok_opname_periode')) {
            $this->db->query(
                "CREATE TABLE stok_opname_periode (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    unit_idunit INT NOT NULL,
                    tanggal DATE NOT NULL,
                    status ENUM('DRAFT','FINAL') NOT NULL DEFAULT 'DRAFT',
                    jumlah_komp INT NOT NULL DEFAULT 0,
                    jumlah_real INT NULL,
                    jumlah_selisih INT NULL,
                    total_barang INT NOT NULL DEFAULT 0,
                    terisi_barang INT NOT NULL DEFAULT 0,
                    mulai_by INT NULL,
                    finalisasi_by INT NULL,
                    tanggal_finalisasi DATETIME NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uk_periode (unit_idunit, tanggal)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        $this->addColumnIfMissing('stok_opname_draft', 'periode_id', "INT UNSIGNED NULL AFTER `unit_idunit`");
        $this->addColumnIfMissing('stok_opname', 'periode_id', "INT UNSIGNED NULL AFTER `unit_idunit`");

        foreach (['stok_opname_draft', 'stok_opname'] as $table) {
            $this->db->query("ALTER TABLE `$table` ADD INDEX idx_opname_periode (periode_id)");
        }

        $this->backfillPeriode();

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function addColumnIfMissing(string $table, string $column, string $definition)
    {
        $rows = $this->db->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->getResultArray();
        if (empty($rows)) {
            $this->db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }

    /**
     * Kelompokkan data draft & final yang sudah ada per (unit, tanggal),
     * buat periode (FINAL bila ada hasil final, DRAFT untuk sisa),
     * lalu hubungkan baris-baris yang ada dengan periode_id.
     */
    private function backfillPeriode()
    {
        $db = $this->db;

        $combos = [];
        foreach (['stok_opname_draft', 'stok_opname'] as $table) {
            $rows = $db->query(
                "SELECT unit_idunit, tanggal, COUNT(*) AS cnt
                 FROM `$table`
                 GROUP BY unit_idunit, tanggal
                 ORDER BY tanggal ASC"
            )->getResultArray();
            foreach ($rows as $r) {
                $key = $r['unit_idunit'] . '|' . $r['tanggal'];
                $combos[$key]['unit'] = (int)$r['unit_idunit'];
                $combos[$key]['tanggal'] = $r['tanggal'];
                $combos[$key]['has_final_' . $table] = true;
            }
        }

        foreach ($combos as $combo) {
            $db->query('SET @opname_periode_id = 0');
            $memosis = (int)$combo['unit'];
            $tanggal = $combo['tanggal'];
            $isFinal = !empty($combo['has_final_stok_opname']);

            $db->query(
                'INSERT INTO stok_opname_periode
                    (unit_idunit, tanggal, status, created_at, updated_at)
                 VALUES (?, ?, ?, NOW(), NOW())',
                [$memosis, $tanggal, $isFinal ? 'FINAL' : 'DRAFT']
            );
            $periodeId = (int)$db->insertID();

            $db->query(
                'UPDATE stok_opname_draft SET periode_id = ? WHERE unit_idunit = ? AND tanggal = ?',
                [$periodeId, $memosis, $tanggal]
            );
            $db->query(
                'UPDATE stok_opname SET periode_id = ? WHERE unit_idunit = ? AND tanggal = ?',
                [$periodeId, $memosis, $tanggal]
            );

            $this->refreshPeriodeSummary($periodeId);
        }
    }

    /**
     * Hitung ulang ringkasan periode dari baris draft miliknya.
     * Dipakai untuk backfill (total_barang = baris draft periode tsb).
     */
    private function refreshPeriodeSummary(int $periodeId)
    {
        $db = $this->db;

        $draft = $db->query(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(jumlah_komp),0) AS komp,
                    COALESCE(SUM(CASE WHEN jumlah_real IS NOT NULL AND TRIM(CAST(jumlah_real AS CHAR)) <> \'\' THEN 1 ELSE 0 END),0) AS terisi,
                    COALESCE(SUM(CAST(jumlah_real AS DECIMAL(15,2))),0) AS real_jml,
                    COALESCE(SUM(CAST(jumlah_selisih AS DECIMAL(15,2))),0) AS selisih
             FROM stok_opname_draft WHERE periode_id = ?',
            [$periodeId]
        )->getRow();

        $db->table('stok_opname_periode')
            ->where('id', $periodeId)
            ->update([
                'total_barang'  => (int)($draft->total ?? 0),
                'terisi_barang' => (int)($draft->terisi ?? 0),
                'jumlah_komp'   => (int)($draft->komp ?? 0),
                'jumlah_real'   => null, // diisi ulang saat finalisasi
                'jumlah_selisih' => null,
            ]);
    }

    public function down()
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['stok_opname_draft', 'stok_opname'] as $table) {
            $this->db->query("DROP INDEX idx_opname_periode ON `$table`");
            $this->db->query("ALTER TABLE `$table` DROP COLUMN periode_id");
        }
        $this->db->query('DROP TABLE IF EXISTS stok_opname_periode');

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}