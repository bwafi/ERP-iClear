<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Status Detail Prospek: hapus BOOKING dan ganti CLOSED menjadi CLOSING.
 *
 * - BOOKING : status sudah tidak dipakai di input prospek.
 * - CLOSED  : diganti istilah CLOSING (konsisten dengan input/marketing).
 *
 * Data BLANKUM CLOSED lama dipindah ke CLOSING sebelum enum diubah
 * (BOOKING dibiarkan apa adanya bila masih ada catatan lama di DB).
 */
class RenameMarketingLeadStatusClosing extends Migration
{
    public function up()
    {
        // Pindahkan data lama ber-status CLOSED ke CLOSING (sebelum enum berubah).
        $this->db->query("UPDATE marketing_lead SET status = 'CLOSING' WHERE status = 'CLOSED'");

        $this->db->query(
            "ALTER TABLE marketing_lead
             MODIFY status ENUM('NEW','FOLLOW_UP','WON','LOST','PROSPEK','DATANG','CLOSING','BATAL')
             NOT NULL DEFAULT 'NEW'"
        );
    }

    public function down()
    {
        $this->db->query(
            "ALTER TABLE marketing_lead
             MODIFY status ENUM('NEW','FOLLOW_UP','WON','LOST','PROSPEK','BOOKING','DATANG','CLOSED','BATAL')
             NOT NULL DEFAULT 'NEW'"
        );

        $this->db->query("UPDATE marketing_lead SET status = 'CLOSED' WHERE status = 'CLOSING'");
    }
}