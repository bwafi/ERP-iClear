<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel riwayat klaim garansi.
 *
 * Sebelumnya tabel ini ada di database lokal tanpa migration, sehingga
 * instalasi/deploy baru tidak punya tempat menyimpan klaim dan alur garansi
 * tidak pernah aktif. `IF NOT EXISTS` membuat migration aman dijalankan pada
 * database yang tabelnya sudah dibuat manual.
 */
class CreateRiwayatClaimGaransi extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('riwayat_claim_garansi');

        $this->db->query("CREATE TABLE IF NOT EXISTS {$table} (
            idriwayat_claim_garansi INT(11) NOT NULL AUTO_INCREMENT,
            service_idservice INT(11) NULL,
            tanggal_claim DATE NULL,
            PRIMARY KEY (idriwayat_claim_garansi),
            KEY idx_riwayat_claim_service (service_idservice)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Tabel yang dibuat manual sebelumnya mungkin belum punya index;
        // klaim dan riwayat selalu mencari lewat service_idservice.
        $index = $this->db->query("SHOW INDEX FROM {$table} WHERE Key_name = 'idx_riwayat_claim_service'")->getRow();
        if ($index === null) {
            $this->db->query("ALTER TABLE {$table} ADD INDEX idx_riwayat_claim_service (service_idservice)");
        }

        // Service lama bisa sudah punya tanggal_claim_garansi tetapi belum
        // punya baris riwayat (tabel dibuat manual belakangan). Isi yang
        // hilang agar modal riwayat dan guard klaim konsisten.
        $serviceTable = $this->db->prefixTable('service');
        $this->db->query("
            INSERT INTO {$table} (service_idservice, tanggal_claim)
            SELECT s.idservice, DATE(s.tanggal_claim_garansi)
            FROM {$serviceTable} s
            LEFT JOIN {$table} r ON r.service_idservice = s.idservice
            WHERE s.tanggal_claim_garansi IS NOT NULL
              AND DATE(s.tanggal_claim_garansi) NOT IN ('0000-00-00', '1970-01-01')
              AND r.idriwayat_claim_garansi IS NULL
        ");
    }

    public function down()
    {
        // Tabel bisa sudah ada sebelum migration ini (dibuat manual), jadi
        // rollback tidak menghapus riwayat klaim yang mungkin sudah berisi data.
    }
}
