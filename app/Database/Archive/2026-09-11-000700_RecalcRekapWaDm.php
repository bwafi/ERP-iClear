<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * PERBAIKAN DATA: hitung ulang total_lead_wa_dm (WhatsApp + Instagram)
 * untuk semua header rekap existing yang nilainya tidak sinkron dengan
 * detailnya. Baca berikutnya (service) selalu menghitung dari detail,
 * jadi nilai ini hanya dipelihara agar kolom tetap konsisten.
 */
class RecalcRekapWaDm extends Migration
{
    public function up()
    {
        $db = $this->db;
        if (!$db->tableExists('marketing_rekap_harian') || !$db->tableExists('marketing_rekap_harian_detail')) {
            return;
        }

        $db->query(<<<'SQL'
            UPDATE marketing_rekap_harian h
            SET h.total_lead_wa_dm = (
                SELECT COALESCE(SUM(d.total), 0)
                FROM marketing_rekap_harian_detail d
                WHERE d.rekap_id = h.id
                  AND UPPER(TRIM(d.platform)) IN ('WHATSAPP', 'INSTAGRAM')
            )
            SQL);
    }

    public function down()
    {
        // Tidak ada pembalikan yang aman — nilai lama sudah tidak konsisten.
    }
}