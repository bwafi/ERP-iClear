<?php

use App\Services\Kpi\ItKpiService;
use App\Services\Kpi\ItKpiSyncService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * KPI Team IT (jabatan 45): commit GitHub, backup harian, uptime harian.
 *
 * Semua perhitungan diuji tanpa database. Sumber jaringan diganti callable
 * supaya kolektor data bisa diuji end-to-end tanpa token asli.
 *
 * @internal
 */
final class ItKpiTest extends CIUnitTestCase
{
    public function testKomponenItAdaLimaDenganBobotTotalSeratus(): void
    {
        $this->assertSame([
            'IT_DEVELOPMENT',
            'IT_BUG_FIX',
            'IT_BACKUP',
            'IT_UPTIME',
            'IT_DOCS',
        ], ItKpiService::CODES);

        $this->assertSame(100.0, array_sum(ItKpiService::WEIGHTS));
    }

    public function testSkorCommitDibatasiSeratusDanNullTanpaData(): void
    {
        $this->assertSame(100.0, ItKpiService::scoreCount(30, 15));
        $this->assertSame(60.0, ItKpiService::scoreCount(9, 15));
        $this->assertSame(0.0, ItKpiService::scoreCount(0, 15));
        $this->assertSame(0.0, ItKpiService::scoreCount(5, 0));
        $this->assertNull(ItKpiService::scoreCount(null, 15));
    }

    public function testSkorHariButuhPembagiPositif(): void
    {
        $this->assertSame(93.3333, ItKpiService::scoreDays(28, 30));
        $this->assertSame(0.0, ItKpiService::scoreDays(0, 30));
        $this->assertNull(ItKpiService::scoreDays(0, 0));
        $this->assertNull(ItKpiService::scoreDays(null, 30));
    }

    public function testRestoreGagalMembatasiSkorBackupMaksimalLimaPuluh(): void
    {
        $this->assertSame(50.0, ItKpiService::applyRestoreCap(93.3333, true));
        $this->assertSame(93.3333, ItKpiService::applyRestoreCap(93.3333, false));
        $this->assertNull(ItKpiService::applyRestoreCap(null, true));
    }

    public function testFormatTampilanKomponenIt(): void
    {
        $this->assertSame('count', ItKpiService::displayFormat('IT_DEVELOPMENT'));
        $this->assertSame('count', ItKpiService::displayFormat('IT_DOCS'));
        $this->assertSame('percent', ItKpiService::displayFormat('IT_BACKUP'));
        $this->assertSame('percent', ItKpiService::displayFormat('IT_UPTIME'));
        $this->assertNull(ItKpiService::displayFormat('OMSET_TOKO'));

        $this->assertSame('commit', ItKpiService::displayUnit('IT_BUG_FIX'));
        $this->assertNull(ItKpiService::displayUnit('IT_BACKUP'));
        $this->assertNull(ItKpiService::displayUnit('OMSET_TOKO'));
    }

    public function testEvaluasiDevelopmentMemakaiTargetCommit(): void
    {
        $result = ItKpiService::evaluate('IT_DEVELOPMENT', ['feat_commits' => 9], 15.0);

        $this->assertSame(60.0, $result['achievement']);
        $this->assertSame(15.0, $result['target']);
        $this->assertSame(9.0, $result['actual']);
        $this->assertSame(6.0, $result['shortfall']);
    }

    public function testEvaluasiBackupMemakaiHariDanCapRestore(): void
    {
        $result = ItKpiService::evaluate('IT_BACKUP', [
            'backup_ok_days'      => 28,
            'backup_checked_days' => 30,
            'restore_ok'          => 0,
        ], 100.0);

        // 28/30 = 93.33, lalu uji restore gagal membatasi ke 50.
        $this->assertSame(50.0, $result['achievement']);
        $this->assertSame(100.0, $result['target']);
        $this->assertSame(93.33, $result['actual']);
        $this->assertSame(6.67, $result['shortfall']);
    }

    public function testEvaluasiTanpaDataMenghasilkanNullBukanNol(): void
    {
        $uptime = ItKpiService::evaluate('IT_UPTIME', [
            'uptime_ok_days'      => null,
            'uptime_checked_days' => null,
        ], 100.0);

        $this->assertNull($uptime['achievement']);
        $this->assertNull($uptime['actual']);
        $this->assertNull($uptime['shortfall']);

        $belumSync = ItKpiService::evaluate('IT_DEVELOPMENT', null, 15.0);
        $this->assertNull($belumSync['achievement']);
        $this->assertSame(15.0, $belumSync['target']);
    }

    public function testParseCommitMenghitungPrefixKonvensional(): void
    {
        $commits = [
            $this->commit('feat(kpi): add IT KPI', 'a@example.com'),
            $this->commit("feat: second feature\n\nbody", 'a@example.com'),
            $this->commit('fix(service): repair payment', 'a@example.com'),
            $this->commit('docs(prd): update module 08', 'a@example.com'),
            $this->commit('chore(config): bump dependency', 'a@example.com'),
            $this->commit('FEAT: uppercase is still feat', 'a@example.com'),
        ];

        $counts = ItKpiSyncService::parseGitHubCommits($commits, '');

        $this->assertSame(3, $counts['feat']);
        $this->assertSame(1, $counts['fix']);
        $this->assertSame(1, $counts['docs']);
    }

