<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder master Content Management (idempotent — aman dijalankan berulang).
 *
 * Mengisi master reference: platform, content_types, performance_metrics,
 * dan brand_checklist_items. Semua insert hanya bila code belum ada.
 */
class ContentManagementSeeder extends Seeder
{
    private function seedMaster(string $table, array $rows)
    {
        foreach ($rows as $row) {
            $exists = $this->db->table($table)->where('code', $row['code'])->countAllResults();
            if ($exists === 0) {
                $this->db->table($table)->insert([
                    'code'        => $row['code'],
                    'name'        => $row['name'],
                    'is_active'   => 1,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function run()
    {
        $this->seedMaster('platforms', [
            ['code' => 'INSTAGRAM', 'name' => 'Instagram'],
            ['code' => 'FACEBOOK',  'name' => 'Facebook'],
            ['code' => 'TIKTOK',    'name' => 'TikTok'],
            ['code' => 'YOUTUBE',   'name' => 'YouTube'],
            ['code' => 'WHATSAPP',  'name' => 'WhatsApp'],
            ['code' => 'WEBSITE',   'name' => 'Website'],
        ]);

        $this->seedMaster('content_types', [
            ['code' => 'FEED',      'name' => 'Feed Post'],
            ['code' => 'REELS',     'name' => 'Reels'],
            ['code' => 'VIDEO',     'name' => 'Video'],
            ['code' => 'STORY',     'name' => 'Story'],
            ['code' => 'CAROUSEL',  'name' => 'Carousel'],
            ['code' => 'POSTER',    'name' => 'Poster / Desain'],
            ['code' => 'TESTIMONI', 'name' => 'Testimoni'],
            ['code' => 'LAINNYA',   'name' => 'Lainnya'],
        ]);

        $this->seedMaster('performance_metrics', [
            ['code' => 'REACH',      'name' => 'Jangkauan (Reach)'],
            ['code' => 'IMPRESSIONS', 'name' => 'Tayangan (Impressions)'],
            ['code' => 'ENGAGEMENT', 'name' => 'Interaksi (Engagement)'],
            ['code' => 'SHARES',     'name' => 'Bagikan (Shares)'],
            ['code' => 'SAVES',      'name' => 'Simpan (Saves)'],
            ['code' => 'CLICKS',     'name' => 'Klik (Clicks)'],
            ['code' => 'VIEWS',      'name' => 'Putar (Views)'],
            ['code' => 'FOLLOWERS',  'name' => 'Pengikut Baru (Followers)'],
            ['code' => 'SALES',      'name' => 'Penjualan (Sales)'],
        ]);

        $this->seedMaster('brand_checklist_items', [
            ['code' => 'LOGO',   'name' => 'Logo'],
            ['code' => 'WARNA',  'name' => 'Warna'],
            ['code' => 'FONT',   'name' => 'Font'],
            ['code' => 'TONE',   'name' => 'Tone'],
            ['code' => 'LAYOUT', 'name' => 'Layout'],
            ['code' => 'CTA',    'name' => 'CTA'],
        ]);

        $this->seedJabatanTalent();
        $this->grantKontenMenusToTalent();
    }

    /**
     * Jabatan Talent (ID_JABATAN 48) — idempotent.
     */
    private function seedJabatanTalent(): void
    {
        $exists = $this->db->table('jabatan')->where('ID_JABATAN', 48)->countAllResults();
        if ($exists === 0) {
            $this->db->table('jabatan')->insert([
                'ID_JABATAN'   => 48,
                'NAMA_JABATAN' => 'Talent',
            ]);
        }
    }

    /**
     * Grant menu konten (10040-10042) ke jabatan Talent 48 — idempotent.
     */
    private function grantKontenMenusToTalent(): void
    {
        $menuIds = $this->db->table('menu')
            ->whereIn('idmenu', [10040, 10041, 10042])
            ->get()
            ->getResult();

        $roles = json_decode($this->db->table('jabatan')->where('ID_JABATAN', 48)->get()->getRow()->ROLES_JABATAN ?? '', true);
        if (!is_array($roles)) {
            $roles = [];
        }
        $ids = array_values(array_unique(array_merge($roles, array_column($menuIds, 'idmenu'))));

        $this->db->table('jabatan')->where('ID_JABATAN', 48)->update(['ROLES_JABATAN' => json_encode($ids)]);
    }
}