<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Backfill ringkasan selisih periode stok opname.
 *
 * Sebelumnya jumlah_real & jumlah_selisih di tabel stok_opname_periode hanya
 * dihitung saat status FINAL. Untuk periode DRAFT (dan data drafting existing),
 * kolom tsb NULL sehingga "Total Selisih" di monitor & riwayat tampil "-".
 *
 * Migration ini menghitung ulang ringkasan untuk semua periode DRAFT dari
 * baris draft-nya (SUM real & SUM selisih dari baris yang sudah terisi).
 */
class BackfillRingkasanSelisihDraftStokOpname extends Migration
{
    public function up()
    {
        $periods = $this->db->query("SELECT id FROM stok_opname_periode WHERE status = 'DRAFT'")->getResultArray();

        foreach ($periods as $p) {
            $row = $this->db->query(
                "SELECT COALESCE(SUM(
                            CASE WHEN jumlah_real IS NOT NULL AND TRIM(CAST(jumlah_real AS CHAR)) <> '' 
                                 THEN CAST(jumlah_real AS DECIMAL(15,2)) ELSE 0 END
                        ),0) AS real_jml,
                        COALESCE(SUM(
                            CASE WHEN jumlah_selisih IS NOT NULL THEN CAST(jumlah_selisih AS DECIMAL(15,2)) ELSE 0 END
                        ),0) AS selisih
                 FROM stok_opname_draft WHERE periode_id = ?",
                [(int)$p['id']]
            )->getRow();

            $this->db->query(
                'UPDATE stok_opname_periode SET jumlah_real = ?, jumlah_selisih = ? WHERE id = ?',
                [(float)$row->real_jml, (float)$row->selisih, (int)$p['id']]
            );
        }
    }

    public function down()
    {
        $this->db->query(
            "UPDATE stok_opname_periode SET jumlah_real = NULL, jumlah_selisih = NULL WHERE status = 'DRAFT'"
        );
    }
}