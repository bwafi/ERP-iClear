<?php

namespace App\Services\SocialMedia;

use App\Models\ModelSocialMediaSnapshot;
use App\Models\ModelSocialMediaTarget;
use App\Models\ModelSocialMediaPost;

/**
 * SocialMediaKpiService — agregasi metric KPI Social Media.
 *
 * SUMBER METRIC = social_media_posts (published_at) + social_media_metric_snapshots
 * (snapshot terbaru per post) — BUKAN input metric manual.
 *
 * Metric kumulatif (views/likes/...) TIDAK dijumlah seluruh snapshot agar tidak
 * menghitung metric yang sama berkali-kali; cukup snapshot terakhir per post.
 *
 * Filter: Unit (social_media_accounts.unit_id), Platform, Periode, Metric.
 */
class SocialMediaKpiService
{
    public const METRICS = ['views', 'plays', 'likes', 'comments', 'shares', 'saves'];
    public const PLATFORMS = ['facebook', 'tiktok'];

    private ModelSocialMediaSnapshot $snapshotModel;
    private ModelSocialMediaTarget $targetModel;
    private ModelSocialMediaPost $postModel;

    public function __construct(
        ?ModelSocialMediaSnapshot $snapshotModel = null,
        ?ModelSocialMediaTarget $targetModel = null,
        ?ModelSocialMediaPost $postModel = null
    ) {
        $this->snapshotModel = $snapshotModel ?? new ModelSocialMediaSnapshot();
        $this->targetModel   = $targetModel ?? new ModelSocialMediaTarget();
        $this->postModel     = $postModel ?? new ModelSocialMediaPost();
    }

    /**
     * Total metric satu periode (snapshot terbaru per post), opsi filter.
     *
     * @return array{views:float,plays:float,likes:float,comments:float,shares:float,saves:float,posts:int}
     */
    public function totals(int $month, int $year, ?array $unitIds = null, ?string $platform = null): array
    {
        $snap = $this->snapshotModel->latestSnapshotTotals($month, $year, $unitIds, $platform);
        $posts = $this->postModel->findPostsInPeriod($month, $year, $unitIds, $platform);

        return array_merge($snap, ['posts' => count($posts)]);
    }

    /** Total per unit (untuk ringkasan tabel KPI). */
    public function totalsByUnit(int $month, int $year, ?array $unitIds = null, ?string $platform = null): array
    {
        return $this->snapshotModel->latestSnapshotByUnit($month, $year, $unitIds, $platform);
    }

    /**
     * Achievement (%) satu metric terhadap target periode.
     * Target unit-spesifik lebih diutamakan, fallback target global (unit_id 0).
     * Semua metric social media higher-is-better.
     *
     * @return array{actual:float,target:float,achievement:?float}
     */
    public function metricAchievement(int $month, int $year, int $unitId, string $platform, string $metric): array
    {
        $platform   = strtolower($platform);
        $periodMonth = sprintf('%04d-%02d', $year, $month);

        $totals = $this->totals($month, $year, [$unitId], $platform);
        $actual = (float)($totals[$metric] ?? 0);

        $target = $this->targetModel->findTarget($periodMonth, $unitId, $platform, $metric);

        $targetValue = $target ? (float)$target->target_value : 0.0;

        if ($targetValue <= 0) {
            return ['actual' => $actual, 'target' => 0, 'achievement' => null];
        }

        $achievement = ($actual / $targetValue) * 100;

        return ['actual' => $actual, 'target' => $targetValue, 'achievement' => round($achievement, 2)];
    }

    /**
     * Ringkasan KPI per (unit × platform): seluruh metric + achievement & posts.
     */
    public function summary(int $month, int $year, ?array $unitIds = null, ?string $platform = null): array
    {
        $rows = $this->totalsByUnit($month, $year, $unitIds, $platform);

        $out = [];
        $platforms = $platform ? [$platform, null] : self::PLATFORMS;

        foreach ($rows as $row) {
            $unitId = (int)$row['unit_id'];
            foreach (self::PLATFORMS as $p) {
                $used = $platform ? ($platform === $p) : true;
                if (!$used) {
                    continue;
                }
                $item = [
                    'unit_id'  => $unitId,
                    'platform' => $p,
                    'posts'    => (int)$row['posts'],
                ];
                foreach (self::METRICS as $metric) {
                    $actual = (float)$row[$metric];
                    $ach = $this->metricAchievement($month, $year, $unitId, $p, $metric);
                    $item['metrics'][$metric] = [
                        'actual'      => $actual,
                        'target'      => $ach['target'],
                        'achievement' => $ach['achievement'],
                    ];
                }
                $out[] = $item;
            }
        }

        return $out;
    }
}