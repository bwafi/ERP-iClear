<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CLOSING_RATE (Closing Rate) jadi komponen AUTOMATIC.
 *
 * Ada sumber datanya langsung (marketing_lead status CLOSING ÷ prospek rekap
 * harian), sehingga TIDAK diinput manual lagi. Diproses oleh
 * ClosingRateCalculator di engine KPI.
 */
class ClosingRateJadiAutomatic extends Migration
{
    public function up()
    {
        $this->db->query(
            "UPDATE kpi_components SET type = 'automatic' WHERE code = 'CLOSING_RATE'"
        );
    }

    public function down()
    {
        $this->db->query(
            "UPDATE kpi_components SET type = 'manual' WHERE code = 'CLOSING_RATE'"
        );
    }
}