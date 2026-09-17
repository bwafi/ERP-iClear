<?php

namespace Tests\SocialMedia;

use App\Services\SocialMedia\BrightDataClient;
use App\Services\SocialMedia\BrightDataSocialMediaProvider;
use App\Services\SocialMedia\SocialMediaScraperException;
use App\Services\SocialMedia\SocialMediaUnsupportedPlatformException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Test BrightDataSocialMediaProvider — ASYNC TRIGGER (mock HTTP, tanpa request asli).
 */
class ProviderBatchTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (['BRIGHT_DATA_TOKEN' => 'tok', 'BRIGHT_DATA_FACEBOOK_DATASET_ID' => 'fb-ds', 'BRIGHT_DATA_TIKTOK_DATASET_ID' => 'tt-ds'] as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (['BRIGHT_DATA_TOKEN', 'BRIGHT_DATA_FACEBOOK_DATASET_ID', 'BRIGHT_DATA_TIKTOK_DATASET_ID'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    private function makeProvider(array &$container): BrightDataSocialMediaProvider
    {
        $container = [];
        $stack  = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['snapshot_id' => 'sd_dinamis_abc'])),
        ]));
        $stack->push(Middleware::history($container));
        $client = new BrightDataClient(new Client(['handler' => $stack, 'http_errors' => false]));

        return new BrightDataSocialMediaProvider($client);
    }

    public function testTriggerFacebookMengembalikanSnapshotDanDataset(): void
    {
        $container = [];
        $provider = $this->makeProvider($container);
        $account  = (object)['id' => 1, 'platform' => 'facebook', 'profile_url' => 'https://www.facebook.com/IClear.Center/'];

        $result = $provider->trigger($account);

        $this->assertSame('sd_dinamis_abc', $result['snapshot_id']);
        $this->assertSame('fb-ds', $result['dataset_id']);
        $this->assertCount(1, $container);
        $uri = (string)$container[0]['request']->getUri();
        $this->assertStringContainsString('/datasets/v3/trigger', $uri);
        $this->assertStringContainsString('dataset_id=fb-ds', $uri);
    }

    public function testTriggerTikTokMemakaiDatasetTikTok(): void
    {
        $container = [];
        $provider = $this->makeProvider($container);
        $account  = (object)['id' => 2, 'platform' => 'tiktok', 'profile_url' => 'https://www.tiktok.com/@iclear.service'];

        $result = $provider->trigger($account);

        $this->assertSame('sd_dinamis_abc', $result['snapshot_id']);
        $this->assertSame('tt-ds', $result['dataset_id']);
        $uri = (string)$container[0]['request']->getUri();
        $this->assertStringContainsString('dataset_id=tt-ds', $uri);
    }

    public function testTriggerProfileUrlKosongLempar(): void
    {
        $container = [];
        $provider = $this->makeProvider($container);
        $account  = (object)['id' => 3, 'platform' => 'facebook', 'profile_url' => ''];

        $this->expectException(SocialMediaScraperException::class);
        $this->expectExceptionMessage('profile_url');
        $provider->trigger($account);
    }

    public function testTriggerPlatformTidakDidukungLempar(): void
    {
        $container = [];
        $provider = $this->makeProvider($container);
        $account  = (object)['id' => 4, 'platform' => 'instagram', 'profile_url' => 'https://instagram.com/x'];

        $this->expectException(SocialMediaUnsupportedPlatformException::class);
        $provider->trigger($account);
    }
}