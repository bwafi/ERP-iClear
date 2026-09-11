<?php

namespace App\Services\Kommo;

use App\Config\Kommo as KommoConfig;

/**
 * Klien Kommo API v4 (resmi, https://{subdomain}.kommo.com/api/v4/...).
 *
 * Memegang kredensial OAuth2 dari Config\Kommo (.env). Token TIDAK pernah
 * di-log. Pada 401, access token otomatis di-refresh pakai refresh_token
 * (satu percobaan), lalu request diulang sekali.
 *
 * Timeout, HTTP error, respon tidak valid (non-JSON) → KommoApiException.
 */
class KommoApiService implements KommoApiInterface
{
    private const API_PATH = '/api/v4';

    private KommoConfig $config;

    private string $accessToken;

    private string $refreshToken;

    /** Cache status pipeline: [pipeline_id => [status_id => [...]]] */
    private array $statusCache = [];

    /** Cache nama user: [user_id => ?string] */
    private array $userCache = [];

    /** Cache contact: [contact_id => array] (termasuk hasil null/[]). */
    private array $contactCache = [];

    /** Cache talks: [contact_id => list<array>] */
    private array $talksCache = [];

    public function __construct(?KommoConfig $config = null)
    {
        $this->config      = $config ?? new KommoConfig();
        $this->accessToken = $this->config->accessToken;
        $this->refreshToken = $this->config->refreshToken;
    }

    public function isConfigured(): bool
    {
        return $this->config->enabled
            && $this->config->subdomain !== ''
            && $this->accessToken !== '';
    }

    public function getLead(int $leadId): array
    {
        $data = $this->request('GET', '/leads/' . $leadId . '?with=contacts');
        if (!is_array($data)) {
            throw KommoApiException::invalidResponse('bentuk lead tidak dikenal');
        }

        return $data;
    }

    /**
     * Daftar lead (untuk debug/monitor awal). Bukan bagian dari alur webhook.
     *
     * @return list<array<string,mixed>>
     */
    public function getLeads(int $limit = 10): array
    {
        $limit = max(1, min(250, $limit));
        $data  = $this->request('GET', '/leads?limit=' . $limit);
        return is_array($data) && is_array($data['_embedded']['leads'] ?? null)
            ? array_values($data['_embedded']['leads'])
            : [];
    }

    /**
     * Satu halaman lead (dengan contact id ter-embed) untuk backfill.
     * API baru tidak menyediakan _total_items; hasMore dihitung dari
     * jumlah item = limit (halaman kosong = habis).
     *
     * @return array{items: list<array<string,mixed>>, total: int, hasMore: bool}
     */
    public function getLeadsPage(int $page = 1, int $limit = 100): array
    {
        $page  = max(1, $page);
        $limit = max(1, min(250, $limit));
        $data  = $this->request('GET', '/leads?limit=' . $limit . '&page=' . $page . '&with=contacts');

        $items = is_array($data) && is_array($data['_embedded']['leads'] ?? null)
            ? array_values($data['_embedded']['leads'])
            : [];

        return [
            'items'   => $items,
            'total'   => 0, // tidak tersedia di API baru — gunakan perkiraan
            'hasMore' => count($items) === $limit,
        ];
    }

    public function getContact(int $contactId): array
    {
        if (array_key_exists($contactId, $this->contactCache)) {
            return $this->contactCache[$contactId];
        }
        try {
            $data = $this->request('GET', '/contacts/' . $contactId);
            $result = is_array($data) ? $data : [];
        } catch (KommoApiException $e) {
            if ($e->getCode() === KommoApiException::NOT_FOUND) {
                $result = [];
            } else {
                // Contact non-kritis: gagal ambil detail → tetap lanjut pakai lead saja.
                log_message('warning', '[Kommo] getContact(' . $contactId . ') gagal: ' . $e->getCode());
                $result = [];
            }
        }
        return $this->contactCache[$contactId] = $result;
    }

