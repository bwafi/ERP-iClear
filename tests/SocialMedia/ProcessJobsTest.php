<?php

namespace Tests\SocialMedia;

use App\Models\ModelSocialMediaAccount;
use App\Models\ModelSocialMediaPost;
use App\Models\ModelSocialMediaScrapeJob;
use App\Models\ModelSocialMediaSnapshot;
use App\Services\SocialMedia\BrightDataApiException;
use App\Services\SocialMedia\SocialMediaProviderInterface;
use App\Services\SocialMedia\SocialMediaScraperService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fake provider untuk testing process batch.
 */
class FakeProcessProvider implements SocialMediaProviderInterface
{
    /** @var array<string,string> snapshot_id => progress status ('ready'|'running'|'starting'|'failed') */
    public array $progressMap = [];

    /** @var array<string,array> snapshot_id => raw rows */
    public array $snapshotMap = [];

    /** @var array<string,bool> snapshot_id => throw exception */
    public array $throwMap = [];

    public int $progressCalls = 0;
    public int $snapshotCalls = 0;

    public function providerKey(): string
    {
        return 'test-prov';
    }

    public function trigger(object $account): array
    {
        return ['snapshot_id' => 'sd_trig_' . $account->id, 'dataset_id' => 'ds_1'];
    }

    public function fetchProgress(string $snapshotId): string
    {
        $this->progressCalls++;
        if (!empty($this->throwMap[$snapshotId])) {
            throw new BrightDataApiException("API error saat progress {$snapshotId}");
        }
        return $this->progressMap[$snapshotId] ?? 'running';
    }

    public function fetchAndNormalizeSnapshot(string $platform, string $snapshotId): array
    {
        $this->snapshotCalls++;
        if (!empty($this->throwMap[$snapshotId])) {
            throw new BrightDataApiException("API error saat snapshot {$snapshotId}");
        }
        return $this->snapshotMap[$snapshotId] ?? [];
    }
}

/**
 * Test suite lengkap untuk Social Media Processor (social:process).
 */
class ProcessJobsTest extends CIUnitTestCase
{
    private ModelSocialMediaAccount $accountModel;
    private ModelSocialMediaScrapeJob $jobModel;
    private ModelSocialMediaPost $postModel;
    private ModelSocialMediaSnapshot $snapshotModel;
    private FakeProcessProvider $provider;
    private SocialMediaScraperService $service;

    private array $createdAccounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountModel  = new ModelSocialMediaAccount();
        $this->jobModel      = new ModelSocialMediaScrapeJob();
        $this->postModel     = new ModelSocialMediaPost();
        $this->snapshotModel = new ModelSocialMediaSnapshot();
        $this->provider      = new FakeProcessProvider();

        $this->service = new SocialMediaScraperService(
            $this->accountModel,
            $this->postModel,
            $this->snapshotModel,
            $this->jobModel
        );
        $this->service->registerProvider($this->provider);

