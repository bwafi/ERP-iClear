<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Biaya Iklan: tambah kolom tanggal input.
 * period_month / period_year tetap diisi otomatis dari tanggal.
 */
class AddAdsCostTanggal extends Migration
{
    public function up()
    {
        $fields = [
            'tanggal' => [
                'type'       => 'DATE',
                'null'       => true,
                'default'    => null,
                'after'      => 'period_year',
            ],
        ];
        $this->forge->addColumn('marketing_ads_cost', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('marketing_ads_cost', 'tanggal');
    }
}