<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Guard anti double-submit untuk transaksi_kas_bank.
 *
 * - Kolom submission_key menyimpan token form (dihasilkan server di halaman
 *   transfer / antar-unit) UNTUK KAKI PERTAMA (arah KELUAR) saja.
 * - UNIQUE(submission_key) membuat submit ganda tidak bisa membuat dua
 *   transaksi; tidak bergantung pada sumber_id (yang NULL untuk baris
 *   buatan pengguna via transfer/antar-unit).
 * - Kaki kedua (MASUK) membiarkan submission_key NULL (MariaDB mengizinkan
 *   banyak NULL pada unique index).
 */
class AddSubmissionKeyTransaksiKasBank extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('transaksi_kas_bank')) {
            return;
        }

        $db = $this->db;
        $exists = $db->query("SHOW COLUMNS FROM transaksi_kas_bank LIKE 'submission_key'")->getResultArray();
        if (count($exists) === 0) {
            $db->query('ALTER TABLE transaksi_kas_bank
                ADD COLUMN submission_key VARCHAR(64) NULL
                COMMENT \'token anti double-submit (kaki KELUAR saja)\'
                AFTER transfer_ref');
            $db->query('ALTER TABLE transaksi_kas_bank
                ADD UNIQUE KEY uniq_tkb_submission (submission_key)');
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('transaksi_kas_bank')) {
            return;
        }

        $db = $this->db;
        $keys = $db->query("SHOW INDEX FROM transaksi_kas_bank WHERE Key_name = 'uniq_tkb_submission'")->getResultArray();
        if (count($keys) > 0) {
            $db->query('ALTER TABLE transaksi_kas_bank DROP INDEX uniq_tkb_submission');
        }

        $cols = $db->query("SHOW COLUMNS FROM transaksi_kas_bank LIKE 'submission_key'")->getResultArray();
        if (count($cols) > 0) {
            $db->query('ALTER TABLE transaksi_kas_bank DROP COLUMN submission_key');
        }
    }
}