<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Integrasi Kommo CRM → ERP (non-destructive).
 *
 * - kommo_lead_id     : identifier eksternal lead di Kommo.
 * - kommo_account_id  : id akun Kommo asal webhook.
 * - kommo_pipeline_id : pipeline tempat lead berada.
 * - kommo_status_id   : status terakhir di Kommo (untuk debug/remap).
 * - kommo_updated_at  : updated_at dari Kommo (unix) — dasar deteksi perubahan.
 * - kommo_deleted_at  : soft-delete saat event `leads.delete` dari Kommo;
 *                       lead TIDAK dihapus fisik (source of truth tetap ERP).
 *
 * Unique index (kommo_lead_id) menjamin event/lead yang sama hanya membuat
 * SATU baris (idempotent) — event berikutnya berupa UPDATE.
 */
class AddKommoLeadFields extends Migration
{
    public function up()
    {
        $fields = [
            'kommo_lead_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'created_by'],
            'kommo_account_id'  => ['type' => 'INT', 'null' => true],
            'kommo_pipeline_id' => ['type' => 'INT', 'null' => true],
            'kommo_status_id'   => ['type' => 'INT', 'null' => true],
            'kommo_updated_at'  => ['type' => 'INT', 'null' => true],
            'kommo_deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ];
        $this->forge->addColumn('marketing_lead', $fields);

        $this->db->query('ALTER TABLE marketing_lead ADD UNIQUE INDEX uniq_kommo_lead (kommo_lead_id)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE marketing_lead DROP INDEX uniq_kommo_lead');
        $cols = ['kommo_lead_id', 'kommo_account_id', 'kommo_pipeline_id', 'kommo_status_id', 'kommo_updated_at', 'kommo_deleted_at'];
        $this->forge->dropColumn('marketing_lead', $cols);
    }
}