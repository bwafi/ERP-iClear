<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rekap Marketing Harian Manual (source of truth KPI Marketing).
 *
 * KEPUTUSAN DESAIN 2026-09: KPI Marketing memakai input harian CS, BUKAN
 * Kommo. Kommo tetap ada untuk CRM, tapi angka KPI (lead, iklan, prospek,
 * datang, rate) dihitung dari dua tabel ini.
 *
 * - marketing_rekap_harian          : satu baris per (unit, tanggal).
 * - marketing_rekap_harian_detail   : angka per platform.
 *
 * Non-destruktif: hanya CREATE tabel baru, tidak menyentuh tabel existing.
 */
class CreateMarketingRekapHarian extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (!$db->tableExists('marketing_rekap_harian')) {
            $this->forge->addField([
                'id'                         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'tanggal'                    => ['type' => 'DATE', 'null' => false],
                'unit_id'                    => ['type' => 'INT', 'unsigned' => true, 'null' => false],
                'total_lead_wa_dm'           => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
                'lead_total_iklan_dashboard' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
                'created_by'                 => ['type' => 'INT', 'null' => true],
                'created_at'                 => ['type' => 'DATETIME', 'null' => true],
                'updated_at'                 => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['unit_id', 'tanggal'], false, true, 'uniq_rekap_harian_unit_tanggal');
            $this->forge->addKey('tanggal');
            $this->forge->createTable('marketing_rekap_harian', true);
        }

        if (!$db->tableExists('marketing_rekap_harian_detail')) {
            $this->forge->addField([
                'id'        => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'rekap_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
                'platform'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false],
                'non_iklan' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
                'iklan'     => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
                'total'     => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
                'prospek'   => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
                'datang'    => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
                'rate'      => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false, 'default' => 0],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['rekap_id', 'platform'], false, true, 'uniq_rekap_detail_platform');
            $this->forge->addKey('rekap_id');
            $this->forge->addForeignKey('rekap_id', 'marketing_rekap_harian', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('marketing_rekap_harian_detail', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('marketing_rekap_harian_detail', true);
        $this->forge->dropTable('marketing_rekap_harian', true);
    }
}