<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Jenis konten: REGULAR (konten biasa) vs ADS (iklan).
 * KPI "Performa Konten (sesuai target)" hanya menilai konten ADS.
 */
class AddJenisKontenToContents extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE contents
            ADD COLUMN jenis_konten ENUM('REGULAR','ADS') NOT NULL DEFAULT 'REGULAR' AFTER content_type_id");
    }

    public function down()
    {
        $this->db->query('ALTER TABLE contents DROP COLUMN jenis_konten');
    }
}