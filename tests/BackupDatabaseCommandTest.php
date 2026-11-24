<?php

namespace Tests;

use App\Services\System\DatabaseBackupService;
use CodeIgniter\Test\CIUnitTestCase;
use RuntimeException;

/**
 * Perilaku `backup:db` — dump, verifikasi, retensi, histori.
 *
 * Binary dump diganti script stub supaya test tidak membutuhkan MySQL.
 *
 * @internal
 */
final class BackupDatabaseCommandTest extends CIUnitTestCase
{
    private string $dir;

    private string $okBin;

    private string $failBin;

    private string $emptyBin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/erp_backup_' . uniqid('', true);
        mkdir($this->dir, 0777, true);

        $this->okBin = $this->stub('dump-ok.sh', implode("\n", [
            '#!/bin/sh',
            "if [ \"\$1\" = \"--version\" ]; then echo 'mysqldump  Ver 10.11'; exit 0; fi",
            "echo 'CREATE TABLE `t` (id int);'",
            "echo 'INSERT INTO `t` VALUES (1);'",
        ]));

        $this->failBin = $this->stub('dump-fail.sh', implode("\n", [
            '#!/bin/sh',
            'echo "access denied" >&2',
            'exit 2',
        ]));

        $this->emptyBin = $this->stub('dump-empty.sh', implode("\n", [
            '#!/bin/sh',
            "echo '-- dump tanpa tabel'",
        ]));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->dir);

        parent::tearDown();
    }

    public function testBackupSuksesMenghasilkanGzipValidDanHistori(): void
    {
        $service = $this->service($this->okBin);
        $result  = $service->run();

        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.sql.gz', $result['file']);
        $this->assertSame(1, $result['tables']);
        $this->assertGreaterThan(0, $result['bytes']);
        $this->assertFileDoesNotExist($result['path'] . '.part');

        $sql = gzdecode((string) file_get_contents($result['path']));
        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);

        $history = $this->history();
        $this->assertCount(1, $history);
        $this->assertSame('ok', $history[0]['status']);
        $this->assertSame(1, $history[0]['tables']);
        $this->assertSame($result['file'], $history[0]['file']);
        $this->assertStringEndsWith('+07:00', (string) $history[0]['at']);
    }

    public function testTimezoneTidakValidKembaliKeWib(): void
    {
        $service = $this->service($this->okBin, 'Bukan/Zona');
        $service->run();

        $history = $this->history();
        $this->assertCount(1, $history);
        $this->assertStringEndsWith('+07:00', (string) $history[0]['at']);
    }

    public function testDumpGagalTidakMeninggalkanFileDanMencatatHistori(): void
    {
        $service = $this->service($this->failBin);

        try {
            $service->run();
            $this->fail('Dump gagal seharusnya melempar RuntimeException.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('access denied', $e->getMessage());
        }

        $this->assertSame([], glob($this->dir . '/*.sql.gz') ?: []);
        $this->assertSame([], glob($this->dir . '/*.part') ?: []);

        $history = $this->history();
        $this->assertCount(1, $history);
        $this->assertSame('failed', $history[0]['status']);
        $this->assertStringContainsString('access denied', (string) $history[0]['error']);
    }

    public function testDumpTanpaCreateTableDianggapGagal(): void
    {
        $service = $this->service($this->emptyBin);

        try {
            $service->run();
            $this->fail('Dump tanpa tabel seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('CREATE TABLE', $e->getMessage());
        }

        $this->assertSame([], glob($this->dir . '/*.sql.gz') ?: []);
        $this->assertSame('failed', $this->history()[0]['status']);
    }

    public function testRetensiMenghapusFileLamaDanMenyisakanTerbaru(): void
    {
        $now   = time();
        $lama  = $this->backupFile('erp_test_2026-11-01.sql.gz', $now - 30 * 86400);
        $batas = $this->backupFile('erp_test_2026-11-10.sql.gz', $now - 10 * 86400);
        $baru  = $this->backupFile('erp_test_2026-11-20.sql.gz', $now - 86400);

        $deleted = DatabaseBackupService::prune($this->dir, 'erp_test', 14, $now);

        $this->assertSame([$lama], $deleted);
        $this->assertFileDoesNotExist($lama);
        $this->assertFileExists($batas);
        $this->assertFileExists($baru);
    }

    public function testRetensiMenyisakanBackupTerbaruWalauLewatBatas(): void
    {
        $now = time();
        $satu = $this->backupFile('erp_test_2026-01-01.sql.gz', $now - 300 * 86400);

        $deleted = DatabaseBackupService::prune($this->dir, 'erp_test', 14, $now);

        $this->assertSame([], $deleted);
        $this->assertFileExists($satu);
    }

    public function testRetensiTidakMenyentuhDatabaseLainDanMembersihkanPartLama(): void
    {
        $now  = time();
        $lain = $this->backupFile('crm_2026-01-01.sql.gz', $now - 300 * 86400);
        $part = $this->dir . '/erp_test_rusak.sql.gz.part';
        file_put_contents($part, 'x');
        touch($part, $now - 2 * 86400);

        DatabaseBackupService::prune($this->dir, 'erp_test', 14, $now);

        $this->assertFileExists($lain);
        $this->assertFileDoesNotExist($part);
    }

    public function testDriverSelainMySQLiDitolak(): void
    {
        $this->assertTrue(DatabaseBackupService::supportsDriver('MySQLi'));
        $this->assertTrue(DatabaseBackupService::supportsDriver('mysqli'));
        $this->assertFalse(DatabaseBackupService::supportsDriver('SQLite3'));
    }

    public function testNamaDatabaseDisanitasi(): void
    {
        $this->assertSame('erp_local', DatabaseBackupService::sanitizeName('erp_local'));
        $this->assertSame('erp_local_2', DatabaseBackupService::sanitizeName('../erp local/2'));
        $this->assertSame('database', DatabaseBackupService::sanitizeName('///'));
    }

    private function service(string $bin, string $timezone = 'Asia/Jakarta'): DatabaseBackupService
    {
        return new DatabaseBackupService([
            'hostname' => 'localhost',
            'username' => 'tester',
            'password' => 'rahasia',
            'database' => 'erp_test',
            'port'     => 3306,
        ], $bin, $this->dir, 14, $timezone);
    }

    private function stub(string $name, string $content): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content . "\n");
        chmod($path, 0755);

        return $path;
    }

    private function backupFile(string $name, int $mtime): string
    {
        clearstatcache();
        $path = $this->dir . '/' . $name;
        file_put_contents($path, 'dummy');
        touch($path, $mtime);
        clearstatcache();

        return $path;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(): array
    {
        $path = $this->dir . '/history.jsonl';
        if (! is_file($path)) {
            return [];
        }

        $rows = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }
}