    public function getUserName(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        if (array_key_exists($userId, $this->userCache)) {
            return $this->userCache[$userId];
        }
        try {
            $data = $this->request('GET', '/users/' . $userId);
            if (!is_array($data) || ($data['name'] ?? null) === null) {
                return $this->userCache[$userId] = null;
            }
            return $this->userCache[$userId] = (string)$data['name'];
        } catch (KommoApiException $e) {
            log_message('warning', '[Kommo] getUserName(' . $userId . ') gagal: ' . $e->getCode());
            return $this->userCache[$userId] = null;
        }
    }

    public function talkOriginForLead(array $contactIds, int $leadId): ?string
    {
        // Kumpulkan semua talks dari semua contact lead (cached per contact).
        $candidates = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $contactIds)))) as $cid) {
            if ($cid <= 0) {
                continue;
            }
            foreach ($this->talksForContact($cid) as $talk) {
                $candidates[] = $talk;
            }
        }
        if ($candidates === []) {
            return null;
        }

        // Prioritas 1: talk yang tertaut lead ini (entity_type=lead, entity_id=leadId).
        // Prioritas 2: talk tercatat paling awal (kontak pertama masuk).
        $match = null;
        $first = null;
        foreach ($candidates as $talk) {
            $created = (int)($talk['created_at'] ?? 0);
            if ($first === null || $created < (int)$first['created_at']) {
                $first = $talk;
            }
            if (($talk['entity_type'] ?? '') === 'lead' && (int)($talk['entity_id'] ?? 0) === $leadId) {
                if ($match === null || $created < (int)$match['created_at']) {
                    $match = $talk;
                }
            }
        }
        $originTalk = $match ?? $first;

        return ($originTalk['origin'] ?? null) !== null ? (string)$originTalk['origin'] : null;
    }

    /**
     * Talks milik satu contact (GET /api/v4/talks?filter[contact_id]={id}).
     *
     * @return list<array<string,mixed>>
     */
    private function talksForContact(int $contactId): array
    {
        if (array_key_exists($contactId, $this->talksCache)) {
            return $this->talksCache[$contactId];
        }
        try {
            $data = $this->request('GET', '/talks?filter[contact_id]=' . $contactId . '&limit=250&page=1');
            $result = is_array($data) && is_array($data['_embedded']['talks'] ?? null)
                ? array_values($data['_embedded']['talks'])
                : [];
        } catch (KommoApiException $e) {
            log_message('warning', '[Kommo] talks contact ' . $contactId . ' gagal: ' . $e->getCode());
            $result = [];
        }
        return $this->talksCache[$contactId] = $result;
    }

    public function statusInfo(int $pipelineId): array
    {
        if (isset($this->statusCache[$pipelineId])) {
            return $this->statusCache[$pipelineId];
        }

        $statuses = [];
        try {
            $data = $this->request('GET', '/leads/pipelines/' . $pipelineId);
            $embedded = $data['_embedded']['statuses'] ?? [];
            foreach ($embedded as $st) {
                $sid = (int)($st['id'] ?? 0);
                if ($sid > 0) {
                    $statuses[$sid] = [
                        'name'        => (string)($st['name'] ?? ''),
                        'is_editable' => (bool)($st['is_editable'] ?? true),
                    ];
                }
            }
        } catch (KommoApiException $e) {
            log_message('warning', '[Kommo] statusInfo(pipeline=' . $pipelineId . ') gagal: ' . $e->getCode());
        }

        return $this->statusCache[$pipelineId] = $statuses;
    }

    /** Untuk test: bisa menyalakan/mematikan konfigurasi. */
    public function setConfigured(bool $enabled): void
    {
        $this->config->enabled = $enabled;
    }

    // ── HTTP ──────────────────────────────────────────────────────

    /**
     * Base URL API v4.
     *
     * Prioritas:
     *  1. KOM_API_HOST (env override)
     *  2. 'api_domain' dari payload JWT (klaim terbaca, sign tidak diverifikasi)
     *  3. https://{subdomain}.kommo.com (akun lama / token non-JWT)
     */
    private function apiBase(): string
    {
        $host = $this->apiHost();
        if ($host === '') {
            return 'https://' . rawurlencode($this->config->subdomain) . '.kommo.com' . self::API_PATH;
        }
        return 'https://' . $host . self::API_PATH;
    }

    private function apiHost(): string
    {
        if ($this->config->apiHost !== '') {
            return (string)$this->config->apiHost;
        }

        if ($this->accessToken !== '') {
            $parts = explode('.', $this->accessToken);
            if (count($parts) === 3) {
                $payload = json_decode($this->base64UrlDecode($parts[1]), true);
                if (is_array($payload) && !empty($payload['api_domain']) && is_string($payload['api_domain'])) {
                    return $payload['api_domain'];
                }
            }
        }

        // Token tanpa JWT / tanpa api_domain → pakai domain subdomain klasik.
        return '';
    }

    private function base64UrlDecode(string $encoded): string
    {
        $value = strtr($encoded, '-_', '+/');
        $pad   = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($value, true) !== false ? base64_decode($value, true) : '';
    }

    /**
     * @param array<string,mixed>|null $payload request body JSON (opsional)
     * @throws KommoApiException
     */
    private function request(string $method, string $path, ?array $payload = null, int $attempt = 0): mixed
    {
        if ($this->accessToken === '') {
            throw KommoApiException::auth('access token kosong — isi KOM_ACCESS_TOKEN di .env');
        }

        $ch = curl_init();
        $clientHost = rawurlencode($this->config->subdomain) . '.kommo.com';
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            // Routing cluster API (api-c.kommo.com dst.) menuntut Host header
            // berupa subdomain akun. Tanpa ini microservice akun tidak ter-route.
            'Host: ' . $clientHost,
        ];
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiBase() . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->config->timeout,
            CURLOPT_TIMEOUT        => $this->config->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body    = curl_exec($ch);
        $errno   = curl_errno($ch);
        $errMsg  = curl_error($ch);
        $status  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            // Tidak menyertakan URL penuh (aman); hanya path + kesalahan transport.
            throw KommoApiException::transport('curl#' . $errno . ' pada ' . $method . ' ' . $path . ': ' . $errMsg);
        }

        // Refresh token lalu ulang SATU kali bila 401.
        if ($status === 401 && $attempt === 0 && $this->refreshAccessToken()) {
            return $this->request($method, $path, $payload, 1);
        }

        if ($status === 401) {
            throw KommoApiException::auth();
        }
        if ($status === 404) {
            throw KommoApiException::notFound();
        }

        $decoded = $body === '' ? null : json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $detail = is_array($decoded)
                ? ($decoded['detail'] ?? ($decoded['title'] ?? json_encode($decoded)))
                : (string)$body;
            throw KommoApiException::http($status, (string)$detail);
        }
        if ($decoded === null) {
            throw KommoApiException::invalidResponse('body tidak valid (JSON) untuk ' . $method . ' ' . $path);
        }

        return $decoded;
    }

    /**
     * Refresh access_token pakai refresh_token.
     * Endpoint OAuth2 Kommo: POST https://{sub}.kommo.com/oauth2/access_token
     * (fallback ke /api/v4/oauth2/token untuk akun baru).
     */
    private function refreshAccessToken(): bool
    {
        if ($this->config->clientId === '' || $this->config->clientSecret === '' || $this->refreshToken === '') {
            return false;
        }

        $payload = [
            'client_id'     => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->refreshToken,
        ];

        foreach ([
            'https://' . rawurlencode($this->config->subdomain) . '.kommo.com/oauth2/access_token',
            $this->apiBase() . '/oauth2/token',
        ] as $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => $this->config->timeout,
                CURLOPT_TIMEOUT        => $this->config->timeout,
            ]);
            $body   = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = $body === '' ? null : json_decode((string)$body, true);
            $token = $data['access_token'] ?? null;
            if ($status >= 200 && $status < 300 && is_string($token) && $token !== '') {
                $this->accessToken  = $token;
                $newRefresh = $data['refresh_token'] ?? null;
                if (is_string($newRefresh) && $newRefresh !== '') {
                    $this->refreshToken = $newRefresh;
                }
                // Catatan: token hasil refresh hanya berlaku untuk proses ini.
                // Untuk persistensi lintas restart, update KOM_ACCESS_TOKEN di .env (TODO).
                log_message('info', '[Kommo] access token berhasil di-refresh.');
                return true;
            }
        }

        log_message('warning', '[Kommo] refresh token gagal — cek KOM_CLIENT_ID/SECRET/REFRESH_TOKEN.');
        return false;
    }
}