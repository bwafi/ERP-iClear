<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * KPI Digital Marketing / Kepala Divisi (jabatan 43) — 7 komponen:
 *
 *   Lead 15% | Customer 15% | Conversion 15% | CPL 10% | Omzet Marketing 20%
 *   ROI/ROAS 15% | Pertumbuhan Channel 10% (reuse komponen CHANNEL_GROWTH)
 *
 * - marketing_source  : master channel/source lead (configurable).
 * - marketing_lead    : lead marketing (NEW/FOLLOW_UP/WON/LOST), link customer_id saat WON.
 * - marketing_ads_cost: biaya iklan per periode + channel/campaign.
 * - Bobot pos 43: ganti legacy (BUDGETING 15 / OMSET_TOKO 70 / ROAS 15) seperti
 *   pola RemoveLegacyKpiWeightsForMultimedia — komponen lama tidak dihapus dari
 *   master, hanya relasi bobot posisi 43 yang diganti.
 */
class AddMarketingLeadCrmKpi extends Migration
{
    protected const COMPONENTS = [
        ['code' => 'LEAD_MARKETING',     'name' => 'Lead',             'weight' => 15],
        ['code' => 'CUSTOMER_MARKETING', 'name' => 'Customer',         'weight' => 15],
        ['code' => 'CONVERSION_MARKETING','name' => 'Conversion',      'weight' => 15],
        ['code' => 'CPL',                'name' => 'Cost Per Lead',    'weight' => 10],
        ['code' => 'OMZET_MARKETING',    'name' => 'Omzet Marketing',  'weight' => 20],
        ['code' => 'ROAS_MARKETING',     'name' => 'ROI/ROAS',         'weight' => 15],
    ];

    // CHANNEL_GROWTH reuse komponen existing (Pertumbuhan Channel) bobot 10.
    protected const LEGACY_POS43 = ['BUDGETING', 'OMSET_TOKO', 'ROAS'];

    protected const MENUS = [
        ['idmenu' => 10044, 'urutan' => 113, 'nama_menu' => 'Marketing KPI',  'roles' => 'marketing_kpi', 'url' => 'marketing'],
        ['idmenu' => 10045, 'urutan' => 114, 'nama_menu' => 'Lead Marketing',  'roles' => 'marketing_lead', 'url' => 'marketing/leads'],
        ['idmenu' => 10046, 'urutan' => 115, 'nama_menu' => 'Biaya Iklan',     'roles' => 'marketing_ads',  'url' => 'marketing/ads'],
    ];

    protected const TARGET_JABATANS = [0, 1, 2, 34, 43, 44, 48];

