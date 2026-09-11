<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sinkronisasi Kommo → ERP: kolom tambahan hasil backfill/sync.
 *
 * - price : nilai transaksi (lead.price Kommo, dalam Rupiah). Dipakai nanti
 *           sebagai dasar omzet bila lead ditandai WON + dihubungkan customer.
 * - cabang: custom field Kommo "Cabang Tujuan" (branch tujuan outlet).
 */
class AddMarketingLeadPriceCabang extends Migration
{
    public function up()
    {
        $fields = [
            'price'  => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'default' => null, 'after' => 'ads_organic'],
            'cabang' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'cs'],
        ];
        $this->forge->addColumn('marketing_lead', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('marketing_lead', ['price', 'cabang']);
    }
}