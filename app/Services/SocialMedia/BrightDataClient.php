<?php

namespace App\Services\SocialMedia;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * BrightDataClient — hanya menangani HTTP/API (tidak berisi logic scraping/bisnis).
 *
 * Konfigurasi teknis dari .env:
 *   BRIGHT_DATA_TOKEN=...
 *   BRIGHT_DATA_FACEBOOK_DATASET_ID=...
 *   BRIGHT_DATA_TIKTOK_DATASET_ID=...
 *
 * Endpoint (ASYNC TIGGER):
 *   POST /datasets/v3/trigger?dataset_id=...&notify=false&include_errors=true
 *   → response { "snapshot_id": "sd_..." } langsung, TANPA menunggu ready.
 *
 * PENTING:
 *   - Tidak ada sleep/polling /progress /snapshot di class ini.
 *   - snapshot_id selalu dinamis (sd_...), tidak pernah di-hardcode.
 *   - Tidak ada URL akun / jumlah akun / kode dataset yang di-hardcode.
 *   - API token TIDAK pernah muncul di pesan exception / log.
 */
class BrightDataClient
{
    public const ENDPOINT_TRIGGER  = 'https://api.brightdata.com/datasets/v3/trigger';
    public const ENDPOINT_PROGRESS = 'https://api.brightdata.com/datasets/v3/progress/%s';
    public const ENDPOINT_SNAPSHOT = 'https://api.brightdata.com/datasets/v3/snapshot/%s?format=json';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 30, 'connect_timeout' => 10]);
    }

    public function datasetId(string $platform): string
    {
        $key = ($platform === 'tiktok')
            ? 'BRIGHT_DATA_TIKTOK_DATASET_ID'
            : 'BRIGHT_DATA_FACEBOOK_DATASET_ID';

        $id = (string)env($key);
        if ($id === '') {
            throw new SocialMediaScraperException("Dataset Bright Data untuk platform '{$platform}' belum dikonfigurasi di .env.");
        }

        return $id;
    }

    /**
     * TRIGGER ASYNC: mulai collection Bright Data tanpa menunggu selesai.
     *
     * Satu panggilan per akun (SUTU input). Response langsung memuat
     * snapshot_id — command social:pull hanya menyimpan & selesai.
     *
     * @param string   $platform    facebook | tiktok
     * @param string[] $profileUrls daftar URL akun (umumnya 1)
     *
     * @return string snapshot_id (dynamic, sd_...)
     *
     * @throws SocialMediaScraperException        token/dataset tidak dikonfigurasi
     * @throws SocialMediaTransientException      timeout/koneksi/429/5xx
     * @throws BrightDataApiException             4xx lain/invalid JSON/snapshot_id hilang
     */
    public function trigger(string $platform, array $profileUrls): string
    {
        if ($profileUrls === []) {
            throw new BrightDataApiException('Tidak ada URL akun untuk trigger Bright Data.');
        }

        $dataset   = $this->datasetId($platform);
        $startDate = (string)env('BRIGHT_DATA_START_DATE', date('m-01-Y'));
        $endDate   = (string)env('BRIGHT_DATA_END_DATE', date('m-t-Y'));
        $maxPosts  = max(1, (int)env('BRIGHT_DATA_MAX_POSTS', 30));

        $input = [];
        foreach ($profileUrls as $url) {
            $entry = ['url' => (string)$url];

            // TikTok: format terbukti (url + tanggal). Facebook: + num_of_posts.
            if ($platform === 'facebook') {
                $entry['num_of_posts'] = $maxPosts;
            }
            if ($startDate !== '') {
                $entry['start_date'] = $startDate;
            }
            if ($endDate !== '') {
                $entry['end_date'] = $endDate;
            }
            $input[] = $entry;
        }

        $url = self::ENDPOINT_TRIGGER
            . '?dataset_id=' . rawurlencode($dataset)
            . '&notify=false'
            . '&include_errors=true';

        $response  = $this->requestJson('POST', $url, [
            'input'           => $input,
            'limit_per_input' => null,
        ], []);
        $snapshotId = trim((string)($response['snapshot_id'] ?? $response['snapshotId'] ?? ''));

        if ($snapshotId === '') {
            throw new BrightDataApiException('Response trigger Bright Data tidak memuat snapshot_id.');
        }

        return $snapshotId;
    }

    /**
     * Cek status progress snapshot di Bright Data (GET /datasets/v3/progress/{snapshot_id}).
     *
     * @return array{status: string, ...}
     */
    public function getProgress(string $snapshotId): array
    {
        $snapshotId = trim($snapshotId);
        if ($snapshotId === '') {
            throw new BrightDataApiException('Snapshot ID kosong.');
        }

        $url = sprintf(self::ENDPOINT_PROGRESS, rawurlencode($snapshotId));
        return $this->requestJson('GET', $url, null, []);
    }

    /**
     * Download data snapshot JSON yang sudah ready (GET /datasets/v3/snapshot/{snapshot_id}?format=json).
     *
     * @return array<int,array<string,mixed>> raw rows dari Bright Data
     */
    public function getSnapshot(string $snapshotId): array
    {
        $snapshotId = trim($snapshotId);
        if ($snapshotId === '') {
            throw new BrightDataApiException('Snapshot ID kosong.');
        }

        $url = sprintf(self::ENDPOINT_SNAPSHOT, rawurlencode($snapshotId));
        $res = $this->requestJson('GET', $url, null, []);

        if (isset($res['data']) && is_array($res['data'])) {
            return $res['data'];
        }

        return $res;
    }

    /**
     * Request JSON kembali array assoc.
     *
     * @param array|null $json body; null → tanpa body
     */
    private function requestJson(string $method, string $url, ?array $json, array $headers): array
    {
        $token = (string)env('BRIGHT_DATA_TOKEN');
        if ($token === '') {
            throw new SocialMediaScraperException('BRIGHT_DATA_TOKEN belum dikonfigurasi di .env.');
        }

        $options = [
            'headers' => array_merge([
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
            ], $headers),
            'http_errors' => false,
        ];
        if ($json !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['json'] = $json;
        }

        try {
            $response = $this->http->request($method, $url, $options);
        } catch (ConnectException $e) {
            throw new SocialMediaTransientException('Timeout/koneksi gagal ke Bright Data.', 0, $e);
        } catch (GuzzleException $e) {
            throw new BrightDataApiException('HTTP request ke Bright Data gagal.', 0, $e);
        }

        $status = (int)$response->getStatusCode();

        if ($status >= 500) {
            throw new SocialMediaTransientException("Bright Data HTTP {$status} (server error).");
        }
        if ($status === 429) {
            throw new SocialMediaTransientException('Bright Data rate limit (HTTP 429).');
        }
        if ($status === 401) {
            throw new BrightDataApiException('Autentikasi Bright Data gagal (HTTP 401).');
        }
        if ($status === 404) {
            throw new BrightDataApiException('Dataset Bright Data tidak ditemukan (HTTP 404).');
        }
        if ($status < 200 || $status >= 300) {
            throw new BrightDataApiException("Bright Data HTTP {$status}.");
        }

        $raw = (string)$response->getBody();
        if (trim($raw) === '') {
            throw new BrightDataApiException('Response kosong dari Bright Data.');
        }

        if (strpos($raw, "\xEF\xBB\xBF") === 0) {
            $raw = substr($raw, 3); // hilangkan BOM bila ada
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BrightDataApiException('Invalid JSON dari Bright Data.');
        }

        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }
}