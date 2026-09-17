<?php

namespace App\Services\SocialMedia;

/**
 * BrightDataSocialMediaProvider — provider Bright Data via ASYNC TRIGGER.
 *
 * social:pull  → trigger('/datasets/v3/trigger') → snapshot_id → simpan job → selesai.
 * social:process (nanti) → progress/snapshot → normalize → posts + snapshots.
 *
 * Provider menentukan dataset, membangun input sesuai platform, memanggil
 * BrightDataClient (HTTP), normalisasi response trigger dan post rows.
 */
class BrightDataSocialMediaProvider implements SocialMediaProviderInterface
{
    private BrightDataClient $client;

    public function __construct(BrightDataClient $client)
    {
        $this->client = $client;
    }

    public function providerKey(): string
    {
        return 'bright_data';
    }

    /**
     * TRIGGER ASYNC satu akun.
     *
     * @param object $account baris social_media_accounts
     *
     * @return array{snapshot_id: string, dataset_id: string}
     */
    public function trigger(object $account): array
    {
        $platform = strtolower((string)($account->platform ?? ''));

        if (!in_array($platform, ['facebook', 'tiktok'], true)) {
            throw new SocialMediaUnsupportedPlatformException("Platform '{$platform}' belum didukung provider Bright Data.");
        }

        $profileUrl = trim((string)($account->profile_url ?? ''));
        if ($profileUrl === '') {
            throw new SocialMediaScraperException('profile_url akun kosong.');
        }

        $datasetId  = $this->client->datasetId($platform);
        $snapshotId = $this->client->trigger($platform, [$profileUrl]);

        return [
            'snapshot_id' => $snapshotId,
            'dataset_id'  => $datasetId,
        ];
    }

    /**
     * Ambil status progress snapshot dari Bright Data.
     *
     * @param string $snapshotId
     * @return string Status ternormalisasi: 'ready' | 'failed' | 'running' | 'starting'
     */
    public function fetchProgress(string $snapshotId): string
    {
        $res = $this->client->getProgress($snapshotId);
        $status = strtolower((string)($res['status'] ?? ''));

        if ($status === 'ready') {
            return 'ready';
        }
        if (in_array($status, ['failed', 'error', 'canceled'], true)) {
            return 'failed';
        }
        if (in_array($status, ['starting', 'queued', 'pending'], true)) {
            return 'starting';
        }

        return 'running';
    }

    /**
     * Download dan normalisasikan data snapshot Bright Data yang sudah ready.
     *
     * @param string $platform 'facebook'|'tiktok'
     * @param string $snapshotId
     * @return array<int,array<string,mixed>> Normalized posts
     */
    public function fetchAndNormalizeSnapshot(string $platform, string $snapshotId): array
    {
        $rows = $this->client->getSnapshot($snapshotId);

        $platform = strtolower($platform);
        if ($platform === 'facebook') {
            return $this->normalizeFacebook($rows);
        }
        if ($platform === 'tiktok') {
            return $this->normalizeTikTok($rows);
        }

        throw new SocialMediaUnsupportedPlatformException("Platform '{$platform}' belum didukung untuk normalisasi.");
    }

