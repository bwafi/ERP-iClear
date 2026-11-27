<?php

namespace Tests\unit;

use App\Libraries\SlowQueryLogger;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Logger as LoggerConfig;

/**
 * Regresi SlowQueryLogger: konstruksi tidak boleh gagal dan query lambat
 * harus tertulis ke file khusus, bukan bergantung pada logger utama.
 */
final class SlowQueryLoggerTest extends CIUnitTestCase
{
    private string $dir;
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir  = WRITEPATH . 'logs/slow_queries/';
        $this->file = $this->dir . 'log-' . date('Y-m-d') . '.log';

        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    public function testKonstruksiTidakMengubahConfigLoggerUtama(): void
    {
        $thresholdAwal = config(LoggerConfig::class)->threshold;

        new SlowQueryLogger();

        $this->assertSame($thresholdAwal, config(LoggerConfig::class)->threshold,
            'config logger utama tidak boleh ikut berubah');
    }

    public function testQueryLambatDitulisKeFileSlowQuery(): void
    {
        $logger = new SlowQueryLogger();
        $logger->logQuery(2.5, 'SELECT * FROM barang WHERE idbarang = ?', [7]);

        $this->assertFileExists($this->file, 'file slow query dibuat');

        $isi = (string) file_get_contents($this->file);
        $this->assertStringContainsString('Slow Query (2.500s)', $isi);
        $this->assertStringContainsString('idbarang = 7', $isi);
    }

    public function testQueryCepatTidakDitulis(): void
    {
        $logger = new SlowQueryLogger();
        $logger->logQuery(0.1, 'SELECT 1');

        $this->assertFileDoesNotExist($this->file, 'query di bawah threshold tidak ditulis');
    }
}