    public function testFilterAuthorMencocokkanEmailDanLogin(): void
    {
        $commit = [
            'commit' => ['author' => ['email' => 'syahronibawafi@gmail.com']],
            'author' => ['login' => 'bwafi'],
        ];

        $this->assertTrue(ItKpiSyncService::commitMatchesAuthor($commit, []));
        $this->assertTrue(ItKpiSyncService::commitMatchesAuthor($commit, ['syahronibawafi@gmail.com']));
        $this->assertTrue(ItKpiSyncService::commitMatchesAuthor($commit, ['bwafi']));
        $this->assertFalse(ItKpiSyncService::commitMatchesAuthor($commit, ['oranglain']));
    }

    public function testBackupMenghitungHariTanpaEntriSebagaiGagal(): void
    {
        $dir = $this->tempDir();
        $this->writeLines($dir . '/history.jsonl', [
            '{"at":"2026-11-01T02:15:00+07:00","status":"ok","tables":138}',
            '{"at":"2026-11-02T02:15:00+07:00","status":"failed","tables":null}',
            '{"at":"2026-11-03T02:15:00+07:00","status":"ok","tables":0}',
            '{"at":"2026-11-04T02:15:00+07:00","status":"ok","tables":138}',
        ]);

        $summary = ItKpiSyncService::backupSummary(
            $dir . '/history.jsonl',
            ['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05'],
            'Asia/Jakarta'
        );

        $this->assertSame(2, $summary['ok_days']);
        $this->assertSame(5, $summary['checked_days']);
        $this->assertSame(['2026-11-02', '2026-11-03', '2026-11-05'], $summary['failed_days']);
    }

    public function testBackupDuaKaliSehariCukupSatuSukses(): void
    {
        $dir = $this->tempDir();
        $this->writeLines($dir . '/history.jsonl', [
            '{"at":"2026-11-01T02:15:00+07:00","status":"failed","tables":null}',
            '{"at":"2026-11-01T09:15:00+07:00","status":"ok","tables":138}',
        ]);

        $summary = ItKpiSyncService::backupSummary(
            $dir . '/history.jsonl',
            ['2026-11-01'],
            'Asia/Jakarta'
        );

        $this->assertSame(1, $summary['ok_days']);
        $this->assertSame(1, $summary['checked_days']);
        $this->assertSame([], $summary['failed_days']);
    }

    public function testUptimeGagalHanyaBilaDowntimeLebihDariLimaBelasMenit(): void
    {
        // 99.0% = 14,4 menit (OK), 98.0% = 28,8 menit (gagal).
        $result = ItKpiSyncService::parseUptimeRatios([
            ['custom_uptime_ratio' => '100.000-99.000-98.000'],
        ], 3);

        $this->assertSame(2, $result['ok_days']);
        $this->assertSame(3, $result['checked_days']);
        $this->assertSame([2], $result['failed_indexes']);
    }

    public function testUptimeMemakaiMonitorTerburuk(): void
    {
        $result = ItKpiSyncService::parseUptimeRatios([
            ['custom_uptime_ratio' => '100.000-100.000'],
            ['custom_uptime_ratio' => '100.000-50.000'],
        ], 2);

        $this->assertSame(1, $result['ok_days']);
        $this->assertSame(2, $result['checked_days']);
        $this->assertSame([1], $result['failed_indexes']);
    }

    public function testUptimeTanpaRatioValidTidakDihitung(): void
    {
        $result = ItKpiSyncService::parseUptimeRatios([
            ['custom_uptime_ratio' => '-1.000--1.000'],
        ], 2);

        $this->assertSame(0, $result['ok_days']);
        $this->assertSame(0, $result['checked_days']);
        $this->assertSame([], $result['failed_indexes']);
    }

    public function testRecordRestoreDitulisDanDibacaKembali(): void
    {
        $dir = $this->tempDir();
        $now = new DateTimeImmutable('2026-11-20 10:00:00', new DateTimeZone('Asia/Jakarta'));

        $service = new ItKpiSyncService([
            'backup_dir' => $dir,
            'timezone'   => 'Asia/Jakarta',
        ]);
        $service->recordRestore('failed', $now);

        $days = $this->daysBetween('2026-11-01', '2026-11-30');
        $this->assertFalse(ItKpiSyncService::restoreStatus($dir . '/restore.jsonl', $days, 'Asia/Jakarta'));
    }

