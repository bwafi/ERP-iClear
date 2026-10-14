<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ledger DP (uang muka) service — tahap 1.
 *
 * Tabel `service_dp` mencatat SETIAP penerimaan DP untuk service baru. Tabel
 * ini hanya ledger DP, BUKAN ledger pembayaran/pelunasan: pelunasan tetap
 * memakai mekanisme pembayaran service yang sudah ada.
 *
 * Prinsip:
 *  - Hanya berlaku untuk service baru yang dibuat setelah fitur diterapkan.
 *  - Tidak menyentuh/memigrasi service lama maupun jurnal historis.
 *  - `service.dp_bayar` tetap diisi sebagai proyeksi (SUM DP aktif) agar semua
 *    pembaca lama (harus_dibayar, invoice) tidak perlu diubah.
 *  - Tanpa marker `dp_ledger_version`: pembeda cukup jalur intake baru yang
 *    memang selalu menulis ke ledger ini.
 *
 * Idempotent: lewati bila tabel sudah ada. Tanpa foreign key DB (konsisten
 * dengan pola `hutang_piutang`).
 */
class CreateServiceDpLedger extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('service_dp')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'service_idservice' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
            ],
            'kode_dp' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'tanggal' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'jumlah' => [
                'type'    => 'BIGINT',
                'default' => 0,
            ],
            'metode' => [
                'type'       => 'ENUM',
                'constraint' => ['tunai', 'transfer'],
                'default'    => 'tunai',
            ],
            'bank_idbank' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'referensi' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['aktif', 'dibatalkan'],
                'default'    => 'aktif',
            ],
            'keterangan' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'unit_id' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'input_by' => [
                'type'       => 'INTEGER',
                'constraint' => 11,
                'null'       => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('kode_dp', false, true, 'uniq_sdp_kode');
        $this->forge->addKey('service_idservice', false, false, 'idx_sdp_service');
        $this->forge->addKey(['service_idservice', 'status'], false, false, 'idx_sdp_service_status');
        $this->forge->addKey('unit_id', false, false, 'idx_sdp_unit');
        $this->forge->addKey('tanggal', false, false, 'idx_sdp_tanggal');
        $this->forge->createTable('service_dp', true);
    }

    public function down()
    {
        $this->forge->dropTable('service_dp', true);
    }
}
