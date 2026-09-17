<?php

namespace Tests\SocialMedia;

use App\Services\SocialMedia\BrightDataSocialMediaProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test normalisasi response Facebook & TikTok (tanpa jaringan).
 */
class NormalizeTest extends TestCase
{
    private function provider(): BrightDataSocialMediaProvider
    {
        return new BrightDataSocialMediaProvider(new \App\Services\SocialMedia\BrightDataClient(
            new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([]))]),
            2,
            1
        ));
    }

    public function testFacebookNormalization(): void
    {
        $rows = [[
            'post_id'          => '123456',
            'url'              => 'https://www.facebook.com/x/posts/123456',
            'content'          => 'Promo diskon 50%',
            'date_posted'      => '2026-09-14 10:00:00',
            'post_type'        => 'photo',
            'video_view_count' => 1000,
            'play_count'       => 700,
            'likes'            => 150,
            'num_comments'     => 20,
            'num_shares'       => 5,
        ]];

        $out = $this->provider()->normalizeFacebook($rows);

        $this->assertCount(1, $out);
        $this->assertSame('123456', $out[0]['external_post_id']);
        $this->assertSame('https://www.facebook.com/x/posts/123456', $out[0]['post_url']);
        $this->assertSame('Promo diskon 50%', $out[0]['caption']);
        $this->assertSame('2026-09-14 10:00:00', $out[0]['published_at']);
        $this->assertSame('photo', $out[0]['post_type']);
        $this->assertSame(1000, $out[0]['views']);
        $this->assertSame(700, $out[0]['plays']);
        $this->assertSame(150, $out[0]['likes']);
        $this->assertSame(20, $out[0]['comments']);
        $this->assertSame(5, $out[0]['shares']);
        $this->assertNull($out[0]['saves']);
    }

    public function testFacebookViewsAndPlaysStaySeparate(): void
    {
        $out = $this->provider()->normalizeFacebook([[
            'post_id'          => '99',
            'video_view_count' => 500,
            'play_count'       => 250,
        ]]);

        // Tidak dijumlah! views ≠ plays.
        $this->assertSame(500, $out[0]['views']);
        $this->assertSame(250, $out[0]['plays']);
        $this->assertNotSame($out[0]['views'], $out[0]['plays']);
    }

    public function testTikTokNormalization(): void
    {
        $rows = [[
            'post_id'       => 'tt_id_1',
            'url'           => 'https://www.tiktok.com/@iclear.service/video/123',
            'description'   => 'Tips servis',
            'create_time'   => 1726293600, // 2024-09-14
            'post_type'     => 'video',
            'play_count'    => 1200,
            'digg_count'    => 300,
            'comment_count' => 45,
            'share_count'   => 12,
            'collect_count' => 60,
        ]];

        $out = $this->provider()->normalizeTikTok($rows);

        $this->assertCount(1, $out);
        $this->assertSame('tt_id_1', $out[0]['external_post_id']);
        $this->assertSame('Tips servis', $out[0]['caption']);
        $this->assertSame(date('Y-m-d H:i:s', 1726293600), $out[0]['published_at']);
        $this->assertSame(1200, $out[0]['views']);
        $this->assertNull($out[0]['plays']);
        $this->assertSame(300, $out[0]['likes']);
        $this->assertSame(45, $out[0]['comments']);
        $this->assertSame(12, $out[0]['shares']);
        $this->assertSame(60, $out[0]['saves']);
    }

    public function testPostIdKosongDilewati(): void
    {
        $rows = [
            ['post_id' => '', 'likes' => 1],
            ['post_id' => 'ok', 'likes' => 2],
        ];
        $out = $this->provider()->normalizeFacebook($rows);

        $this->assertCount(1, $out);
        $this->assertSame('ok', $out[0]['external_post_id']);
    }

    public function testMetricTidakTersediaJadiNull(): void
    {
        $out = $this->provider()->normalizeFacebook([['post_id' => 'x1']]);
        $this->assertNull($out[0]['likes']);
        $this->assertNull($out[0]['views']);
    }

    public function testMetricNolTetapNol(): void
    {
        $out = $this->provider()->normalizeFacebook([['post_id' => 'x2', 'likes' => '0', 'play_count' => 0]]);
        $this->assertSame(0, $out[0]['likes']);
        $this->assertSame(0, $out[0]['plays']);
    }

    public function testErrorResponseMemicuException(): void
    {
        $this->expectException(\App\Services\SocialMedia\BrightDataApiException::class);
        $this->provider()->normalizeFacebook([['error_code' => 400, 'error' => 'bad request']]);
    }

    public function testTanggalUnixTimestamp(): void
    {
        $out = $this->provider()->normalizeTikTok([['post_id' => 'u1', 'create_time' => '1726293600']]);
        $this->assertSame(date('Y-m-d H:i:s', 1726293600), $out[0]['published_at']);
    }

    public function testTanggalTidakValidJadiNull(): void
    {
        $out = $this->provider()->normalizeTikTok([['post_id' => 'u2', 'create_time' => 'bukan tanggal']]);
        $this->assertNull($out[0]['published_at']);
    }
}