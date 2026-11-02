<?php

namespace App\Services\Presensi;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Menyimpan surat keterangan dokter di luar public/ supaya hanya bisa diambil
 * lewat route ber-auth. Format dideteksi dari isi file, bukan dari klaim
 * browser, karena build PHP 7.4 web tidak punya `finfo`.
 */
class SuratIzinService
{
    private const GAMBAR = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];

    public function simpan(UploadedFile $file, int $akunId, ?string $dir = null): string
    {
        $config = config('Presensi');
        $dir    = $dir ?? $config->suratDir;

        if (! $file->isValid()) {
            throw new PresensiException('File surat tidak valid.');
        }

        if ($file->getSize() > $config->maxSuratBytes) {
            throw new PresensiException('Ukuran surat maksimal ' . round($config->maxSuratBytes / 1048576) . ' MB.');
        }

        $ext = $this->deteksi($file->getTempName());
        if ($ext === null) {
            throw new PresensiException('Surat harus berformat JPG atau PNG.');
        }

        $sub    = date('Ym');
        $folder = rtrim($dir, '/') . '/' . $sub;
        if (! is_dir($folder) && ! mkdir($folder, 0775, true) && ! is_dir($folder)) {
            throw new PresensiException('Folder surat tidak dapat dibuat.', 500);
        }

        $nama = sprintf('surat_izin_%d_%s.%s', $akunId, bin2hex(random_bytes(6)), $ext);

        if (! $file->move($folder, $nama)) {
            throw new PresensiException('Surat gagal disimpan.', 500);
        }

        return $sub . '/' . $nama;
    }

    public function hapus(?string $path, ?string $dir = null): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $file = rtrim($dir ?? config('Presensi')->suratDir, '/') . '/' . $path;
        if (is_file($file)) {
            unlink($file);
        }
    }

    private function deteksi(string $path): ?string
    {
        $info = @getimagesize($path);

        return is_array($info) ? (self::GAMBAR[$info[2]] ?? null) : null;
    }
}
