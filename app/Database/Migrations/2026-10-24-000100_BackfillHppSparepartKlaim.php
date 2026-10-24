<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Klaim lama selalu menyimpan HPP 0. Isi dengan HPP master supaya biaya
 * pengganti ikut mengurangi laba; HPP 0 setelah ini berarti pilihan sengaja.
 */
class BackfillHppSparepartKlaim extends Migration
{
    public function up()
    {
        $sparepart = $this->db->prefixTable('service_sparepart');
        $hpp = $this->db->prefixTable('hpp_barang');

        $this->db->query("
            UPDATE {$sparepart} sp
            JOIN {$hpp} h ON h.idbarang = sp.barang_idbarang
            SET sp.hpp_penjualan_garansi = h.hpp
            WHERE sp.jumlah_tambahan_garansi > 0
              AND (sp.hpp_penjualan_garansi IS NULL OR sp.hpp_penjualan_garansi = 0)
              AND h.hpp > 0
        ");
    }

    public function down()
    {
        // Data lama tidak bisa dibedakan lagi; biarkan HPP hasil backfill.
    }
}
