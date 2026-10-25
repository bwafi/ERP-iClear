<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sparepart yang rusak saat klaim garansi. Sengaja terpisah dari
 * `barang_rusak` karena parts ini tidak mengurangi stok.
 */
class CreateBarangRusakClaim extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('barang_rusak_claim');

        $this->db->query("CREATE TABLE IF NOT EXISTS {$table} (
            idbarang_rusak_claim INT(11) NOT NULL AUTO_INCREMENT,
            service_idservice INT(11) NULL,
            barang_idbarang INT(11) NULL,
            jumlah DECIMAL(15,2) NOT NULL DEFAULT 0,
            keterangan TEXT NULL,
            unit_idunit INT(11) NULL,
            input_by INT(11) NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (idbarang_rusak_claim),
            KEY idx_barang_rusak_claim_service (service_idservice),
            KEY idx_barang_rusak_claim_barang (barang_idbarang)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS ' . $this->db->prefixTable('barang_rusak_claim'));
    }
}
