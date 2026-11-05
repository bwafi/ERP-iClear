<?php

namespace Tests;

use App\Commands\SessionFlush;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Perintah `session:flush` hanya menghapus file session, bukan file lain.
 *
 * @internal
 */
final class SessionFlushCommandTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/erp_session_flush_' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);

        parent::tearDown();
    }

    private function file(string $nama, int $mtime): void
    {
        $path = $this->dir . '/' . $nama;
        file_put_contents($path, 'sesi');
        touch($path, $mtime);
    }

    public function testFlushSemuaMenghapusFileSessionSaja(): void
    {
        $this->file('ci_sessionbaru', time());
        $this->file('ci_sessionlama', time() - 100000);
        $this->file('index.html', time());
        $this->file('catatan.txt', time());

        [$terhapus, $gagal] = SessionFlush::bersihkan($this->dir, 'ci_session', false, 43200);

        $this->assertSame(2, $terhapus);
        $this->assertSame(0, $gagal);
        $this->assertFileDoesNotExist($this->dir . '/ci_sessionbaru');
        $this->assertFileDoesNotExist($this->dir . '/ci_sessionlama');
        $this->assertFileExists($this->dir . '/index.html');
        $this->assertFileExists($this->dir . '/catatan.txt');
    }

    public function testExpiredHanyaMenghapusYangLewatBatas(): void
    {
        $this->file('ci_sessionbaru', time());
        $this->file('ci_sessionlama', time() - 100000);

        [$terhapus] = SessionFlush::bersihkan($this->dir, 'ci_session', true, 43200);

        $this->assertSame(1, $terhapus);
        $this->assertFileExists($this->dir . '/ci_sessionbaru');
        $this->assertFileDoesNotExist($this->dir . '/ci_sessionlama');
    }

    public function testExpirationNolTidakMenghapusApaPun(): void
    {
        $this->file('ci_sessionlama', time() - 100000);

        [$terhapus] = SessionFlush::bersihkan($this->dir, 'ci_session', true, 0);

        $this->assertSame(0, $terhapus);
        $this->assertFileExists($this->dir . '/ci_sessionlama');
    }
}
