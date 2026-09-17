<?php

namespace App\Services\SocialMedia;

use App\Models\ModelSocialMediaAccount;
use App\Models\ModelSocialMediaPost;
use App\Models\ModelSocialMediaScrapeJob;
use App\Models\ModelSocialMediaSnapshot;

/**
 * SocialMediaScraperService — orkestrasi scraping (ASYNC TRIGGER).
 *
 * Flow:
 *   akun aktif (bright_data) → provider→trigger() → snapshot_id → simpan job → selesai
 *
 * Command `social:pull` TIDAK menunggu snapshot ready / tidak download data.
 * Proses ambil+normalisasi hasil (GET progress/snapshot) dilakukan processor
 * terpisah (social:process) menggunakan baris di social_media_scrape_jobs.
 *
 * Tanggung jawab:
 *   - account: ambil akun aktif dari DB (tidak pernah di-hardcode jumlah/URL)
 *   - trigger: per akun, satu gagal → akun lain tetap lanjut
 *   - job: simpan snapshot_id idempoten (UNIQUE snapshot_id)
 *   - post+snapshot storage tetap ada (dipakai processor hasil, bukan social:pull)
 *   - error handling: 400/401/404/429/500/timeout/invalid JSON/DB error
 *
 * TIDAK berisi token/URL akun/dataset di kode.
 */
class SocialMediaScraperService
{
    private ModelSocialMediaAccount $accountModel;
    private ModelSocialMediaPost $postModel;
    private ModelSocialMediaSnapshot $snapshotModel;
    private ModelSocialMediaScrapeJob $jobModel;

    /** @var array<string,SocialMediaProviderInterface> */
    private array $providers = [];

    public function __construct(
        ?ModelSocialMediaAccount $accountModel = null,
        ?ModelSocialMediaPost $postModel = null,
        ?ModelSocialMediaSnapshot $snapshotModel = null,
        ?ModelSocialMediaScrapeJob $jobModel = null
    ) {
        $this->accountModel  = $accountModel ?? new ModelSocialMediaAccount();
        $this->postModel     = $postModel ?? new ModelSocialMediaPost();
        $this->snapshotModel = $snapshotModel ?? new ModelSocialMediaSnapshot();
        $this->jobModel      = $jobModel ?? new ModelSocialMediaScrapeJob();
    }

    public function registerProvider(SocialMediaProviderInterface $provider): void
    {
        $this->providers[$provider->providerKey()] = $provider;
    }

    public function hasProvider(string $providerKey): bool
    {
        return isset($this->providers[$providerKey]);
    }

    /**
     * TRIGGER ASYNC SEMUA akun aktif provider tertentu.
     * Selesai langsung tanpa polling / menunggu snapshot ready.
     *
     * @param string|null $provider  provider key; null → semua provider terdaftar
     * @param array|null  $platforms filter platform (default semua)
     *
     * @return array{
     *   ok: int,
     *   failed: int,
     *   errors: array<int,array{account:int,message:string}>,
     *   results: array<int,array{account:int,account_name:string,platform:string,snapshot_id:string|null,error_message:string|null}>
     * }
     */
    public function triggerAll(?string $provider = null, ?array $platforms = null): array
    {
        $result      = ['ok' => 0, 'failed' => 0, 'errors' => [], 'results' => []];
        $providerKey = $provider ?? 'bright_data';

        if (!$this->hasProvider($providerKey)) {
            throw new SocialMediaScraperException("Provider '{$providerKey}' belum terdaftar.");
        }

        $accounts = $this->accountModel->activeByProvider($providerKey, $platforms);
        if ($accounts === []) {
            log_message('info', "[SocialMediaScraper] tidak ada akun aktif untuk provider {$providerKey}.");
            return $result;
        }

        foreach ($accounts as $account) {
            $accountId   = (int)$account->id;
            $accountName = (string)($account->account_name ?? '');
            $platform    = (string)$account->platform;

            try {
                $triggered  = $this->providers[$providerKey]->trigger($account);
                $snapshotId = trim((string)($triggered['snapshot_id'] ?? ''));
                $datasetId  = trim((string)($triggered['dataset_id'] ?? ''));

                if ($snapshotId === '') {
                    throw new BrightDataApiException('Response trigger tidak memuat snapshot_id.');
                }

                $this->jobModel->upsertTriggeredJob($accountId, $platform, $datasetId, $snapshotId);

                $result['ok']++;
                $result['results'][] = [
                    'account'       => $accountId,
                    'account_name'  => $accountName,
                    'platform'      => $platform,
                    'snapshot_id'   => $snapshotId,
                    'error_message' => null,
                ];
            } catch (\Throwable $e) {
                $message = $this->sanitizeMessage($e->getMessage());
                $result['failed']++;
                $result['errors'][] = ['account' => $accountId, 'message' => $message];
                $result['results'][] = [
                    'account'       => $accountId,
                    'account_name'  => $accountName,
                    'platform'      => $platform,
                    'snapshot_id'   => null,
                    'error_message' => $message,
                ];
                log_message('error', "[SocialMediaScraper] akun #{$accountId} ({$platform}): {$message}");
            }
        }

        log_message('info', "[SocialMediaScraper] trigger selesai: {$result['ok']} akun ok, {$result['failed']} gagal.");
        return $result;
    }