    public function testRestoreStatusMengambilEntriTerakhirPadaPeriode(): void
    {        $dir = $this->tempDir();
        $this->writeLines($dir . '/restore.jsonl', [
            '{"at":"2026-10-30T10:00:00+07:00","status":"failed"}',
            '{"at":"2026-11-02T10:00:00+07:00","status":"ok"}',
            '{"at":"2026-11-20T10:00:00+07:00","status":"failed"}',
        ]);

        $november = ItKpiSyncService::restoreStatus(
            $dir . '/restore.jsonl',
            $this->daysBetween('2026-11-01', '2026-11-30'),
            'Asia/Jakarta'
        );
        $october = ItKpiSyncService::restoreStatus(
            $dir . '/restore.jsonl',
            $this->daysBetween('2026-10-01', '2026-10-31'),
            'Asia/Jakarta'
        );

        $this->assertFalse($november);
        $this->assertFalse($october);
        $this->assertNull(ItKpiSyncService::restoreStatus($dir . '/missing.jsonl', ['2026-11-01'], 'Asia/Jakarta'));
    }

    public function testHariDimonitorBulanBerjalanSampaiKemarin(): void
    {
        $now = new DateTimeImmutable('2026-11-24 10:00:00', new DateTimeZone('Asia/Jakarta'));

        $current = ItKpiSyncService::monitoredDays(2026, 11, $now, 'Asia/Jakarta');
        $past    = ItKpiSyncService::monitoredDays(2026, 10, $now, 'Asia/Jakarta');
        $future  = ItKpiSyncService::monitoredDays(2026, 12, $now, 'Asia/Jakarta');

        $this->assertCount(23, $current);
        $this->assertSame('2026-11-01', $current[0]);
        $this->assertSame('2026-11-23', $current[22]);
        $this->assertCount(31, $past);
        $this->assertSame([], $future);
    }

    public function testCollectMenggabungkanCommitBackupDanUptime(): void
    {
        $dir = $this->tempDir();
        $days = $this->daysBetween('2026-10-01', '2026-10-31');

        $history = [];
        foreach ($days as $index => $day) {
            $history[] = json_encode([
                'at'     => $day . 'T02:15:00+07:00',
                'status' => $index === 0 ? 'failed' : 'ok',
                'tables' => $index === 0 ? null : 138,
            ]);
        }
        $this->writeLines($dir . '/history.jsonl', $history);
        $this->writeLines($dir . '/restore.jsonl', [
            '{"at":"2026-10-20T10:00:00+07:00","status":"ok"}',
        ]);

        $http = function (string $method, string $url, array $headers, ?string $body): array {
            if (strpos($url, 'api.github.com') !== false) {
                return ['status' => 200, 'body' => json_encode([
                    $this->commit('feat: fitur', 'dev@example.com'),
                    $this->commit('fix: bug', 'dev@example.com'),
                    $this->commit('docs: panduan', 'dev@example.com'),
                    $this->commit('chore: bump', 'dev@example.com'),
                ])];
            }

            if (strpos($url, 'uptimerobot.com') !== false) {
                $ratios    = array_fill(0, 31, '100.000');
                $ratios[5] = '90.000'; // satu hari downtime 144 menit

                return ['status' => 200, 'body' => json_encode([
                    'stat'     => 'ok',
                    'monitors' => [['custom_uptime_ratio' => implode('-', $ratios)]],
                ])];
            }

            return ['status' => 404, 'body' => '{}'];
        };

        $service = new ItKpiSyncService([
            'github_token'    => 'token',
            'github_repos'    => 'bwafi/ERP-iClear:main, bwafi/CrmIclear:main, bwafi/iClear:main',
            'github_authors'  => 'dev@example.com',
            'uptime_key'      => 'ur-key',
            'uptime_monitors' => '1-2',
            'backup_dir'      => $dir,
            'timezone'        => 'Asia/Jakarta',
            'http'            => $http,
        ]);

        $result = $service->collect(2026, 10, new DateTimeImmutable('2026-11-24 10:00:00', new DateTimeZone('Asia/Jakarta')));

        $this->assertSame(3, $result['feat_commits']);
        $this->assertSame(3, $result['fix_commits']);
        $this->assertSame(3, $result['docs_commits']);
        $this->assertSame(30, $result['backup_ok_days']);
        $this->assertSame(31, $result['backup_checked_days']);
        $this->assertSame(1, $result['restore_ok']);
        $this->assertSame(30, $result['uptime_ok_days']);
        $this->assertSame(31, $result['uptime_checked_days']);
        $this->assertSame([], $result['errors']);
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /**
     * @return array<string,mixed>
     */
    private function commit(string $message, string $email): array
    {
        return [
            'sha'    => sha1($message),
            'commit' => [
                'message' => $message,
                'author'  => ['email' => $email],
            ],
            'author' => ['login' => 'bwafi'],
        ];
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/erp_it_kpi_' . uniqid('', true);
        mkdir($dir, 0777, true);

        return $dir;
    }

    /**
     * @param list<string> $lines
     */
    private function writeLines(string $path, array $lines): void
    {
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    /**
     * @return list<string>
     */
    private function daysBetween(string $from, string $to): array
    {
        $days = [];
        $date = new DateTimeImmutable($from);
        $end  = new DateTimeImmutable($to);

        while ($date <= $end) {
            $days[] = $date->format('Y-m-d');
            $date   = $date->modify('+1 day');
        }

        return $days;
    }
}
