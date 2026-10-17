<?php

namespace App\Services\Meta;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Klien Meta Marketing API (Graph) — hanya HTTP & parsing, tanpa logika bisnis.
 *
 * Konfigurasi dari .env:
 *   META_ADS_TOKEN=...
 *   META_AD_ACCOUNT_ID=act_...   (atau tanpa prefix "act_")
 *
 * HANYA BACA (GET). Tidak pernah menulis/mengubah apa pun di Meta.
 * Token tidak pernah muncul di pesan exception; URL tidak pernah di-log.
 */
class MetaAdsClient
{
    public const GRAPH_BASE = 'https://graph.facebook.com/v25.0/';

    /** Action Meta yang dipetakan ke kolom "Klik" dan "Hasil" di laporan. */
    public const ACTION_LINK_CLICK    = 'link_click';
    public const ACTION_CONVERSATIONS = 'onsite_conversion.messaging_conversation_started_7d';

    /** Batas percobaan ulang untuk kegagalan jaringan. */
    public const COBA_MAKS = 3;

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 30, 'connect_timeout' => 10]);
    }

    /**
     * Insight harian per kampanye untuk satu rentang tanggal.
     *
     * Satu panggilan (plus paging) mengembalikan SEMUA kampanye sekaligus.
     *
     * @return array{
     *   rows: array<int, array{campaign_id:string, campaign_name:string, date:string,
     *                         spend:float, reach:int, frequency:float, impressions:int,
     *                         link_clicks:int, conversations:int}>,
     *   rate: array<string, mixed>
     * }
     */
    public function fetchCampaignInsights(string $since, string $until): array
    {
        $query = [
            'level'          => 'campaign',
            'time_increment' => 1,
            'time_range'     => json_encode(['since' => $since, 'until' => $until]),
            'fields'         => 'campaign_id,campaign_name,spend,reach,frequency,impressions,actions',
            'limit'          => 500,
        ];

        $rows = [];
        $rate = [];
        $url  = $this->url('insights', $query);
        $first = true;

        while ($url !== null) {
            [$decoded, $response] = $this->request($url);

            if ($first) {
                $rate  = $this->normalizeRate($response);
                $first = false;
            }

            foreach ((array)($decoded['data'] ?? []) as $row) {
                $rows[] = $this->normalizeInsightRow((array)$row);
            }

            // URL paging sudah memuat access_token — jangan dijejali query lagi.
            $url = $decoded['paging']['next'] ?? null;
        }

        return ['rows' => $rows, 'rate' => $rate];
    }

    /**
     * Peta kampanye Meta: daily budget + objective + tujuan (untuk label Objektif).
     *
     * @return array<string, array{daily_budget: float|null, objective: string|null,
     *                             optimization_goal: string|null, destination_type: string|null}>
     */
    public function fetchCampaigns(): array
    {
        $map = [];

        foreach ($this->pagedGet('campaigns', ['fields' => 'id,daily_budget,objective', 'limit' => 500]) as $c) {
            $id = trim((string)($c['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $map[$id] = [
                'daily_budget'      => isset($c['daily_budget']) && $c['daily_budget'] !== '' ? (float)$c['daily_budget'] : null,
                'objective'         => isset($c['objective']) && $c['objective'] !== '' ? (string)$c['objective'] : null,
                'optimization_goal' => null,
                'destination_type'  => null,
            ];
        }

        foreach ($this->pagedGet('adsets', ['fields' => 'campaign_id,optimization_goal,destination_type', 'limit' => 500]) as $a) {
            $id = trim((string)($a['campaign_id'] ?? ''));
            if ($id === '' || !isset($map[$id])) {
                continue;
            }
            foreach (['optimization_goal', 'destination_type'] as $field) {
                if (isset($a[$field]) && $a[$field] !== '') {
                    $map[$id][$field] = (string)$a[$field];
                }
            }
        }

        return $map;
    }

    // ── internal ────────────────────────────────────────────────────

    /** Menelusuri seluruh halaman sebuah edge. */
    private function pagedGet(string $edge, array $query): array
    {
        $all = [];
        $url = $this->url($edge, $query);

        while ($url !== null) {
            [$decoded] = $this->request($url);
            foreach ((array)($decoded['data'] ?? []) as $row) {
                $all[] = (array)$row;
            }
            $url = $decoded['paging']['next'] ?? null;
        }

        return $all;
    }

    private function url(string $edge, array $query): string
    {
        $query['access_token'] = $this->token();

        return self::GRAPH_BASE . $this->adAccountId() . '/' . $edge . '?' . http_build_query($query);
    }

    /**
     * @return array{0: array, 1: ResponseInterface}
     */
    private function request(string $url): array
    {
        $sebelumnya = null;
        $sebelumnya = set_error_handler(function ($severity, $message, $file = '', $line = 0) use (&$sebelumnya) {
            // OpenSSL memunculkan "SSL operation failed … unexpected eof while
            // reading" SETELAH seluruh body diterima (koneksi ditutup tanpa
            // close_notify). Di CLI, CodeIgniter mengubah peringatan itu jadi
            // ErrorException, sehingga Guzzle menganggap pembacaan gagal
            // padahal datanya utuh — dan seluruh tarikan batal.
            //
            // Hanya peringatan itu yang diabaikan; sisanya diteruskan ke
            // handler sebelumnya supaya masalah sungguhan tetap terlihat.
            // Respons tetap divalidasi (status HTTP, JSON, error Graph),
            // jadi kegagalan nyata tidak ikut tertelan.
            if (strpos((string)$message, 'SSL operation failed') !== false) {
                return true;
            }

            return is_callable($sebelumnya) ? $sebelumnya($severity, $message, $file, $line) : false;
        });

        try {
            $response = $this->kirim($url);

            $status  = (int)$response->getStatusCode();
            $decoded = json_decode((string)$response->getBody(), true);
            $error   = $this->apiError($decoded);
        } catch (ConnectException $e) {
            throw new MetaAdsException('Timeout/koneksi gagal ke Meta Marketing API.', 0, $e);
        } catch (GuzzleException $e) {
            throw new MetaAdsException('HTTP request ke Meta Marketing API gagal.', 0, $e);
        } finally {
            restore_error_handler();
        }

        if ($status < 200 || $status >= 300) {
            throw new MetaAdsException(sprintf(
                'Meta Marketing API menolak (HTTP %d)%s',
                $status,
                $error !== null ? ': ' . $error : '.'
            ));
        }

        if (!is_array($decoded)) {
            throw new MetaAdsException('Respons Meta bukan JSON yang valid.');
        }

        return [$decoded, $response];
    }

    /**
     * Kirim satu GET, dengan percobaan ulang terbatas untuk kegagalan jaringan.
     *
     * Transport di sini memakai stream PHP (ext-curl tidak aktif di server ini)
     * dan sesekali gagal menyambung. GET bersifat idempoten, jadi aman diulang.
     * Hanya kegagalan jaringan yang diulang: respons dari Meta (termasuk 4xx/5xx)
     * tidak pernah diulang, karena itu keputusan server yang sudah final.
     */
    private function kirim(string $url): ResponseInterface
    {
        $percobaan = 0;

        while (true) {
            $percobaan++;

            try {
                return $this->http->request('GET', $url, [
                    'headers'     => ['Accept' => 'application/json'],
                    'http_errors' => false,
                ]);
            } catch (ConnectException $e) {
                if ($percobaan >= self::COBA_MAKS) {
                    throw new MetaAdsException('Timeout/koneksi gagal ke Meta Marketing API.', 0, $e);
                }
            } catch (RequestException $e) {
                // Punya respons = Meta menjawab; jangan diulang.
                if ($e->hasResponse() || $percobaan >= self::COBA_MAKS) {
                    throw new MetaAdsException('HTTP request ke Meta Marketing API gagal.', 0, $e);
                }
            } catch (GuzzleException $e) {
                throw new MetaAdsException('HTTP request ke Meta Marketing API gagal.', 0, $e);
            }

            usleep(500000 * $percobaan);
        }
    }

    /** Kode & pesan error dari body Graph API, tanpa bagian yang memuat token. */
    private function apiError($decoded): ?string
    {
        if (!is_array($decoded) || !isset($decoded['error']) || !is_array($decoded['error'])) {
            return null;
        }

        $e     = $decoded['error'];
        $parts = [];
        if (isset($e['code'])) {
            $parts[] = 'kode ' . $e['code'];
        }
        if (!empty($e['message'])) {
            $parts[] = (string)$e['message'];
        }

        return $parts === [] ? null : implode(' — ', $parts);
    }

    private function normalizeInsightRow(array $row): array
    {
        $actions = (array)($row['actions'] ?? []);

        return [
            'campaign_id'   => (string)($row['campaign_id'] ?? ''),
            'campaign_name' => (string)($row['campaign_name'] ?? ''),
            'date'          => (string)($row['date_start'] ?? ''),
            'spend'         => (float)($row['spend'] ?? 0),
            'reach'         => (int)($row['reach'] ?? 0),
            'frequency'     => (float)($row['frequency'] ?? 0),
            'impressions'   => (int)($row['impressions'] ?? 0),
            'link_clicks'   => (int)$this->actionValue($actions, self::ACTION_LINK_CLICK),
            'conversations' => (int)$this->actionValue($actions, self::ACTION_CONVERSATIONS),
        ];
    }

    private function actionValue(array $actions, string $type): float
    {
        foreach ($actions as $a) {
            if (is_array($a) && ($a['action_type'] ?? null) === $type) {
                return (float)($a['value'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Normalkan dua header rate limit Meta jadi satu peta berkunci tetap,
     * supaya pemanggil tidak perlu tahu nama header asalnya.
     */
    private function normalizeRate(ResponseInterface $response): array
    {
        $rate = [
            'app_id_util_pct'                 => null,
            'acc_id_util_pct'                 => null,
            'tier'                            => null,
            'call_count'                      => null,
            'total_time'                      => null,
            'total_cputime'                   => null,
            'estimated_time_to_regain_access'  => null,
        ];

        $throttle = $this->jsonHeader($response, 'x-fb-ads-insights-throttle');
        if ($throttle !== []) {
            $rate['app_id_util_pct'] = $this->numOrNull($throttle['app_id_util_pct'] ?? null);
            $rate['acc_id_util_pct'] = $this->numOrNull($throttle['acc_id_util_pct'] ?? null);
            $rate['tier']            = $throttle['ads_api_access_tier'] ?? $rate['tier'];
        }

        // Bentuk: { "<ad_account_id>": [ { ... } ] }
        foreach ($this->jsonHeader($response, 'x-business-use-case-usage') as $entries) {
            foreach ((array)$entries as $e) {
                if (!is_array($e)) {
                    continue;
                }
                $rate['call_count']                     = $this->numOrNull($e['call_count'] ?? null) ?? $rate['call_count'];
                $rate['total_time']                     = $this->numOrNull($e['total_time'] ?? null) ?? $rate['total_time'];
                $rate['total_cputime']                  = $this->numOrNull($e['total_cputime'] ?? null) ?? $rate['total_cputime'];
                $rate['estimated_time_to_regain_access'] = $this->numOrNull($e['estimated_time_to_regain_access'] ?? null)
                    ?? $rate['estimated_time_to_regain_access'];
                if (!empty($e['ads_api_access_tier'])) {
                    $rate['tier'] = (string)$e['ads_api_access_tier'];
                }
            }
        }

        return $rate;
    }

    private function jsonHeader(ResponseInterface $response, string $name): array
    {
        $raw = trim($response->getHeaderLine($name));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Angka apa adanya (int tetap int, mis. 0; desimal tetap float). */
    private function numOrNull($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return $v;
        }

        return is_numeric($v) ? $v + 0 : null;
    }

    private function token(): string
    {
        $token = trim((string)env('META_ADS_TOKEN'));
        if ($token === '') {
            throw new MetaAdsException('META_ADS_TOKEN belum dikonfigurasi di .env.');
        }

        return $token;
    }

    private function adAccountId(): string
    {
        $id = trim((string)env('META_AD_ACCOUNT_ID'));
        if ($id === '') {
            throw new MetaAdsException('META_AD_ACCOUNT_ID belum dikonfigurasi di .env.');
        }

        return strpos($id, 'act_') === 0 ? $id : 'act_' . $id;
    }
}
