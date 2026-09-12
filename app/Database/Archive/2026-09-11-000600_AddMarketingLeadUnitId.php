<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Detail prospek marketing: tambah unit (cabang) pada baris manual.
 *
 * NON-DESTRUKTIF: hanya menambah kolom unit_id (opsional) di marketing_lead.
 */
class AddMarketingLeadUnitId extends Migration
{
    public function up()
    {
        $this->forge->addColumn('marketing_lead', [
            'unit_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null, 'after' => 'platform'],
        ]);
        $this->db->query("ALTER TABLE marketing_lead ADD INDEX idx_unit (unit_id)");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE marketing_lead DROP INDEX idx_unit");
        $this->forge->dropColumn('marketing_lead', ['unit_id']);
    }
}