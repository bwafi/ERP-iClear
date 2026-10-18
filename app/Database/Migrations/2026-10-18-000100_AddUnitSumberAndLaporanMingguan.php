<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Unit sumber + laporan mingguan + adopsi campaign Oktober.
 *
 *  - `unit_sumber`: penanda unit hasil tebakan nama ('auto') vs koreksi manual
 *    ('manual'). Sync Meta hanya menulis unit bila masih 'auto'.
 *  - `marketing_laporan_mingguan`: satu baris per (tahun, bulan, minggu).
 *  - Lima campaign manual Oktober dipetakan sekali ke ID campaign Meta-nya,
 *    supaya tarikan Meta menimpa baris manual (bukan menggandakan).
 */
class AddUnitSumberAndLaporanMingguan extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->fieldExists('unit_sumber', 'marketing_campaigns')) {
            $this->forge->addColumn('marketing_campaigns', [
                'unit_sumber' => [
                    'type'       => 'ENUM',
                    'constraint' => ['auto', 'manual'],
                    'default'    => 'auto',
                    'null'       => false,
                    'after'      => 'unit_id',
                ],
            ]);
        }

        if (!$db->tableExists('marketing_laporan_mingguan')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'period_year'  => ['type' => 'SMALLINT', 'constraint' => 4, 'null' => false],
                'period_month' => ['type' => 'TINYINT', 'constraint' => 2, 'null' => false],
                'minggu'       => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false],
                'submitted_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['period_year', 'period_month', 'minggu']);
            $this->forge->createTable('marketing_laporan_mingguan', true);
        }

        // Unit yang sudah diisi sebelum fitur ini dianggap koreksi manual,
        // supaya sync pertama tidak menimpanya.
        $db->table('marketing_campaigns')
            ->where('unit_id IS NOT NULL', null, false)
            ->update(['unit_sumber' => 'manual']);

        $maps = [
            'iClear Prob 76'       => '120251146211920189',
            'iClear Jember 64'     => '120251147591300189',
            'iClear Banyuwangi 60' => '120249387385360189',
            'iClear Pandaan 09'    => '120251197138500189',
            'iClear Genteng 04'    => '120251224385770189',
        ];
        foreach ($maps as $nama => $metaId) {
            $row = $db->table('marketing_campaigns')
                ->select('id')
                ->where('period_month', 10)
                ->where('period_year', 2026)
                ->where('meta_campaign_id', null)
                ->like('nama', $nama)
                ->orderBy('id', 'ASC')
                ->limit(1)
                ->get()->getRow();

            if ($row) {
                $db->table('marketing_campaigns')
                    ->where('id', (int)$row->id)
                    ->update(['meta_campaign_id' => $metaId, 'unit_sumber' => 'auto']);
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('marketing_laporan_mingguan')) {
            $this->forge->dropTable('marketing_laporan_mingguan', true);
        }
        if ($db->fieldExists('unit_sumber', 'marketing_campaigns')) {
            $this->forge->dropColumn('marketing_campaigns', 'unit_sumber');
        }
    }
}
