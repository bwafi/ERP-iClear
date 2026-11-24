<?php

namespace App\Services\Kpi;

use App\Models\ModelKpiItMetric;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * ItKpiSyncService — mengumpulkan data mentah KPI Team IT (jabatan 45).
 *
 * Sumber:
 *   - commit `feat`/`fix`/`docs` dari GitHub (`GITHUB_REPOS`);
 *   - hari backup sukses dari `backups/history.jsonl`;
 *   - hari uptime OK dari UptimeRobot (`UPTIMEROBOT_API_KEY`).
 *
 * Hasil disimpan sebagai snapshot `kpi_it_metrics` per bulan sehingga engine
 * KPI tidak melakukan panggilan jaringan dan angka bulan lalu stabil.
 *
 * Uptime memakai `custom_uptime_ranges` per hari, bukan log 24 jam, supaya
 * tetap bisa menghitung bulan penuh pada paket UptimeRobot yang menyimpan log
 * pendek. Hari dianggap gagal bila downtime > 15 menit (900 detik).
 */
class ItKpiSyncService
{
    private const GITHUB_API = 'https://api.github.com';

    private const UPTIME_API = 'https://api.uptimerobot.com/v2/getMonitors';

    /** Batas aman pagination GitHub per repo. */
    private const MAX_PAGES = 50;

    /** Downtime maksimal yang masih dianggap hari OK. */
    public const MAX_DOWNTIME_SECONDS = 900;

    /** @var array<string,mixed> */
    private $config;

    /** @var callable|null */
    private $http;

