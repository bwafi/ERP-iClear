<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * KPI Pertumbuhan Channel (bobot 10%) — Digital Marketing.
 *
 * - master channel + channel_metric (metric per channel, is_kpi = dihitung KPI,
 *   target_growth default per metric).
 * - channel_performance: input actual bulanan per channel+metric (unique).
 * - komponen KPI baru CHANNEL_GROWTH (10%) + bobot ulang 5 komponen konten agar
 *   total tetap 100%: Jumlah 15, Deadline 15, Kualitas 25, Brand 15, Performa 20.
 * - menu sidebar 10043 "Performa Channel".
 */
class AddChannelGrowthKpi extends Migration
{
    private const WEIGHT_NEW = [
        'KONTEN_JUMLAH'   => 15,
        'KONTEN_DEADLINE' => 15,
        'KONTEN_KUALITAS' => 25,
        'KONTEN_BRAND'    => 15,
        'KONTEN_PERFORMA' => 20,
        'CHANNEL_GROWTH'  => 10,
    ];

    public function up()
    {
        $this->createChannelsSchema();

        // ── Komponen KPI CHANNEL_GROWTH ─────────────────────────────
        $now = date('Y-m-d H:i:s');
        $grow = $this->db->table('kpi_components')
            ->where('code', 'CHANNEL_GROWTH')
            ->get()->getRow();

        if ($grow) {
            $componentId = (int)$grow->id;
        } else {
            $this->db->table('kpi_components')->insert([
                'code'                 => 'CHANNEL_GROWTH',
                'name'                 => 'Pertumbuhan Channel',
                'description'          => 'Pertumbuhan performa channel social media (Digital Marketing) — dihitung ContentKpiService dari data channel_performance.',
                'type'                 => 'automatic',
                'category'             => 'marketing',
                'unit_of_measure'      => 'percent',
                'calculation_strategy' => null,
                'is_active'            => 1,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            $componentId = (int)$this->db->insertID();
        }

        $this->db->table('kpi_weights')
            ->where('position_id', 44)
            ->where('kpi_component_id', $componentId)
            ->delete();
        $this->db->table('kpi_weights')->insert([
            'kpi_component_id' => $componentId,
            'position_id'      => 44,
            'weight'           => self::WEIGHT_NEW['CHANNEL_GROWTH'],
            'weight_group'     => 'kpi',
            'effective_from'   => '2026-09-01',
            'effective_to'     => null,
            'created_by'       => null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        // ── Bobot ulang 5 komponen konten (total 90) ────────────────
        foreach (['KONTEN_JUMLAH', 'KONTEN_DEADLINE', 'KONTEN_KUALITAS', 'KONTEN_BRAND', 'KONTEN_PERFORMA'] as $code) {
            $comp = $this->db->table('kpi_components')->where('code', $code)->get()->getRow();
            if (!$comp) {
                continue;
            }
            $this->db->table('kpi_weights')
                ->where('position_id', 44)
                ->where('kpi_component_id', $comp->id)
                ->update(['weight' => self::WEIGHT_NEW[$code]]);
        }

        // ── Menu 10043 Performa Channel ─────────────────────────────
        $this->ensureChannelMenu();
    }

    private function createChannelsSchema()
    {
        // channel
        if (!$this->db->tableExists('channel')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'code'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('channel', true);
        }

        // channel_metric
        if (!$this->db->tableExists('channel_metric')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'channel_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'code'          => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false],
                'name'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
                'is_kpi'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'target_growth' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
                'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('channel_id');
            $this->forge->addKey(['channel_id', 'code'], false, false, 'uq_channel_metric');
            $this->forge->createTable('channel_metric', true);
        }

        // channel_performance
        if (!$this->db->tableExists('channel_performance')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'channel_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'metric_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'period_month'  => ['type' => 'TINYINT', 'constraint' => 2, 'null' => false],
                'period_year'   => ['type' => 'SMALLINT', 'null' => false],
                'actual'        => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => 0],
                'target_growth' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => true],
                'note'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_by'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['channel_id', 'metric_id', 'period_month', 'period_year'], false, true, 'uq_channel_perf');
            $this->forge->addKey(['period_year', 'period_month']);
            $this->forge->createTable('channel_performance', true);
        }

        $this->seedChannels();
    }

    private function seedChannels()
    {
        $db = $this->db;
        $count = (int)$db->table('channel')->countAllResults();
        if ($count > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $channels = [
            ['code' => 'IG',   'name' => 'Instagram', 'metrics' => [
                ['code' => 'IG_FOLLOWERS',   'name' => 'Followers',   'is_kpi' => 1, 'target_growth' => 8],
                ['code' => 'IG_REACH',       'name' => 'Reach',       'is_kpi' => 1, 'target_growth' => 8],
                ['code' => 'IG_ENGAGEMENT',  'name' => 'Engagement',  'is_kpi' => 0, 'target_growth' => null],
            ]],
            ['code' => 'TIKTOK', 'name' => 'TikTok', 'metrics' => [
                ['code' => 'TT_FOLLOWERS',   'name' => 'Followers',   'is_kpi' => 1, 'target_growth' => 10],
                ['code' => 'TT_LIKES',       'name' => 'Likes',       'is_kpi' => 0, 'target_growth' => null],
                ['code' => 'TT_VIEWS',       'name' => 'Views',       'is_kpi' => 0, 'target_growth' => null],
            ]],
            ['code' => 'FB',   'name' => 'Facebook', 'metrics' => [
                ['code' => 'FB_FOLLOWERS',   'name' => 'Followers',   'is_kpi' => 1, 'target_growth' => 8],
                ['code' => 'FB_REACH',       'name' => 'Reach',       'is_kpi' => 0, 'target_growth' => null],
                ['code' => 'FB_ENGAGEMENT',  'name' => 'Engagement',  'is_kpi' => 0, 'target_growth' => null],
            ]],
            ['code' => 'YT',   'name' => 'YouTube', 'metrics' => [
                ['code' => 'YT_SUBSCRIBERS', 'name' => 'Subscribers', 'is_kpi' => 1, 'target_growth' => 10],
                ['code' => 'YT_VIEWS',       'name' => 'Views',       'is_kpi' => 0, 'target_growth' => null],
            ]],
        ];

        foreach ($channels as $ch) {
            $db->table('channel')->insert([
                'code'       => $ch['code'],
                'name'       => $ch['name'],
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $channelId = (int)$db->insertID();
            foreach ($ch['metrics'] as $m) {
                $db->table('channel_metric')->insert([
                    'channel_id'    => $channelId,
                    'code'          => $m['code'],
                    'name'          => $m['name'],
                    'is_kpi'        => $m['is_kpi'],
                    'target_growth' => $m['target_growth'],
                    'is_active'     => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    private function ensureChannelMenu()
    {
        $db = $this->db;
        $exists = (int)$db->table('menu')->where('idmenu', 10043)->countAllResults();
        if ($exists === 0) {
            $db->table('menu')->insert([
                'idmenu'     => 10043,
                'urutan'     => 112,
                'nama_menu'  => 'Performa Channel',
                'roles'      => 'konten_channel',
                'url'        => 'konten/channel',
                'show_menu'  => 1,
                'sub'        => 0,
                'parent'     => 10040,
                'utama'      => 1,
                'categories' => 0,
                'icon'       => null,
            ]);
        }

        foreach ([0, 1, 2, 34, 43, 44, 48] as $jabatanId) {
            $row = $db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (!$row) {
                continue;
            }
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }
            $roles = array_values(array_unique(array_merge($roles, [10043])));
            $db->table('jabatan')
                ->where('ID_JABATAN', $jabatanId)
                ->update(['ROLES_JABATAN' => json_encode($roles)]);
        }
    }

    public function down()
    {
        $db = $this->db;

        $grow = $db->table('kpi_components')->where('code', 'CHANNEL_GROWTH')->get()->getRow();
        if ($grow) {
            $db->table('kpi_weights')->where('kpi_component_id', $grow->id)->delete();
            $db->table('kpi_components')->where('id', $grow->id)->delete();
        }

        // Kembalikan bobot konten ke 20/20/25/15/20.
        foreach (['KONTEN_JUMLAH' => 20, 'KONTEN_DEADLINE' => 20, 'KONTEN_KUALITAS' => 25, 'KONTEN_BRAND' => 15, 'KONTEN_PERFORMA' => 20] as $code => $w) {
            $comp = $db->table('kpi_components')->where('code', $code)->get()->getRow();
            if (!$comp) {
                continue;
            }
            $db->table('kpi_weights')
                ->where('position_id', 44)
                ->where('kpi_component_id', $comp->id)
                ->update(['weight' => $w]);
        }

        foreach ([0, 1, 2, 34, 43, 44, 48] as $jabatanId) {
            $row = $db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (!$row) {
                continue;
            }
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
            }
            $merged = array_values(array_filter($roles, fn($r) => (int)$r !== 10043));
            if ($merged !== $roles) {
                $db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode(array_values($merged))]);
            }
        }

        $db->table('menu')->where('idmenu', 10043)->delete();
        $db->table('channel_performance')->delete();
        $db->table('channel_metric')->delete();
        $db->table('channel')->delete();
    }
}