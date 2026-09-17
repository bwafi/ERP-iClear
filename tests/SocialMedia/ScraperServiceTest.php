<?php

namespace Tests\SocialMedia;

use App\Models\ModelSocialMediaAccount;
use App\Services\SocialMedia\BrightDataApiException;
use App\Services\SocialMedia\SocialMediaProviderInterface;
use App\Services\SocialMedia\SocialMediaScraperService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Provider fake (ASYNC TRIGGER) untuk test service — tanpa jaringan.
 */
class FakeTriggerProvider implements SocialMediaProviderInterface
{
    public int $calls = 0;
    public array $failFor = [];
    public ?\Throwable $throw = null;

    /** @var array<int,string> accountId => snapshot id yang dibuat */
    public array $snapshotIds = [];

    public function providerKey(): string
    {
        return 'test-prov';
    }

    public function trigger(object $account): array
    {
        $this->calls++;
        if (in_array($account->account_name, $this->failFor, true)) {
            throw new BrightDataApiException('invalid response');
        }
        if ($this->throw) {
            throw $this->throw;
        }
        $snapshotId = $this->snapshotIds[(int)$account->id] ?? 'sd_auto_' . $this->calls;

        return [
            'snapshot_id' => $snapshotId,
            'dataset_id'  => 'ds-' . $account->platform,
        ];
    }

    public function fetchProgress(string $snapshotId): string
    {
        return 'ready';
    }

    public function fetchAndNormalizeSnapshot(string $platform, string $snapshotId): array
    {
        return [];
    }
}

/**
 * Test alur ASYNC TRIGGER service:
 *   trigger → snapshot_id → simpan job (idempoten) → selesai tanpa polling.
 * Plus test storage posts/snapshot (dipakai processor hasil).
 */
class ScraperServiceTest extends CIUnitTestCase
{
    private ModelSocialMediaAccount $accountModel;
    private FakeTriggerProvider $provider;
    private SocialMediaScraperService $service;

    /** @var int[] id akun test untuk dibersihkan */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountModel = new ModelSocialMediaAccount();
        $this->provider     = new FakeTriggerProvider();
        $this->service      = new SocialMediaScraperService();
        $this->service->registerProvider($this->provider);

