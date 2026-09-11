<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tautan detail prospek (marketing_lead) ke service selesai.
 *
 * NON-DESTRUKTIF: hanya menambah kolom service_id (opsional) pada baris manual.
 * Digunakan agar omset status CLOSED dapat dihitung otomatis dari
 * service yang sudah selesai (sum service_sparepart.sub_total).
 */
class AddMarketingLeadServiceId extends Migration
{
    public function up()
    {
        $this->forge->addColumn('marketing_lead', [
            'service_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null, 'after' => 'omset'],
        ]);
        $this->db->query("ALTER TABLE marketing_lead ADD INDEX idx_service (service_id)");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE marketing_lead DROP INDEX idx_service");
        $this->forge->dropColumn('marketing_lead', ['service_id']);
    }
}