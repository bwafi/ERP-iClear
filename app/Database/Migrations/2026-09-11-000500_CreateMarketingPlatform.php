<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Master platform rekap marketing harian.
 *
 * Request operasional: platform TIDAK diketik manual — pakai dropdown dari
 * tabel master ini. Menambah platform = insert baris, tanpa ubah kode.
 * Nama platform yang sudah dipakai di rekap existing ikut di-backfill agar
 * detail lama tetap tampil di dropdown.
 */
class CreateMarketingPlatform extends Migration
{
    /** Platform default (berurutan). */
    protected const DEFAULTS = [
        'WhatsApp',
        'Instagram',
        'TikTok',
        'Facebook',
        'Telegram',
        'Website',
    ];

    public function up()
    {
        $db = $this->db;

        if (!$db->tableExists('marketing_platform')) {
            $this->forge->addField([
                'id'        => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'name'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
                'urutan'    => ['type' => 'INT', 'constraint' => 11, 'null' => false, 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('name', false, true, 'uniq_marketing_platform_name');
            $this->forge->createTable('marketing_platform', true);
        }

        $db->table('marketing_platform')->where('id', 0)->countAllResults(); // warmup
        $existing = $db->table('marketing_platform')->get()->getResultArray();

        $names = [];
        foreach ($existing as $row) {
            $names[strtolower(trim($row['name']))] = true;
        }

        $urutan = 0;
        foreach (self::DEFAULTS as $name) {
            $urutan++;
            if (isset($names[strtolower($name)])) {
                continue;
            }
            $db->table('marketing_platform')->insert([
                'name'      => $name,
                'urutan'    => $urutan,
                'is_active' => 1,
            ]);
            $names[strtolower($name)] = true;
        }

        // Backfill: platform yang pernah dipakai di rekap juga masuk master.
        if ($db->tableExists('marketing_rekap_harian_detail')) {
            $used = $db->query('SELECT DISTINCT TRIM(platform) p FROM marketing_rekap_harian_detail')->getResultArray();
            foreach ($used as $row) {
                $name = trim((string)$row['p']);
                if ($name === '' || isset($names[strtolower($name)])) {
                    continue;
                }
                $db->table('marketing_platform')->insert([
                    'name'      => mb_substr($name, 0, 100),
                    'urutan'    => ++$urutan,
                    'is_active' => 1,
                ]);
                $names[strtolower($name)] = true;
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('marketing_platform', true);
    }
}