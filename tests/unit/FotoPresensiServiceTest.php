<?php

namespace Tests\unit;

use App\Services\Presensi\FotoPresensiService;
use App\Services\Presensi\PresensiException;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\FakeUploadedFile;

class FotoPresensiServiceTest extends CIUnitTestCase
{
    private string $dir;
    private string $sumber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir    = WRITEPATH . 'uploads/presensi_test_' . uniqid();
        $this->sumber = tempnam(sys_get_temp_dir(), 'absen');
        file_put_contents($this->sumber, 'isi-foto');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $this->removeDir($this->dir);
        }
        if (is_file($this->sumber)) {
            unlink($this->sumber);
        }
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function foto(string $mime = 'image/jpeg', bool $valid = true, int $ukuran = 1024): FakeUploadedFile
    {
        return FakeUploadedFile::buat($this->sumber, $mime, $valid, $ukuran);
    }

    public function testJpegValidTersimpan(): void
    {
        $file = $this->foto();
        $path = (new FotoPresensiService())->simpan($file, 'masuk', 10, $this->dir);

        $this->assertMatchesRegularExpression('#^\d{6}/absen_masuk_10_.*\.jpg$#', $path);
        $this->assertFileExists($this->dir . '/' . $path);
        $this->assertTrue($file->dipindah);
    }

    public function testMimeTidakDidukungDitolak(): void
    {
        $this->expectException(PresensiException::class);

        try {
            (new FotoPresensiService())->simpan($this->foto('application/pdf'), 'masuk', 10, $this->dir);
        } catch (PresensiException $e) {
            $this->assertSame(422, $e->status);
            throw $e;
        }
    }

    public function testUkuranTerlaluBesarDitolak(): void
    {
        $this->expectException(PresensiException::class);
        (new FotoPresensiService())->simpan($this->foto('image/jpeg', true, 2097153), 'masuk', 10, $this->dir);
    }

    public function testFileTidakValidDitolak(): void
    {
        $this->expectException(PresensiException::class);
        (new FotoPresensiService())->simpan($this->foto('image/jpeg', false), 'masuk', 10, $this->dir);
    }

    public function testHapusMenghapusFile(): void
    {
        $service = new FotoPresensiService();
        $path    = $service->simpan($this->foto(), 'masuk', 10, $this->dir);

        $this->assertFileExists($this->dir . '/' . $path);
        $service->hapus($path, $this->dir);
        $this->assertFileDoesNotExist($this->dir . '/' . $path);
    }
}