        // Bersihkan job yang dibuat oleh test sebelumnya
        $db = \Config\Database::connect('default');
        $db->table('social_media_scrape_jobs')->like('snapshot_id', 'sd_', 'after')->delete();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('default');
        foreach ($this->createdAccounts as $accId) {
            $db->table('social_media_scrape_jobs')->where('social_media_account_id', $accId)->delete();
            $this->accountModel->delete($accId);
        }
        $db->table('social_media_scrape_jobs')->like('snapshot_id', 'sd_', 'after')->delete();
        parent::tearDown();
    }

    private function createAccount(string $name = 'Test Acc', string $platform = 'facebook'): int
    {
        $id = (int)$this->accountModel->insert([
            'unit_id'      => 1,
            'platform'     => $platform,
            'account_name' => $name,
            'profile_url'  => 'https://example.test/' . uniqid(),
            'provider'     => 'test-prov',
            'is_active'    => 1,
        ]);
        $this->createdAccounts[] = $id;
        return $id;
    }

    private function createJob(int $accountId, string $snapshotId, string $status = 'pending', string $platform = 'facebook'): int
    {
        return $this->jobModel->upsertTriggeredJob($accountId, $platform, 'ds_1', $snapshotId, $status);
    }

    public function testPendingJobYangMasihRunningTidakDownload(): void
    {
        $accId = $this->createAccount('Acc 1');
        $this->createJob($accId, 'sd_run_1', 'pending');

        $this->provider->progressMap['sd_run_1'] = 'running';

        $summary = $this->service->processPendingBatch(10, 'test-prov');

        $this->assertSame(1, $summary['total']);
        $this->assertSame(0, $summary['completed']);
        $this->assertSame(1, $summary['waiting']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame(0, $this->provider->snapshotCalls, 'Snapshot tidak boleh di-download jika masih running.');

        $job = $this->jobModel->findBySnapshot('sd_run_1');
        $this->assertSame(ModelSocialMediaScrapeJob::STATUS_PROCESSING, $job->status);
    }

    public function testReadyJobDownloadDanPersist(): void
    {
        $accId = $this->createAccount('Acc 2');
        $this->createJob($accId, 'sd_ready_1', 'pending');

        $this->provider->progressMap['sd_ready_1'] = 'ready';
        $this->provider->snapshotMap['sd_ready_1'] = [
            [
                'external_post_id' => 'p_ready_1',
                'post_url'         => 'https://example.test/post/1',
                'caption'          => 'Test Caption',
                'published_at'     => '2026-09-10 12:00:00',
                'views'            => 500,
                'likes'            => 50,
                'comments'         => 5,
            ],
        ];

        $summary = $this->service->processPendingBatch(10, 'test-prov');

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(1, $this->provider->snapshotCalls);

        $job = $this->jobModel->findBySnapshot('sd_ready_1');
        $this->assertSame(ModelSocialMediaScrapeJob::STATUS_COMPLETED, $job->status);
        $this->assertNotNull($job->completed_at);

        $post = $this->postModel->findByPostIdentity($accId, 'p_ready_1');
        $this->assertNotNull($post);
        $this->assertSame('Test Caption', $post->caption);

        $db = \Config\Database::connect('default');
        $snap = $db->table('social_media_metric_snapshots')
            ->where('social_media_post_id', $post->id)
            ->get()
            ->getFirstRow();
        $this->assertNotNull($snap);
        $this->assertSame('500', (string)$snap->views);
        $this->assertSame('50', (string)$snap->likes);
    }

    public function testFailedJobDitandaiFailed(): void
    {
        $accId = $this->createAccount('Acc 3');
        $this->createJob($accId, 'sd_fail_1', 'pending');

        $this->provider->progressMap['sd_fail_1'] = 'failed';

        $summary = $this->service->processPendingBatch(10, 'test-prov');

        $this->assertSame(1, $summary['total']);
        $this->assertSame(0, $summary['completed']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0, $this->provider->snapshotCalls);

        $job = $this->jobModel->findBySnapshot('sd_fail_1');
        $this->assertSame(ModelSocialMediaScrapeJob::STATUS_FAILED, $job->status);
        $this->assertNotNull($job->error_message);
    }

    public function testCompletedJobDilewati(): void
    {
        $accId = $this->createAccount('Acc 4');
        $this->createJob($accId, 'sd_comp_1', ModelSocialMediaScrapeJob::STATUS_COMPLETED);

        $summary = $this->service->processPendingBatch(10, 'test-prov');

        $this->assertSame(0, $summary['total'], 'Completed job tidak boleh masuk antrean batch.');
        $this->assertSame(0, $this->provider->progressCalls);
    }

    public function testInvalidSnapshotResponseDitandaiError(): void
    {
        $accId = $this->createAccount('Acc 5');
        $this->createJob($accId, 'sd_err_1', 'pending');

        $this->provider->progressMap['sd_err_1'] = 'ready';
        $this->provider->throwMap['sd_err_1']    = true;

        $summary = $this->service->processPendingBatch(10, 'test-prov');

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['failed']);

        $job = $this->jobModel->findBySnapshot('sd_err_1');
        $this->assertSame(ModelSocialMediaScrapeJob::STATUS_FAILED, $job->status);
        $this->assertStringContainsString('API error saat', (string)$job->error_message);
    }

    public function testSatuJobGagalJobLainTetapDiproses(): void
    {
        $acc1 = $this->createAccount('Acc 6A');
        $acc2 = $this->createAccount('Acc 6B');
        $acc3 = $this->createAccount('Acc 6C');

        $this->createJob($acc1, 'sd_batch_fail', 'pending');
        $this->createJob($acc2, 'sd_batch_ok', 'pending');
        $this->createJob($acc3, 'sd_batch_run', 'pending');

        $this->provider->progressMap['sd_batch_fail'] = 'failed';
        $this->provider->progressMap['sd_batch_ok']   = 'ready';
        $this->provider->snapshotMap['sd_batch_ok']   = [
            ['external_post_id' => 'p_ok_1', 'published_at' => '2026-09-10 10:00:00', 'views' => 10],
        ];
        $this->provider->progressMap['sd_batch_run']  = 'running';

        $summary = $this->service->processPendingBatch(10, 'test-prov');

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(1, $summary['waiting']);
        $this->assertSame(1, $summary['failed']);

        $jobOk = $this->jobModel->findBySnapshot('sd_batch_ok');
        $this->assertSame(ModelSocialMediaScrapeJob::STATUS_COMPLETED, $jobOk->status);
    }

    public function testMenjalankanProcessorDuaKaliIdempoten(): void
    {
        $accId = $this->createAccount('Acc 7');
        $this->createJob($accId, 'sd_idem_1', 'pending');

        $this->provider->progressMap['sd_idem_1'] = 'ready';
        $this->provider->snapshotMap['sd_idem_1'] = [
            ['external_post_id' => 'p_idem_1', 'published_at' => '2026-09-10 10:00:00', 'likes' => 100],
        ];

        // Run 1 -> completed
        $sum1 = $this->service->processPendingBatch(10, 'test-prov');
        $this->assertSame(1, $sum1['completed']);

        // Run 2 -> tidak ada job pending
        $sum2 = $this->service->processPendingBatch(10, 'test-prov');
        $this->assertSame(0, $sum2['total']);

        // Cek posts & snapshots tidak duplicate
        $db = \Config\Database::connect('default');
        $postCount = $db->table('social_media_posts')->where('social_media_account_id', $accId)->countAllResults();
        $this->assertSame(1, $postCount);
    }

    public function testBatchLimitBekerja(): void
    {
        $accId = $this->createAccount('Acc 8');
        $this->createJob($accId, 'sd_limit_1', 'pending');
        $this->createJob($accId, 'sd_limit_2', 'pending');
        $this->createJob($accId, 'sd_limit_3', 'pending');

        $this->provider->progressMap['sd_limit_1'] = 'running';
        $this->provider->progressMap['sd_limit_2'] = 'running';
        $this->provider->progressMap['sd_limit_3'] = 'running';

        // Limit = 2
        $summary = $this->service->processPendingBatch(2, 'test-prov');

        $this->assertSame(2, $summary['total'], 'Hanya 2 job yang diambil sesuai limit.');
        $this->assertSame(2, $summary['waiting']);
    }
}