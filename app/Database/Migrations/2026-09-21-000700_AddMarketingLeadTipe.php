<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tipe lead Detail Prospek: Iklan / Non Iklan.
 *
 * NON-DESTRUKTIF: menambah kolom opsional `tipe` pada marketing_lead untuk
 * baris manual (Detail Prospek). Baris sinkronisasi Kommo tetap utuh.
 */
class AddMarketingLeadTipe extends Migration
{
    public function up()
    {
        $this->forge->addColumn('marketing_lead', [
            'tipe' => ['type' => "ENUM('IKLAN','NON_IKLAN')", 'null' => true, 'default' => null, 'after' => 'platform'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('marketing_lead', 'tipe');
    }
}