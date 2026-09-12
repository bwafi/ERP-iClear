<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tambah kolom 'harga' (opsional) di tabel aset_kpi.
 */
class AddHargaToAsetKpi extends Migration
{
    public function up()
    {
        $this->db->query(
            "ALTER TABLE aset_kpi ADD COLUMN harga DECIMAL(15,2) NULL DEFAULT NULL AFTER quantity"
        );
    }

    public function down()
    {
        $this->db->query("ALTER TABLE aset_kpi DROP COLUMN harga");
    }
}
