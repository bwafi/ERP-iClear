<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rekonsiliasi Harian Finance.
 *
 * Setiap unit mencatat pencocokan transaksi harian untuk 3 kelompok:
 *  1. Cash Masuk
 *  2. Transfer Masuk
 *  3. Kas Keluar
 *
 * Selisih disimpan sebagai temuan (source KPI Akurasi), TIDAK mengurangi
 * skor KPI Rekonsiliasi.
 */
class CreateFinanceRekonDaily extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('finance_rekon_daily')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'unit_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
            ],
            'tanggal' => [
                'type' => 'DATE',
                'null' => false,
            ],

            'erp_cash_masuk' => [
                'type' => 'BIGINT',
                'null' => false,
                'default' => 0,
            ],
            'actual_cash_masuk' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'selisih_cash_masuk' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'checked_cash_masuk' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],

            'erp_transfer_masuk' => [
                'type' => 'BIGINT',
                'null' => false,
                'default' => 0,
            ],
            'actual_transfer_masuk' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'selisih_transfer_masuk' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'checked_transfer_masuk' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],

            'erp_kas_keluar' => [
                'type' => 'BIGINT',
                'null' => false,
                'default' => 0,
            ],
            'actual_kas_keluar' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'selisih_kas_keluar' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'checked_kas_keluar' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],

            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'input_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['unit_id', 'tanggal'], 'uq_rekon_daily_unit_tanggal');
        $this->forge->createTable('finance_rekon_daily', true);
    }

    public function down()
    {
        $this->forge->dropTable('finance_rekon_daily', true);
    }
}
