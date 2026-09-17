<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model tracking job/snapshot Bright Data (async trigger).
 *
 * snapshot_id = identifier job dari Bright Data (selalu `sd_...` dinamis).
 * UNIQUE di kolom snapshot_id — reprocess response tidak membuat duplicate.
 */
class ModelSocialMediaScrapeJob extends Model
{
    protected $DBGroup = 'default';

    protected $table         = 'social_media_scrape_jobs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'social_media_account_id',
        'platform',
        'dataset_id',
        'snapshot_id',
        'status',
        'requested_at',
        'completed_at',
        'error_message',
    ];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_RUNNING    = 'running';
    public const STATUS_READY      = 'ready';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    /**
     * Ambil batch job yang siap dicek/diproses (pending / processing / running).
     *
     * @param int $limit
     * @return object[]
     */
    public function getPendingBatch(int $limit = 10): array
    {
        return $this->select('social_media_scrape_jobs.*, social_media_accounts.account_name, social_media_accounts.profile_url')
            ->join('social_media_accounts', 'social_media_accounts.id = social_media_scrape_jobs.social_media_account_id', 'left')
            ->whereIn('social_media_scrape_jobs.status', [
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                self::STATUS_RUNNING,
            ])
            ->orderBy('social_media_scrape_jobs.id', 'ASC')
            ->limit($limit)
            ->find();
    }

    /** Cari job berdasar snapshot_id dari Bright Data. */
    public function findBySnapshot(string $snapshotId): ?object
    {
        return $this->where('snapshot_id', $snapshotId)->first();
    }

    /**
     * Simpan job hasil trigger — IDEMPOTEN per snapshot_id.
     * snapshot_id yang sudah tercatat tidak dibuat duplikat.
     *
     * @return int id job (existing bila snapshot_id sama)
     */
    public function upsertTriggeredJob(int $accountId, string $platform, string $datasetId, string $snapshotId, string $status = self::STATUS_PENDING): int
    {
        $existing = $this->findBySnapshot($snapshotId);
        if ($existing) {
            return (int)$existing->id;
        }

        return (int)$this->insert([
            'social_media_account_id' => $accountId,
            'platform'                => $platform,
            'dataset_id'              => $datasetId,
            'snapshot_id'             => $snapshotId,
            'status'                  => $status,
            'requested_at'            => date('Y-m-d H:i:s'),
        ]);
    }

    /** Update status progress (misal 'processing' atau 'running'). */
    public function markStatus(int $jobId, string $status): bool
    {
        return $this->update($jobId, [
            'status' => $status,
        ]);
    }

    /** Tandai job berhasil selesai di-download & diparsing. */
    public function markCompleted(int $jobId): bool
    {
        return $this->update($jobId, [
            'status'       => self::STATUS_COMPLETED,
            'completed_at' => date('Y-m-d H:i:s'),
            'error_message'=> null,
        ]);
    }

    /** Tandai job gagal (untuk proses error saat trigger/process). */
    public function markFailed(int $jobId, string $message): bool
    {
        return $this->update($jobId, [
            'status'        => self::STATUS_FAILED,
            'error_message' => $message,
            'completed_at'  => date('Y-m-d H:i:s'),
        ]);
    }
}