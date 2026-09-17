<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Seed/upsert 6 Social Media Account (Facebook + TikTok) untuk KPI Social Media.
 *
 * Idempoten: akun diidentifikasi (unit_id + platform + profile_url).
 * Jika sudah ada → update field; jika belum ada → insert. Tidak duplicate.
 *
 * Unit harus sudah tersedia (EnsureUnitGenteng dijalankan sebelumnya).
 */
class SeedSocialMediaAccounts extends Migration
{
    /** @var array<int,array> */
    private array $accounts = [
        // ── Facebook ─────────────────────────────────────────────────
        [
            'platform'       => 'facebook',
            'account_name'   => 'iClear Center',
            'profile_url'    => 'https://www.facebook.com/IClear.Center/',
            'external_account_id' => null,
            'unit_key'       => 'Center',     // = ICLEAR Probolinggo (unit pusat)
            'provider'       => 'bright_data',
            'is_active'      => 1,
        ],
        [
            'platform'       => 'facebook',
            'account_name'   => 'iClear Jember',
            'profile_url'    => 'https://www.facebook.com/profile.php?id=61579394566805',
            'external_account_id' => '61579394566805',
            'unit_key'       => 'Jember',
            'provider'       => 'bright_data',
            'is_active'      => 1,
        ],
        [
            'platform'       => 'facebook',
            'account_name'   => 'iClear Banyuwangi',
            'profile_url'    => 'https://www.facebook.com/profile.php?id=61581753750368',
            'external_account_id' => '61581753750368',
            'unit_key'       => 'Banyuwangi',
            'provider'       => 'bright_data',
            'is_active'      => 1,
        ],
        [
            'platform'       => 'facebook',
            'account_name'   => 'iClear Pandaan',
            'profile_url'    => 'https://www.facebook.com/profile.php?id=61590338063714',
            'external_account_id' => '61590338063714',
            'unit_key'       => 'Pandaan',
            'provider'       => 'bright_data',
            'is_active'      => 1,
        ],
        [
            'platform'       => 'facebook',
            'account_name'   => 'iClear Genteng',
            'profile_url'    => 'https://www.facebook.com/profile.php?id=61593144644371',
            'external_account_id' => '61593144644371',
            'unit_key'       => 'Genteng',
            'provider'       => 'bright_data',
            'is_active'      => 1,
        ],
        // ── TikTok (pusat/global → Unit Center) ──────────────────────
        [
            'platform'       => 'tiktok',
            'account_name'   => 'iClear Service',
            'username'       => '@iclear.service',
            'profile_url'    => 'https://www.tiktok.com/@iclear.service',
            'external_account_id' => null,
            'unit_key'       => 'Center',
            'provider'       => 'bright_data',
            'is_active'      => 1,
        ],
    ];

    public function up()
    {
        foreach ($this->accounts as $acc) {
            $unit = $this->resolveUnit($acc['unit_key']);
            if (!$unit) {
                log_message('error', '[SeedSocialMediaAccounts] Unit tidak ditemukan untuk: ' . $acc['unit_key']);
                continue;
            }

            $existing = $this->db->table('social_media_accounts')
                ->where('unit_id', (int)$unit->idunit)
                ->where('platform', $acc['platform'])
                ->where('profile_url', $acc['profile_url'])
                ->get()
                ->getRow();

            $payload = [
                'unit_id'             => (int)$unit->idunit,
                'platform'            => $acc['platform'],
                'account_name'        => $acc['account_name'],
                'username'            => $acc['username'] ?? null,
                'profile_url'         => $acc['profile_url'],
                'external_account_id' => $acc['external_account_id'] ?? null,
                'provider'            => $acc['provider'],
                'is_active'           => $acc['is_active'],
                'updated_at'          => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                $this->db->table('social_media_accounts')
                    ->where('id', (int)$existing->id)
                    ->update($payload);
            } else {
                $payload['created_at'] = date('Y-m-d H:i:s');
                $this->db->table('social_media_accounts')->insert($payload);
            }
        }
    }

    public function down()
    {
        // Tidak menghapus akun saat rollback — biarkan data akun tetap ada;
        // penghapusan akun dilakukan via UI management.
    }

    private function resolveUnit(string $unitKey): ?object
    {
        $map = [
            'Center'     => 'ICLEAR Probolinggo',
            'Jember'     => 'ICLEAR Jember',
            'Banyuwangi' => 'ICLEAR Banyuwangi',
            'Pandaan'    => 'ICLEAR Pandaan',
            'Genteng'    => 'ICLEAR Genteng',
        ];

        $match = $map[$unitKey] ?? null;
        if ($match === null) {
            return null;
        }

        $row = $this->db->table('unit')
            ->groupStart()
            ->like('NAMA_UNIT', $match, 'both')
            ->groupEnd()
            ->get()
            ->getRow();

        return $row ?: null;
    }
}