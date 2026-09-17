<?php

namespace App\Services\SocialMedia;

/**
 * Interface provider scraping social media.
 *
 * Abstraksi ini memungkinkan penambahan provider lain (mis. Instagram/Apify)
 * tanpa mengubah SocialMediaScraperService.
 */
interface SocialMediaProviderInterface
{
    /**
     * Nama/identitas provider (mis. 'bright_data').
     */
    public function providerKey(): string;

    /**
     * TRIGGER ASYNC untuk satu akun: mulai collection di provider
     * dan langsung KEMBALIKAN snapshot id (tanpa menunggu ready/download).
     *
     * @param object $account baris social_media_accounts (aktif)
     *
     * @return array{snapshot_id: string, dataset_id: string}
     */
    public function trigger(object $account): array;

    /**
     * Ambil status progress snapshot dari provider.
     *
     * @param string $snapshotId
     * @return string Status: 'starting'|'running'|'ready'|'failed'
     */
    public function fetchProgress(string $snapshotId): string;

    /**
     * Download dan normalisasikan data snapshot jika sudah ready.
     *
     * @param string $platform 'facebook'|'tiktok'
     * @param string $snapshotId
     * @return array<int,array<string,mixed>> Normalized posts
     */
    public function fetchAndNormalizeSnapshot(string $platform, string $snapshotId): array;
}