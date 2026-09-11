<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Refactor detail prospek marketing (pendukung rekap harian).
 *
 * NON-DESTRUKTIF: hanya menambah kolom baris manual (Detail Prospek) pada
 * marketing_lead + memperluas enum status. Baris sinkronisasi Kommo tetap utuh
 * dan tetap tersimpan (dipisahkan lewat kolom kommo_lead_id).
 *
 * Detail prospek TIDAK digunakan sebagai pembentuk angka rekap; rekap harian
 * CS tetap source of truth KPI Marketing. Detail prospek hanya data pendukung
 * (closing/omzet) untuk status CLOSED.
 */
class ReworkMarketingLeadDetailProspek extends Migration
{
    public function up()
    {
        // Kolom baru untuk baris manual (kommo_lead_id IS NULL).
        $fields = [
            'platform'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null, 'after' => 'nama'],
            'no_telp_wa'      => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'default' => null, 'after' => 'platform'],
            'keterangan'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null, 'after' => 'no_telp_wa'],
            'tanggal_booking' => ['type' => 'DATE', 'null' => true, 'default' => null, 'after' => 'keterangan'],
            'omset'           => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'default' => null, 'after' => 'tanggal_booking'],
            'catatan'         => ['type' => 'TEXT', 'null' => true, 'after' => 'omset'],
            'nomor'           => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'default' => null, 'after' => 'catatan'],
        ];
        $this->forge->addColumn('marketing_lead', $fields);

        // Perluas enum status: status CRM lama (NEW/FOLLOW_UP/WON/LOST) tetap
        // dipertahankan agar data lama & sinkronisasi Kommo tidak rusak;
        // status Detail Prospek operasional ditambahkan.
        $this->db->query(
            "ALTER TABLE marketing_lead
             MODIFY status ENUM('NEW','FOLLOW_UP','WON','LOST','PROSPEK','BOOKING','DATANG','CLOSED','BATAL')
             NOT NULL DEFAULT 'NEW'"
        );

        // Indeks untuk filter detail prospek manual.
        $this->db->query(
            "ALTER TABLE marketing_lead ADD INDEX idx_kommo_manual (kommo_lead_id)"
        );
    }

    public function down()
    {
        $this->db->query("ALTER TABLE marketing_lead DROP INDEX idx_kommo_manual");
        $this->forge->dropColumn('marketing_lead', [
            'platform',
            'no_telp_wa',
            'keterangan',
            'tanggal_booking',
            'omset',
            'catatan',
            'nomor',
        ]);
        $this->db->query(
            "ALTER TABLE marketing_lead
             MODIFY status ENUM('NEW','FOLLOW_UP','WON','LOST')
             NOT NULL DEFAULT 'NEW'"
        );
    }
}