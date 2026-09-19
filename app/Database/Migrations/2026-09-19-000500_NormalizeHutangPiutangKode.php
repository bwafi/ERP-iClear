<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Normalisasi kode `hutang_piutang`: hapus prefix lama "HP-".
 *
 * Sebelumnya kode memakai format HP-<SEG>-... (mis. HP-HUT-PB12). Kini prefix
 * "HP-" tidak dipakai sehingga baris lama (termasuk projection hasil backfill)
 * dinormalisasi agar konsisten dengan generateKode() yang baru.
 */
class NormalizeHutangPiutangKode extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('hutang_piutang')) {
            return;
        }

        // Hindari bentrok UNIQUE: hanya normalisasi bila nilai hasil normalisasi
        // belum ada. Baris tanpa pasangan dinormalisasi via REPLACE.
        $this->db->query("
            UPDATE hutang_piutang hp
            SET hp.kode = REPLACE(hp.kode, 'HP-', '')
            WHERE hp.kode LIKE 'HP-%'
              AND NOT EXISTS (
                  SELECT 1 FROM (SELECT kode FROM hutang_piutang) x
                  WHERE x.kode = REPLACE(hp.kode, 'HP-', '')
              )
        ");
    }

    public function down()
    {
        // Tidak dikembalikan: penomoran lama tidak digunakan lagi.
    }
}
