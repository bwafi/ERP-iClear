<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel pendukung integrasi Meta Ads + penanda asal baris Performa Ads.
 *
 *  - marketing_ads_performance.sumber     : 'manual' (diketik) | 'meta' (hasil tarikan);
 *  - marketing_ads_performance.ditarik_pada: kapan angka Meta diambil.
 *  - meta_label_objektif : padanan objective+optimization_goal+destination_type → label
 *                          yang tampil di kolom "Objektif" laporan.
 *  - meta_pull_log       : catatan tiap tarikan, termasuk nilai rate limit.
 */
class CreateMetaAdsTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->fieldExists('sumber', 'marketing_ads_performance')) {
            $this->forge->addColumn('marketing_ads_performance', [
                'sumber' => [
                    'type'       => 'ENUM',
                    'constraint' => ['manual', 'meta'],
                    'null'       => false,
                    'default'    => 'manual',
                ],
                'ditarik_pada' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
        }

        if (!$db->tableExists('meta_label_objektif')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'objective' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'optimization_goal' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'destination_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'label' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['objective', 'optimization_goal', 'destination_type']);
            $this->forge->createTable('meta_label_objektif', true);
        }

        if (!$db->tableExists('meta_pull_log')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'tanggal_mulai'  => ['type' => 'DATE', 'null' => true],
                'tanggal_sampai' => ['type' => 'DATE', 'null' => true],
                'jumlah_kampanye' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'jumlah_baris'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'baru'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'diperbarui'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'dilewati'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'call_count'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'total_time'     => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'total_cputime'  => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'app_id_util_pct' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'acc_id_util_pct' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'estimated_time_to_regain_access' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'tier' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['ok', 'gagal'],
                    'null'       => false,
                    'default'    => 'ok',
                ],
                'pesan'      => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('meta_pull_log', true);
        }
    }

    public function down()
    {
        // Non-destruktif: tabel & kolom dipertahankan agar data tidak hilang.
    }
}