    /**
     * Normalisasi response Facebook (public, dapat di-test).
     *
     * Mapping:
     *   post_id          → external_post_id
     *   url              → post_url
     *   content          → caption
     *   date_posted      → published_at
     *   post_type        → post_type
     *   video_view_count → views
     *   play_count       → plays
     *   likes            → likes
     *   num_comments     → comments
     *   num_shares       → shares
     *
     * catatan: video_view_count ≠ play_count, DIPISAH (tidak dijumlah).
     * Post tanpa id tetap dipakai selama punya url (identity fallback hash url)
     * — akun tanpa external_account_id (mis. halaman custom) tidak gagal.
     *
     * @param array $rows baris mentah Bright Data (termasuk elemen berisi 'error')
     */
    public function normalizeFacebook(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            // Baris error/include_errors → lempar agar service bisa menandai akun gagal,
            // tapi akun lain tetap lanjut.
            if (!empty($raw['error']) || !empty($raw['error_code'])) {
                throw new BrightDataApiException('Bright Data mengembalikan error untuk akun: ' . json_encode($raw['error'] ?? $raw['error_code']));
            }

            $postUrl = $this->cleanString($raw['url'] ?? null);
            $postId  = trim((string)($raw['post_id'] ?? $raw['id'] ?? ''));

            if ($postId === '' && $postUrl === null) {
                continue; // tidak ada id maupun url → tidak bisa di-identifikasi
            }
            if ($postId === '') {
                $postId = $this->fallbackPostId('fb', $postUrl);
            }

            $normalized[] = [
                'external_post_id' => $postId,
                'post_url'         => $postUrl,
                'post_type'        => $this->cleanString($raw['post_type'] ?? null),
                'caption'          => $this->cleanString($raw['content'] ?? null),
                'published_at'     => $this->toDateTime($raw['date_posted'] ?? null),
                'views'            => $this->toIntOrNull($raw['video_view_count'] ?? null),
                'plays'            => $this->toIntOrNull($raw['play_count'] ?? null),
                'likes'            => $this->toIntOrNull($raw['likes'] ?? null),
                'comments'         => $this->toIntOrNull($raw['num_comments'] ?? null),
                'shares'           => $this->toIntOrNull($raw['num_shares'] ?? null),
                'saves'            => null,
            ];
        }

        return $normalized;
    }

    /**
     * Normalisasi response TikTok.
     *
     * Mapping:
     *   post_id       → external_post_id
     *   url           → post_url
     *   description   → caption
     *   create_time   → published_at
     *   post_type     → post_type
     *   play_count    → views
     *   digg_count    → likes
     *   comment_count → comments
     *   share_count   → shares
     *   collect_count → saves
     *
     * @param array $rows baris mentah Bright Data
     */
    public function normalizeTikTok(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            if (!empty($raw['error']) || !empty($raw['error_code'])) {
                throw new BrightDataApiException('Bright Data mengembalikan error untuk akun: ' . json_encode($raw['error'] ?? $raw['error_code']));
            }

            $postUrl = $this->cleanString($raw['url'] ?? $raw['link'] ?? null);
            $postId  = trim((string)($raw['post_id'] ?? $raw['id'] ?? ''));

            if ($postId === '' && $postUrl === null) {
                continue;
            }
            if ($postId === '') {
                $postId = $this->fallbackPostId('tt', $postUrl);
            }

            $normalized[] = [
                'external_post_id' => $postId,
                'post_url'         => $postUrl,
                'post_type'        => $this->cleanString($raw['post_type'] ?? null),
                'caption'          => $this->cleanString($raw['description'] ?? $raw['desc'] ?? null),
                'published_at'     => $this->toDateTime($raw['create_time'] ?? null),
                'views'            => $this->toIntOrNull($raw['play_count'] ?? null),
                'plays'            => null,
                'likes'            => $this->toIntOrNull($raw['digg_count'] ?? null),
                'comments'         => $this->toIntOrNull($raw['comment_count'] ?? null),
                'shares'           => $this->toIntOrNull($raw['share_count'] ?? null),
                'saves'            => $this->toIntOrNull($raw['collect_count'] ?? null),
            ];
        }

        return $normalized;
    }

    /**
     * Identity stabil untuk post yang TIDAK punya id Bright Data.
     * Deterministik dari URL post → upsert tetap idempoten antar run.
     */
    private function fallbackPostId(string $prefix, ?string $url): string
    {
        return $prefix . '-url-' . md5((string)$url);
    }

    private function cleanString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string)$value);
        return $s === '' ? null : $s;
    }

    private function toIntOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }

    private function toDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $t = is_numeric($value) ? (int)$value : strtotime((string)$value);
        return $t > 0 ? date('Y-m-d H:i:s', $t) : null;
    }
}