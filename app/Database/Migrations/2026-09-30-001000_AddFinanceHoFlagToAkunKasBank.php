<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Finance/HO vs Shared antar-unit — pemisah jenis rekening.
 *
 * MASALAH YANG DISELESAIKAN
 * -------------------------
 *-species sebelumnya hanya punya DUA bentuk data:
 *
 *     unit_id = NULL, is_shared = 1
 *
 * yang dipakai untuk DUA konsep berbeda:
 *
 *     (A) Rekening Finance/HO  — mis. IRA/BCA. Bukan milik unit mana pun,
 *         tidak butuh alokasi_saldo_kas_bank, boleh jadi TUJUAN transfer dari
 *         unit mana pun, tapi dana HANYA boleh dikeluarkan oleh ROOT/ADMIN
 *         CENTER.
 *
 *     (C) Rekening shared antar-unit — mis. rekening yang dialokasikan ke
 *         Unit 1 + Unit 2. Unit 3 tidak boleh memakainya.
 *
 * Keduanya identik di kolom lama, sehingga tidak bisa dibedakan tanpa
 * menebak. Menebak dari "punya alokasi atau tidak" adalah lubang keamanan:
 * rekening shared yang alokasinya belum dikonfigurasi akan otomatis berubah
 * jadi rekening Finance/HO. Karena itu dipakai pemisah EKSPLISIT.
 *
 * PERUBAHAN (additive, tanpa mengubah data lama)
 * ---------------------------------------------
 * Menambah satu kolom `is_finance_ho`:
 *
 *     1  -> rekening Finance/HO (destination = semua unit, source = ROOT/CENTER)
 *     0  -> rekening UNIT (is_shared = 0) atau SHARED (is_shared = 1)
 *
 * Default 0 dipakai SENGaja: tidak ada rekening lama yang diam-diam mendapat
 * hak baru. Rekening yang sebenarnya Finance/HO harus ditandai eksplisit
 * lewat Master Akun Kas & Bank (atau spark kasbank:...) — keputusan bisnis,
 * bukan tebakan migration.
 *
 * Tidak ada insert alokasi, backfill, perubahan saldo awal, mapping bank,
 * cutoff, unit_id, maupun is_shared. Kolom ini murni untuk implementasi
 * permission rekening.
 */
class AddFinanceHoFlagToAkunKasBank extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('is_finance_ho', 'akun_kas_bank')) {
            $this->forge->addColumn('akun_kas_bank', [
                'is_finance_ho' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_shared',
                ],
            ]);
        }

        // Query listing rekening memfilter jenis ini tiap request. Dijalankan
        // sebagai ALTER terpisah (bukan lewat Forge::addKey) supaya tetap
        // terbentuk walau kolomnya sudah ada dari run sebelumnya.
        $ada = $this->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = \'akun_kas_bank\'
               AND index_name = \'idx_kb_scope\''
        )->getRow();

        if ((int) ($ada->c ?? 0) === 0) {
            $this->db->query('ALTER TABLE akun_kas_bank '
                . 'ADD INDEX idx_kb_scope (is_shared, is_finance_ho, unit_id)');
        }
    }

    public function down()
    {
        $ada = $this->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = \'akun_kas_bank\'
               AND index_name = \'idx_kb_scope\''
        )->getRow();

        if ((int) ($ada->c ?? 0) > 0) {
            $this->db->query('ALTER TABLE akun_kas_bank DROP INDEX idx_kb_scope');
        }

        if ($this->db->fieldExists('is_finance_ho', 'akun_kas_bank')) {
            $this->forge->dropColumn('akun_kas_bank', 'is_finance_ho');
        }
    }
}
