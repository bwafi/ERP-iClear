<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFulltextIndexPelanggan extends Migration
{
    public function up()
    {
        $db = $this->db;
        $db->query("CREATE INDEX idx_pelanggan_deleted ON pelanggan (deleted)");
        $db->query("CREATE FULLTEXT INDEX idx_pelanggan_search ON pelanggan (nama, no_hp, alamat, nik)");
    }

    public function down()
    {
        $db = $this->db;
        $db->query("ALTER TABLE pelanggan DROP INDEX idx_pelanggan_search");
        $db->query("ALTER TABLE pelanggan DROP INDEX idx_pelanggan_deleted");
    }
}