<?php

namespace Tests\SocialMedia;

use App\Models\ModelSocialMediaAccount;
use App\Models\ModelSocialMediaPost;
use App\Models\ModelSocialMediaSnapshot;
use App\Models\ModelSocialMediaTarget;
use App\Services\SocialMedia\SocialMediaKpiService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Test KPI service: filter unit/periode/platform, target achievement,
 * snapshot terbaru tanpa double counting.
 */
class KpiServiceTest extends CIUnitTestCase
{
    private ModelSocialMediaAccount $accountModel;
    private ModelSocialMediaPost $postModel;
    private ModelSocialMediaSnapshot $snapshotModel;
    private ModelSocialMediaTarget $targetModel;

    /** @var int[] */
    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountModel  = new ModelSocialMediaAccount();
        $this->postModel     = new ModelSocialMediaPost();
        $this->snapshotModel = new ModelSocialMediaSnapshot();
        $this->targetModel   = new ModelSocialMediaTarget();
    }

    protected function tearDown(): void
    {
        // Bersihkan target periode khusus test.
        $this->targetModel->where('period_month', '2099-01')->delete();

        foreach ($this->accounts as $id) {
            $this->accountModel->delete($id);
        }
        parent::tearDown();
    }

    private function createAccount(int $unitId, string $platform): int
    {
        $id = $this->accountModel->insert([
            'unit_id'       => $unitId,
            'platform'      => $platform,
            'account_name'  => 'TEST-KPI-' . $platform,
            'profile_url'   => 'https://example.test/' . $platform . '/' . uniqid(),
            'provider'      => 'bright_data',
            'is_active'     => 1,
        ]);
        $this->accounts[] = (int)$id;
        return (int)$id;
    }

    private function addPost(int $accountId, string $platform, string $extId, string $publishedAt): int
    {
        return $this->postModel->upsert($accountId, $platform, [
            'external_post_id' => $extId,
            'post_url'         => "https://example.test/p/{$extId}",
            'caption'          => 'kpi test ' . $extId,
            'published_at'     => $publishedAt,
        ]);
    }

    public function testFilterUnitDanPeriode(): void
    {
        $accJ = $this->createAccount(2, 'facebook');
        $accB = $this->createAccount(3, 'facebook');

        $this->addPost($accJ, 'facebook', 'j-sep', '2026-09-10 09:00:00');
        $this->addPost($accB, 'facebook', 'b-sep', '2026-09-11 09:00:00');
        $this->addPost($accJ, 'facebook', 'j-agu', '2026-08-10 09:00:00');

        $snap = $this->snapshotModel;
        foreach (['j-sep', 'b-sep'] as $ext) {
            $post = $this->postModel->findByPostIdentity(($ext[0] === 'j') ? $accJ : $accB, $ext);
            $snap->insertSnapshot($post->id, '2026-09-15 10:00:00', ['likes' => 10]);
        }

        $kpi = new SocialMediaKpiService($this->snapshotModel, $this->targetModel, $this->postModel);

        // Filter unit 2 → hanya 1 post (Sep)
        $t = $kpi->totals(9, 2026, [2], 'facebook');
        $this->assertSame(1, $t['posts']);
        $this->assertSame(10.0, $t['likes']);

        // Periode Agustus → 1 post (j-agu) tapi tanpa snapshot → likes 0
        $ta = $kpi->totals(8, 2026, [2], 'facebook');
        $this->assertSame(1, $ta['posts']);
        $this->assertSame(0.0, $ta['likes']);
    }

    public function testSnapshotTerbaruTidakDoubleCounting(): void
    {
        $acc = $this->createAccount(1, 'facebook');
        $postId = $this->addPost($acc, 'facebook', 'double-1', '2026-09-05 09:00:00');

        // Dua snapshot berbeda waktu (safeguard: di test ini tidak pakai unique clash)
        $this->snapshotModel->insertSnapshot($postId, '2026-09-14 10:00:00', ['likes' => 100, 'views' => 1000]);
        $this->snapshotModel->insertSnapshot($postId, '2026-09-21 10:00:00', ['likes' => 150, 'views' => 1200]);

        $kpi = new SocialMediaKpiService($this->snapshotModel, $this->targetModel, $this->postModel);
        $t = $kpi->totals(9, 2026, [1], 'facebook');

        // Pakai snapshot TERBARU: 150, bukan 100+150=250.
        $this->assertSame(150.0, $t['likes']);
        $this->assertSame(1200.0, $t['views']);
    }

    public function testPlatformFilter(): void
    {
        $accFb = $this->createAccount(1, 'facebook');
        $accTt = $this->createAccount(1, 'tiktok');

        $pFb = $this->addPost($accFb, 'facebook', 'fb-1', '2026-09-01 08:00:00');
        $pTt = $this->addPost($accTt, 'tiktok', 'tt-1', '2026-09-02 08:00:00');

        $this->snapshotModel->insertSnapshot($pFb, '2026-09-15 10:00:00', ['likes' => 5]);
        $this->snapshotModel->insertSnapshot($pTt, '2026-09-15 10:00:00', ['likes' => 7]);

        $kpi = new SocialMediaKpiService($this->snapshotModel, $this->targetModel, $this->postModel);

        $t = $kpi->totals(9, 2026, [1], 'facebook');
        $this->assertSame(1, $t['posts']);
        $this->assertSame(5.0, $t['likes']);

        $t2 = $kpi->totals(9, 2026, [1], 'tiktok');
        $this->assertSame(1, $t2['posts']);
        $this->assertSame(7.0, $t2['likes']);
    }

    public function testTargetAchievement(): void
    {
        $acc = $this->createAccount(1, 'facebook');
        $postId = $this->addPost($acc, 'facebook', 'target-1', '2099-01-01 08:00:00');
        $this->snapshotModel->insertSnapshot($postId, '2099-01-15 10:00:00', ['likes' => 200]);

        $this->targetModel->upsertTarget('2099-01', 1, 'facebook', 'likes', 100);

        $kpi = new SocialMediaKpiService($this->snapshotModel, $this->targetModel, $this->postModel);
        $r = $kpi->metricAchievement(1, 2099, 1, 'facebook', 'likes');

        $this->assertSame(200.0, $r['actual']);
        $this->assertSame(100.0, $r['target']);
        $this->assertSame(200.0, $r['achievement']);
    }

    public function testTargetGlobalFallback(): void
    {
        $acc = $this->createAccount(2, 'facebook');
        $postId = $this->addPost($acc, 'facebook', 'glob-1', '2099-01-01 08:00:00');
        $this->snapshotModel->insertSnapshot($postId, '2099-01-15 10:00:00', ['shares' => 50]);

        // Target global (unit_id 0) untuk Jember yang tak punya target khusus
        $this->targetModel->upsertTarget('2099-01', 0, 'facebook', 'shares', 25);

        $kpi = new SocialMediaKpiService($this->snapshotModel, $this->targetModel, $this->postModel);
        $r = $kpi->metricAchievement(1, 2099, 2, 'facebook', 'shares');

        $this->assertSame(50.0, $r['actual']);
        $this->assertSame(25.0, $r['target']);
        $this->assertSame(200.0, $r['achievement']);
    }
}