<?php

namespace Tests\SocialMedia;

use App\Services\SocialMedia\BrightDataApiException;
use App\Services\SocialMedia\BrightDataClient;
use App\Services\SocialMedia\SocialMediaTransientException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Test BrightDataClient (ASYNC TRIGGER) dengan MOCK Guzzle — tanpa request asli.
 */
class BrightDataClientTest extends TestCase
{
    private static function setEnvValue(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }

    public static function setUpBeforeClass(): void
    {
        self::setEnvValue('BRIGHT_DATA_TOKEN', 'test-token-yang-rahasia');
        self::setEnvValue('BRIGHT_DATA_FACEBOOK_DATASET_ID', 'fb-dataset');
        self::setEnvValue('BRIGHT_DATA_TIKTOK_DATASET_ID', 'tt-dataset');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (['BRIGHT_DATA_TOKEN', 'BRIGHT_DATA_FACEBOOK_DATASET_ID', 'BRIGHT_DATA_TIKTOK_DATASET_ID'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }

    /** @return BrightDataClient client; request history ditulis ke $container (by ref) */
    private function clientWithResponse(Response $response, &$container): BrightDataClient
    {
        $container = [];
        $history   = Middleware::history($container);
        $stack     = HandlerStack::create(new MockHandler([$response]));
        $stack->push($history);

        return new BrightDataClient(new Client(['handler' => $stack, 'http_errors' => false]));
    }

    public function testTriggerFacebookPostingKeEndpointTrigger(): void
    {
        $container = [];
        $client = $this->clientWithResponse(
            new Response(200, [], json_encode(['snapshot_id' => 'sd_abc123'])),
            $container
        );

        $snapshotId = $client->trigger('facebook', ['https://www.facebook.com/IClear.Center/']);

        $this->assertSame('sd_abc123', $snapshotId);
        $this->assertCount(1, $container);
        $this->assertSame('POST', $container[0]['request']->getMethod());

        $uri = (string)$container[0]['request']->getUri();
        $this->assertStringContainsString('/datasets/v3/trigger', $uri);
        $this->assertStringContainsString('dataset_id=fb-dataset', $uri);
        $this->assertStringContainsString('notify=false', $uri);
        $this->assertStringContainsString('include_errors=true', $uri);

        $body = json_decode((string)$container[0]['request']->getBody(), true);
        $this->assertSame('https://www.facebook.com/IClear.Center/', $body['input'][0]['url']);
        $this->assertArrayHasKey('num_of_posts', $body['input'][0], 'Facebook tetap kirim num_of_posts (format teruji).');
        $this->assertArrayHasKey('start_date', $body['input'][0]);
        $this->assertArrayHasKey('end_date', $body['input'][0]);
        $this->assertSame(null, $body['limit_per_input']);
    }

    public function testTriggerTikTokTanpaNumPosts(): void
    {
        $container = [];
        $container = [];
        $client = $this->clientWithResponse(
            new Response(200, [], json_encode(['snapshot_id' => 'sd_xyz789']))
        , $container);

        $snapshotId = $client->trigger('tiktok', ['https://www.tiktok.com/@iclear.service']);

        $this->assertSame('sd_xyz789', $snapshotId);
        $body = json_decode((string)$container[0]['request']->getBody(), true);
        $this->assertSame('https://www.tiktok.com/@iclear.service', $body['input'][0]['url']);
        $this->assertArrayNotHasKey('num_of_posts', $body['input'][0], 'TikTok format: url + tanggal saja.');
        $this->assertArrayHasKey('start_date', $body['input'][0]);
        $this->assertArrayHasKey('end_date', $body['input'][0]);
    }

    public function testTidakAdaRequestProgressAtauSnapshot(): void
    {
        $container = [];
        $container = [];
        $client = $this->clientWithResponse(
            new Response(200, [], json_encode(['snapshot_id' => 'sd_dinamis']))
        , $container);

        $client->trigger('tiktok', ['https://www.tiktok.com/@iclear.service']);

        $this->assertSame(1, count($container), 'Trigger = tepat 1 request HTTP, tanpa polling.');
        $uri = strtolower((string)$container[0]['request']->getUri());
        $this->assertStringNotContainsString('/progress', $uri);
        $this->assertStringNotContainsString('/snapshot', $uri);
    }

    public function testResponseTanpaSnapshotIdLemparApiException(): void
    {
        $client = $this->clientWithResponse(new Response(200, [], json_encode(['status' => 'error'])), $container);

        $this->expectException(BrightDataApiException::class);
        $client->trigger('tiktok', ['https://www.tiktok.com/@iclear.service']);
    }

    public function testHttp400Ditangani(): void
    {
        $client = $this->clientWithResponse(new Response(400, [], '{"error":"bad"}'), $container);
        $this->expectException(BrightDataApiException::class);
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testHttp401Ditangani(): void
    {
        $client = $this->clientWithResponse(new Response(401, [], 'unauthorized'), $container);
        $this->expectException(BrightDataApiException::class);
        $this->expectExceptionMessage('401');
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testHttp404DatasetTidakDitemukan(): void
    {
        $client = $this->clientWithResponse(new Response(404, [], 'not found'), $container);
        $this->expectException(BrightDataApiException::class);
        $this->expectExceptionMessage('404');
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testHttp429RateLimitDitangani(): void
    {
        $client = $this->clientWithResponse(new Response(429, [], 'rate limited'), $container);
        $this->expectException(SocialMediaTransientException::class);
        $this->expectExceptionMessage('429');
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testHttp500Ditangani(): void
    {
        $client = $this->clientWithResponse(new Response(500, [], 'internal error'), $container);
        $this->expectException(SocialMediaTransientException::class);
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testInvalidJSONDitangani(): void
    {
        $client = $this->clientWithResponse(new Response(200, [], '{not-json'), $container);
        $this->expectException(BrightDataApiException::class);
        $this->expectExceptionMessage('Invalid JSON');
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testResponseKosongDitangani(): void
    {
        $client = $this->clientWithResponse(new Response(200, [], ''), $container);
        $this->expectException(BrightDataApiException::class);
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testTimeoutDitangani(): void
    {
        $request = new Request('POST', BrightDataClient::ENDPOINT_TRIGGER);
        $handler = static function () use ($request) {
            throw new ConnectException('Connection timed out', $request);
        };
        $client = new BrightDataClient(new Client(['handler' => HandlerStack::create($handler), 'http_errors' => false]));

        $this->expectException(SocialMediaTransientException::class);
        $this->expectExceptionMessage('Timeout/koneksi');
        $client->trigger('facebook', ['https://www.facebook.com/x/']);
    }

    public function testBOMPadaResponseTetapBisaDiparse(): void
    {
        $client = $this->clientWithResponse(new Response(200, [], "\xEF\xBB\xBF" . json_encode(['snapshot_id' => 'sd_bom'])), $container);
        $this->assertSame('sd_bom', $client->trigger('facebook', ['https://www.facebook.com/x/']));
    }

    public function testTokenBelumDikonfigurasiDitangani(): void
    {
        putenv('BRIGHT_DATA_TOKEN');
        unset($_ENV['BRIGHT_DATA_TOKEN'], $_SERVER['BRIGHT_DATA_TOKEN']);
        try {
            $client = $this->clientWithResponse(new Response(200, [], '{"snapshot_id":"sd_x"}'), $container);
            $this->expectException(\App\Services\SocialMedia\SocialMediaScraperException::class);
            $client->trigger('facebook', ['https://www.facebook.com/x/']);
        } finally {
            self::setEnvValue('BRIGHT_DATA_TOKEN', 'test-token-yang-rahasia');
        }
    }
}