    /**
     * @param array<string,mixed> $config github_token, github_repos, github_authors,
     *                                    uptime_key, uptime_monitors, backup_dir,
     *                                    timezone, http (callable untuk test)
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'github_token'    => '',
            'github_repos'    => '',
            'github_authors'  => '',
            'uptime_key'      => '',
            'uptime_monitors' => '',
            'backup_dir'      => (defined('ROOTPATH') ? ROOTPATH : getcwd() . DIRECTORY_SEPARATOR) . 'backups',
            'timezone'        => 'Asia/Jakarta',
            'http'            => null,
        ], $config);

        $this->http = is_callable($this->config['http']) ? $this->config['http'] : null;
    }

    /**
     * Kumpulkan data satu bulan tanpa menulis database.
     *
     * @return array<string,mixed>
     */
    public function collect(int $year, int $month, ?DateTimeImmutable $now = null): array
    {
        $tz  = $this->timezone();
        $now = ($now ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);

        $days = self::monitoredDays($year, $month, $now, $tz->getName());

        $metrics = [
            'period'              => sprintf('%04d-%02d', $year, $month),
            'feat_commits'        => null,
            'fix_commits'         => null,
            'docs_commits'        => null,
            'backup_ok_days'      => null,
            'backup_checked_days' => null,
            'uptime_ok_days'      => null,
            'uptime_checked_days' => null,
            'restore_ok'          => null,
            'detail'              => [
                'github' => null,
                'backup' => null,
                'uptime' => null,
            ],
            'errors'              => [],
        ];

        // ── GitHub commits ────────────────────────────────────────
        $repos = self::parseRepos((string) $this->config['github_repos']);
        if ($repos === []) {
            $metrics['detail']['github'] = ['status' => 'skipped', 'reason' => 'GITHUB_REPOS kosong'];
        } else {
            try {
                $github = $this->githubCounts($repos, $year, $month, $tz);
                $metrics['feat_commits'] = $github['feat'];
                $metrics['fix_commits']  = $github['fix'];
                $metrics['docs_commits'] = $github['docs'];
                $metrics['detail']['github'] = [
                    'status'  => 'ok',
                    'authors' => trim((string) $this->config['github_authors']),
                    'repos'   => $github['repos'],
                ];
            } catch (Throwable $e) {
                $metrics['errors'][] = 'GitHub: ' . $e->getMessage();
                $metrics['detail']['github'] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        // ── Backup harian ─────────────────────────────────────────
        $backupFile = rtrim((string) $this->config['backup_dir'], '/\\')
            . DIRECTORY_SEPARATOR . 'history.jsonl';

        try {
            $backup = self::backupSummary($backupFile, $days, $tz->getName());
            $metrics['backup_ok_days']      = $backup['ok_days'];
            $metrics['backup_checked_days'] = $backup['checked_days'];
            $metrics['detail']['backup']    = [
                'status'       => is_file($backupFile) ? 'ok' : 'no-history',
                'file'         => $backupFile,
                'failed_days'  => $backup['failed_days'],
            ];
        } catch (Throwable $e) {
            $metrics['errors'][] = 'Backup: ' . $e->getMessage();
            $metrics['detail']['backup'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // ── Uji restore bulanan ───────────────────────────────────
        $restoreFile = rtrim((string) $this->config['backup_dir'], '/\\')
            . DIRECTORY_SEPARATOR . 'restore.jsonl';
        $restore = self::restoreStatus($restoreFile, $days, $tz->getName());
        $metrics['restore_ok'] = $restore === null ? null : ($restore ? 1 : 0);

        // ── Uptime harian ─────────────────────────────────────────
        $uptimeKey = trim((string) $this->config['uptime_key']);
        if ($uptimeKey === '') {
            $metrics['detail']['uptime'] = [
                'status' => 'skipped',
                'reason' => 'UPTIMEROBOT_API_KEY belum diisi',
            ];
        } elseif ($days === []) {
            $metrics['detail']['uptime'] = ['status' => 'skipped', 'reason' => 'Belum ada hari selesai'];
        } else {
            try {
                $uptime = $this->uptimeStats($days, $tz);
                $metrics['uptime_ok_days']      = $uptime['ok_days'];
                $metrics['uptime_checked_days'] = $uptime['checked_days'];
                $metrics['detail']['uptime']    = [
                    'status'         => 'ok',
                    'monitors'       => $uptime['monitor_count'],
                    'failed_indexes' => $uptime['failed_indexes'],
                    'days'           => $uptime['days'],
                ];
            } catch (Throwable $e) {
                $metrics['errors'][] = 'Uptime: ' . $e->getMessage();
                $metrics['detail']['uptime'] = ['status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $metrics;
    }

    /**
     * Kumpulkan lalu simpan snapshot bulanan (upsert berdasarkan period).
     *
     * @return array<string,mixed>
     */
    public function sync(int $year, int $month, ?DateTimeImmutable $now = null): array
    {
        $collected = $this->collect($year, $month, $now);
        $tz        = $this->timezone();

        $payload = [
            'period'              => $collected['period'],
            'feat_commits'        => $collected['feat_commits'],
            'fix_commits'         => $collected['fix_commits'],
            'docs_commits'        => $collected['docs_commits'],
            'backup_ok_days'      => $collected['backup_ok_days'],
            'backup_checked_days' => $collected['backup_checked_days'],
            'uptime_ok_days'      => $collected['uptime_ok_days'],
            'uptime_checked_days' => $collected['uptime_checked_days'],
            'restore_ok'          => $collected['restore_ok'],
            'detail'              => json_encode($collected['detail'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'synced_at'           => (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s'),
        ];

        $model    = new ModelKpiItMetric();
        $existing = $model->where('period', $collected['period'])->first();

        if ($existing) {
            $model->update((int) $existing->id, $payload);
        } else {
            $model->insert($payload);
        }

        $collected['saved'] = true;

        return $collected;
    }

    /**
     * Catat hasil uji restore bulanan (dipanggil `it:kpi-sync --restore=ok|failed`).
     */
    public function recordRestore(string $status, ?DateTimeImmutable $now = null): void
    {
        if (! in_array($status, ['ok', 'failed'], true)) {
            throw new RuntimeException('Status restore harus "ok" atau "failed".');
        }

        $dir = rtrim((string) $this->config['backup_dir'], '/\\');
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Folder backup tidak bisa dibuat: ' . $dir);
        }

        $entry = [
            'at'     => ($now ?? new DateTimeImmutable('now', $this->timezone()))->setTimezone($this->timezone())->format('c'),
            'status' => $status,
        ];

        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'restore.jsonl',
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    /* ═══════════════════════ GitHub ═══════════════════════ */

    /**
     * @param list<array{repo:string,branch:string}> $repos
     *
     * @return array{feat:int,fix:int,docs:int,repos:list<array<string,mixed>>}
     */
    private function githubCounts(array $repos, int $year, int $month, DateTimeZone $tz): array
    {
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $end   = $start->modify('first day of next month');
        $utc   = new DateTimeZone('UTC');
        $since = $start->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');
        $until = $end->setTimezone($utc)->format('Y-m-d\TH:i:s\Z');

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: ERP-iClear-KPI',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        $token = trim((string) $this->config['github_token']);
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $authors = (string) $this->config['github_authors'];
        $totals  = ['feat' => 0, 'fix' => 0, 'docs' => 0];
        $detail  = [];

        foreach ($repos as $repo) {
            $repoFeat = 0;
            $repoFix  = 0;
            $repoDocs = 0;
            $page     = 1;
            $pages    = 0;

            do {
                $url = sprintf(
                    '%s/repos/%s/commits?sha=%s&since=%s&until=%s&per_page=100&page=%d',
                    self::GITHUB_API,
                    $repo['repo'],
                    rawurlencode($repo['branch']),
                    rawurlencode($since),
                    rawurlencode($until),
                    $page
                );

                $response = $this->request('GET', $url, $headers, null);
                $status   = (int) $response['status'];

                if ($status === 409) {
                    break; // repo kosong
                }
                if ($status < 200 || $status >= 300) {
                    throw new RuntimeException(sprintf(
                        'repo %s HTTP %d: %s',
                        $repo['repo'],
                        $status,
                        substr(trim((string) $response['body']), 0, 200)
                    ));
                }

                $data = json_decode((string) $response['body'], true);
                if (! is_array($data)) {
                    throw new RuntimeException('repo ' . $repo['repo'] . ': respons GitHub bukan JSON array.');
                }

                $counts = self::parseGitHubCommits($data, $authors);
                $repoFeat += $counts['feat'];
                $repoFix  += $counts['fix'];
                $repoDocs += $counts['docs'];

                $pages++;
                $page++;
            } while (count($data) === 100 && $pages < self::MAX_PAGES);

            $totals['feat'] += $repoFeat;
            $totals['fix']  += $repoFix;
            $totals['docs'] += $repoDocs;

            $detail[] = [
                'repo'   => $repo['repo'],
                'branch' => $repo['branch'],
                'feat'   => $repoFeat,
                'fix'    => $repoFix,
                'docs'   => $repoDocs,
                'pages'  => $pages,
            ];
        }

        return [
            'feat'  => $totals['feat'],
            'fix'   => $totals['fix'],
            'docs'  => $totals['docs'],
            'repos' => $detail,
        ];
    }

    /**
     * Hitung prefix Conventional Commits (`feat`, `fix`, `docs`).
     *
     * @param list<array<string,mixed>> $commits
     *
     * @return array{feat:int,fix:int,docs:int}
     */
    public static function parseGitHubCommits(array $commits, string $authors): array
    {
        $needles = self::authorNeedles($authors);
        $counts  = ['feat' => 0, 'fix' => 0, 'docs' => 0];

        foreach ($commits as $commit) {
            if (! is_array($commit)) {
                continue;
            }
            if ($needles !== [] && ! self::commitMatchesAuthor($commit, $needles)) {
                continue;
            }

            $message = (string) ($commit['commit']['message'] ?? '');
            $subject = preg_split('/\r?\n/', $message)[0] ?? '';

            if (preg_match('/^\s*([A-Za-z]+)(?:\([^)]*\))?!?\s*:/', $subject, $matches) !== 1) {
                continue;
            }

            $type = strtolower($matches[1]);
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return $counts;
    }

    /**
     * Author boleh dicocokkan lewat email commit atau login GitHub.
     *
     * @param array<string,mixed> $commit
     * @param list<string>        $needles
     */
    public static function commitMatchesAuthor(array $commit, array $needles): bool
    {
        $needles = array_values(array_filter(array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            $needles
        )));

        if ($needles === []) {
            return true;
        }

        $candidates = [
            strtolower((string) ($commit['commit']['author']['email'] ?? '')),
            strtolower((string) ($commit['author']['login'] ?? '')),
        ];

        foreach ($needles as $needle) {
            if ($needle !== '' && in_array($needle, $candidates, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function authorNeedles(string $authors): array
    {
        if (trim($authors) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            explode(',', $authors)
        )));
    }

    /**
     * @return list<array{repo:string,branch:string}>
     */
    private static function parseRepos(string $raw): array
    {
        $repos = [];

        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (preg_match('#^([A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+)(?::([A-Za-z0-9_./-]+))?$#', $entry, $m) !== 1) {
                throw new RuntimeException('Format GITHUB_REPOS tidak valid: ' . $entry);
            }

            $repos[] = [
                'repo'   => $m[1],
                'branch' => $m[2] ?? 'main',
            ];
        }

        return $repos;
    }

    /* ═══════════════════════ UptimeRobot ═══════════════════════ */

    /**
     * @param list<string> $days
     *
     * @return array<string,mixed>
     */
    private function uptimeStats(array $days, DateTimeZone $tz): array
    {
        $ranges = [];
        foreach ($days as $day) {
            $start    = new DateTimeImmutable($day . ' 00:00:00', $tz);
            $ranges[] = $start->getTimestamp() . '_' . ($start->getTimestamp() + 86399);
        }

        $form = [
            'api_key'              => (string) $this->config['uptime_key'],
            'format'               => 'json',
            'custom_uptime_ranges' => implode('-', $ranges),
        ];

        $monitors = trim((string) $this->config['uptime_monitors']);
        if ($monitors !== '') {
            $form['monitors'] = $monitors;
        }

        $response = $this->request(
            'POST',
            self::UPTIME_API,
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query($form)
        );

        $status = (int) $response['status'];
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('HTTP ' . $status . ': ' . substr(trim((string) $response['body']), 0, 200));
        }

        $data = json_decode((string) $response['body'], true);
        if (! is_array($data)) {
            throw new RuntimeException('Respons UptimeRobot bukan JSON.');
        }
        if (($data['stat'] ?? '') !== 'ok') {
            $message = $data['error']['message'] ?? 'stat bukan ok';
            throw new RuntimeException((string) $message);
        }

        $monitors = isset($data['monitors']) && is_array($data['monitors']) ? $data['monitors'] : [];
        $parsed   = self::parseUptimeRatios($monitors, count($days));

        return $parsed + ['monitor_count' => count($monitors)];
    }

    /**
     * Satu nilai ratio per hari per monitor. Hari dinilai dari monitor terburuk.
     *
     * @param list<array<string,mixed>> $monitors
     *
     * @return array{ok_days:int,checked_days:int,failed_indexes:list<int>,days:list<array<string,mixed>>}
     */
    public static function parseUptimeRatios(array $monitors, int $expectedRanges): array
    {
        $values = array_fill(0, max($expectedRanges, 0), []);

        foreach ($monitors as $monitor) {
            $raw = (string) ($monitor['custom_uptime_ratio'] ?? '');
            if ($raw === '') {
                continue;
            }

            foreach (self::parseRatioSeries($raw) as $index => $ratio) {
                if ($index >= $expectedRanges) {
                    break;
                }
                if ($ratio >= 0) {
                    $values[$index][] = $ratio;
                }
            }
        }

        $ok      = 0;
        $checked = 0;
        $failed  = [];
        $days    = [];

        foreach ($values as $index => $ratios) {
            if ($ratios === []) {
                continue;
            }

            $checked++;
            $worst    = min($ratios);
            $downtime = self::downtimeSecondsFromRatio($worst);
            $isOk     = $downtime <= self::MAX_DOWNTIME_SECONDS;

            if ($isOk) {
                $ok++;
            } else {
                $failed[] = $index;
            }

            $days[] = [
                'index'            => $index,
                'ratio'            => round($worst, 3),
                'downtime_seconds' => (int) round($downtime),
                'ok'               => $isOk,
            ];
        }

        return [
            'ok_days'        => $ok,
            'checked_days'   => $checked,
            'failed_indexes' => $failed,
            'days'           => $days,
        ];
    }

    public static function downtimeSecondsFromRatio(float $ratio): float
    {
        $ratio = max(0.0, min($ratio, 100.0));

        return ((100.0 - $ratio) / 100.0) * 86400.0;
    }

    /**
     * UptimeRobot memisahkan ratio dengan '-' dan memakai '-' juga untuk nilai
     * negatif ("-1.000--1.000"). explode() menghasilkan token kosong sebelum
     * nilai negatif, dan token kosong itulah penanda tanda minus.
     *
     * @return list<float>
     */
    private static function parseRatioSeries(string $raw): array
    {
        $values  = [];
        $negative = false;

        foreach (explode('-', $raw) as $part) {
            if ($part === '') {
                $negative = true;
                continue;
            }

            $values[] = ($negative ? -1.0 : 1.0) * (float) $part;
            $negative = false;
        }

        return $values;
    }

    /* ═══════════════════════ Backup & restore ═══════════════════════ */

    /**
     * Hari sukses = ada minimal satu entri `status=ok` dan `tables>0` pada hari
     * itu. Hari tanpa entri (atau hanya gagal) dihitung gagal.
     *
     * @param list<string> $days
     *
     * @return array{ok_days:int,checked_days:int,failed_days:list<string>}
     */
    public static function backupSummary(string $historyFile, array $days, string $timezone): array
    {
        if (! is_file($historyFile)) {
            return ['ok_days' => 0, 'checked_days' => 0, 'failed_days' => []];
        }

        $tz       = self::safeTimezone($timezone);
        $success  = [];
        $lines    = file($historyFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (! is_array($row)) {
                continue;
            }
            if (($row['status'] ?? '') !== 'ok' || (int) ($row['tables'] ?? 0) < 1) {
                continue;
            }

            $at = (string) ($row['at'] ?? '');
            if ($at === '') {
                continue;
            }

            try {
                $day = (new DateTimeImmutable($at))->setTimezone($tz)->format('Y-m-d');
            } catch (Throwable $e) {
                continue;
            }

            $success[$day] = true;
        }

        $ok     = 0;
        $failed = [];

        foreach ($days as $day) {
            if (isset($success[$day])) {
                $ok++;
            } else {
                $failed[] = $day;
            }
        }

        return [
            'ok_days'      => $ok,
            'checked_days' => count($days),
            'failed_days'  => $failed,
        ];
    }

    /**
     * Status uji restore terakhir pada periode. null = belum pernah diuji.
     *
     * @param list<string> $days
     */
    public static function restoreStatus(string $restoreFile, array $days, string $timezone): ?bool
    {
        if ($days === [] || ! is_file($restoreFile)) {
            return null;
        }

        $tzAllowed = array_fill_keys($days, true);
        $tz        = self::safeTimezone($timezone);
        $lines     = file($restoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $latestAt  = null;
        $latest    = null;

        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (! is_array($row) || ! in_array($row['status'] ?? '', ['ok', 'failed'], true)) {
                continue;
            }

            $at = (string) ($row['at'] ?? '');
            if ($at === '') {
                continue;
            }

            try {
                $date = new DateTimeImmutable($at);
            } catch (Throwable $e) {
                continue;
            }

            if (! isset($tzAllowed[$date->setTimezone($tz)->format('Y-m-d')])) {
                continue;
            }

            if ($latestAt === null || $date > $latestAt) {
                $latestAt = $date;
                $latest   = $row['status'];
            }
        }

        return $latest === null ? null : ($latest === 'ok');
    }

    /* ═══════════════════════ Periode ═══════════════════════ */

    /**
     * Hari yang sudah selesai pada periode. Bulan berjalan berhenti di
     * kemarin supaya backup/cron hari ini yang belum jalan tidak dihitung gagal.
     *
     * @return list<string>
     */
    public static function monitoredDays(int $year, int $month, DateTimeImmutable $now, string $timezone): array
    {
        $tz         = self::safeTimezone($timezone);
        $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $today      = $now->setTimezone($tz);
        $target     = $monthStart->format('Y-m');

        if ($target > $today->format('Y-m')) {
            return [];
        }

        $lastDay = $target === $today->format('Y-m')
            ? (int) $today->format('j') - 1
            : (int) $monthStart->format('t');

        $days = [];
        for ($day = 1; $day <= $lastDay; $day++) {
            $days[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return $days;
    }

    /* ═══════════════════════ HTTP ═══════════════════════ */

    /**
     * @param list<string> $headers
     *
     * @return array{status:int,body:string}
     */
    private function request(string $method, string $url, array $headers, ?string $body): array
    {
        if ($this->http !== null) {
            return (array) call_user_func($this->http, $method, $url, $headers, $body);
        }

        if (! function_exists('curl_init')) {
            return $this->streamRequest($method, $url, $headers, $body);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Tidak bisa membuat koneksi HTTP.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);
        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error  = curl_error($handle);
        curl_close($handle);

        if ($result === false) {
            throw new RuntimeException('HTTP gagal: ' . $error);
        }

        return ['status' => $status, 'body' => (string) $result];
    }

    /**
     * @param list<string> $headers
     *
     * @return array{status:int,body:string}
     */
    private function streamRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body ?? '',
                'ignore_errors' => true,
                'timeout'       => 30,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            throw new RuntimeException('HTTP gagal: ' . $url);
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return ['status' => $status, 'body' => $result];
    }

    private function timezone(): DateTimeZone
    {
        return self::safeTimezone((string) $this->config['timezone']);
    }

    private static function safeTimezone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Throwable $e) {
            return new DateTimeZone('Asia/Jakarta');
        }
    }
}