    /**
     * Simplifikator: hasil trigger yang siap dipakai processor (social:process).
     * Daftar job berstatus PENDING/RUNNING per (opsional) platform.
     *
     * @return object[]
     */
    public function pendingJobs(?array $platforms = null): array
    {
        $builder = $this->jobModel
            ->whereIn('status', [ModelSocialMediaScrapeJob::STATUS_PENDING, ModelSocialMediaScrapeJob::STATUS_PROCESSING, ModelSocialMediaScrapeJob::STATUS_RUNNING])
            ->orderBy('requested_at', 'ASC');
        if ($platforms !== null && $platforms !== []) {
            $builder->whereIn('platform', $platforms);
        }

        return $builder->findAll();
    }

    /**
     * PROSES BATCH JOB (social:process):
     * Cek status progress Bright Data per job dalam batch.
     * Jika ready -> download snapshot -> normalize -> storePosts -> markCompleted.
     * Jika starting/running -> biarkan job tetap pending/processing.
     * Jika failed -> markFailed.
     * Selesai dalam 1 kali cek batch (tanpa loop/polling sleep).
     *
     * @param int $limit Max job yang diproses per pemanggilan
     * @param string|null $provider
     * @return array{
     *   total: int,
     *   completed: int,
     *   waiting: int,
     *   failed: int,
     *   results: array<int,array{job_id:int,account_id:int,account_name:string,platform:string,snapshot_id:string,status:string,message:?string,posts_stored:int}>
     * }
     */
    public function processPendingBatch(int $limit = 10, ?string $provider = null): array
    {
        $providerKey = $provider ?? 'bright_data';
        if (!$this->hasProvider($providerKey)) {
            throw new SocialMediaScraperException("Provider '{$providerKey}' belum terdaftar.");
        }

        $providerInstance = $this->providers[$providerKey];
        $jobs = $this->jobModel->getPendingBatch($limit);

        $summary = [
            'total'     => count($jobs),
            'completed' => 0,
            'waiting'   => 0,
            'failed'    => 0,
            'results'   => [],
        ];

        foreach ($jobs as $job) {
            $jobId       = (int)$job->id;
            $accountId   = (int)$job->social_media_account_id;
            $accountName = (string)($job->account_name ?? 'Akun #' . $accountId);
            $platform    = (string)$job->platform;
            $snapshotId  = (string)$job->snapshot_id;

            try {
                // 1. Cek status progress
                $progressStatus = $providerInstance->fetchProgress($snapshotId);

                if (in_array($progressStatus, ['starting', 'running'], true)) {
                    // Update ke running/processing jika sebelumnya pending
                    $this->jobModel->markStatus($jobId, ModelSocialMediaScrapeJob::STATUS_PROCESSING);
                    $summary['waiting']++;
                    $summary['results'][] = [
                        'job_id'       => $jobId,
                        'account_id'   => $accountId,
                        'account_name' => $accountName,
                        'platform'     => $platform,
                        'snapshot_id'  => $snapshotId,
                        'status'       => $progressStatus,
                        'message'      => "Snapshot masih dalam status: {$progressStatus}",
                        'posts_stored' => 0,
                    ];
                    continue;
                }

                if ($progressStatus === 'failed') {
                    $this->jobModel->markFailed($jobId, 'Bright Data mengindikasikan snapshot gagal.');
                    $summary['failed']++;
                    $summary['results'][] = [
                        'job_id'       => $jobId,
                        'account_id'   => $accountId,
                        'account_name' => $accountName,
                        'platform'     => $platform,
                        'snapshot_id'  => $snapshotId,
                        'status'       => 'failed',
                        'message'      => 'Bright Data mengindikasikan snapshot gagal.',
                        'posts_stored' => 0,
                    ];
                    continue;
                }

                if ($progressStatus === 'ready') {
                    // 2. Download dan normalisasikan
                    $posts = $providerInstance->fetchAndNormalizeSnapshot($platform, $snapshotId);

                    // 3. Simpan posts & snapshots
                    $capturedAt = date('Y-m-d H:i:s');
                    $storedCount = $this->storePosts($accountId, $platform, $posts, $capturedAt);

                    // 4. Tandai job completed
                    $this->jobModel->markCompleted($jobId);

                    $summary['completed']++;
                    $summary['results'][] = [
                        'job_id'       => $jobId,
                        'account_id'   => $accountId,
                        'account_name' => $accountName,
                        'platform'     => $platform,
                        'snapshot_id'  => $snapshotId,
                        'status'       => 'completed',
                        'message'      => "Berhasil memproses {$storedCount} post.",
                        'posts_stored' => $storedCount,
                    ];
                }
            } catch (\Throwable $e) {
                $err = $this->sanitizeMessage($e->getMessage());
                $this->jobModel->markFailed($jobId, $err);
                $summary['failed']++;
                $summary['results'][] = [
                    'job_id'       => $jobId,
                    'account_id'   => $accountId,
                    'account_name' => $accountName,
                    'platform'     => $platform,
                    'snapshot_id'  => $snapshotId,
                    'status'       => 'failed',
                    'message'      => $err,
                    'posts_stored' => 0,
                ];
                log_message('error', "[SocialMediaProcess] Job #{$jobId} (Snapshot {$snapshotId}) gagal: {$err}");
            }
        }

        return $summary;
    }

