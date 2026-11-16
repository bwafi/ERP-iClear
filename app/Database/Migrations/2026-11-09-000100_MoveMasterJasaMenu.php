<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pindahkan grup menu Master Jasa tepat setelah grup Produk (1006) di section
 * DATA MASTER. Hanya urutan yang berubah; nama, parent, dan grant role tetap.
 */
class MoveMasterJasaMenu extends Migration
{
    private const GRUP_ID   = 10170;
    private const URUTAN    = 1007;   // bebas antara Produk (1006) dan Supplier (1009)
    private const URUTAN_ASAL = 10170;

    public function up()
    {
        $this->pindah(self::URUTAN);
    }

    public function down()
    {
        $this->pindah(self::URUTAN_ASAL);
    }

    private function pindah(int $urutan): void
    {
        if (! $this->db->tableExists('menu')) {
            return;
        }

        if (! $this->db->table('menu')->where('idmenu', self::GRUP_ID)->countAllResults()) {
            return;
        }

        $this->db->table('menu')
            ->where('idmenu', self::GRUP_ID)
            ->update(['urutan' => $urutan]);
    }
}
