<?php

namespace Tests\unit;

use App\Services\Presensi\PresensiException;
use App\Services\Presensi\SuratIzinService;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\FakeUploadedFile;

class SuratIzinServiceTest extends CIUnitTestCase
{
    private string $dir;
    private string $sumber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir    = WRITEPATH . 'uploads/izin_test_' . uniqid();
        $this->sumber = tempnam(sys_get_temp_dir(), 'surat');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (scandir($this->dir) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $this->dir . '/' . $item;
                is_dir($path) ? $this->hapusDir($path) : unlink($path);
            }
            rmdir($this->dir);
        }
        if (is_file($this->sumber)) {
            unlink($this->sumber);
        }
        parent::tearDown();
    }

    private function hapusDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->hapusDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function surat(string $isi, string $mime = 'application/octet-stream', int $ukuran = 1024): FakeUploadedFile
    {
        file_put_contents($this->sumber, $isi);

        return FakeUploadedFile::buat($this->sumber, $mime, true, $ukuran);
    }

    private function png(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    public function testPngValidTersimpan(): void
    {
        $path = (new SuratIzinService())->simpan($this->surat($this->png()), 10, $this->dir);

        $this->assertMatchesRegularExpression('#^\d{6}/surat_izin_10_.*\.png$#', $path);
        $this->assertFileExists($this->dir . '/' . $path);
    }

    public function testPdfDitolak(): void
    {
        $this->expectException(PresensiException::class);
        (new SuratIzinService())->simpan($this->surat('%PDF-1.4 surat dokter'), 10, $this->dir);
    }

    public function testIsiBukanGambarDitolak(): void
    {
        $this->expectException(PresensiException::class);
        (new SuratIzinService())->simpan($this->surat('bukan surat'), 10, $this->dir);
    }

    public function testUkuranTerlaluBesarDitolak(): void
    {
        $this->expectException(PresensiException::class);
        (new SuratIzinService())->simpan($this->surat($this->png(), 'image/png', 2097153), 10, $this->dir);
    }

    public function testHapusMenghapusFile(): void
    {
        $service = new SuratIzinService();
        $path    = $service->simpan($this->surat($this->png()), 10, $this->dir);

        $this->assertFileExists($this->dir . '/' . $path);
        $service->hapus($path, $this->dir);
        $this->assertFileDoesNotExist($this->dir . '/' . $path);
    }
}