    /**
     * Simpan daftar post ternormalisasi: upsert post + insert snapshot.
     * (Dipakai processor hasil; TIDAK dipanggil oleh social:pull.)
     *
     * @return int jumlah post yang tersimpan (snapshot terinsert)
     */
    public function storePosts(int $accountId, string $platform, array $posts, ?string $capturedAt = null): int
    {
        $capturedAt = $capturedAt ?? date('Y-m-d H:i:s');
        $stored     = 0;
        $skipped    = 0;

        foreach ($posts as $post) {
            if (!$this->isPostInWindow($post)) {
                $skipped++;
                continue;
            }
            $postId = $this->postModel->upsert($accountId, $platform, $post);
            $snapshotInserted = $this->snapshotModel->insertSnapshot($postId, $capturedAt, $post);
            if ($snapshotInserted) {
                $stored++;
            }
        }

        if ($skipped > 0) {
            log_message('info', "[SocialMediaScraper] akun #{$accountId}: {$skipped} post di luar rentang bulan di-skip.");
        }

        return $stored;
    }

    /**
     * Filter tanggal AMAN di sisi DB: simpan hanya post dalam rentang bulan
     * berjalan (server). Bisa dioverride BRIGHT_DATA_START_DATE/END_DATE
     * (format m-d-Y) bila butuh periode selain bulan ini.
     */
    private function isPostInWindow(array $post): bool
    {
        $start = $this->windowBoundary('BRIGHT_DATA_START_DATE', date('m-01-Y'));
        $end   = $this->windowBoundary('BRIGHT_DATA_END_DATE', date('m-t-Y'));

        if ($start === null || $end === null) {
            return true;
        }

        $posted = (string)($post['date_posted'] ?? $post['published_at'] ?? '');
        $t = $posted !== '' ? strtotime($posted) : false;

        if ($t === false) {
            return true; // tanggal tak dikenal → simpan apa adanya
        }

        return $t >= $start && $t <= $end + 86399; // sampai akhir hari
    }

    private function windowBoundary(string $envKey, string $default): ?int
    {
        $v = trim((string)env($envKey, ''));
        if ($v === '' || !preg_match('/^\d{2}-\d{2}-\d{4}$/', $v)) {
            $v = $default; // otomatis = bulan berjalan dari server
        }
        $boundary = \DateTime::createFromFormat('m-d-Y', $v);

        return $boundary === false ? null : $boundary->getTimestamp();
    }

    /**
     * Pastikan pesan error TIDAK mengandung API token.
     */
    private function sanitizeMessage(string $message): string
    {
        $token = (string)env('BRIGHT_DATA_TOKEN');
        if ($token !== '' && $message !== '') {
            $message = str_replace($token, '[REDACTED]', $message);
        }
        return $message;
    }
}