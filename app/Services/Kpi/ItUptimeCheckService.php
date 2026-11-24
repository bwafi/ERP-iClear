<?php

namespace App\Services\Kpi;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * ItUptimeCheckService — satu pengecekan uptime ERP, satu baris bukti.
 *
 * Dipanggil cron 3 kali sehari (02:00, 08:00, 22:00 WIB). Hasilnya
 * ditambahkan ke `backups/uptime.jsonl`, bukan tabel database: bila MySQL
 * mati, kegagalan tetap tercatat sehingga harinya dinilai gagal, bukan
 * "tidak termonitor".
 *
 * Sukses = HTTP 200 setelah redirect dalam batas waktu. `marker` opsional
 * untuk memastikan halaman yang dimuat benar-benar halaman login, bukan
 * halaman error yang tetap membalas 200.
 *
 * Satu baris ledger: at, ok, http, ms, url, error.
 */
class ItUptimeCheckService
{
    public const DEFAULT_URL = 'https://erp.icleargroup.id/login';

    /** @var array<string,mixed> */
    private $config;

    /** @var callable|null */
    private $http;

    /**
     * @param array<string,mixed> $config url, marker, timeout, timezone,
     *                                    ledger_dir, http (callable untuk test)
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'url'        => self::DEFAULT_URL,
            'marker'     => '',
            'timeout'    => 10,
            'timezone'   => 'Asia/Jakarta',
            'ledger_dir' => (defined('ROOTPATH') ? ROOTPATH : getcwd() . DIRECTORY_SEPARATOR) . 'backups',
            'http'       => null,
        ], $config);

        $this->http = is_callable($this->config['http']) ? $this->config['http'] : null;
    }

    public function url(): string
    {
        return trim((string) $this->config['url']);
    }

    public function path(): string
    {
        return rtrim((string) $this->config['ledger_dir'], '/\\')
            . DIRECTORY_SEPARATOR . 'uptime.jsonl';
    }

    /**
     * Cek sekali lalu catat buktinya.
     *
     * @return array{ok:bool,http:int,ms:int,error:?string,at:string}
     */
    public function run(?DateTimeImmutable $now = null): array
    {
        $result = $this->probe();

        $result['at'] = $this->businessTime($now)->format('Y-m-d\TH:i:sP');

        $this->record($result);

        return $result;
    }

    /**
     * Lakukan HTTP GET tanpa menulis ledger.
     *
     * @return array{ok:bool,http:int,ms:int,error:?string}
     */
    public function probe(): array
    {
        $timeout = max(1, (int) $this->config['timeout']);
        $marker  = (string) $this->config['marker'];

        try {
            $response = $this->request($this->url(), $timeout);
            $status   = (int) ($response['status'] ?? 0);
            $body     = (string) ($response['body'] ?? '');
            $ms       = (int) ($response['ms'] ?? 0);
            $error    = $response['error'] ?? null;

            return [
                'ok'    => $error === null && self::isSuccess($status, $body, $marker),
                'http'  => $status,
                'ms'    => $ms,
                'error' => $error === null ? null : (string) $error,
            ];
        } catch (Throwable $e) {
            return [
                'ok'    => false,
                'http'  => 0,
                'ms'    => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Tambahkan satu baris ke ledger. Kegagalan menulis tidak dilempar:
     * cron tidak perlu gempar hanya karena file tidak bisa ditulis.
     *
     * @param array<string,mixed> $result
     */
    public function record(array $result, ?DateTimeImmutable $now = null): string
    {
        $path = $this->path();
        $dir  = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return $path;
        }

        $entry = [
            'at'    => (string) ($result['at'] ?? $this->businessTime($now)->format('Y-m-d\TH:i:sP')),
            'ok'    => (bool) ($result['ok'] ?? false),
            'http'  => (int) ($result['http'] ?? 0),
            'ms'    => (int) ($result['ms'] ?? 0),
            'url'   => $this->url(),
            'error' => $result['error'] ?? null,
        ];

        @file_put_contents(
            $path,
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );

        return $path;
    }

    /**
     * Hanya HTTP 200 yang dianggap sukses. Marker dibandingkan tanpa peduli
     * huruf besar-kecil dan spasi tepi.
     */
    public static function isSuccess(int $status, string $body, string $marker): bool
    {
        if ($status !== 200) {
            return false;
        }

        $marker = trim($marker);
        if ($marker === '') {
            return true;
        }

        return stripos($body, $marker) !== false;
    }

    /* ═══════════════════════ internal ═══════════════════════ */

    /**
     * @return array{status:int,body:string,ms:int,error:?string}
     */
    private function request(string $url, int $timeout): array
    {
        if ($url === '') {
            throw new RuntimeException('URL cek uptime kosong.');
        }

        if ($this->http !== null) {
            return (array) call_user_func($this->http, $url, $timeout);
        }

        if (! function_exists('curl_init')) {
            return $this->streamRequest($url, $timeout);
        }

        $start  = microtime(true);
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Tidak bisa membuat koneksi HTTP.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'ERP-iClear-KPI-Uptime',
        ]);

        $body   = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error  = curl_error($handle);
        curl_close($handle);

        $ms = (int) round((microtime(true) - $start) * 1000);

        if ($body === false) {
            return ['status' => 0, 'body' => '', 'ms' => $ms, 'error' => 'HTTP gagal: ' . $error];
        }

        return ['status' => $status, 'body' => (string) $body, 'ms' => $ms, 'error' => null];
    }

    /**
     * Fallback bila ekstensi curl tidak tersedia.
     *
     * @return array{status:int,body:string,ms:int,error:?string}
     */
    private function streamRequest(string $url, int $timeout): array
    {
        $start   = microtime(true);
        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'ignore_errors'   => true,
                'timeout'         => $timeout,
                'follow_location' => 1,
                'max_redirects'   => 3,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $ms   = (int) round((microtime(true) - $start) * 1000);

        if ($body === false) {
            return ['status' => 0, 'body' => '', 'ms' => $ms, 'error' => 'HTTP gagal: ' . $url];
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return ['status' => $status, 'body' => (string) $body, 'ms' => $ms, 'error' => null];
    }

    private function businessTime(?DateTimeImmutable $now = null): DateTimeImmutable
    {
        try {
            $tz = new DateTimeZone((string) $this->config['timezone']);
        } catch (Throwable $e) {
            $tz = new DateTimeZone('Asia/Jakarta');
        }

        return ($now ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);
    }
}
