<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catatan/alasan klaim garansi. Kolom dipisah dari migration create karena
 * tabel riwayat bisa sudah dibuat manual di database lama.
 */
class AddCatatanToRiwayatClaimGaransi extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('riwayat_claim_garansi');
        $kolom = $this->db->query("SHOW COLUMNS FROM {$table} LIKE 'catatan'")->getRow();

        if ($kolom === null) {
            $this->db->query("ALTER TABLE {$table} ADD COLUMN catatan TEXT NULL AFTER tanggal_claim");
        }
    }

    public function down()
    {
        $table = $this->db->prefixTable('riwayat_claim_garansi');
        $kolom = $this->db->query("SHOW COLUMNS FROM {$table} LIKE 'catatan'")->getRow();

        if ($kolom !== null) {
            $this->db->query("ALTER TABLE {$table} DROP COLUMN catatan");
        }
    }
}