        $db = \Config\Database::connect('default');
        $db->table('social_media_accounts')->where('provider', 'test-prov')->delete();
        $db->table('social_media_scrape_jobs')->like('snapshot_id', 'sd_', 'after')->delete();
    }

    protected function tearDown(): void
    {
        $db = \Config\Database::connect('default');
        $db->table('social_media_accounts')->where('provider', 'test-prov')->delete();
        $db->table('social_media_scrape_jobs')->like('snapshot_id', 'sd_', 'after')->delete();
        parent::tearDown();
    }

    private function createAccount(array $overrides = []): int
    {
        $data = array_merge([
            'unit_id'       => 1,
            'platform'      => 'facebook',
            'account_name'  => 'TEST-AKUN',
            'username'      => null,
            'external_account_id' => null,
            'profile_url'   => 'https://example.test/test-' . uniqid(),
            'provider'      => 'test-prov',
            'is_active'     => 1,
        ], $overrides);

        $id = $this->accountModel->insert($data);
        $this->created[] = (int)$id;
        return (int)$id;
    }

    private function jobRows(int $accountId): array
    {
        return \Config\Database::connect('default')
            ->table('social_media_scrape_jobs')
            ->where('social_media_account_id', $accountId)
            ->get()
            ->getResultArray();
    }

    private function samplePosts(): array
    {
        return [
            [
                'external_post_id' => 'ext-1',
                'post_url'         => 'https://example.test/p/1',
                'caption'          => 'post 1',
                'published_at'     => '2026-09-10 08:00:00',
                'views'            => 100,
                'plays'            => 60,
                'likes'            => 5,
                'comments'         => 1,
                'shares'           => 0,
                'saves'            => null,
            ],
        ];
    }

    public function testAkunNonaktifTidakDitrigger(): void
    {
        $this->createAccount(['is_active' => 0]);

        $result = $this->service->triggerAll('test-prov');

        $this->assertSame(0, $result['ok']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $this->provider->calls);
    }

    public function testTriggerBerhasilDanSnapshotDisimpanKeDatabase(): void
    {
        $id = $this->createAccount();
        $this->provider->snapshotIds[$id] = 'sd_dinamis_123';

        $result = $this->service->triggerAll('test-prov');

        $this->assertSame(1, $result['ok']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $this->provider->calls);

        $jobs = $this->jobRows($id);
        $this->assertCount(1, $jobs, 'snapshot_id ter-trigger harus tersimpan di social_media_scrape_jobs.');
        $this->assertSame('sd_dinamis_123', $jobs[0]['snapshot_id']);
        $this->assertSame('facebook', $jobs[0]['platform']);
        $this->assertSame('ds-facebook', $jobs[0]['dataset_id']);
        $this->assertSame('pending', $jobs[0]['status']);
        $this->assertNotNull($jobs[0]['requested_at']);
    }

    public function testTriggerKembalikanSnapshotIdYangBervariasi(): void
    {
        $id = $this->createAccount();
        $this->provider->snapshotIds[$id] = 'sd_per_tahun_pertama';

        $first = $this->service->triggerAll('test-prov');
        $this->assertSame('sd_per_tahun_pertama', $first['results'][0]['snapshot_id']);

        $this->provider->snapshotIds[$id] = 'sd_per_tahun_kedua';
        $second = $this->service->triggerAll('test-prov');
        $this->assertSame('sd_per_tahun_kedua', $second['results'][0]['snapshot_id']);

        $this->assertCount(2, $this->jobRows($id), 'Trigger baru dengan snapshot_id baru = job baru.');
    }

    public function testSnapshotIdSamaTidakMembuatDuplicateJob(): void
    {
        $id = $this->createAccount();
        $this->provider->snapshotIds[$id] = 'sd_identik';

        $this->service->triggerAll('test-prov');
        $this->service->triggerAll('test-prov'); // response yang sama diproses ulang

        $jobs = $this->jobRows($id);
        $this->assertCount(1, $jobs, 'snapshot_id sama tidak boleh jadi duplicate record.');
        $this->assertSame('sd_identik', $jobs[0]['snapshot_id']);
    }

    public function testSatuAkunGagalAkunLainTetapDiproses(): void
    {
        $this->createAccount(['account_name' => 'OPS-1', 'platform' => 'facebook']);
        $this->createAccount(['account_name' => 'FAIL', 'platform' => 'facebook']);
        $this->createAccount(['account_name' => 'OPS-3', 'platform' => 'tiktok']);

        $this->provider->failFor = ['FAIL'];

        $result = $this->service->triggerAll('test-prov');

        $this->assertSame(2, $result['ok']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(3, $this->provider->calls);
        $this->assertSame('invalid response', $result['errors'][0]['message']);
    }

    public function testApiErrorDitanganiTanpaMenghentikanBatch(): void
    {
        $id = $this->createAccount();
        $this->provider->throw = new BrightDataApiException('Bright Data HTTP 400.');

        $result = $this->service->triggerAll('test-prov');

        $this->assertSame(0, $result['ok']);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('HTTP 400', $result['errors'][0]['message']);
        $this->assertNull($result['results'][0]['snapshot_id']);
        $this->assertCount(0, $this->jobRows($id), 'Trigger gagal → tidak ada job tersimpan.');
    }

    public function testResultMemuatDetailUntukOutputCLI(): void
    {
        $id = $this->createAccount(['account_name' => 'iClear Center']);
        $this->provider->snapshotIds[$id] = 'sd_out';

        $result = $this->service->triggerAll('test-prov');

        $this->assertSame('iClear Center', $result['results'][0]['account_name']);
        $this->assertSame('facebook', $result['results'][0]['platform']);
        $this->assertSame('sd_out', $result['results'][0]['snapshot_id']);
        $this->assertNull($result['results'][0]['error_message']);
    }

    // ── Storage posts/snapshot (dipakai processor hasil, bukan social:pull) ──

    public function testPostTidakDuplicate(): void
    {
        $id = $this->createAccount();
        $this->service->storePosts($id, 'facebook', $this->samplePosts());
        $this->service->storePosts($id, 'facebook', $this->samplePosts());

        $db = \Config\Database::connect('default');
        $count = $db->table('social_media_posts')
            ->where('social_media_account_id', $id)
            ->where('external_post_id', 'ext-1')
            ->countAllResults();

        $this->assertSame(1, $count, 'Post identity duplicate tidak boleh.');
    }

    public function testSnapshotTidakOverwriteBilaWaktuBerbeda(): void
    {
        $id = $this->createAccount();

        $this->service->storePosts($id, 'facebook', $this->samplePosts(), '2026-09-14 10:00:00');
        $this->service->storePosts($id, 'facebook', $this->samplePosts(), '2026-09-21 10:00:00');

        $db = \Config\Database::connect('default');
        $post = $db->table('social_media_posts')
            ->where('social_media_account_id', $id)
            ->get()
            ->getFirstRow();
        $count = $db->table('social_media_metric_snapshots')
            ->where('social_media_post_id', $post->id)
            ->countAllResults();

        $this->assertSame(2, $count, 'Snapshot tetap disimpan historis (tidak overwrite).');
    }

    public function testRetryJobSamaTidakBuatSnapshotDuplicate(): void
    {
        $id = $this->createAccount();

        $this->service->storePosts($id, 'facebook', $this->samplePosts(), '2026-09-14 10:00:00');
        $this->service->storePosts($id, 'facebook', $this->samplePosts(), '2026-09-14 10:00:00');

        $db = \Config\Database::connect('default');
        $post = $db->table('social_media_posts')
            ->where('social_media_account_id', $id)
            ->get()
            ->getFirstRow();
        $count = $db->table('social_media_metric_snapshots')
            ->where('social_media_post_id', $post->id)
            ->countAllResults();

        $this->assertSame(1, $count, 'Retry job yang sama tidak boleh menghasilkan snapshot duplicate.');
    }

    public function testPostTanpaExternalIdTetapTersimpanViaUrlFallback(): void
    {
        $id = $this->createAccount([
            'account_name' => 'CENTER (tanpa external id)',
        ]);

        // Normalizer (layer provider) menyediakan identity fallback dari URL,
        // sehingga akun tanpa external_account_id tidak gagal.
        $provider = new \App\Services\SocialMedia\BrightDataSocialMediaProvider(
            new \App\Services\SocialMedia\BrightDataClient()
        );
        $normalized = $provider->normalizeFacebook([[
            'url'          => 'https://www.facebook.com/IClear.Center/posts/12345',
            'content'      => 'post tanpa id Bright Data',
            'date_posted'  => '2026-09-10 08:00:00',
            'likes'        => 7,
        ]]);

        $this->assertCount(1, $normalized);
        $this->assertNotSame('', $normalized[0]['external_post_id'], 'Identity fallback dihasilkan dari URL.');
        $this->assertSame('fb-url-' . md5('https://www.facebook.com/IClear.Center/posts/12345'), $normalized[0]['external_post_id']);

        $stored = $this->service->storePosts($id, 'facebook', $normalized);

        $this->assertSame(1, $stored, 'Post tanpa external id tetap disimpan (identity fallback hash URL).');

        $db = \Config\Database::connect('default');
        $count = $db->table('social_media_posts')
            ->where('social_media_account_id', $id)
            ->countAllResults();
        $this->assertSame(1, $count);
    }

    public function testViewsDanPlaysTersimpanTerpisah(): void
    {
        $id = $this->createAccount();
        $this->service->storePosts($id, 'facebook', $this->samplePosts(), '2026-09-14 10:00:00');

        $db = \Config\Database::connect('default');
        $snap = $db->table('social_media_metric_snapshots')
            ->join('social_media_posts', 'social_media_posts.id = social_media_metric_snapshots.social_media_post_id')
            ->where('social_media_posts.social_media_account_id', $id)
            ->get()
            ->getFirstRow();

        $this->assertSame('100', (string)$snap->views);
        $this->assertSame('60', (string)$snap->plays);
        $this->assertNull($snap->saves);
    }
}