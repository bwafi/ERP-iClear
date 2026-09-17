<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model Social Media Metric Snapshot.
 *
 * Setiap scraping menyimpan snapshot BARU (tidak pernah overwrite yang lama).
 * NULL = metric tidak tersedia; 0 = metric tersedia dan nilainya 0.
 *
 * Identity snapshot = (social_media_post_id + captured_at) — retry job yang
 * sama (captured_at sama / sama menit) tidak membuat snapshot duplicate.
 */
class ModelSocialMediaSnapshot extends Model
{
    protected $DBGroup = 'default';

    protected $table         = 'social_media_metric_snapshots';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'social_media_post_id',
        'captured_at',
        'views',
        'plays',
        'likes',
        'comments',
        'shares',
        'saves',
    ];

    /**
     * Insert snapshot. Idempoten terhadap (post_id, captured_at)^.
     * Returns true bila terinsert, false bila sudah ada (duplicate retry).
     */
    public function insertSnapshot(int $postId, string $capturedAt, array $metrics): bool
    {
        $exists = $this->where('social_media_post_id', $postId)
            ->where('captured_at', $capturedAt)
            ->countAllResults();

        if ($exists > 0) {
            return false;
        }

        $data = [
            'social_media_post_id' => $postId,
            'captured_at'          => $capturedAt,
            'views'                => $this->nullableInt($metrics['views'] ?? null),
            'plays'                => $this->nullableInt($metrics['plays'] ?? null),
            'likes'                => $this->nullableInt($metrics['likes'] ?? null),
            'comments'             => $this->nullableInt($metrics['comments'] ?? null),
            'shares'               => $this->nullableInt($metrics['shares'] ?? null),
            'saves'                => $this->nullableInt($metrics['saves'] ?? null),
        ];

        $this->insert($data);
        return true;
    }

    /**
     * Ambil snapshot TERBARU per post (≤ capturedAt maksimum dalam periode).
     * Diagregasi per metric -> hindari double counting metric kumulatif.
     */
    public function latestSnapshotTotals(int $month, int $year, ?array $unitIds = null, ?string $platform = null): array
    {
        $db = $this->db;

        $sql = "SELECT
                    COALESCE(SUM(sn.views), 0)    AS views,
                    COALESCE(SUM(sn.plays), 0)    AS plays,
                    COALESCE(SUM(sn.likes), 0)    AS likes,
                    COALESCE(SUM(sn.comments), 0) AS comments,
                    COALESCE(SUM(sn.shares), 0)   AS shares,
                    COALESCE(SUM(sn.saves), 0)    AS saves
                FROM (
                    SELECT sms.social_media_post_id, MAX(sms.captured_at) AS cap
                    FROM social_media_metric_snapshots sms
                    JOIN social_media_posts sp ON sp.id = sms.social_media_post_id
                    JOIN social_media_accounts a ON a.id = sp.social_media_account_id
                    WHERE DATE_FORMAT(sp.published_at, '%Y-%m') = ?
                      AND a.is_active = 1
                    GROUP BY sms.social_media_post_id
                ) AS latest
                JOIN social_media_metric_snapshots sn
                  ON sn.social_media_post_id = latest.social_media_post_id
                 AND sn.captured_at = latest.cap
                JOIN social_media_posts sp2 ON sp2.id = sn.social_media_post_id
                JOIN social_media_accounts a2 ON a2.id = sp2.social_media_account_id
                WHERE 1=1";

        $params = [sprintf('%04d-%02d', $year, $month)];

        if ($unitIds !== null && $unitIds !== []) {
            $placeholders = rtrim(str_repeat('?,', count($unitIds)), ',');
            $sql .= " AND a2.unit_id IN ({$placeholders})";
            array_push($params, ...array_map('intval', $unitIds));
        }
        if ($platform !== null && $platform !== '') {
            $sql .= ' AND sp2.platform = ?';
            $params[] = $platform;
        }

        $row = $db->query($sql, $params)->getRow();

        return [
            'views'    => (float)($row->views ?? 0),
            'plays'    => (float)($row->plays ?? 0),
            'likes'    => (float)($row->likes ?? 0),
            'comments' => (float)($row->comments ?? 0),
            'shares'   => (float)($row->shares ?? 0),
            'saves'    => (float)($row->saves ?? 0),
        ];
    }

    /** Per-unit total untuk tabel/summary KPI. */
    public function latestSnapshotByUnit(int $month, int $year, ?array $unitIds = null, ?string $platform = null): array
    {
        $db = $this->db;

        $sql = "SELECT a2.unit_id,
                    COALESCE(SUM(sn.views), 0)    AS views,
                    COALESCE(SUM(sn.plays), 0)    AS plays,
                    COALESCE(SUM(sn.likes), 0)    AS likes,
                    COALESCE(SUM(sn.comments), 0) AS comments,
                    COALESCE(SUM(sn.shares), 0)   AS shares,
                    COALESCE(SUM(sn.saves), 0)    AS saves,
                    COUNT(DISTINCT sn.social_media_post_id) AS posts
                FROM (
                    SELECT sms.social_media_post_id, MAX(sms.captured_at) AS cap
                    FROM social_media_metric_snapshots sms
                    JOIN social_media_posts sp ON sp.id = sms.social_media_post_id
                    JOIN social_media_accounts a ON a.id = sp.social_media_account_id
                    WHERE DATE_FORMAT(sp.published_at, '%Y-%m') = ?
                      AND a.is_active = 1
                    GROUP BY sms.social_media_post_id
                ) AS latest
                JOIN social_media_metric_snapshots sn
                  ON sn.social_media_post_id = latest.social_media_post_id
                 AND sn.captured_at = latest.cap
                JOIN social_media_posts sp2 ON sp2.id = sn.social_media_post_id
                JOIN social_media_accounts a2 ON a2.id = sp2.social_media_account_id
                WHERE 1=1";

        $params = [sprintf('%04d-%02d', $year, $month)];

        if ($unitIds !== null && $unitIds !== []) {
            $placeholders = rtrim(str_repeat('?,', count($unitIds)), ',');
            $sql .= " AND a2.unit_id IN ({$placeholders})";
            array_push($params, ...array_map('intval', $unitIds));
        }
        if ($platform !== null && $platform !== '') {
            $sql .= ' AND sp2.platform = ?';
            $params[] = $platform;
        }

        $sql .= ' GROUP BY a2.unit_id';

        return $db->query($sql, $params)->getResultArray();
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }
}