    public function up()
    {
        $this->createMarketingSchema();

        $now = date('Y-m-d H:i:s');
        $db = $this->db;

        // Komponen KPI baru (6).
        $componentIds = [];
        foreach (self::COMPONENTS as $c) {
            $exists = $db->table('kpi_components')->where('code', $c['code'])->get()->getRow();
            if ($exists) {
                $componentIds[$c['code']] = (int)$exists->id;
                continue;
            }
            $db->table('kpi_components')->insert([
                'code'                 => $c['code'],
                'name'                 => $c['name'],
                'description'          => 'KPI Digital Marketing / Kepala Divisi — dihitung MarketingKpiService dari data operasional (lead, ads, transaksi).',
                'type'                 => 'automatic',
                'category'             => 'marketing',
                'unit_of_measure'      => 'percent',
                'calculation_strategy' => null,
                'is_active'            => 1,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
            $componentIds[$c['code']] = (int)$db->insertID();
        }

        // Bobot pos 43: hapus legacy kpi-group yang bukan komponen baru.
        $legacyIds = [];
        foreach (self::LEGACY_POS43 as $code) {
            $comp = $db->table('kpi_components')->where('code', $code)->get()->getRow();
            if (!$comp) {
                continue;
            }
            $legacyIds[] = (int)$comp->id;
        }
        if (!empty($legacyIds)) {
            $db->table('kpi_weights')
                ->where('position_id', 43)
                ->whereIn('kpi_component_id', $legacyIds)
                ->delete();
        }

        // Set 6 komponen baru + Pertumbuhan Channel (CHANNEL_GROWTH) untuk pos 43.
        $grow = $db->table('kpi_components')->where('code', 'CHANNEL_GROWTH')->get()->getRow();
        if (!$grow) {
            throw new \RuntimeException('Komponen CHANNEL_GROWTH harus ada (jalankan migrasi AddChannelGrowthKpi terlebih dahulu).');
        }
        $weights = self::COMPONENTS;
        $weights[] = ['code' => 'CHANNEL_GROWTH', 'name' => 'Pertumbuhan Channel', 'weight' => 10];

        foreach ($weights as $w) {
            $cid = $w['code'] === 'CHANNEL_GROWTH' ? (int)$grow->id : $componentIds[$w['code']];
            $db->table('kpi_weights')
                ->where('position_id', 43)
                ->where('kpi_component_id', $cid)
                ->where('weight_group', 'kpi')
                ->delete();
            $db->table('kpi_weights')->insert([
                'kpi_component_id' => $cid,
                'position_id'      => 43,
                'weight'           => $w['weight'],
                'weight_group'     => 'kpi',
                'effective_from'   => '2026-09-01',
                'effective_to'     => null,
                'created_by'       => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        $this->ensureMenus();
    }

    private function createMarketingSchema()
    {
        $db = $this->db;

        if (!$db->tableExists('marketing_source')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'code'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
                'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('marketing_source', true);
        }

        if (!$db->tableExists('marketing_lead')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'tanggal'      => ['type' => 'DATE', 'null' => false],
                'nama'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
                'no_hp'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'source_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'ads_organic'  => ['type' => "ENUM('ADS','ORGANIC')", 'default' => 'ORGANIC', 'null' => false],
                'cs'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'status'       => ["type" => "ENUM('NEW','FOLLOW_UP','WON','LOST')", 'default' => 'NEW', 'null' => false],
                'customer_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'tanggal_won'  => ['type' => 'DATE', 'null' => true],
                'created_by'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('tanggal');
            $this->forge->addKey('status');
            $this->forge->createTable('marketing_lead', true);
        }

        if (!$db->tableExists('marketing_ads_cost')) {
            $this->forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'period_month' => ['type' => 'TINYINT', 'constraint' => 2, 'null' => false],
                'period_year'  => ['type' => 'SMALLINT', 'null' => false],
                'channel_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
                'campaign'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'amount'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => false, 'default' => 0],
                'note'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_by'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['period_year', 'period_month']);
            $this->forge->createTable('marketing_ads_cost', true);
        }

        // Seed master source (idempotent).
        if ((int)$db->table('marketing_source')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            $sources = ['INSTAGRAM', 'FACEBOOK', 'TIKTOK', 'YOUTUBE', 'GOOGLE_ADS', 'WHATSAPP', 'REFERRAL', 'ORGANIC', 'LAINNYA'];
            foreach ($sources as $code) {
                $db->table('marketing_source')->insert([
                    'code' => $code,
                    'name' => str_replace('_', ' ', ucwords(strtolower($code))),
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function ensureMenus()
    {
        $db = $this->db;
        foreach (self::MENUS as $def) {
            $exists = (int)$db->table('menu')->where('idmenu', $def['idmenu'])->countAllResults();
            if ($exists === 0) {
                $def['show_menu'] = 1;
                $def['sub'] = 0;
                $def['parent'] = 10040;
                $def['utama'] = 1;
                $def['categories'] = 0;
                $def['icon'] = null;
                $db->table('menu')->insert($def);
            }
        }

        $menuIds = array_column(self::MENUS, 'idmenu');
        foreach (self::TARGET_JABATANS as $jabatanId) {
            $row = $db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (!$row) {
                continue;
            }
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                $roles = [];
            }
            $merged = array_values(array_unique(array_merge($roles, $menuIds)));
            if ($merged !== $roles) {
                $db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }
    }

    public function down()
    {
        $db = $this->db;
        $now = date('Y-m-d H:i:s');

        // Kembalikan bobot legacy pos 43.
        foreach (['BUDGETING' => 15, 'OMSET_TOKO' => 70, 'ROAS' => 15] as $code => $w) {
            $comp = $db->table('kpi_components')->where('code', $code)->get()->getRow();
            if (!$comp) {
                continue;
            }
            $exists = $db->table('kpi_weights')
                ->where('position_id', 43)
                ->where('kpi_component_id', $comp->id)
                ->where('weight_group', 'kpi')
                ->get()->getRow();
            if (!$exists) {
                $db->table('kpi_weights')->insert([
                    'kpi_component_id' => (int)$comp->id,
                    'position_id'      => 43,
                    'weight'           => $w,
                    'weight_group'     => 'kpi',
                    'effective_from'   => '2024-01-01',
                    'effective_to'     => null,
                    'created_by'       => null,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }

        $grow = $db->table('kpi_components')->where('code', 'CHANNEL_GROWTH')->get()->getRow();
        if ($grow) {
            $db->table('kpi_weights')
                ->where('position_id', 43)
                ->where('kpi_component_id', $grow->id)
                ->delete();
        }

        foreach (self::COMPONENTS as $c) {
            $comp = $db->table('kpi_components')->where('code', $c['code'])->get()->getRow();
            if (!$comp) {
                continue;
            }
            $db->table('kpi_weights')->where('kpi_component_id', $comp->id)->delete();
            $db->table('kpi_components')->where('id', $comp->id)->delete();
        }

        foreach (self::MENUS as $def) {
            $db->table('menu')->where('idmenu', $def['idmenu'])->delete();
        }
        foreach (self::TARGET_JABATANS as $jabatanId) {
            $row = $db->table('jabatan')->where('ID_JABATAN', $jabatanId)->get()->getRow();
            if (!$row) {
                continue;
            }
            $roles = json_decode($row->ROLES_JABATAN ?? '', true);
            if (!is_array($roles)) {
                continue;
            }
            $menuIds = array_map('strval', array_column(self::MENUS, 'idmenu'));
            $merged = array_values(array_filter($roles, fn($r) => !in_array((string)$r, $menuIds, true)));
            if ($merged !== $roles) {
                $db->table('jabatan')
                    ->where('ID_JABATAN', $jabatanId)
                    ->update(['ROLES_JABATAN' => json_encode($merged)]);
            }
        }

        $db->table('marketing_ads_cost')->delete();
        $db->table('marketing_lead')->delete();
        $db->table('marketing_source')->delete();
    }
}