<?php

use App\Services\Kpi\ItUptimeCheckService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cek uptime terjadwal: satu HTTP GET, satu baris bukti di ledger.
 *
 * Sumber jaringan diganti callable supaya test tidak menyentuh internet.
 *
 * @internal
 */
final class ItUptimeCheckTest extends CIUnitTestCase
{
    /** @var list<string> */
    private $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
        $this->dirs = [];

        parent::tearDown();
    }

    public function testCekSuksesMenulisSatuBarisLedger(): void
    {
        $service = $this->service(fn () => ['status' => 200, 'body' => '<h1>Login</h1>', 'ms' => 412, 'error' => null]);

        $result = $service->run($this->now());

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['http']);

        $rows = $this->ledgerRows($service->path());
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['ok']);
        $this->assertSame(200, $rows[0]['http']);
        $this->assertSame(412, $rows[0]['ms']);
        $this->assertSame($service->url(), $rows[0]['url']);
        $this->assertNull($rows[0]['error']);
    }

    public function testCekGagalTetapMencatatBaris(): void
    {
        $service = $this->service(fn () => ['status' => 503, 'body' => 'maintenance', 'ms' => 33, 'error' => null]);

        $result = $service->run($this->now());

        $this->assertFalse($result['ok']);
        $this->assertSame(503, $result['http']);

        $rows = $this->ledgerRows($service->path());
        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['ok']);
        $this->assertSame(503, $rows[0]['http']);
    }

    public function testMarkerTidakCocokDianggapGagal(): void
    {
        $service = $this->service(
            fn () => ['status' => 200, 'body' => '<h1>Masuk</h1>', 'ms' => 90, 'error' => null],
            ['marker' => 'login']
        );

        $this->assertFalse($service->run($this->now())['ok']);
    }

    public function testMarkerDibandingkanTanpaBedaHurufBesarKecil(): void
    {
        $this->assertTrue(ItUptimeCheckService::isSuccess(200, '<H1>LOGIN ERP</H1>', 'login'));
        $this->assertTrue(ItUptimeCheckService::isSuccess(200, '<h1>Login</h1>', '  '));
        $this->assertFalse(ItUptimeCheckService::isSuccess(301, '<h1>Login</h1>', 'login'));
        $this->assertFalse(ItUptimeCheckService::isSuccess(200, '', 'login'));
    }

    public function testErrorJaringanDicatatTanpaMelemparException(): void
    {
        $service = $this->service(function (): array {
            throw new RuntimeException('Operation timed out after 10000 ms');
        });

        $result = $service->run($this->now());

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['http']);
        $this->assertStringContainsString('timed out', (string) $result['error']);

        $rows = $this->ledgerRows($service->path());
        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]['ok']);
        $this->assertStringContainsString('timed out', (string) $rows[0]['error']);
    }

    public function testJamDuaPuluhDuaWibTetapTanggalBisnisYangSama(): void
    {
        $service = $this->service(fn () => ['status' => 200, 'body' => '', 'ms' => 10, 'error' => null]);

        $now = new DateTimeImmutable('2026-11-24 22:00:00', new DateTimeZone('Asia/Jakarta'));

        $service->run($now);

        $rows = $this->ledgerRows($service->path());
        $this->assertCount(1, $rows);
        $this->assertStringStartsWith('2026-11-24T22:00', (string) $rows[0]['at']);
    }

    public function testTigaCekSehariMenambahTigaBaris(): void
    {
        $service = $this->service(fn () => ['status' => 200, 'body' => '', 'ms' => 10, 'error' => null]);
        $tz      = new DateTimeZone('Asia/Jakarta');

        foreach (['02:00:00', '08:00:00', '22:00:00'] as $time) {
            $service->run(new DateTimeImmutable('2026-11-24 ' . $time, $tz));
        }

        $this->assertCount(3, $this->ledgerRows($service->path()));
    }

    /* ── helper ──────────────────────────────────────────────── */

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-24 08:00:00', new DateTimeZone('Asia/Jakarta'));
    }

    /**
     * @param array<string,mixed> $config
     */
    private function service(callable $http, array $config = []): ItUptimeCheckService
    {
        $dir          = sys_get_temp_dir() . '/kpi-uptime-' . uniqid('', true);
        $this->dirs[] = $dir;

        return new ItUptimeCheckService(array_merge([
            'url'        => 'https://erp.icleargroup.id/login',
            'ledger_dir' => $dir,
            'http'       => $http,
        ], $config));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function ledgerRows(string $path): array
    {
        $this->assertFileExists($path);

        $rows = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $rows[] = json_decode($line, true);
        }

        return $rows;
    }
}
