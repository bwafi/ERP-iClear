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
 *
 * STATUS HASIL 100% OTOMATIS dari perbandingan ERP vs Aktual — tanpa input
 * manual "sudah diperiksa". Tidak ada kolom checked_*:
 *   actual_* IS NULL                  -> belum diperiksa (BELUM_DIPERIKSA)
 *   actual_* terisi & selisih_* = 0   -> COCOK
 *   actual_* terisi & selisih_* != 0  -> SELISIH
 * sehingga NULL = belum diisi, dan angka 0 tetap nilai yang sah.
 *
 * Kolom actual_* & selisih_* harus NULL-able karena itu pembeda "belum diisi".
 *
 * Catatan tipe nominal: BIGINT (rupiah bulat). PHP int 64-bit pada server
 * (PHP_INT_SIZE = 8) dan kolom MySQL bigint(20), jadi aman sampai 9.22e18.
 *
 * Approval workflow (terpisah dari status hasil rekonsiliasi):
 *   status_proses: draft -> submitted -> verified | need_revision
 * Hanya status_proses = 'verified' yang dihitung sebagai "hari lengkap" KPI.
 */
class CreateFinanceRekonDaily extends Migration
{
    public function up()
    {
        // Catatan: memakai createTable(..., ifNotExists: true) sebagai penjaga
        // idempotensi. $this->db bertipe ConnectionInterface yang tidak
        // mendeklarasikan tableExists(), sehingga tidak dipakai di sini.
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

            'catatan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'catatan_revisi' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // Workflow approval Admin -> Manager (terpisah dari status hasil).
            'status_proses' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'draft',
            ],
            'submitted_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'verified_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'verified_at' => [
                'type' => 'DATETIME',
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
